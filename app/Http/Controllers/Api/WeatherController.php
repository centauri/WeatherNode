<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WeatherReading;
use App\Models\DailySummary;
use App\Models\Setting;
use App\Services\Weather\EcowittService;
use App\Services\AirQuality\WaqiService;
use App\Services\Astronomy\SunMoonService;
use App\Services\Aviation\MetarService;
use App\Services\OpenData\KnmiNowcastService;
use App\Services\OpenData\KnmiWmsService;
use App\Services\Radar\RadarFutureFramesService;
use App\Services\Dashboard\DashboardPayloadService;
use App\Services\Forecast\ForecastServiceFactory;
use App\Contracts\Nlg\Narrator;
use App\Services\Nlg\ForecastNlgCacheService;
use App\Support\StatTileRegistry;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WeatherController extends Controller
{
    /**
     * Get current weather conditions with all available sensor data
     */
    public function current(EcowittService $ecowitt): JsonResponse
    {
        $reading = WeatherReading::mostRecent();
        
        if ($reading) {
            return response()->json([
                'success' => true,
                'data' => $reading->toApiArray(),
                'station' => [
                    'name' => Setting::stationName(),
                    'location' => Setting::stationLocation(),
                    'latitude' => Setting::latitude(),
                    'longitude' => Setting::longitude(),
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No weather data available. Configure a live data source in the admin panel.',
            'data' => null,
        ]);
    }

    /**
     * Get today's weather summary with min/max values
     */
    public function today(): JsonResponse
    {
        $summary = DailySummary::forDate(today());
        
        // Calculate from readings if no summary exists
        if (!$summary) {
            $readings = WeatherReading::today()->get();
            
            if ($readings->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No data for today',
                ]);
            }

            $summary = [
                'date' => today()->toDateString(),
                'temp_high' => $readings->max('temperature'),
                'temp_high_time' => $readings->sortByDesc('temperature')->first()?->recorded_at?->format('H:i'),
                'temp_low' => $readings->min('temperature'),
                'temp_low_time' => $readings->sortBy('temperature')->first()?->recorded_at?->format('H:i'),
                'temp_avg' => round($readings->avg('temperature'), 1),
                'humidity_high' => $readings->max('humidity'),
                'humidity_low' => $readings->min('humidity'),
                'humidity_avg' => round($readings->avg('humidity'), 1),
                'rain_total' => $readings->max('rain_daily'),
                'rain_rate_max' => $readings->max('rain_rate'),
                'wind_avg' => round($readings->avg('wind_speed'), 1),
                'wind_max' => $readings->max('wind_gust'),
                'wind_max_time' => $readings->sortByDesc('wind_gust')->first()?->recorded_at?->format('H:i'),
                'pressure_high' => $readings->max('pressure_rel'),
                'pressure_low' => $readings->min('pressure_rel'),
                'uv_max' => $readings->max('uv_index'),
                'solar_max' => $readings->max('solar_radiation'),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * Get forecast data from cache only (populated by weather:poll-external)
     */
    public function forecast(Narrator $narrator, ForecastNlgCacheService $cacheService): JsonResponse
    {
        $latitude = Setting::latitude();
        $longitude = Setting::longitude();
        $source = Setting::getValue('forecast.default_source', 'fct_yrno_block.php');
        $stationId = Setting::getValue('weatherflow.station_id', '');
        $sourceKeys = [
            'fct_yrno_block.php' => "yrno_forecast_{$latitude}_{$longitude}",
            'fct_darksky_block.php' => "openweathermap_forecast_{$latitude}_{$longitude}",
            'fct_wu_block.php' => "wunderground_forecast_{$latitude}_{$longitude}",
            'fct_wxsim_block.php' => "wxsim_forecast_" . md5(Setting::getValue('wxsim.file_path', '')),
            'fct_ec_block.php' => "ec_forecast_{$latitude}_{$longitude}",
            'fct_tempest_block.php' => 'tempest_forecast_' . ($stationId !== '' ? $stationId : '0'),
            'fct_aemet_block.php' => "aemet_forecast_" . Setting::getValue('aemet.municipio', ''),
            'fct_dwd_block.php' => 'dwd_forecast_' . Setting::getValue('dwd.station_id', ''),
        ];

        $forecastData = Cache::get($sourceKeys[$source] ?? null);
        if (!$forecastData) {
            $forecastData = Cache::get("forecast_{$latitude}_{$longitude}");
        }

        $forecast = is_array($forecastData) ? ($forecastData['forecast'] ?? null) : null;
        $daily = $this->extractDailyForecast($forecast, 14) ?? [];
        $hourly = $this->extractHourlyForecast($forecast, 48) ?? [];

        // If we only have 1–2 days, cache is likely stale (poll may be failing). Force a fresh
        // fetch so the next request gets the full 9 days from Yr.no.
        if ($source === 'fct_yrno_block.php' && count($daily) < 3 && is_array($forecast) && count($forecast) > 12) {
            $cacheKey = $sourceKeys[$source] ?? null;
            if ($cacheKey) {
                Cache::forget($cacheKey);
                Cache::forget("forecast_{$latitude}_{$longitude}");
                $fresh = ForecastServiceFactory::make()->fetchForecast();
                if ($fresh && isset($fresh['forecast']) && count($fresh['forecast']) > 12) {
                    Cache::put($cacheKey, $fresh, now()->addMinutes(120));
                    Cache::put("forecast_{$latitude}_{$longitude}", $fresh, now()->addMinutes(120));
                    $forecast = $fresh['forecast'];
                    $daily = $this->extractDailyForecast($forecast, 14) ?? [];
                    $hourly = $this->extractHourlyForecast($forecast, 48) ?? [];
                }
            }
        }

        // Add NLG text to all daily forecasts
        // Try to get pre-generated cached NLG first, fall back to on-demand generation
        $currentLocale = app()->getLocale();
        $nlgEnabled = (bool) Setting::getValue('nlg.llm_enabled', false);
        $nlgProvider = Setting::getValue('nlg.provider', 'openai');
        $providers = config('nlg.providers', []);
        $nlgProviderName = $providers[$nlgProvider]['label'] ?? null;
        $nlgModelName = $this->resolveConfiguredNlgModel($nlgProvider, $providers);
        $configuredAiLocales = Setting::getValue('nlg.ai_locales', null);
        if (!is_array($configuredAiLocales)) {
            $configuredAiLocales = [Setting::defaultLanguage()];
        }
        $configuredAiLocales = $cacheService->resolveLocales(null, $configuredAiLocales);
        $configuredAiDaysLimit = $cacheService->resolveAiDaysLimit(
            Setting::getValue('nlg.ai_days', ForecastNlgCacheService::DEFAULT_AI_DAYS),
            ForecastNlgCacheService::DEFAULT_AI_DAYS,
        );

        if (is_array($forecast)) {
            foreach ($daily as $dayIndex => &$day) {
                $draftNlg = Cache::get(ForecastNlgCacheService::draftCacheKey($currentLocale, $day['date']));
                $finalNlg = Cache::get(ForecastNlgCacheService::finalCacheKey($currentLocale, $day['date']));

                if (is_string($finalNlg) && trim($finalNlg) !== '') {
                    $day['nlg_text'] = $finalNlg;
                    if (!is_string($draftNlg) || trim($draftNlg) === '') {
                        $draftNlg = $finalNlg;
                    }
                } else {
                    $dayStart = $day['date'] . 'T00:00:00Z';
                    $dayEnd = $day['date'] . 'T23:59:59Z';

                    $dayHours = array_filter($forecast, function ($entry) use ($dayStart, $dayEnd) {
                        return $entry['time'] >= $dayStart && $entry['time'] <= $dayEnd;
                    });

                    if (empty($dayHours)) {
                        $payload = [
                            'date' => $day['date'],
                            'min_temp_c' => $day['temp_low'] ?? null,
                            'max_temp_c' => $day['temp_high'] ?? null,
                            'precip_prob_pct' => ($day['precipitation'] ?? 0) > 0 ? 70 : 10,
                            'precip_mm' => $day['precipitation'] ?? 0,
                            'precip_type' => ($day['precipitation'] ?? 0) > 0 ? 'rain' : 'none',
                        ];
                        $day['nlg_text'] = $narrator->narrate($payload, ['locale' => $currentLocale]);
                    } else {
                        $periods = [];
                        foreach ($dayHours as $hour) {
                            try {
                                $hourTime = new \DateTime($hour['time']);
                                $hourOfDay = (int) $hourTime->format('H');

                                $period = null;
                                if ($hourOfDay >= 6 && $hourOfDay < 12) {
                                    $period = 'morning';
                                } elseif ($hourOfDay >= 12 && $hourOfDay < 18) {
                                    $period = 'afternoon';
                                } elseif ($hourOfDay >= 18) {
                                    $period = 'evening';
                                }

                                if ($period) {
                                    if (!isset($periods[$period])) {
                                        $periods[$period] = [
                                            'name' => $period,
                                            'temp_c' => [],
                                            'wind_ms' => [],
                                            'wind_dir_deg' => [],
                                            'precip_mm' => [],
                                            'cloud_pct' => [],
                                        ];
                                    }

                                    if (isset($hour['temperature']) && $hour['temperature'] !== null) {
                                        $periods[$period]['temp_c'][] = $hour['temperature'];
                                    }
                                    if (isset($hour['wind_speed']) && $hour['wind_speed'] !== null) {
                                        $periods[$period]['wind_ms'][] = $hour['wind_speed'] / 3.6;
                                    }
                                    if (isset($hour['wind_direction']) && $hour['wind_direction'] !== null) {
                                        $periods[$period]['wind_dir_deg'][] = $hour['wind_direction'];
                                    }
                                    if (isset($hour['cloud_cover']) && $hour['cloud_cover'] !== null) {
                                        $periods[$period]['cloud_pct'][] = $hour['cloud_cover'];
                                    }
                                    $precip = $hour['precipitation_1h'] ?? $hour['precipitation_6h'] ?? null;
                                    if ($precip !== null && $precip > 0) {
                                        $periods[$period]['precip_mm'][] = $precip;
                                    }
                                }
                            } catch (\Exception $e) {
                                continue;
                            }
                        }

                        $preparedPeriods = [];
                        foreach ($periods as $periodName => $period) {
                            $preparedPeriods[] = [
                                'name' => $periodName,
                                'temp_c' => !empty($period['temp_c']) ? array_sum($period['temp_c']) / count($period['temp_c']) : null,
                                'wind_ms' => !empty($period['wind_ms']) ? array_sum($period['wind_ms']) / count($period['wind_ms']) : null,
                                'wind_dir_deg' => !empty($period['wind_dir_deg']) ? $this->circularMeanDeg($period['wind_dir_deg']) : null,
                                'cloud_pct' => !empty($period['cloud_pct']) ? array_sum($period['cloud_pct']) / count($period['cloud_pct']) : null,
                                'precip_mm' => !empty($period['precip_mm']) ? array_sum($period['precip_mm']) : 0,
                                'precip_type' => !empty($period['precip_mm']) ? 'rain' : 'none',
                                'precip_prob_pct' => !empty($period['precip_mm']) ? 70 : 10,
                            ];
                        }

                        if (!empty($preparedPeriods)) {
                            $payload = [
                                'date' => $day['date'],
                                'periods' => $preparedPeriods,
                            ];
                            $day['nlg_text'] = $narrator->narrate($payload, ['locale' => $currentLocale]);
                        } else {
                            $payload = [
                                'date' => $day['date'],
                                'min_temp_c' => $day['temp_low'] ?? null,
                                'max_temp_c' => $day['temp_high'] ?? null,
                                'precip_prob_pct' => ($day['precipitation'] ?? 0) > 0 ? 70 : 10,
                                'precip_mm' => $day['precipitation'] ?? 0,
                                'precip_type' => ($day['precipitation'] ?? 0) > 0 ? 'rain' : 'none',
                            ];
                            $day['nlg_text'] = $narrator->narrate($payload, ['locale' => $currentLocale]);
                        }
                    }

                    $this->storeDeterministicNlg($currentLocale, $day['date'], $day['nlg_text']);
                    $draftNlg = $day['nlg_text'];
                    $finalNlg = $day['nlg_text'];
                }

                $day['nlg_meta'] = $this->buildForecastDayNlgMeta(
                    $currentLocale,
                    $dayIndex,
                    $configuredAiLocales,
                    $configuredAiDaysLimit,
                    $nlgEnabled,
                    $nlgModelName,
                    is_string($draftNlg) ? $draftNlg : null,
                    is_string($finalNlg) ? $finalNlg : null,
                );
            }
            unset($day);
        }

        // Get forecast source name
        $sourceNames = [
            'fct_yrno_block.php' => 'Yr.no (Norwegian Meteorological Institute)',
            'fct_darksky_block.php' => 'OpenWeatherMap',
            'fct_wu_block.php' => 'Weather Underground',
            'fct_wxsim_block.php' => 'WxSim',
            'fct_ec_block.php' => 'Environment Canada',
            'fct_tempest_block.php' => 'WeatherFlow Tempest',
            'fct_aemet_block.php' => 'AEMET (Agencia Estatal de Meteorología)',
            'fct_dwd_block.php' => 'DWD (Deutscher Wetterdienst)',
        ];
        $sourceName = $sourceNames[$source] ?? 'Yr.no (Norwegian Meteorological Institute)';

        return response()->json([
            'success' => true,
            'data' => [
                'daily' => $daily,
                'hourly' => $hourly,
            ],
            'meta' => [
                'forecast_source' => $sourceName,
                'nlg' => [
                    'enabled' => true, // NLG is always enabled (deterministic)
                    'ai_enabled' => $nlgEnabled,
                    'ai_provider' => $nlgProviderName,
                    'ai_model' => $nlgModelName,
                    'ai_days' => $configuredAiDaysLimit,
                    'ai_locales' => $configuredAiLocales,
                ],
            ],
        ]);
    }

    /**
     * Get air quality data from WAQI and local PM2.5 sensors
     * Reads from cache (populated by weather:poll-external)
     */
    public function airQuality(WaqiService $waqi): JsonResponse
    {
        // Read WAQI data from cache (populated by poller)
        $latitude = Setting::latitude();
        $longitude = Setting::longitude();
        $stationMode = Setting::getValue('waqi.station_mode', 'auto');
        $stationId = Setting::getValue('waqi.station_id', '');

        $waqiCacheKey = ($stationMode === 'manual' && !empty($stationId))
            ? "waqi_station_{$stationId}"
            : "waqi_{$latitude}_{$longitude}";
        $externalData = Cache::get($waqiCacheKey);

        // Get local PM2.5 data from weather station
        $reading = WeatherReading::mostRecent();
        $localPm25 = null;

        if ($reading && $reading->hasPm25Sensors()) {
            $localPm25 = [
                'ch1' => $reading->pm25_ch1,
                'ch2' => $reading->pm25_ch2,
                'ch3' => $reading->pm25_ch3,
                'ch4' => $reading->pm25_ch4,
                'avg_24h_ch1' => $reading->pm25_avg_24h_ch1,
                'level' => $reading->pm25_level,
                'pm10' => $reading->pm10,
                'pm10_avg_24h' => $reading->pm10_avg_24h,
            ];
        }

        return response()->json([
            'success' => $externalData !== null || $localPm25 !== null,
            'waqi' => $externalData,
            'local_pm25' => $localPm25,
        ]);
    }

    /**
     * Get current noise sensor data. For live widget polling: if cache is older than 1 min,
     * fetches from Sensor.Community and updates cache. Same auth as dashboard API.
     */
    public function noise(): JsonResponse
    {
        if (!Setting::getValue('luftdaten_noise.enabled', false)) {
            return response()->json(['success' => false, 'enabled' => false, 'data' => null]);
        }
        $sensorId = trim(Setting::getValue('luftdaten_noise.sensor_id', ''));
        if ($sensorId === '') {
            return response()->json(['success' => false, 'enabled' => true, 'data' => null]);
        }
        $cacheKey = "luftdaten_noise_{$sensorId}";
        $data = Cache::get($cacheKey);
        $cachedAt = is_array($data) ? ($data['cached_at'] ?? null) : null;
        $stale = true;
        if ($cachedAt !== null) {
            try {
                $stale = Carbon::parse($cachedAt)->diffInSeconds(now(), false) >= 60;
            } catch (\Throwable $e) {
                $stale = true;
            }
        }
        if ($stale) {
            $service = app(\App\Services\AirQuality\LuftdatenService::class);
            $fresh = $service->fetchBySensorId($sensorId);
            if ($fresh !== null) {
                $fresh['cached_at'] = now()->toIso8601String();
                Cache::put($cacheKey, $fresh, now()->addMinutes(20));
                $data = $fresh;
            }
        }
        return response()->json([
            'success' => $data !== null && is_array($data),
            'enabled' => true,
            'data' => $data,
            'sensor_id' => $sensorId,
        ]);
    }

    /**
     * Get sun and moon data
     * Reads from cache (populated by weather:poll-external)
     */
    public function astronomy(
        SunMoonService $sunMoon
    ): JsonResponse {
        // Read from cache (populated by poller)
        $sun = Cache::get('astronomy_sun');
        $moon = Cache::get('astronomy_moon');
        $aurora = Cache::get('aurora_kp_index');
        $issPassesData = Cache::get('iss_passes');
        
        // Events and meteors: cache to avoid recalculating 5 years of moon phases on every request
        $eventsCacheKey = 'astronomy_events_1825';
        $eventsCacheMinutes = 60; // 1 hour; events are date-based and change daily
        $events = Cache::remember($eventsCacheKey, now()->addMinutes($eventsCacheMinutes), function () use ($sunMoon) {
            return $sunMoon->getUpcomingEvents(1825);
        });

        $meteorsCacheKey = 'astronomy_meteors_' . date('Y');
        $meteors = Cache::remember($meteorsCacheKey, now()->addHours(24), function () use ($sunMoon) {
            return $sunMoon->getMeteorShowers();
        });
        
        // Format ISS data for frontend (cache-only)
        $issData = null;
        $issLocation = Cache::get('iss_location');

        // Get astronauts in space (cached separately)
        $apiSource = Setting::getValue('iss.astronauts_api_source', 'corquaid');
        $astronautsCacheKey = "astros_in_space_{$apiSource}";
        $issAstronauts = Cache::get($astronautsCacheKey);
        
        $showISS = Setting::getValue('iss.show_iss', true);
        $showTiangong = Setting::getValue('iss.show_tiangong', true);
        
        // Format ISS data for frontend
        $issData = null;
        if ($showISS) {
            // Get Tiangong passes and location from cache
            $tiangongPassesData = Cache::get('tiangong_passes');
            $tiangongLocation = Cache::get('tiangong_location');
            
            // Get passes from cache (populated by poller)
            if ($issPassesData && isset($issPassesData['passes']) && count($issPassesData['passes']) > 0) {
                $passes = $issPassesData['passes'];
                
                // Find next visible pass
                $nextVisiblePass = null;
                foreach ($passes as $pass) {
                    if ($pass['visible'] ?? false) {
                        $nextVisiblePass = $pass;
                        break;
                    }
                }
                // If no visible pass found, use first pass
                if (!$nextVisiblePass) {
                    $nextVisiblePass = $passes[0] ?? null;
                }
                
                // Calculate distance if we have location
                $distance = null;
                if ($issLocation && $issLocation['success']) {
                    $latitude = Setting::latitude();
                    $longitude = Setting::longitude();
                    $distance = $this->calculateDistance(
                        $latitude,
                        $longitude,
                        $issLocation['latitude'],
                        $issLocation['longitude']
                    );
                }
                
                $issData = [
                    'location' => $issLocation,
                    'next_pass' => $nextVisiblePass,
                    'all_passes' => $passes,
                    'pass_source' => $issPassesData['source'] ?? 'unknown',
                    'pass_note' => $issPassesData['note'] ?? null,
                    'astronauts' => $issAstronauts,
                    'distance_km' => $distance ? round($distance) : null,
                    'altitude_km' => 408, // Average ISS altitude
                    'speed_kmh' => 27600, // Average orbital speed
                    'count' => count($passes),
                ];
            }
        }
        
        // Format Tiangong data for frontend
        $tiangongData = null;
        if ($showTiangong) {
            $tiangongPassesData = Cache::get('tiangong_passes');
            $tiangongLocation = Cache::get('tiangong_location');
            
            if ($tiangongPassesData && isset($tiangongPassesData['passes']) && count($tiangongPassesData['passes']) > 0) {
                $passes = $tiangongPassesData['passes'];
                
                // Find next visible pass
                $nextVisiblePass = null;
                foreach ($passes as $pass) {
                    if ($pass['visible'] ?? false) {
                        $nextVisiblePass = $pass;
                        break;
                    }
                }
                if (!$nextVisiblePass) {
                    $nextVisiblePass = $passes[0] ?? null;
                }
                
                // Calculate distance if we have location
                $distance = null;
                if ($tiangongLocation && $tiangongLocation['success']) {
                    $latitude = Setting::latitude();
                    $longitude = Setting::longitude();
                    $distance = $this->calculateDistance(
                        $latitude,
                        $longitude,
                        $tiangongLocation['latitude'],
                        $tiangongLocation['longitude']
                    );
                }
                
                $tiangongData = [
                    'location' => $tiangongLocation,
                    'next_pass' => $nextVisiblePass,
                    'all_passes' => $passes,
                    'pass_source' => $tiangongPassesData['source'] ?? 'unknown',
                    'pass_note' => $tiangongPassesData['note'] ?? null,
                    'distance_km' => $distance ? round($distance) : null,
                    'altitude_km' => 380, // Average Tiangong altitude
                    'speed_kmh' => 27600, // Average orbital speed
                    'count' => count($passes),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'sun' => $sun,
            'moon' => $moon,
            'events' => $events,
            'meteors' => $meteors,
            'aurora' => $aurora,
            'iss' => $issData,
            'tiangong' => $tiangongData,
            'astronauts' => $issAstronauts, // Shared astronaut data
        ]);
    }

    /**
     * Get METAR data for nearby airports
     */
    public function metar(Request $request, MetarService $metar): JsonResponse
    {
        if (!Setting::getValue('metar.enabled', false)) {
            return response()->json([
                'success' => true,
                'data' => null,
            ]);
        }

        $icao = $request->input('icao');
        $primaryIcao = Setting::getValue('metar.primary_icao', 'EHAM');

        // If a custom ICAO is requested, validate and fetch live
        if ($icao && strtoupper($icao) !== strtoupper($primaryIcao)) {
            if (!preg_match('/^[A-Z]{4}$/i', $icao)) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Invalid ICAO code',
                ], 422);
            }

            $data = $metar->fetchMetar([strtoupper($icao)]);

            return response()->json([
                'success' => $data !== null && !empty($data),
                'data' => $data,
            ]);
        }

        // Default: return cached primary station data, fall back to live fetch
        $data = Cache::get("metar_{$primaryIcao}");

        if ($data === null) {
            $data = $metar->fetchMetar([strtoupper($primaryIcao)]);
        }

        return response()->json([
            'success' => $data !== null,
            'data' => $data,
        ]);
    }

    /**
     * Get historical data for charts
     */
    public function history(Request $request): JsonResponse
    {
        $period = $request->input('period', '24h');
        $field = $request->input('field', 'temperature');

        $periodWindows = [
            '24h' => now()->subHours(24),
            '48h' => now()->subHours(48),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '1y' => now()->subYear(),
        ];
        $bucketSecondsByPeriod = [
            '24h' => 300,     // 5 min
            '48h' => 600,     // 10 min
            '7d' => 3600,     // 1 h
            '30d' => 10800,   // 3 h
            '1y' => 86400,    // 1 day
        ];
        $maxPointsByPeriod = [
            '24h' => 288,
            '48h' => 288,
            '7d' => 168,
            '30d' => 240,
            '1y' => 366,
        ];

        if (!array_key_exists($period, $periodWindows)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid period',
            ], 400);
        }

        // Validate field to prevent SQL injection
        $allowedFields = [
            'temperature', 'temperature_indoor', 'feels_like', 'dew_point', 'heat_index', 'wind_chill',
            'humidity', 'humidity_indoor', 'pressure_rel', 'pressure_abs',
            'wind_speed', 'wind_gust', 'wind_direction', 'wind_speed_avg_10m',
            'rain_rate', 'rain_hourly', 'rain_daily', 'rain_weekly', 'rain_monthly', 'rain_yearly',
            'uv_index', 'solar_radiation', 'lux',
            'temp_1', 'temp_2', 'temp_3', 'temp_4', 'temp_5', 'temp_6', 'temp_7', 'temp_8',
            'soil_moisture_1', 'soil_moisture_2', 'soil_moisture_3', 'soil_moisture_4',
            'pm25_ch1', 'pm25_ch2', 'pm25_ch3', 'pm25_ch4', 'pm10', 'co2',
            'lightning_count', 'lightning_distance',
        ];

        if (!in_array($field, $allowedFields)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid field',
            ], 400);
        }

        $bucketSeconds = $bucketSecondsByPeriod[$period];
        $maxPoints = $maxPointsByPeriod[$period];
        $driver = DB::connection()->getDriverName();
        $wrappedField = DB::connection()->getQueryGrammar()->wrap($field);

        $unixTimestampExpr = match ($driver) {
            'sqlite' => "CAST(strftime('%s', recorded_at) AS INTEGER)",
            'pgsql' => "EXTRACT(EPOCH FROM recorded_at)",
            default => 'UNIX_TIMESTAMP(recorded_at)',
        };
        $bucketExpr = match ($driver) {
            'pgsql' => "CAST(($unixTimestampExpr / {$bucketSeconds}) AS BIGINT)",
            'mysql', 'mariadb' => "CAST(($unixTimestampExpr / {$bucketSeconds}) AS UNSIGNED)",
            default => "CAST(($unixTimestampExpr / {$bucketSeconds}) AS INTEGER)",
        };

        $rows = DB::table('weather_readings')
            ->where('recorded_at', '>=', $periodWindows[$period])
            ->whereNotNull($field)
            ->selectRaw('MIN(recorded_at) as recorded_at')
            ->selectRaw("AVG({$wrappedField}) as value")
            ->groupByRaw($bucketExpr)
            ->orderBy('recorded_at')
            ->limit($maxPoints + 2)
            ->get();

        $readings = $rows->map(function (object $row): ?array {
            if (!isset($row->recorded_at) || $row->recorded_at === null) {
                return null;
            }

            try {
                $time = Carbon::parse((string) $row->recorded_at)->toIso8601String();
            } catch (\Throwable $e) {
                return null;
            }

            return [
                'time' => $time,
                'value' => is_numeric($row->value ?? null) ? (float) $row->value : null,
            ];
        })
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'data' => $readings,
            'field' => $field,
            'period' => $period,
            'sampling' => [
                'bucket_seconds' => $bucketSeconds,
                'max_points' => $maxPoints,
            ],
        ]);
    }

    /**
     * Get all dashboard data in one call
     * 
     * IMPORTANT: This method only reads from cache - it does NOT trigger any API calls.
     * External data is populated by the weather:poll-external command.
     * This ensures the dashboard loads instantly.
     */
    public function dashboard(Request $request, DashboardPayloadService $dashboardPayload): JsonResponse
    {
        $payload = $dashboardPayload->getDashboardPayload($request);

        return response()
            ->json($payload)
            ->header('Cache-Control', DashboardPayloadService::browserCacheControl($request->user()));
    }

    
    /**
     * Circular mean of an array of degrees (0-360), avoiding the 359°/1° wraparound problem.
     */
    private function circularMeanDeg(array $degrees): float
    {
        $sinSum = 0;
        $cosSum = 0;
        foreach ($degrees as $d) {
            $rad = deg2rad((float) $d);
            $sinSum += sin($rad);
            $cosSum += cos($rad);
        }
        $avg = rad2deg(atan2($sinSum / count($degrees), $cosSum / count($degrees)));
        return $avg < 0 ? $avg + 360 : round($avg);
    }

    /**
     * Extract daily forecast from cached Yr.no data.
     * Only includes today and future days (skips yesterday) so the widget shows a forward-looking forecast.
     * Uses station timezone so "today" is correct regardless of UTC date in the API.
     */
    private function extractDailyForecast(?array $forecast, int $days): ?array
    {
        if (!$forecast) return null;
        
        $stationTz = Setting::timezone();
        $todayStr = Carbon::now($stationTz)->toDateString();
        
        $daily = [];
        $seenDates = [];
        
        foreach ($forecast as $entry) {
            $timeStr = $entry['time'] ?? $entry['date'] ?? null;
            if (!$timeStr) {
                continue;
            }
            // Resolve date in station timezone so we skip yesterday correctly (API times are often UTC)
            try {
                $entryDate = Carbon::parse($timeStr, 'UTC')->setTimezone($stationTz)->toDateString();
            } catch (\Exception $e) {
                $entryDate = trim(substr($timeStr, 0, 10));
            }
            if ($entryDate < $todayStr) {
                continue; // skip yesterday and past days
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
                // Update min/max and precipitation for the day that matches this entry's date
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
        
        // Safety: drop any past dates and enforce chronological order (e.g. cache/format edge cases)
        $daily = array_values(array_filter($daily, fn ($d) => ($d['date'] ?? '') >= $todayStr));
        usort($daily, fn ($a, $b) => strcmp($a['date'] ?? '', $b['date'] ?? ''));
        $daily = array_slice($daily, 0, $days);
        
        return $daily ?: null;
    }
    
    /**
     * Extract hourly forecast from cached Yr.no data.
     * Only returns entries from "now" onward so that stale cache (e.g. after poll stops for days)
     * does not expose past hours — which would make the temperature widget's "Verwachting"
     * line stop early and the /forecast page show no clickable days.
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

    /**
     * Get sensor configuration - which sensors are active
     */
    public function sensors(): JsonResponse
    {
        $reading = WeatherReading::mostRecent();
        
        if (!$reading) {
            return response()->json([
                'success' => false,
                'message' => 'No data available',
            ]);
        }

        $sensors = [
            'outdoor' => [
                'temperature' => $reading->temperature !== null,
                'humidity' => $reading->humidity !== null,
            ],
            'indoor' => [
                'temperature' => $reading->temperature_indoor !== null,
                'humidity' => $reading->humidity_indoor !== null,
            ],
            'pressure' => $reading->pressure_rel !== null,
            'wind' => $reading->wind_speed !== null,
            'rain' => $reading->rain_daily !== null,
            'solar' => [
                'uv' => $reading->uv_index !== null,
                'radiation' => $reading->solar_radiation !== null,
            ],
            'lightning' => $reading->lightning_distance !== null || $reading->lightning_count !== null,
            'extra_temps' => [],
            'soil_moisture' => [],
            'soil_temp' => [],
            'leaf_wetness' => [],
            'pm25' => [],
            'leak' => [],
            'co2' => $reading->co2 !== null,
        ];

        // Check extra temperature sensors
        for ($i = 1; $i <= 8; $i++) {
            if ($reading->{"temp_{$i}"} !== null) {
                $sensors['extra_temps'][] = $i;
            }
        }

        // Check soil moisture sensors
        for ($i = 1; $i <= 8; $i++) {
            if ($reading->{"soil_moisture_{$i}"} !== null) {
                $sensors['soil_moisture'][] = $i;
            }
        }

        // Check soil temperature sensors
        for ($i = 1; $i <= 8; $i++) {
            if ($reading->{"soil_temp_{$i}"} !== null) {
                $sensors['soil_temp'][] = $i;
            }
        }

        // Check leaf wetness sensors
        for ($i = 1; $i <= 8; $i++) {
            if ($reading->{"leaf_wetness_{$i}"} !== null) {
                $sensors['leaf_wetness'][] = $i;
            }
        }

        // Check PM2.5 sensors
        for ($i = 1; $i <= 4; $i++) {
            if ($reading->{"pm25_ch{$i}"} !== null) {
                $sensors['pm25'][] = $i;
            }
        }

        // Check leak sensors
        for ($i = 1; $i <= 4; $i++) {
            if ($reading->{"leak_ch{$i}"} !== null) {
                $sensors['leak'][] = $i;
            }
        }

        return response()->json([
            'success' => true,
            'sensors' => $sensors,
            'battery_status' => $reading->battery_status,
            'station' => [
                'type' => $reading->station_type,
                'model' => $reading->station_model,
                'runtime_hours' => $reading->station_runtime ? round($reading->station_runtime / 3600, 1) : null,
            ],
        ]);
    }

    /**
     * Save widget and Quick Stats tile order from drag-and-drop on dashboard
     * Route is protected by auth+admin middleware in web.php
     */
    public function saveWidgetOrder(Request $request, DashboardPayloadService $dashboardPayload): JsonResponse
    {
        $widgetOrder = $request->input('widget_order', []);
        // Unknown ids are dropped rather than persisted; the bar renders strictly
        // from the registry, so junk in the order would silently do nothing.
        $statOrder = StatTileRegistry::sanitizeOrder((array) $request->input('stat_order', []));

        if (empty($widgetOrder) && empty($statOrder)) {
            return response()->json(['success' => false, 'message' => 'No widget order provided']);
        }

        // Get current layout settings
        $layout = $this->readWidgetLayout();

        // Each side is optional: edit mode can save a stats-only reorder.
        if (!empty($widgetOrder)) {
            $layout['widget_order'] = $widgetOrder;
        }
        if (!empty($statOrder)) {
            $layout['stat_order'] = $statOrder;
        }

        Setting::setValue('widgets.layout', $layout, 'json', 'widgets');

        // Ensure dashboard API immediately serves the new layout (no stale 30s payload cache).
        $dashboardPayload->forgetDashboardPayloadCaches();

        \Log::info('Widget order saved', ['order' => $widgetOrder, 'stat_order' => $statOrder]);

        return response()->json([
            'success' => true,
            'message' => 'Widget order saved!',
            'widget_order' => $widgetOrder,
            'stat_order' => $statOrder,
        ]);
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

    /**
     * Earthquakes Page
     * Shows all recent earthquakes in a sortable table (cache-only)
     */
    public function earthquakesPage(Request $request)
    {
        $latitude = Setting::latitude();
        $longitude = Setting::longitude();

        // Get sort parameter
        $sort = $request->get('sort', 'time');

        // 'nearby' by default: a site with a configured radius should not open
        // on events thousands of kilometres away.
        $scope = $request->get('scope') === 'all' ? 'all' : 'nearby';

        if ($scope === 'nearby') {
            $cachedAll = Cache::get("earthquakes_{$latitude}_{$longitude}", []);

            // The nearby list is populated by the scheduler. Until it has run,
            // derive it from the worldwide list rather than showing nothing.
            if (!is_array($cachedAll) || empty($cachedAll)) {
                $radiusKm = (int) Setting::getValue('earthquakes.radius_km', 500);
                $worldwide = Cache::get('earthquakes_all', []);
                $cachedAll = array_values(array_filter(
                    is_array($worldwide) ? $worldwide : [],
                    fn ($eq) => is_array($eq) && isset($eq['distance']) && $eq['distance'] <= $radiusKm
                ));
            }
        } else {
            $cachedAll = Cache::get('earthquakes_all');
            if (!is_array($cachedAll) || empty($cachedAll)) {
                $cachedAll = Cache::get("earthquakes_{$latitude}_{$longitude}", []);
            }
        }

        $earthquakes = array_values(array_filter(array_map(function ($eq) {
            if (!is_array($eq)) {
                return null;
            }

            if (!isset($eq['distance']) && isset($eq['distance_km'])) {
                $eq['distance'] = $eq['distance_km'];
            }
            if (!isset($eq['place']) && isset($eq['location'])) {
                $eq['place'] = $eq['location'];
            }
            if (!isset($eq['location']) && isset($eq['place'])) {
                $eq['location'] = $eq['place'];
            }
            if (!isset($eq['time']) && isset($eq['date_time'])) {
                $eq['time'] = $eq['date_time'];
            }
            if (!isset($eq['date_time']) && isset($eq['time'])) {
                $eq['date_time'] = $eq['time'];
            }

            return $eq;
        }, is_array($cachedAll) ? $cachedAll : [])));

        // Sort earthquakes
        if (!empty($earthquakes)) {
            usort($earthquakes, function ($a, $b) use ($sort) {
                switch ($sort) {
                    case 'magnitude':
                        return ($b['magnitude'] ?? 0) <=> ($a['magnitude'] ?? 0);
                    case 'distance':
                        return ($a['distance'] ?? 0) <=> ($b['distance'] ?? 0);
                    case 'time':
                    default:
                        return strtotime($b['time'] ?? $b['date_time'] ?? '0') <=> strtotime($a['time'] ?? $a['date_time'] ?? '0');
                }
            });
        }

        $stationLocation = Setting::stationLocation() ?: Setting::stationName();

        return view('weather.earthquakes', [
            'earthquakes' => $earthquakes,
            'sort' => $sort,
            'scope' => $scope,
            'stationLocation' => $stationLocation,
            'settings' => [
                'latitude' => $latitude,
                'longitude' => $longitude,
            ],
        ]);
    }

    /**
     * Embeddable live weather widget (iframe embed for external websites)
     */
    public function widget(Request $request)
    {
        $reading  = WeatherReading::mostRecent();
        $units    = view()->shared('activeUnits') ?? 'metric';

        $tempUnit  = $units === 'imperial' ? '°F' : '°C';
        $windUnit  = match ($units) {
            'imperial'   => 'mph',
            'scandinavia' => 'm/s',
            default      => 'km/h',
        };

        // Extract values with units conversion (readings stored in metric)
        $temp = null;
        $humidity = null;
        $windSpeed = null;
        $rainDaily = null;
        $pressure = null;
        $feelsLike = null;
        $windDir = null;
        $lastUpdate = null;
        $beaufort = null;

        if ($reading) {
            $temp = $reading->temperature;
            if ($units === 'imperial' && $temp !== null) {
                $temp = round($temp * 9 / 5 + 32, 1);
            }
            $feelsLike = $reading->feels_like;
            if ($units === 'imperial' && $feelsLike !== null) {
                $feelsLike = round($feelsLike * 9 / 5 + 32, 1);
            }
            $humidity  = $reading->humidity;
            $windSpeed = $reading->wind_speed;
            if ($units === 'imperial' && $windSpeed !== null) {
                $windSpeed = round($windSpeed * 0.621371, 1);
            } elseif ($units === 'scandinavia' && $windSpeed !== null) {
                $windSpeed = round($windSpeed / 3.6, 1);
            }
            $windDir   = $reading->wind_direction_compass;
            $rainDaily = $reading->rain_daily ?? 0;
            $pressure  = $reading->pressure_rel;
            $beaufort  = $reading->beaufort_description;
            $lastUpdate = $reading->recorded_at?->diffForHumans();
        }

        return view('weather.embed', [
            'reading'         => $reading,
            'stationName'     => Setting::stationName(),
            'stationLocation' => Setting::stationLocation() ?: Setting::stationName(),
            'siteUrl'         => rtrim(url('/'), '/'),
            'activeUnits'     => $units,
            'tempUnit'        => $tempUnit,
            'windUnit'        => $windUnit,
            'temp'            => $temp,
            'feelsLike'       => $feelsLike,
            'humidity'        => $humidity,
            'windSpeed'       => $windSpeed,
            'windDir'         => $windDir,
            'rainDaily'       => $rainDaily,
            'pressure'        => $pressure,
            'beaufort'        => $beaufort,
            'lastUpdate'      => $lastUpdate,
        ]);
    }

    /**
     * Air Quality Page with settings
     * Reads from cache (populated by weather:poll-external)
     */
    public function airQualityPage(Request $request)
    {
        // Read WAQI data from cache (populated by poller)
        $latitude = Setting::latitude();
        $longitude = Setting::longitude();
        $stationMode = Setting::getValue('waqi.station_mode', 'auto');
        $stationId = Setting::getValue('waqi.station_id', '');

        // Index type: cookie override > admin setting
        $defaultIndexType = Setting::getValue('airquality.index_type', 'us');
        $indexType = $request->cookie('aqi_index_type', $defaultIndexType);

        // Validate index type
        if (!in_array($indexType, ['us', 'eea', 'uk'])) {
            $indexType = $defaultIndexType;
        }

        // Try cache-only WAQI data (new and legacy keys)
        $waqiKeys = ($stationMode === 'manual' && !empty($stationId))
            ? ["waqi_station_{$stationId}", "waqi_station_{$stationId}_{$defaultIndexType}"]
            : ["waqi_{$latitude}_{$longitude}", "waqi_{$latitude}_{$longitude}_{$defaultIndexType}"];
        $waqiData = null;
        foreach ($waqiKeys as $cacheKey) {
            $cached = Cache::get($cacheKey);
            if (!empty($cached)) {
                $waqiData = $cached;
                break;
            }
        }

        // Recalculate AQI if user's index type differs from cached data
        if (!empty($waqiData) && ($waqiData['index_type'] ?? $defaultIndexType) !== $indexType) {
            $waqiData = $this->recalculateWaqiAqi($waqiData, $indexType);
        }

        // Read Luftdaten data from cache (populated by poller)
        $luftdatenSensorId = Setting::getValue('luftdaten.sensor_id', '');
        $luftdatenData = $luftdatenSensorId ? Cache::get("luftdaten_{$luftdatenSensorId}") : null;

        // Recalculate Luftdaten AQI if user's index type differs
        if (!empty($luftdatenData) && isset($luftdatenData['formatted'])) {
            $luftdatenData = $this->recalculateLuftdatenAqi($luftdatenData, $indexType);
        }

        // Luftdaten noise sensor (optionally refreshed on load when stale)
        $luftdatenNoiseData = $this->getLuftdatenNoiseForDisplay();

        $noiseEnabled = Setting::getValue('luftdaten_noise.enabled', false);
        $noiseSensorId = trim(Setting::getValue('luftdaten_noise.sensor_id', ''));
        $grafanaNoiseHistoryUrl = null;
        if ($noiseEnabled && $noiseSensorId !== '') {
            $grafanaNoiseHistoryUrl = 'https://api-rrd.madavi.de:3000/grafana/d-solo/000000004/single-sensor-view-for-map?'
                . http_build_query([
                    'orgId' => 1,
                    'var-node' => $noiseSensorId,
                    'from' => 'now-24h',
                    'to' => 'now',
                    'timezone' => 'browser',
                    'theme' => 'dark',
                    'panelId' => 'panel-12',
                    '__feature.dashboardSceneSolo' => 'true',
                ]);
        }

        $stationLocation = Setting::stationLocation() ?: Setting::stationName();

        // Pollen forecast (populated by poller)
        $pollenData = Cache::get('pollen_forecast');

        // Determine active tab: default based on arriving route
        $activeTab = $request->input('tab',
            $request->routeIs('pollen') ? 'pollen' : ($request->routeIs('noise') ? 'noise' : 'airquality')
        );
        if (!in_array($activeTab, ['airquality', 'noise', 'pollen'], true)) {
            $activeTab = 'airquality';
        }

        return view('weather.airquality', [
            'waqi' => $waqiData ?? [],
            'luftdaten' => $luftdatenData,
            'luftdaten_noise' => $luftdatenNoiseData,
            'grafana_noise_history_url' => $grafanaNoiseHistoryUrl,
            'indexType' => $indexType,
            'defaultIndexType' => $defaultIndexType,
            'stationLocation' => $stationLocation,
            'pollen' => $pollenData,
            'activeTab' => $activeTab,
            'settings' => [
                'waqi_enabled' => Setting::getValue('waqi.enabled', true),
                'waqi_station_mode' => Setting::getValue('waqi.station_mode', 'auto'),
                'waqi_station_id' => Setting::getValue('waqi.station_id', ''),
                'luftdaten_enabled' => Setting::getValue('luftdaten.enabled', true),
                'luftdaten_sensor_id' => Setting::getValue('luftdaten.sensor_id', ''),
                'luftdaten_noise_enabled' => Setting::getValue('luftdaten_noise.enabled', false),
                'luftdaten_noise_sensor_id' => Setting::getValue('luftdaten_noise.sensor_id', ''),
                'index_type' => $indexType,
                'pollen_openmeteo_enabled' => (bool) Setting::getValue('pollen.openmeteo_enabled', true),
                'pollen_google_enabled'    => (bool) Setting::getValue('pollen.google_enabled', false),
                'pollen_ambee_enabled'     => (bool) Setting::getValue('pollen.ambee_enabled', false),
            ],
        ]);
    }

    /**
     * Recalculate WAQI AQI based on user's selected index type
     */
    private function recalculateWaqiAqi(array $data, string $indexType): array
    {
        // Get concentrations (already converted from AQI sub-indices)
        $concentrations = $data['pollutants_concentration'] ?? [];

        if (empty($concentrations)) {
            return $data;
        }

        // Use the WaqiService to recalculate with user's index type
        $waqiService = app(\App\Services\AirQuality\WaqiService::class);
        $calculatedIndex = $waqiService->calculateIndex($concentrations, $indexType);

        // Get category for the new index type
        $category = $waqiService->getAqiCategory($calculatedIndex['value'], $indexType);

        // Update the data
        $data['aqi'] = $calculatedIndex['value'];
        $data['index_type'] = $indexType;
        $data['category'] = $category;
        $data['dominant_pollutant_calculated'] = $calculatedIndex['dominant_pollutant'];

        return $data;
    }

    /**
     * Recalculate Luftdaten AQI based on user's selected index type
     */
    private function recalculateLuftdatenAqi(array $data, string $indexType): array
    {
        // Get PM values from formatted data or raw values
        $pm25 = $data['formatted']['pm25']['value'] ?? $data['values']['P2'] ?? $data['values']['pm25'] ?? null;
        $pm10 = $data['formatted']['pm10']['value'] ?? $data['values']['P1'] ?? $data['values']['pm10'] ?? null;

        if ($pm25 === null && $pm10 === null) {
            return $data;
        }

        // Use a temporary instance to calculate AQI
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

        // Update the formatted data with new AQI
        $data['formatted']['aqi'] = $aqi;
        $data['formatted']['index_type'] = $indexType;

        return $data;
    }

    /**
     * Get noise sensor data for display. If "refresh on load" is enabled and cache is
     * older than configured max age, fetches from Sensor.Community and updates cache.
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
                    // Keep existing cache on parse/refresh error
                }
            }
        }
        return $data;
    }

    /**
     * Calculate distance between two points using Haversine formula
     */
    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Get radar data (RainViewer API frames)
     */
    public function radar(): JsonResponse
    {
        $frames = Cache::get('rainviewer_frames_proxy');
        if (is_array($frames) && isset($frames['data'])) {
            $frames = $frames['data'];
        } elseif (!is_array($frames)) {
            $frames = Cache::get('rainviewer_frames');
        }

        $pastFrames = data_get($frames, 'radar.past');
        $generated = (int) ($frames['generated'] ?? 0);
        $framesStale = !is_array($pastFrames)
            || count($pastFrames) === 0
            || $generated <= 0
            || (time() - $generated) > 720;

        if ($framesStale) {
            try {
                /** @var \App\Services\Radar\RainViewerService $service */
                $service = app(\App\Services\Radar\RainViewerService::class);
                $freshFrames = $service->getRadarFrames(bypassCache: true);
                $freshPast = data_get($freshFrames, 'radar.past');

                if (is_array($freshPast) && count($freshPast) > 0) {
                    Cache::put('rainviewer_frames', $freshFrames, now()->addMinutes(30));
                    Cache::put('rainviewer_frames_proxy', [
                        'success' => true,
                        'data' => [
                            'version' => $freshFrames['version'] ?? '2.0',
                            'generated' => $freshFrames['generated'] ?? time(),
                            'host' => $freshFrames['host'] ?? 'https://tilecache.rainviewer.com',
                            'radar' => [
                                'past' => $freshPast,
                            ],
                        ],
                    ], now()->addMinutes(30));

                    $frames = $freshFrames;
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('RainViewer radar fallback refresh failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($frames) {
            $useProxy = (bool) Setting::getValue('radar.use_proxy', false);
            $host = $useProxy
                ? '/api/radar/tile'
                : ($frames['host'] ?? 'https://tilecache.rainviewer.com');
            return response()->json([
                'success' => true,
                'data' => [
                    'version' => $frames['version'] ?? '2.0',
                    'generated' => $frames['generated'] ?? time(),
                    'host' => $host,
                    'radar' => [
                        'past' => $frames['radar']['past'] ?? [],
                    ],
                ],
                'station' => [
                    'latitude' => Setting::latitude(),
                    'longitude' => Setting::longitude(),
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Radar data not available',
            'data' => null,
        ]);
    }

    /**
     * Get KNMI radar nowcast data (2-hour precipitation forecast)
     * Reads from cache (populated by weather:poll-external)
     */
    public function radarNowcast(KnmiNowcastService $nowcastService): JsonResponse
    {
        // Check if feature is enabled
        $enabled = Setting::getValue('radar.nowcast_enabled', false);
        
        if (!$enabled) {
            return response()->json([
                'success' => false,
                'message' => 'Radar nowcast is not enabled',
                'data' => null,
            ]);
        }

        $metadata = Cache::get('knmi_nowcast_metadata');
        if (!$metadata) {
            // Self-heal on first run: metadata generation is local and does not call external APIs.
            $metadata = $nowcastService->getNowcastMetadata();
            if (!empty($metadata['times'])) {
                Cache::put('knmi_nowcast_metadata', $metadata, now()->addMinutes(30));
            }
        }
        if (!$metadata) {
            return response()->json([
                'success' => false,
                'message' => 'Radar nowcast data not available',
                'data' => null,
            ]);
        }
        
        return response()->json([
            'success' => true,
            'data' => $metadata,
            'station' => [
                'latitude' => Setting::latitude(),
                'longitude' => Setting::longitude(),
            ],
        ]);
    }

    /**
     * Get provider-agnostic future radar frames for dashboard widget blending.
     */
    public function radarFutureFrames(RadarFutureFramesService $futureFramesService): JsonResponse
    {
        if (!(bool) Setting::getValue('radar.widget_future_frames_enabled', false)) {
            return response()->json([
                'success' => true,
                'message' => null,
                'data' => [
                    'provider' => RadarFutureFramesService::PROVIDER_NONE,
                    'configured_provider' => $futureFramesService->normalizeProviderKey(
                        (string) Setting::getValue('radar.widget_future_frames_provider', RadarFutureFramesService::PROVIDER_AUTO)
                    ),
                    'frames' => [],
                ],
            ]);
        }

        $configuredProvider = $futureFramesService->normalizeProviderKey(
            (string) Setting::getValue('radar.widget_future_frames_provider', RadarFutureFramesService::PROVIDER_AUTO)
        );
        $result = $futureFramesService->getFutureFrames($configuredProvider);

        return response()->json([
            'success' => (bool) ($result['success'] ?? false),
            'message' => $result['message'] ?? null,
            'data' => [
                'provider' => $result['provider'] ?? RadarFutureFramesService::PROVIDER_NONE,
                'configured_provider' => $configuredProvider,
                'frames' => $this->normalizeFutureFramesForClient(
                    is_array($result['frames'] ?? null) ? $result['frames'] : []
                ),
            ],
            'station' => [
                'latitude' => Setting::latitude(),
                'longitude' => Setting::longitude(),
            ],
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $frames
     * @return array<int, array<string, mixed>>
     */
    private function normalizeFutureFramesForClient(array $frames): array
    {
        return array_map(function ($frame) {
            if (!is_array($frame)) {
                return [];
            }

            $normalized = $frame;
            $url = (string) ($frame['url'] ?? '');
            $requiresProxy = (bool) ($frame['requires_proxy'] ?? false);

            if ($requiresProxy && $url !== '' && preg_match('#^https://#i', $url)) {
                $proxyUrl = '/api/radar/future-image?url=' . rawurlencode($url);
                $normalized['upstream_url'] = $url;
                $normalized['proxy_url'] = $proxyUrl;
                // Make proxy URL the canonical URL so even older clients avoid CORS issues.
                $normalized['url'] = $proxyUrl;
            }

            return $normalized;
        }, $frames);
    }

    /**
     * Get solar radiation forecast data (Open-Meteo / Forecast.Solar / etc.)
     * Reads from cache (populated by weather:poll-external --source=solar_forecast).
     * Includes astronomy-based sunrise/sunset for today and tomorrow for daylight trimming.
     * Response is cached for 5 minutes to avoid recalculating sun times on every hit.
     */
    public function solarNowcast(SunMoonService $sunMoon): JsonResponse
    {
        $enabled = Setting::getValue('solar_forecast.enabled', false);

        if (!$enabled) {
            return response()->json([
                'success' => false,
                'message' => 'Solar radiation forecast is not enabled',
                'data' => null,
            ]);
        }

        $forecastHours = (int) Setting::getValue('solar_forecast.forecast_hours', 48);
        $cacheKey = "solar_forecast_{$forecastHours}h";
        $data = Cache::get($cacheKey);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Solar radiation forecast data not available',
                'data' => null,
            ]);
        }

        $tz = Setting::timezone();
        $today = Carbon::today($tz);
        $responseCacheKey = "solar_nowcast_response_{$today->format('Y-m-d')}_{$forecastHours}";
        $sunData = Cache::remember($responseCacheKey, 300, function () use ($sunMoon, $today, $tz) {
            $tomorrow = $today->copy()->addDay();
            $sunToday = Cache::get('astronomy_sun') ?? $sunMoon->getSunData($today);
            $sunTomorrow = $sunMoon->getSunData($tomorrow);

            return [
                'sun_today' => [
                    'sunrise_iso' => $this->sunTimeToIso($today, $sunToday['sunrise'] ?? null, $tz),
                    'sunset_iso' => $this->sunTimeToIso($today, $sunToday['sunset'] ?? null, $tz),
                ],
                'sun_tomorrow' => [
                    'sunrise_iso' => $this->sunTimeToIso($tomorrow, $sunTomorrow['sunrise'] ?? null, $tz),
                    'sunset_iso' => $this->sunTimeToIso($tomorrow, $sunTomorrow['sunset'] ?? null, $tz),
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'sun_today' => $sunData['sun_today'],
            'sun_tomorrow' => $sunData['sun_tomorrow'],
            'station' => [
                'latitude' => Setting::latitude(),
                'longitude' => Setting::longitude(),
            ],
        ]);
    }

    /**
     * Build ISO8601 UTC string for a date + "H:i" time in station timezone.
     */
    private function sunTimeToIso(Carbon $date, ?string $time, string $tz): ?string
    {
        if ($time === null || $time === '') {
            return null;
        }
        $combined = $date->format('Y-m-d') . ' ' . $time . ':00';
        try {
            return Carbon::parse($combined, $tz)->utc()->toIso8601String();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get KNMI WMS layers metadata and available times
     * Reads from cache (populated by weather:poll-external)
     */
    public function wmsLayers(KnmiWmsService $wmsService): JsonResponse
    {
        // Check if feature is enabled
        $enabled = Setting::getValue('satellite.wms_enabled', false);
        
        if (!$enabled) {
            return response()->json([
                'success' => false,
                'message' => 'WMS layers are not enabled',
                'data' => null,
            ]);
        }

        $layers = $wmsService->getAvailableLayers();
        $times = Cache::get('knmi_wms_times', []);
        $latestTime = Cache::get('knmi_wms_latest_time');

        return response()->json([
            'success' => true,
            'data' => [
                'layers' => $layers,
                'times' => $times,
                'latest_time' => $latestTime,
                'base_url' => 'https://anonymous.api.dataplatform.knmi.nl/wms/adaguc-server',
                'dataset' => 'msg_cpp_products',
            ],
            'station' => [
                'latitude' => Setting::latitude(),
                'longitude' => Setting::longitude(),
            ],
        ]);
    }

    /**
     * Get WMS GetMap URL for a specific layer/time
     */
    public function wmsMap(KnmiWmsService $wmsService, Request $request): JsonResponse
    {
        $enabled = Setting::getValue('satellite.wms_enabled', false);
        
        if (!$enabled) {
            return response()->json([
                'success' => false,
                'message' => 'WMS layers are not enabled',
            ]);
        }

        $layer = $request->input('layer');
        $style = $request->input('style', 'default');
        $time = $request->input('time', 'latest');
        $width = (int) $request->input('width', 512);
        $height = (int) $request->input('height', 512);
        $opacity = (float) $request->input('opacity', 1.0);

        // Validate layer
        $availableLayers = $wmsService->getAvailableLayers();
        if (!isset($availableLayers[$layer])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid layer',
            ]);
        }

        $url = $wmsService->getWmsUrl($layer, $style, $time, null, $width, $height, $opacity);
        $legendUrl = $wmsService->getLegendUrl($layer, $style);

        return response()->json([
            'success' => true,
            'data' => [
                'url' => $url,
                'legend_url' => $legendUrl,
                'layer_info' => $availableLayers[$layer],
            ],
        ]);
    }

    private function storeDeterministicNlg(string $locale, string $date, string $text): void
    {
        $ttl = now()->addMinutes(ForecastNlgCacheService::CACHE_TTL_MINUTES);

        Cache::put(ForecastNlgCacheService::draftCacheKey($locale, $date), $text, $ttl);
        Cache::put(ForecastNlgCacheService::finalCacheKey($locale, $date), $text, $ttl);
    }

    private function buildForecastDayNlgMeta(
        string $locale,
        int $dayIndex,
        array $configuredAiLocales,
        ?int $configuredAiDaysLimit,
        bool $aiEnabled,
        ?string $aiModelName,
        ?string $draftText,
        ?string $finalText,
    ): array {
        $localeSelected = in_array($locale, $configuredAiLocales, true);
        $dayWithinAiWindow = $configuredAiDaysLimit === null || $dayIndex < $configuredAiDaysLimit;
        $isAiEnhanced = $aiEnabled
            && $localeSelected
            && $dayWithinAiWindow
            && is_string($draftText)
            && trim($draftText) !== ''
            && is_string($finalText)
            && trim($finalText) !== ''
            && trim($draftText) !== trim($finalText);

        if ($isAiEnhanced) {
            return [
                'source' => 'ai',
                'status_label' => $aiModelName ? __('Enhanced with') . ' ' . $aiModelName : __('AI enhanced'),
                'is_ai_enhanced' => true,
            ];
        }

        if (!$aiEnabled) {
            $statusLabel = __('Deterministic only');
        } elseif (!$localeSelected) {
            $statusLabel = __('Deterministic only for this language');
        } elseif (!$dayWithinAiWindow) {
            $statusLabel = __('AI not scheduled for this forecast day');
        } else {
            $statusLabel = __('Deterministic fallback');
        }

        return [
            'source' => 'deterministic',
            'status_label' => $statusLabel,
            'is_ai_enhanced' => false,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $providers
     */
    private function resolveConfiguredNlgModel(string $provider, array $providers): ?string
    {
        $configuredModel = trim((string) Setting::getValue('nlg.model', ''));
        if ($configuredModel !== '') {
            return $configuredModel;
        }

        $defaultModel = trim((string) ($providers[$provider]['default_model'] ?? ''));

        return $defaultModel !== '' ? $defaultModel : null;
    }

}
