<?php

namespace App\Services\Dashboard;

use App\Models\DailySummary;
use App\Models\Setting;
use App\Models\WeatherReading;
use App\Services\Alerts\AlertAggregatorService;
use App\Services\AirQuality\WaqiService;
use App\Support\StatTileRegistry;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardPayloadService
{
    private const DASHBOARD_PAYLOAD_KEYS_CACHE_KEY = 'dashboard_payload_keys';

    /**
     * Browser cache policy for dashboard responses (the page and its payload).
     *
     * Visitors and wall displays get a short window so repeat views stay cheap.
     * Admins must not: they change a setting and navigate straight back to the
     * dashboard, and a cached copy would keep showing the old configuration for
     * up to a minute — long enough to read as "my change did nothing" and send
     * them reaching for a hard refresh.
     */
    public static function browserCacheControl(?Authenticatable $user): string
    {
        return ($user?->is_admin ?? false)
            ? 'private, no-cache, no-store, must-revalidate'
            : 'private, max-age=30, stale-while-revalidate=30';
    }

    public function getDashboardPayload(Request $request): array
    {
        $defaultIndexType = Setting::getValue('airquality.index_type', 'us');
        $userIndexType = $request->cookie('aqi_index_type', $defaultIndexType);
        if (!in_array($userIndexType, ['us', 'eea', 'uk'], true)) {
            $userIndexType = $defaultIndexType;
        }

        $lang = $this->normalizeDashboardLang((string) $request->query('lang', app()->getLocale()));
        $dashboardCacheKey = $this->dashboardPayloadCacheKey($lang, $userIndexType);
        $this->trackDashboardPayloadCacheKey($dashboardCacheKey);

        $payload = Cache::remember($dashboardCacheKey, 30, function () use ($lang, $userIndexType, $defaultIndexType) {
            $previousLocale = app()->getLocale();
            try {
                app()->setLocale($lang);
                return $this->buildDashboardPayload($userIndexType, $defaultIndexType);
            } finally {
                app()->setLocale($previousLocale);
            }
        });

        // Widget layout changes must be visible immediately; never serve stale order from payload cache.
        $layout = $this->readWidgetLayout();
        $payload['grid_cols'] = (int) ($layout['grid_cols'] ?? 3);
        $payload['widget_order'] = is_array($layout['widget_order'] ?? null) ? $layout['widget_order'] : [];
        $payload['stat_order'] = StatTileRegistry::storedOrder();

        return $payload;
    }

    public function forgetDashboardPayloadCaches(): void
    {
        $localeCandidates = ['default', app()->getLocale()];
        $configuredLocales = config('localization.locales', []);
        if (is_array($configuredLocales)) {
            $localeCandidates = array_merge($localeCandidates, array_keys($configuredLocales));
        }

        $normalizedLocales = [];
        foreach ($localeCandidates as $locale) {
            if (!is_string($locale)) {
                continue;
            }
            $normalizedLocales[$this->normalizeDashboardLang($locale)] = true;
        }

        $cacheKeys = [];
        foreach (array_keys($normalizedLocales) as $locale) {
            foreach (['us', 'eea', 'uk'] as $indexType) {
                $cacheKeys[] = $this->dashboardPayloadCacheKey($locale, $indexType);
            }
        }

        $trackedKeys = Cache::get(self::DASHBOARD_PAYLOAD_KEYS_CACHE_KEY, []);
        if (is_array($trackedKeys)) {
            foreach ($trackedKeys as $trackedKey) {
                if (is_string($trackedKey) && trim($trackedKey) !== '') {
                    $cacheKeys[] = $trackedKey;
                }
            }
        }

        foreach (array_unique($cacheKeys) as $cacheKey) {
            Cache::forget($cacheKey);
        }

        Cache::forget(self::DASHBOARD_PAYLOAD_KEYS_CACHE_KEY);
    }

    private function buildDashboardPayload(string $userIndexType, string $defaultIndexType): array
    {
        $readingColumns = [
            'recorded_at',
            'temperature',
            'temperature_indoor',
            'feels_like',
            'humidity',
            'humidity_indoor',
            'dew_point',
            'wet_bulb',
            'pressure_rel',
            'pressure_abs',
            'wind_speed',
            'wind_gust',
            'wind_gust_max_daily',
            'wind_direction',
            'rain_rate',
            'rain_hourly',
            'rain_daily',
            'rain_monthly',
            'rain_yearly',
            'rain_total',
            'uv_index',
            'solar_radiation',
            'lux',
            'co2',
            'lightning_distance',
            'lightning_count',
            'lightning_count_daily',
            'lightning_time',
            'battery_status',
            'station_type',
            'station_model',
        ];

        for ($i = 1; $i <= 8; $i++) {
            $readingColumns[] = "temp_{$i}";
            $readingColumns[] = "soil_moisture_{$i}";
            $readingColumns[] = "soil_temp_{$i}";
        }

        for ($i = 1; $i <= 4; $i++) {
            $readingColumns[] = "pm25_ch{$i}";
        }

        $reading = WeatherReading::query()
            ->select($readingColumns)
            ->orderByDesc('recorded_at')
            ->first();

        $current = null;
        $extraSensors = null;
        $lightning = null;

        if ($reading) {
            $lastRainAt = (($reading->rain_rate ?? 0) > 0)
                ? $reading->recorded_at?->toIso8601String()
                : Cache::remember('dashboard_last_rain_at_iso', 120, function () {
                    $lastRainReading = WeatherReading::query()
                        ->where('rain_rate', '>', 0)
                        ->orderByDesc('recorded_at')
                        ->first(['recorded_at']);

                    return $lastRainReading?->recorded_at?->toIso8601String();
                });

            $current = [
                'recorded_at' => $reading->recorded_at->toIso8601String(),
                'temperature' => $reading->temperature,
                'temperature_indoor' => $reading->temperature_indoor,
                'feels_like' => $reading->feels_like,
                'humidity' => $reading->humidity,
                'humidity_indoor' => $reading->humidity_indoor,
                'dew_point' => $reading->dew_point,
                'wet_bulb' => $reading->wet_bulb,
                // Prefer sea-level pressure; fall back to station pressure if needed.
                'pressure' => $reading->pressure_rel ?? $reading->pressure_abs,
                'pressure_trend' => $reading->pressure_trend ? __($reading->pressure_trend) : null,
                'pressure_trend_key' => $this->pressureTrendToKey($reading->pressure_trend),
                'wind_speed' => $reading->wind_speed,
                'wind_gust' => $reading->wind_gust,
                'wind_gust_max_daily' => $reading->wind_gust_max_daily,
                'wind_direction' => $reading->wind_direction,
                'wind_direction_compass' => $reading->wind_direction_compass ?? 'N',
                'beaufort' => $reading->beaufort ?? 0,
                'beaufort_description' => $reading->beaufort_description,
                'rain_rate' => $reading->rain_rate,
                'rain_hourly' => $reading->rain_hourly,
                'rain_daily' => $reading->rain_daily,
                'rain_monthly' => $reading->rain_monthly,
                'rain_yearly' => $this->getYearlyRain($reading),
                'rain_total' => $reading->rain_total,
                'last_rain_at' => $lastRainAt,
                'uv_index' => $reading->uv_index,
                'uv_level' => $reading->uv_level,
                'solar_radiation' => $reading->solar_radiation,
                'lux' => $reading->lux,
            ];

            // Extra temperature sensors
            if ($reading->hasExtraTemperatureSensors()) {
                $extraSensors = [
                    'temps' => $reading->extra_temperatures,
                ];
            }

            // Soil sensors
            if ($reading->hasSoilSensors()) {
                $extraSensors = $extraSensors ?? [];
                $extraSensors['soil'] = $reading->soil_sensors;
            }

            // PM2.5 sensors
            if ($reading->hasPm25Sensors()) {
                $extraSensors = $extraSensors ?? [];
                $extraSensors['pm25'] = [
                    'ch1' => $reading->pm25_ch1,
                    'level' => $reading->pm25_level,
                ];
            }

            // CO2 sensor
            if ($reading->co2) {
                $extraSensors = $extraSensors ?? [];
                $extraSensors['co2'] = $reading->co2;
            }

            // Lightning data
            if ($reading->lightning_count > 0 || $reading->lightning_distance) {
                $lastStrike = WeatherReading::lastStrikeTime();
                $lightning = [
                    'distance' => $reading->lightning_distance,
                    'count' => $reading->lightning_count,
                    'count_daily' => $reading->lightning_count_daily,
                    'last_strike' => $lastStrike?->toIso8601String(),
                    'time_ago' => $lastStrike?->diffForHumans(),
                ];
            }
        }

        // Get today's summary from cache or calculate quickly from DB (station timezone)
        $stationTz = Setting::timezone();
        $todaySummary = Cache::remember('today_summary_' . $stationTz, 300, function () use ($stationTz) {
            $today = Carbon::today($stationTz)->format('Y-m-d');
            $readings = WeatherReading::whereDate('recorded_at', $today)->get();
            if ($readings->isEmpty()) {
                return null;
            }
            $pressures = $readings
                ->map(fn ($r) => $r->pressure_rel ?? $r->pressure_abs)
                ->filter(fn ($p) => $p !== null);

            return [
                'temp_high' => $readings->max('temperature'),
                'temp_low' => $readings->min('temperature'),
                'rain_total' => $readings->max('rain_daily'),
                'wind_max' => $readings->max('wind_gust'),
                'pressure_high' => $pressures->isNotEmpty() ? $pressures->max() : null,
                'pressure_low' => $pressures->isNotEmpty() ? $pressures->min() : null,
            ];
        });

        // Get enabled widgets from settings
        $enabledWidgets = Setting::getValue('widgets.enabled', '["current","forecast","hourly","wind","rain","sun","moon","astro_events"]');
        $enabledWidgets = is_array($enabledWidgets) ? $enabledWidgets : (json_decode($enabledWidgets, true) ?: []);

        // Get grid columns setting from layout
        $layout = $this->readWidgetLayout();
        $gridCols = (int) ($layout['grid_cols'] ?? 3);

        // Get battery status if available
        $batteryStatus = $reading?->battery_status ?? [];

        // Get weather effect settings
        $effectSettings = [
            'enabled' => Setting::getValue('effects.enabled', true),
            'test_mode' => Setting::getValue('effects.test_mode', false),
            'test_effect' => Setting::getValue('effects.test_effect', 'rain'),
            'rain' => [
                'enabled' => Setting::getValue('effects.rain.enabled', true),
                'intensity' => (int) Setting::getValue('effects.rain.intensity', 50),
                'splash_on_cards' => Setting::getValue('effects.rain.splash_on_cards', true),
                'show_forecast' => Setting::getValue('effects.rain.show_forecast', true),
                'forecast_threshold_type' => Setting::getValue('effects.rain.forecast_threshold_type', 'absolute'),
                'forecast_threshold_value' => (float) Setting::getValue('effects.rain.forecast_threshold_value', 0.5),
            ],
            'snow' => [
                'enabled' => Setting::getValue('effects.snow.enabled', true),
                'intensity' => (int) Setting::getValue('effects.snow.intensity', 50),
            ],
            'wind' => [
                'enabled' => Setting::getValue('effects.wind.enabled', true),
                'intensity' => (int) Setting::getValue('effects.wind.intensity', 50),
            ],
            'lightning' => [
                'enabled' => Setting::getValue('effects.lightning.enabled', true),
            ],
            'sun' => [
                'enabled' => Setting::getValue('effects.sun.enabled', true),
            ],
            'clouds' => [
                'enabled' => Setting::getValue('effects.clouds.enabled', true),
            ],
            'fog' => [
                'enabled' => Setting::getValue('effects.fog.enabled', true),
            ],
        ];

        // PRESSURE HISTORY - Last 24 hours for chart (station timezone)
        $pressureHistory = Cache::remember('pressure_history_24h_v2_' . $stationTz, 300, function () use ($stationTz) {
            $cutoff = now()->subHours(24);

            $rows = WeatherReading::where('recorded_at', '>=', $cutoff)
                ->where(function ($q) {
                    $q->whereNotNull('pressure_rel')->orWhereNotNull('pressure_abs');
                })
                ->orderBy('recorded_at')
                ->get(['recorded_at', 'pressure_rel', 'pressure_abs']);

            if ($rows->isEmpty()) {
                return [];
            }

            $byHour = [];
            foreach ($rows as $r) {
                if (!$r->recorded_at) {
                    continue;
                }

                $localTs = $r->recorded_at->copy()->setTimezone($stationTz);
                $hourKey = $localTs->format('Y-m-d H');
                $byHour[$hourKey] = [
                    'time' => $localTs->toIso8601String(),
                    'pressure' => $r->pressure_rel ?? $r->pressure_abs,
                ];
            }

            if (empty($byHour)) {
                return [];
            }

            ksort($byHour);

            return array_values($byHour);
        });

        // WIND HISTORY - Last 24 hours for wind rose (station timezone)
        $windHistory = Cache::remember('wind_history_24h_v2_' . $stationTz, 300, function () use ($stationTz) {
            $cutoff = now()->subHours(24);

            $rows = WeatherReading::where('recorded_at', '>=', $cutoff)
                ->whereNotNull('wind_direction')
                ->whereNotNull('wind_speed')
                ->orderBy('recorded_at')
                ->get(['recorded_at', 'wind_direction', 'wind_speed']);

            if ($rows->isEmpty()) {
                return [];
            }

            return $rows->map(function ($r) use ($stationTz) {
                $localTs = $r->recorded_at ? $r->recorded_at->copy()->setTimezone($stationTz) : null;

                return [
                    'time' => $localTs ? $localTs->toIso8601String() : null,
                    'direction' => (int) $r->wind_direction,
                    'speed' => round((float) $r->wind_speed, 1),
                ];
            })
                ->filter(fn ($r) => !empty($r['time']))
                ->values()
                ->toArray();
        });

        // EXTERNAL DATA - Read from cache ONLY (no API calls!)
        $latitude = Setting::latitude();
        $longitude = Setting::longitude();

        // Forecast data (cached by poller)
        $source = Setting::getValue('forecast.default_source', 'fct_yrno_block.php');
        $stationId = Setting::getValue('weatherflow.station_id', '');
        $forecastData = Cache::get(\App\Support\ForecastCacheKeys::forSource($source));
        if (!$forecastData) {
            $forecastData = Cache::get("forecast_{$latitude}_{$longitude}");
        }
        $forecast = $forecastData['forecast'] ?? null;
        $dailyForecast = $forecast ? $this->extractDailyForecast($forecast, 5) : null;
        $hourlyForecast = $forecast ? $this->extractHourlyForecast($forecast, 48) : null;

        // Sun/Moon data (cached by poller)
        $sunData = Cache::get('astronomy_sun');
        $moonData = Cache::get('astronomy_moon');

        // Astronomical events (limit to next 5 for dashboard widget)
        $sunMoon = app(\App\Services\Astronomy\SunMoonService::class);
        $astronomicalEvents = $sunMoon->getUpcomingEvents(60);
        $astronomicalEvents = array_slice($astronomicalEvents, 0, 5);

        // Aurora Kp-index (cached by poller)
        $auroraData = Cache::get('aurora_kp_index');

        // Air quality data (cached by poller)
        $stationMode = Setting::getValue('waqi.station_mode', 'auto');
        $stationId = Setting::getValue('waqi.station_id', '');
        $waqiCacheKey = ($stationMode === 'manual' && !empty($stationId))
            ? "waqi_station_{$stationId}"
            : "waqi_{$latitude}_{$longitude}";
        $airQuality = Cache::get($waqiCacheKey);

        // Recalculate AQI if user's index type differs from cached data
        if (!empty($airQuality) && ($airQuality['index_type'] ?? $defaultIndexType) !== $userIndexType) {
            $airQuality = $this->recalculateWaqiAqi($airQuality, $userIndexType);
        }

        // Luftdaten (cached by poller)
        $luftdatenSensorId = Setting::getValue('luftdaten.sensor_id', '');
        $luftdaten = $luftdatenSensorId ? Cache::get("luftdaten_{$luftdatenSensorId}") : null;

        // Recalculate Luftdaten AQI if user's index type differs
        if (!empty($luftdaten) && isset($luftdaten['formatted'])) {
            $luftdaten = $this->recalculateLuftdatenAqi($luftdaten, $userIndexType);
        }

        // Pollen forecast (cached by poller)
        $pollenData = Cache::get('pollen_forecast');

        // Tide data (cached by poller) — widget summary only (no full series)
        $tideWidget = null;
        if (Setting::getValue('tide.enabled', false)) {
            $tideSource = Setting::getValue('tide.source', 'rws');
            $tideStation = Setting::getValue(
                "tide.{$tideSource}_station_code",
                Setting::getValue('tide.station_code', \App\Services\TideService::DEFAULT_STATION)
            );
            $tideRaw = Cache::get('tide_' . $tideSource . '_' . $tideStation);
            if ($tideRaw) {
                $tideNowMs = now()->timestamp * 1000;
                $tideFuture = array_filter($tideRaw['tides'] ?? [], fn ($t) => $t['timestamp_unix'] >= $tideNowMs);
                $tideFuture = array_values($tideFuture);
                $nextHigh = collect($tideFuture)->firstWhere('type', 'high');
                $nextLow = collect($tideFuture)->firstWhere('type', 'low');
                $tideWidget = [
                    'station' => $tideRaw['station'] ?? null,
                    'current_level_cm' => $tideRaw['current_level_cm'] ?? null,
                    'trend' => $tideRaw['trend'] ?? 'steady',
                    'next_high' => $nextHigh,
                    'next_low' => $nextLow,
                    'updated_at' => $tideRaw['updated_at'] ?? null,
                ];
            }
        }

        // Wave + sea temperature (cached by poller)
        $waterWaves = null;
        $waveCacheKey = 'waves_' . round((float) $latitude, 2) . '_' . round((float) $longitude, 2);
        $waveRaw = Cache::get($waveCacheKey);
        if ($waveRaw && is_array($waveRaw)) {
            $hasWave = isset($waveRaw['current_wave_height_m']) && $waveRaw['current_wave_height_m'] !== null;
            $hasSst = isset($waveRaw['current_sst_c']) && $waveRaw['current_sst_c'] !== null;
            if ($hasWave || $hasSst) {
                $waterWaves = [
                    'wave_height_m' => $waveRaw['current_wave_height_m'] ?? null,
                    'sst_c' => $waveRaw['current_sst_c'] ?? null,
                ];
            }
        }

        // Luftdaten noise sensor (cached by poller; optionally refreshed on load when stale)
        $luftdatenNoise = $this->getLuftdatenNoiseForDisplay();

        // METAR data (cached by poller)
        $metarData = null;
        if (Setting::getValue('metar.enabled', false)) {
            $primaryIcao = Setting::getValue('metar.primary_icao', 'EHAM');
            $metarData = Cache::get("metar_{$primaryIcao}");
        }

        // Earthquake data (cached by poller)
        $earthquakes = Cache::get("earthquakes_{$latitude}_{$longitude}");

        // Weather alerts — aggregated (official + internal threshold warnings)
        $alerts = app(AlertAggregatorService::class)->getAll();

        // Health status of all data sources (cached by health check command)
        $healthStatus = Cache::get('data_source_health', []);
        $cacheLastUpdate = Cache::get('weather:last_update');
        $readingLastUpdate = $reading?->recorded_at?->toIso8601String();
        $lastUpdate = $readingLastUpdate ?: $cacheLastUpdate;

        // Keep backward-compatible payload key while preferring freshest known timestamp.
        if ($cacheLastUpdate && $readingLastUpdate) {
            try {
                $cacheTs = Carbon::parse($cacheLastUpdate)->getTimestamp();
                $readingTs = Carbon::parse($readingLastUpdate)->getTimestamp();
                $lastUpdate = $readingTs >= $cacheTs ? $readingLastUpdate : $cacheLastUpdate;
            } catch (\Throwable $e) {
                // Ignore parse issues and keep reading-first fallback.
                $lastUpdate = $readingLastUpdate ?: $cacheLastUpdate;
            }
        }

        $extraSensorLabels = [
            'temps' => [],
            'soil' => [],
            'pm25' => [],
            'leak' => [],
            'battery' => [],
        ];
        for ($i = 1; $i <= 8; $i++) {
            $tempLabel = Setting::getValue("ecowitt.temp{$i}_label", '');
            if (!empty($tempLabel)) {
                $extraSensorLabels['temps']["temp_{$i}"] = $tempLabel;
                $extraSensorLabels['battery']["batt{$i}"] = $tempLabel;
            }

            $soilLabel = Setting::getValue("ecowitt.soil{$i}_label", '');
            if (!empty($soilLabel)) {
                $extraSensorLabels['soil'][(string) $i] = $soilLabel;
                $extraSensorLabels['soil']["soil_{$i}"] = $soilLabel;
                $extraSensorLabels['battery']["soilbatt{$i}"] = $soilLabel;
            }
        }

        for ($i = 1; $i <= 4; $i++) {
            $pm25Label = Setting::getValue("ecowitt.pm25_{$i}_label", '');
            if (!empty($pm25Label)) {
                $extraSensorLabels['pm25']["pm25_{$i}"] = $pm25Label;
                $extraSensorLabels['pm25']["ch{$i}"] = $pm25Label;
                $extraSensorLabels['battery']["pm25batt{$i}"] = $pm25Label;
            }

            $leakLabel = Setting::getValue("ecowitt.leak_{$i}_label", '');
            if (!empty($leakLabel)) {
                $extraSensorLabels['leak']["leak_{$i}"] = $leakLabel;
                $extraSensorLabels['battery']["leakbatt{$i}"] = $leakLabel;
            }
        }

        $co2Label = Setting::getValue('ecowitt.co2_label', '');
        if (!empty($co2Label)) {
            $extraSensorLabels['battery']['co2_batt'] = $co2Label;
        }

        return [
            'success' => true,
            'current' => $current,
            'today' => $todaySummary,
            'extra_sensors' => $extraSensors,
            'extra_sensor_labels' => $extraSensorLabels,
            'lightning' => $lightning,
            'battery_status' => $batteryStatus,
            'pressure_history' => $pressureHistory,
            'wind_history' => $windHistory,
            'forecast' => $dailyForecast,
            'hourlyForecast' => $hourlyForecast,
            'sun' => $sunData,
            'moon' => $moonData,
            'aurora' => $auroraData,
            'astronomical_events' => $astronomicalEvents,
            'air_quality' => $airQuality,
            'luftdaten' => $luftdaten,
            'luftdaten_noise' => $luftdatenNoise,
            'pollen' => $pollenData,
            'tide' => $tideWidget,
            'water_waves' => $waterWaves,
            'metar' => $metarData,
            'earthquakes' => $earthquakes,
            'alerts' => $alerts,
            'health_status' => $healthStatus,
            'last_update' => $lastUpdate,
            'station' => [
                'name' => Setting::stationName(),
                'location' => Setting::stationLocation(),
                'type' => $reading?->station_type,
                'model' => $reading?->station_model,
            ],
            'enabled_widgets' => $enabledWidgets,
            'enabled_stats' => StatTileRegistry::enabledIds(),
            'grid_cols' => $gridCols,
            'widget_order' => $layout['widget_order'] ?? [],
            'stat_order' => StatTileRegistry::storedOrder(),
            'effects' => $effectSettings,
        ];
    }

    /**
     * Map pressure trend translation key to a stable key for frontend styling (icon/color).
     */
    private function pressureTrendToKey(?string $trend): ?string
    {
        if (!$trend) {
            return null;
        }

        $t = strtolower($trend);
        if (str_contains($t, 'rising') || str_contains($t, 'ris')) {
            return 'rising';
        }
        if (str_contains($t, 'falling') || str_contains($t, 'fall')) {
            return 'falling';
        }
        if (str_contains($t, 'stable') || str_contains($t, 'stead')) {
            return 'stable';
        }

        return null;
    }

    /**
     * Extract daily forecast from cached Yr.no data.
     */
    private function extractDailyForecast(?array $forecast, int $days): ?array
    {
        if (!$forecast) {
            return null;
        }

        $stationTz = Setting::timezone();
        $todayStr = Carbon::now($stationTz)->toDateString();

        $daily = [];
        $seenDates = [];

        foreach ($forecast as $entry) {
            $timeStr = $entry['time'] ?? $entry['date'] ?? null;
            if (!$timeStr) {
                continue;
            }
            try {
                $entryDate = Carbon::parse($timeStr, 'UTC')->setTimezone($stationTz)->toDateString();
            } catch (\Exception $e) {
                $entryDate = trim(substr($timeStr, 0, 10));
            }
            if ($entryDate < $todayStr) {
                continue;
            }
            if (!isset($seenDates[$entryDate]) && count($daily) < $days) {
                $seenDates[$entryDate] = true;
                $daily[] = [
                    'date' => $entryDate,
                    'temp_high' => $entry['temperature'],
                    'temp_low' => $entry['temperature'],
                    'symbol' => $entry['symbol'],
                    'precipitation' => $entry['precipitation_1h'] ?? $entry['precipitation_6h'] ?? 0,
                    'wind_speed' => $entry['wind_speed'],
                ];
            } elseif (isset($seenDates[$entryDate])) {
                $idx = null;
                foreach ($daily as $i => $d) {
                    if (($d['date'] ?? '') === $entryDate) {
                        $idx = $i;
                        break;
                    }
                }
                if ($idx !== null) {
                    if ($entry['temperature'] !== null && $entry['temperature'] > $daily[$idx]['temp_high']) {
                        $daily[$idx]['temp_high'] = $entry['temperature'];
                    }
                    if ($entry['temperature'] !== null && $entry['temperature'] < $daily[$idx]['temp_low']) {
                        $daily[$idx]['temp_low'] = $entry['temperature'];
                    }
                    $precip = $entry['precipitation_1h'] ?? $entry['precipitation_6h'] ?? 0;
                    if ($precip !== null && is_numeric($precip)) {
                        $daily[$idx]['precipitation'] = ($daily[$idx]['precipitation'] ?? 0) + $precip;
                    }
                }
            }
        }

        $daily = array_values(array_filter($daily, fn ($d) => ($d['date'] ?? '') >= $todayStr));
        usort($daily, fn ($a, $b) => strcmp($a['date'] ?? '', $b['date'] ?? ''));
        $daily = array_slice($daily, 0, $days);

        return $daily ?: null;
    }

    /**
     * Extract hourly forecast from cached Yr.no data.
     */
    private function extractHourlyForecast(?array $forecast, int $hours): ?array
    {
        if (!$forecast) {
            return null;
        }

        $nowUtc = Carbon::now('UTC');
        $fromNow = array_values(array_filter($forecast, function ($entry) use ($nowUtc) {
            $timeStr = $entry['time'] ?? $entry['date'] ?? null;
            if (!$timeStr) {
                return false;
            }
            try {
                $entryTime = Carbon::parse($timeStr, 'UTC');

                return $entryTime->gte($nowUtc);
            } catch (\Exception $e) {
                return false;
            }
        }));

        $sliced = array_slice($fromNow, 0, $hours);

        return $sliced ?: null;
    }

    private function normalizeDashboardLang(string $lang): string
    {
        $normalized = strtolower(str_replace('_', '-', trim($lang)));

        return $normalized !== '' ? $normalized : 'default';
    }

    private function readWidgetLayout(): array
    {
        $layout = Setting::getValue('widgets.layout', ['grid_cols' => 3]);
        if (is_array($layout)) {
            return $layout;
        }

        if (is_string($layout)) {
            $decoded = json_decode($layout, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return ['grid_cols' => 3];
    }

    private function dashboardPayloadCacheKey(string $lang, string $indexType): string
    {
        return "dashboard_payload_{$lang}_{$indexType}";
    }

    private function trackDashboardPayloadCacheKey(string $cacheKey): void
    {
        $trackedKeys = Cache::get(self::DASHBOARD_PAYLOAD_KEYS_CACHE_KEY, []);
        if (!is_array($trackedKeys)) {
            $trackedKeys = [];
        }

        if (!in_array($cacheKey, $trackedKeys, true)) {
            $trackedKeys[] = $cacheKey;
            if (count($trackedKeys) > 200) {
                $trackedKeys = array_slice($trackedKeys, -200);
            }
            Cache::forever(self::DASHBOARD_PAYLOAD_KEYS_CACHE_KEY, $trackedKeys);
        }
    }

    /**
     * Recalculate WAQI AQI based on user's selected index type.
     */
    private function recalculateWaqiAqi(array $data, string $indexType): array
    {
        $concentrations = $data['pollutants_concentration'] ?? [];

        if (empty($concentrations)) {
            return $data;
        }

        $waqiService = app(WaqiService::class);
        $calculatedIndex = $waqiService->calculateIndex($concentrations, $indexType);

        $category = $waqiService->getAqiCategory($calculatedIndex['value'], $indexType);

        $data['aqi'] = $calculatedIndex['value'];
        $data['index_type'] = $indexType;
        $data['category'] = $category;
        $data['dominant_pollutant_calculated'] = $calculatedIndex['dominant_pollutant'];

        return $data;
    }

    /**
     * Recalculate Luftdaten AQI based on user's selected index type.
     */
    private function recalculateLuftdatenAqi(array $data, string $indexType): array
    {
        $pm25 = $data['formatted']['pm25']['value'] ?? $data['values']['P2'] ?? $data['values']['pm25'] ?? null;
        $pm10 = $data['formatted']['pm10']['value'] ?? $data['values']['P1'] ?? $data['values']['pm10'] ?? null;

        if ($pm25 === null && $pm10 === null) {
            return $data;
        }

        $calculator = new class {
            use \App\Services\AirQuality\CalculatesAirQualityIndex;

            public function calculate(array $concentrations, string $indexType): array
            {
                return $this->calculateAqi($concentrations, $indexType);
            }
        };

        $aqi = $calculator->calculate([
            'pm25' => $pm25,
            'pm10' => $pm10,
        ], $indexType);

        $data['formatted']['aqi'] = $aqi;
        $data['formatted']['index_type'] = $indexType;

        return $data;
    }

    /**
     * Get noise sensor data for display.
     */
    private function getLuftdatenNoiseForDisplay(): ?array
    {
        if (!Setting::getValue('luftdaten_noise.enabled', false)) {
            return null;
        }
        $sensorId = trim(Setting::getValue('luftdaten_noise.sensor_id', ''));
        if ($sensorId === '') {
            return null;
        }
        $cacheKey = "luftdaten_noise_{$sensorId}";
        $data = Cache::get($cacheKey);
        $refreshOnLoad = (bool) Setting::getValue('luftdaten_noise.refresh_on_load', false);
        $maxAgeMinutes = (int) Setting::getValue('luftdaten_noise.refresh_on_load_max_age', 2);
        if ($refreshOnLoad && $maxAgeMinutes > 0 && is_array($data)) {
            $cachedAt = $data['cached_at'] ?? null;
            if ($cachedAt !== null) {
                try {
                    $cachedTime = Carbon::parse($cachedAt);
                    if ($cachedTime->diffInMinutes(now(), false) >= $maxAgeMinutes) {
                        $service = app(\App\Services\AirQuality\LuftdatenService::class);
                        $fresh = $service->fetchBySensorId($sensorId);
                        if ($fresh !== null) {
                            $fresh['cached_at'] = now()->toIso8601String();
                            Cache::put($cacheKey, $fresh, now()->addMinutes(20));

                            return $fresh;
                        }
                    }
                } catch (\Throwable $e) {
                    // Keep existing cache on parse/refresh error.
                }
            }
        }

        return $data;
    }

    /**
     * Get yearly rain total based on configuration.
     */
    private function getYearlyRain(WeatherReading $reading): ?float
    {
        $source = Setting::getValue('livedata.rain_yearly_source', 'station');

        if ($source === 'calculated') {
            $year = now()->year;

            $yearlyTotal = DailySummary::whereYear('date', $year)
                ->sum('rain_total');

            return $yearlyTotal ? round($yearlyTotal, 2) : 0.0;
        }

        return $reading->rain_yearly;
    }
}
