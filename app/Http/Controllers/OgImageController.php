<?php

namespace App\Http\Controllers;

use App\Models\DailySummary;
use App\Models\Setting;
use App\Models\WeatherReading;
use App\Services\FireWeatherCalculator;
use App\Services\OgImageService;
use App\Support\OgAppearance;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Serves dynamic 1200×630 Open Graph PNG images for social sharing.
 *
 * All routes require og.enabled = true; otherwise returns a 404.
 * Results are cached so image generation only happens once per TTL.
 *
 * Route names / endpoints:
 *   GET /og/home.png             → og.home
 *   GET /og/forecast.png         → og.forecast
 *   GET /og/history/{date}.png   → og.history
 *   GET /og/statistics/{year}.png → og.statistics
 *   GET /og/fire-weather.png     → og.fire-weather
 *   GET /og/air-quality.png      → og.air-quality
 *   GET /og/astronomy.png        → og.astronomy
 *   GET /og/aviation/{icao}.png  → og.aviation
 *   GET /og/generic/{page}.png   → og.generic
 */
class OgImageController extends Controller
{
    // -------------------------------------------------------------------------
    // Guards & helpers
    // -------------------------------------------------------------------------

    private function ogEnabled(): bool
    {
        return (bool) Setting::getValue('og.enabled', false);
    }

    /** Build a PNG response with appropriate cache headers. */
    private function pngResponse(string $png, int $maxAge = 1800): Response
    {
        return response($png, 200, [
            'Content-Type'  => 'image/png',
            'Cache-Control' => "public, max-age={$maxAge}",
            'X-OG-Driver'   => OgImageService::resolvedDriver() ?? 'none',
        ]);
    }

    /** Abort with 404 if OG images are disabled. */
    private function checkEnabled(): void
    {
        if (!$this->ogEnabled()) {
            abort(404);
        }
    }

    /**
     * Generate (or retrieve from cache) a PNG card.
     *
     * The PNG binary is base64-encoded before caching so that any cache driver
     * (file, database, Redis, …) can store it safely without binary encoding issues.
     * All exceptions are caught, logged, and converted to a clean 500 abort so that
     * Ignition / the debug renderer never receives binary image data in its context.
     *
     * @param  string   $key      Cache key
     * @param  mixed    $ttl      Cache TTL (seconds, DateInterval, or Carbon)
     * @param  \Closure $generate Closure that returns a raw PNG binary string
     * @param  int      $maxAge   HTTP Cache-Control max-age in seconds
     */
    private function cachedPng(string $key, mixed $ttl, \Closure $generate, int $maxAge = 1800): Response
    {
        $key .= '_appearance_'.OgAppearance::fingerprint();
        try {
            // Validate any existing cached value; purge corrupted entries.
            $existing = Cache::get($key);
            if ($existing !== null && !is_string($existing)) {
                Cache::forget($key);
                $existing = null;
            }

            $encoded = $existing ?? Cache::remember($key, $ttl, static function () use ($generate) {
                return base64_encode($generate());
            });

            $png = base64_decode($encoded, true);

            if ($png === false || strlen($png) === 0) {
                // Cached value was corrupted — regenerate once.
                Cache::forget($key);
                $png = base64_decode(
                    Cache::remember($key, $ttl, static function () use ($generate) {
                        return base64_encode($generate());
                    }),
                    true
                );
            }

            if (!$png) {
                abort(503, 'OG image could not be generated.');
            }

            return $this->pngResponse($png, $maxAge);

        } catch (\Throwable $e) {
            // Strip non-UTF-8 bytes from the message so the logger doesn't fail either.
            $safeMsg = mb_convert_encoding($e->getMessage(), 'UTF-8', 'UTF-8');
            logger()->error("OG image '{$key}' generation failed", [
                'exception' => get_class($e),
                'message'   => $safeMsg,
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            ]);
            abort(500, 'OG image generation failed — see laravel.log for details.');
        }
    }

    /** Shared meta used by every card. */
    private function baseMeta(): array
    {
        return [
            'station_name'     => Setting::stationName(),
            'station_location' => Setting::stationLocation() ?: Setting::stationName(),
            'domain'           => parse_url(config('app.url', url('/')), PHP_URL_HOST) ?? url('/'),
            'updated_at'       => now(),
        ];
    }

    /** Resolve display unit system to short labels. */
    private function unitLabels(): array
    {
        $unit = Setting::getValue('display.unit_system', 'metric');
        return match ($unit) {
            'imperial' => ['temp' => '°F', 'wind' => 'mph',  'pressure' => 'inHg'],
            'uk'       => ['temp' => '°C', 'wind' => 'mph',  'pressure' => 'hPa'],
            default    => ['temp' => '°C', 'wind' => 'km/h', 'pressure' => 'hPa'],
        };
    }

    // -------------------------------------------------------------------------
    // Endpoints
    // -------------------------------------------------------------------------

    /** Home / live-conditions card. Cached 30 minutes — intentionally stale so viewers want to visit for current conditions. */
    public function home(): Response
    {
        $this->checkEnabled();

        return $this->cachedPng('og_home', now()->addMinutes(30), function () {
            $reading = WeatherReading::latest('created_at')->first();
            $units   = $this->unitLabels();

            return OgImageService::make()->homeCard(array_merge($this->baseMeta(), [
                'temperature'  => $reading?->temperature,
                'feels_like'   => $reading?->feels_like,
                'humidity'     => $reading?->humidity,
                'wind_speed'   => $reading?->wind_speed,
                'wind_dir_deg' => $reading?->wind_direction,
                'pressure'     => $reading?->pressure_rel,
                'dew_point'    => $reading?->dew_point,
                'rain_daily'   => $reading?->rain_daily,
                'uv_index'     => $reading?->uv_index,
                'solar_rad'    => $reading?->solar_radiation,
                'unit_temp'    => $units['temp'],
                'unit_wind'    => $units['wind'],
                'unit_pressure'=> $units['pressure'],
            ]));
        }, 1800);
    }

    /** Forecast card. Cached 30 minutes. */
    public function forecast(): Response
    {
        $this->checkEnabled();

        return $this->cachedPng('og_forecast', now()->addMinutes(30), function () {
            $units = $this->unitLabels();
            $lat   = Setting::latitude();
            $lon   = Setting::longitude();

            $forecastRaw = Cache::get("yrno_forecast_{$lat}_{$lon}")
                ?? Cache::get("forecast_{$lat}_{$lon}");

            // Cache miss — trigger a live fetch (also repopulates the cache for next time).
            if (!$forecastRaw) {
                $forecastRaw = \App\Services\Forecast\ForecastServiceFactory::make()->fetchForecast();
            }

            $days = [];
            if ($forecastRaw && isset($forecastRaw['forecast'])) {
                $today = now()->startOfDay();

                // Detect hourly (time + temperature) vs daily (date + temp_max) format.
                $first    = $forecastRaw['forecast'][0] ?? [];
                $isHourly = isset($first['time']) && isset($first['temperature']);

                if ($isHourly) {
                    // Group hourly entries by UTC date, then aggregate per day.
                    $byDay = [];
                    foreach ($forecastRaw['forecast'] as $fc) {
                        $dayKey = substr($fc['time'], 0, 10);
                        $byDay[$dayKey]['temps'][]  = $fc['temperature'];
                        $byDay[$dayKey]['precip']   = ($byDay[$dayKey]['precip'] ?? 0)
                            + (float)($fc['precipitation_1h'] ?? $fc['precipitation_6h'] ?? 0);
                        if (isset($fc['wind_speed'])) {
                            $byDay[$dayKey]['winds'][] = $fc['wind_speed'];
                        }
                        if (!empty($fc['symbol'])) {
                            $hour = (int)substr($fc['time'], 11, 2);
                            if ($hour >= 8 && $hour <= 18) {
                                $byDay[$dayKey]['day_symbols'][] = $fc['symbol'];
                            } else {
                                $byDay[$dayKey]['all_symbols'][] = $fc['symbol'];
                            }
                        }
                    }

                    foreach ($byDay as $dayKey => $data) {
                        if (count($days) >= 3) break;
                        $fcDate = \Carbon\Carbon::parse($dayKey)->startOfDay();
                        $diff   = $today->diffInDays($fcDate, false);
                        if ($diff < 0) continue;
                        $label  = match ((int)$diff) {
                            0  => 'Today',
                            1  => 'Tomorrow',
                            default => $fcDate->format('D'),
                        };
                        $pool   = !empty($data['day_symbols']) ? $data['day_symbols'] : ($data['all_symbols'] ?? []);
                        $counts = array_count_values($pool);
                        arsort($counts);
                        $days[] = [
                            'label'  => $label,
                            'high'   => !empty($data['temps']) ? max($data['temps']) : null,
                            'low'    => !empty($data['temps']) ? min($data['temps']) : null,
                            'symbol' => array_key_first($counts) ?? '',
                            'rain'   => ($data['precip'] ?? 0) > 0 ? round($data['precip'], 1) : null,
                            'wind'   => !empty($data['winds']) ? max($data['winds']) : null,
                        ];
                    }
                } else {
                    // Daily data format.
                    foreach ($forecastRaw['forecast'] as $fc) {
                        if (count($days) >= 3) break;
                        $fcDate = \Carbon\Carbon::parse($fc['date'] ?? '')->startOfDay();
                        $diff   = $today->diffInDays($fcDate, false);
                        if ($diff < 0) continue;
                        $label  = match ((int)$diff) {
                            0  => 'Today',
                            1  => 'Tomorrow',
                            default => $fcDate->format('D'),
                        };
                        $days[] = [
                            'label'  => $label,
                            'high'   => $fc['temp_max'] ?? $fc['max_temp'] ?? null,
                            'low'    => $fc['temp_min'] ?? $fc['min_temp'] ?? null,
                            'symbol' => $fc['symbol'] ?? $fc['condition'] ?? '',
                            'rain'   => $fc['precipitation_sum'] ?? $fc['rain_sum'] ?? $fc['precip'] ?? null,
                            'wind'   => $fc['wind_speed_max'] ?? $fc['wind_max'] ?? null,
                        ];
                    }
                }
            }

            return OgImageService::make()->forecastCard(array_merge($this->baseMeta(), [
                'days'      => $days,
                'unit_temp' => $units['temp'],
            ]));
        }, 1800);
    }

    /** History day card. Cached until end of day. */
    public function history(string $date): Response
    {
        $this->checkEnabled();

        try {
            $carbon = \Carbon\Carbon::createFromFormat('Y-m-d', $date);
            if (!$carbon || $carbon->format('Y-m-d') !== $date) abort(404);
        } catch (\Exception) {
            abort(404);
        }

        return $this->cachedPng("og_history_{$date}", now()->endOfDay()->addMinute(), function () use ($date) {
            $summary = DailySummary::where('date', $date)->first();
            $units   = $this->unitLabels();

            return OgImageService::make()->historyCard(array_merge($this->baseMeta(), [
                'date_label'  => \Carbon\Carbon::createFromFormat('Y-m-d', $date)->format('j F Y'),
                'has_data'    => $summary !== null,
                'temp_high'   => $summary?->temp_high,
                'temp_low'    => $summary?->temp_low,
                'temp_avg'    => $summary?->temp_avg,
                'rain'        => $summary?->rain_total,
                'wind_max'    => $summary?->wind_max,
                'wind_avg'    => $summary?->wind_avg,
                'sun_hours'   => $summary?->solar_hours,
                'uv_max'      => $summary?->uv_max,
                'unit_temp'   => $units['temp'],
                'unit_wind'   => $units['wind'],
            ]));
        }, 3600);
    }

    /** Statistics year card. Cached 6 hours. */
    public function statistics(?string $year = null): Response
    {
        $this->checkEnabled();

        $year = $year ? (int)$year : (int)date('Y');
        if ($year < 1900 || $year > 2100) abort(404);

        return $this->cachedPng("og_statistics_{$year}", now()->addHours(6), function () use ($year) {
            $units = $this->unitLabels();

            $agg = DailySummary::whereYear('date', $year)
                ->selectRaw('MAX(temp_high) as max_temp, MIN(temp_low) as min_temp,
                             AVG(temp_avg) as avg_temp, SUM(rain_total) as rain_sum,
                             SUM(solar_hours) as sun_total,
                             SUM(CASE WHEN rain_total >= 0.1 THEN 1 ELSE 0 END) as rain_days,
                             SUM(CASE WHEN temp_low < 0 THEN 1 ELSE 0 END) as frost_days')
                ->first();

            return OgImageService::make()->statisticsCard(array_merge($this->baseMeta(), [
                'year'        => $year,
                'hottest_temp'=> $agg?->max_temp,
                'coldest_temp'=> $agg?->min_temp,
                'rain_total'  => $agg?->rain_sum,
                'avg_temp'    => $agg?->avg_temp,
                'rain_days'   => $agg?->rain_days,
                'sun_total'   => $agg?->sun_total,
                'frost_days'  => $agg?->frost_days,
                'unit_temp'   => $units['temp'],
            ]));
        }, 21600);
    }

    /** Fire weather card. Cached until 00:10 next day (same as page). */
    public function fireWeather(): Response
    {
        $this->checkEnabled();

        // Refresh ~4x/day so the share card tracks the day's fire danger as it warms up,
        // rather than freezing the overnight value (a scheduled job re-warms it every 6h).
        return $this->cachedPng('og_fire_weather', now()->addHours(6), function () {
            $current = Cache::get('fire_weather_current');
            if (!$current) {
                $calc    = app(FireWeatherCalculator::class);
                $current = $calc->currentIndices();
            }

            $reading = WeatherReading::latest('created_at')->first();

            return OgImageService::make()->fireWeatherCard(array_merge($this->baseMeta(), [
                'angstrom'    => $current['angstrom_index'] ?? null,
                'danger_level'=> $current['danger_level'] ?? null,
                'dry_days'    => $current['consecutive_dry'] ?? null,
                'rain_7d'     => $current['rain_7d'] ?? null,
                'rain_30d'    => $current['rain_30d'] ?? null,
                'max_temp'    => $current['temp_high'] ?? $reading?->temperature,
                'min_humidity'=> $current['humidity_low'] ?? $reading?->humidity,
            ]));
        }, 3600);
    }

    /** Air quality card. Cached 1 hour. */
    public function airQuality(): Response
    {
        $this->checkEnabled();

        return $this->cachedPng('og_air_quality', now()->addHour(), function () {
            $lat = Setting::latitude();
            $lon = Setting::longitude();

            $waqi  = Cache::get("waqi_{$lat}_{$lon}");
            $aqi   = null;
            $pm25  = null;
            $pm10  = null;
            $label = 'Air Quality Index';
            $src   = 'Sensor';

            if ($waqi && is_array($waqi)) {
                $aqi  = $waqi['aqi'] ?? $waqi['data']['aqi'] ?? null;
                $pm25 = $waqi['iaqi']['pm25']['v'] ?? $waqi['pm25'] ?? null;
                $pm10 = $waqi['iaqi']['pm10']['v'] ?? $waqi['pm10'] ?? null;
                $src  = $waqi['station']['name'] ?? $waqi['city']['name'] ?? 'WAQI';
                $label = match (true) {
                    $aqi === null   => 'Air Quality Index',
                    $aqi <= 50      => 'Good',
                    $aqi <= 100     => 'Moderate',
                    $aqi <= 150     => 'Unhealthy for Sensitive Groups',
                    $aqi <= 200     => 'Unhealthy',
                    default         => 'Very Unhealthy / Hazardous',
                };
            }

            $reading = WeatherReading::latest('created_at')->first();

            if ($aqi === null) {
                $pm25 = $reading?->pm25_ch1;
                $pm10 = $reading?->pm10;
                $src  = 'Local sensor';
            }

            return OgImageService::make()->airQualityCard(array_merge($this->baseMeta(), [
                'aqi'       => $aqi,
                'aqi_label' => $label,
                'pm25'      => $pm25,
                'pm10'      => $pm10,
                'pm25_24h'  => $reading?->pm25_avg_24h_ch1,
                'co2'       => $reading?->co2,
                'source'    => $src,
            ]));
        }, 3600);
    }

    /** Astronomy card. Cached 1 hour. */
    public function astronomy(): Response
    {
        $this->checkEnabled();

        return $this->cachedPng('og_astronomy', now()->addHour(), function () {
            $sun  = Cache::get('astronomy_sun')  ?? [];
            $moon = Cache::get('astronomy_moon') ?? [];

            $sunrise = null;
            $sunset  = null;
            if (!empty($sun['sunrise'])) {
                $sunrise = is_numeric($sun['sunrise'])
                    ? \Carbon\Carbon::createFromTimestamp($sun['sunrise'])->format('H:i')
                    : $sun['sunrise'];
            }
            if (!empty($sun['sunset'])) {
                $sunset = is_numeric($sun['sunset'])
                    ? \Carbon\Carbon::createFromTimestamp($sun['sunset'])->format('H:i')
                    : $sun['sunset'];
            }

            $dayLength = null;
            if ($sunrise && $sunset) {
                try {
                    $s    = \Carbon\Carbon::createFromFormat('H:i', $sunrise);
                    $e    = \Carbon\Carbon::createFromFormat('H:i', $sunset);
                    $mins = $s->diffInMinutes($e);
                    $dayLength = sprintf('%dh %02dm', intdiv($mins, 60), $mins % 60);
                } catch (\Exception) {}
            }

            // Civil twilight (dawn/dusk) if available
            $dawn = null;
            $dusk = null;
            if (!empty($sun['civil_twilight_begin'])) {
                $val  = $sun['civil_twilight_begin'];
                $dawn = is_numeric($val) ? \Carbon\Carbon::createFromTimestamp($val)->format('H:i') : $val;
            }
            if (!empty($sun['civil_twilight_end'])) {
                $val  = $sun['civil_twilight_end'];
                $dusk = is_numeric($val) ? \Carbon\Carbon::createFromTimestamp($val)->format('H:i') : $val;
            }

            return OgImageService::make()->astronomyCard(array_merge($this->baseMeta(), [
                'date_label'       => now()->format('j F Y'),
                'sunrise'          => $sunrise ?? '—',
                'sunset'           => $sunset  ?? '—',
                'day_length'       => $dayLength ?? '—',
                'moon_phase'       => $moon['phase_name'] ?? '',
                'moon_illumination'=> isset($moon['illumination']) ? (int)round((float)$moon['illumination']) : null,
                'dawn'             => $dawn,
                'dusk'             => $dusk,
            ]));
        }, 3600);
    }

    /** Aviation METAR card. Cached 30 minutes. */
    public function aviation(string $icao): Response
    {
        $this->checkEnabled();

        $icao = strtoupper($icao);
        if (!preg_match('/^[A-Z]{4}$/', $icao)) abort(404);

        return $this->cachedPng("og_aviation_{$icao}", now()->addMinutes(30), function () use ($icao) {
            // Try poller-populated cache first; fall back to a live API call if stale.
            $cached = Cache::get("metar_{$icao}");
            if (!is_array($cached)) {
                $fresh  = app(\App\Services\Aviation\MetarService::class)->fetchMetar([$icao]);
                $cached = is_array($fresh) ? $fresh : null;
            }
            $metar = is_array($cached) ? ($cached[0] ?? null) : null;

            $hasData         = $metar !== null;
            $wind            = null;
            $visibility      = null;
            $qnh             = null;
            $temp            = null;
            $dewpoint        = null;
            $humidity        = null;
            $windDirDeg      = null;
            $flightCategory  = null;
            $airportName     = null;
            $rawMetar        = null;
            $cloudsSummary   = null;

            if ($hasData) {
                $flightCategory = $metar['flight_category'] ?? null;
                $airportName    = $metar['name'] ?? null;
                $raw            = $metar['raw'] ?? null;
                if ($raw) {
                    $rawMetar = strlen($raw) > 85 ? substr($raw, 0, 82) . '…' : $raw;
                }

                // Wind
                $windDir      = $metar['wind']['direction'] ?? null;
                $windSpeedKts = $metar['wind']['speed_kts'] ?? null;
                $windGustKts  = $metar['wind']['gust_kts']  ?? null;
                if ($windDir !== null && $windSpeedKts !== null) {
                    $wind = $windDir . '° / ' . (int)$windSpeedKts . ' kt';
                    if ($windGustKts) $wind .= ' G' . (int)$windGustKts;
                }
                $windDirDeg = $windDir;

                // Visibility
                $visMet = $metar['visibility']['meters'] ?? null;
                if ($visMet !== null) {
                    $visibility = $visMet >= 9999 ? '10+ km'
                        : ($visMet >= 1000 ? number_format($visMet / 1000, 1) . ' km'
                        : round($visMet) . ' m');
                }

                // QNH
                if (($metar['pressure'] ?? null) !== null) {
                    $qnh = $metar['pressure'] . ' hPa';
                }

                // Temp / dew / humidity
                if (($metar['temperature'] ?? null) !== null) $temp     = number_format((float)$metar['temperature'], 1) . '°C';
                if (($metar['dewpoint']    ?? null) !== null) $dewpoint = number_format((float)$metar['dewpoint'],    1) . '°C';
                if (($metar['humidity']    ?? null) !== null) $humidity = (int)$metar['humidity'] . '%';

                // Cloud layers → "FEW 1500ft · BKN 3000ft"
                $clouds = $metar['clouds'] ?? [];
                if (!empty($clouds)) {
                    $cloudsSummary = implode('  ·  ', array_map(
                        fn($c) => ($c['code'] ?? '') . ($c['base_feet'] ? ' ' . number_format($c['base_feet'], 0) . 'ft' : ''),
                        array_slice($clouds, 0, 3)
                    ));
                }
            }

            return OgImageService::make()->aviationCard(array_merge($this->baseMeta(), [
                'icao'            => $icao,
                'airport_name'    => $airportName,
                'has_data'        => $hasData,
                'flight_category' => $flightCategory,
                'wind'            => $wind,
                'visibility'      => $visibility,
                'qnh'             => $qnh,
                'temp'            => $temp,
                'dewpoint'        => $dewpoint,
                'humidity'        => $humidity,
                'wind_dir_deg'    => $windDirDeg,
                'raw_metar'       => $rawMetar,
                'clouds_summary'  => $cloudsSummary,
            ]));
        }, 1800);
    }

    /**
     * Generic page card (radar, satellite, lightning, earthquakes, etc.).
     *
     * @param string $page  slug: radar | satellite | lightning | earthquakes | community | pressure
     */
    public function generic(string $page): Response
    {
        $this->checkEnabled();

        $config = [
            'radar'      => ['title' => 'Rain Radar',         'accent' => OgImageService::SLATE,  'label' => 'RADAR'],
            'satellite'  => ['title' => 'Satellite Imagery',  'accent' => OgImageService::CYAN,   'label' => 'SATELLITE'],
            'lightning'  => ['title' => 'Lightning Map',      'accent' => OgImageService::AMBER,  'label' => 'LIGHTNING'],
            'earthquakes'=> ['title' => 'Earthquake Monitor', 'accent' => OgImageService::VIOLET, 'label' => 'EARTHQUAKES'],
            'community'  => ['title' => 'Community Stations', 'accent' => OgImageService::TEAL,   'label' => 'COMMUNITY'],
            'pressure'   => ['title' => 'Pressure Maps',      'accent' => OgImageService::BLUE,   'label' => 'PRESSURE'],
            'share'      => ['title' => 'Share & Embed',       'accent' => OgImageService::BLUE,   'label' => 'SHARE'],
        ];

        if (!array_key_exists($page, $config)) abort(404);

        return $this->cachedPng("og_generic_{$page}", now()->addDay(), function () use ($page, $config) {
            $cfg = $config[$page];

            return OgImageService::make()->genericCard(array_merge($this->baseMeta(), [
                'page_title'  => $cfg['title'],
                'page_label'  => $cfg['label'],
                'accent'      => $cfg['accent'],
                'tagline'     => Setting::stationName() . ' — ' . (Setting::stationLocation() ?: Setting::stationName()),
            ]));
        }, 86400);
    }
}
