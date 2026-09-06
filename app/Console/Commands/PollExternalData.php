<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\AirQuality\WaqiService;
use App\Services\AirQuality\LuftdatenService;
use App\Services\Pollen\PollenAggregator;
use App\Services\Alerts\MeteoalarmService;
use App\Services\Astronomy\AuroraService;
use App\Services\Weather\EcowittService;
use App\Services\Astronomy\ISSService;
use App\Services\Astronomy\SunMoonService;
use App\Services\Aviation\MetarService;
use App\Services\Earthquake\EarthquakeService;
use App\Services\Forecast\ForecastServiceFactory;
use App\Services\Radar\RainViewerService;
use App\Services\OpenData\KnmiNowcastService;
use App\Services\OpenData\KnmiWmsService;
use App\Services\Solar\SolarForecastFactory;
use App\Services\TideService;
use App\Services\Wave\OpenMeteoWaveService;
use App\Services\River\RijkswaterstaatRiverService;
use App\Support\CacheFreshness;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PollExternalData extends Command
{
    protected $signature = 'weather:poll-external
                            {--source= : Poll specific source (forecast, rainviewer, airquality, airquality_noise, aurora, iss, metar, earthquake, astronomy, alerts, knmi_nowcast, solar_forecast, knmi_wms, pollen, tide, waves, rivers, all)}
                            {--scheduled : Invoked by Laravel scheduler; trust scheduler cadence and skip internal interval gating for single-source runs}
                            {--force : Force refresh - bypasses interval tracking and polls everything}
                            {--ignore-enabled : Poll even if service is disabled in settings}';

    protected $description = 'Poll external APIs and cache data for fast dashboard access. Has smart interval tracking - run every 15 min via cron.';

    // Polling intervals per service (in minutes)
    // The poller tracks when each service was last polled and skips if not due
    protected array $intervals = [
        'forecast'    => 30,   // Yr.no - every 30 min (rate limited)
        'rainviewer'  => 10,   // RainViewer frame metadata - every 10 min
        'airquality'  => 30,   // WAQI/Luftdaten - every 30 min
        'airquality_noise' => 5,   // Noise sensor only; interval overridden by luftdaten_noise.poll_interval_minutes
        'aurora'      => 30,   // NOAA Kp - every 30 min
        'iss'         => 60,   // ISS passes - every hour
        'metar'       => 30,   // Aviation - every 30 min
        'earthquake'  => 15,   // Earthquakes - every 15 min
        'alerts'      => 15,   // Weather alerts - every 15 min
        'astronomy'   => 60,   // Sun/moon - every hour (calculated)
        'knmi_nowcast' => 10,  // KNMI Radar Nowcast - every 10 min (updates every 5 min)
        'solar_forecast' => 30,   // Solar forecast (Open-Meteo / Forecast.Solar / etc.) - every 30 min
        'knmi_wms'    => 60,   // KNMI WMS Layers - every 60 min (metadata changes infrequently)
        'pollen'      => 60,   // Pollen forecast - every 60 min (pollen levels change slowly)
        'tide'        => 60,   // Tide data - every 60 min (predictions change slowly)
        'waves'       => 60,   // Wave + SST data - every 60 min (same Open-Meteo Marine source)
        'rivers'      => 15,   // River gauge readings - every 15 min (real-time, changes faster)
    ];

    // Cache TTLs (in minutes) - set to 3-4x polling interval for resilience
    protected array $cacheTTLs = [
        'forecast'    => 120,  // 2 hours
        'rainviewer'  => 30,   // 30 minutes
        'airquality'  => 120,  // 2 hours
        'airquality_noise' => 20,  // ~4x typical 5 min poll
        'aurora'      => 120,  // 2 hours
        'iss_passes'  => 240,  // 4 hours
        'iss_location'=> 240,  // 4 hours (was 5 min - way too short!)
        'tiangong'    => 240,  // 4 hours
        'astronauts'  => 240,  // 4 hours
        'metar'       => 120,  // 2 hours
        'earthquake'  => 60,   // 1 hour
        'alerts'      => 60,   // 1 hour
        'astronomy'   => 240,  // 4 hours
        'knmi_nowcast' => 30,  // 30 minutes
        'solar_forecast' => 90,   // 90 minutes
        'knmi_wms'    => 240,  // 4 hours
        'pollen'      => 180,  // 3 hours (3x poll interval)
        'tide'        => 180,  // 3 hours (3x poll interval)
        'waves'       => 180,  // 3 hours (3x poll interval)
        'rivers'      => 45,   // 45 minutes (3x poll interval)
    ];

    /**
     * Check if a service should be polled based on its interval
     * Uses self-healing: falls back to log file modification time if cache is cleared
     * Also checks if actual data cache exists - if missing, fetches immediately
     */
    private function shouldPoll(string $service): bool
    {
        if ($this->option('force')) {
            return true;
        }

        // For scheduled single-source runs, trust the scheduler cadence.
        // Exception: airquality_noise uses admin-configured interval (5/10/15/30 min).
        if ($this->option('scheduled') && (($this->option('source') ?? 'all') !== 'all') && $service !== 'airquality_noise') {
            return true;
        }

        // Self-healing: Check if actual data cache exists FIRST
        // If data cache is missing, fetch immediately regardless of timestamp
        // This handles cases where cache was cleared but timestamp was recovered from log
        if (!$this->hasDataCache($service)) {
            $this->line("   ⚠️  Data cache missing - fetching immediately (self-healing)");
            Log::info("Self-healing: Data cache missing for {$service}, fetching immediately");
            return true;
        }

        $lastPolled = $this->getLastPollTime($service);
        if (!$lastPolled) {
            return true; // Never polled before (no cache, no log file)
        }

        // Noise poll interval is configurable (noise changes quickly)
        $interval = $service === 'airquality_noise'
            ? (int) Setting::getValue('luftdaten_noise.poll_interval_minutes', 5)
            : ($this->intervals[$service] ?? 30);
        
        // Use diffInSeconds() for precise comparison to avoid rounding issues
        // diffInMinutes() rounds down, so 29 min 50 sec becomes 29 min, causing skips
        // By using seconds, we ensure accurate interval checking
        $secondsSinceLastPoll = $lastPolled->diffInSeconds(now());
        $intervalSeconds = $interval * 60;
        
        // Check if the interval has passed (using seconds for precision)
        $shouldPoll = $secondsSinceLastPoll >= $intervalSeconds;
        
        if (!$shouldPoll) {
            // Even if not due by interval, check if data cache is getting old
            // If cache is older than 2x the interval, refresh it (self-healing)
            $cacheAge = $this->getDataCacheAge($service);
            if ($cacheAge && $cacheAge > ($interval * 2)) {
                $this->line("   ⚠️  Data cache is stale ({$cacheAge} min old) - refreshing (self-healing)");
                Log::info("Self-healing: Data cache stale for {$service} ({$cacheAge} min), refreshing");
                return true;
            }
        }
        
        return $shouldPoll;
    }

    /**
     * Get the age of the data cache in minutes
     */
    private function getDataCacheAge(string $service): ?int
    {
        $latitude = Setting::latitude();
        $longitude = Setting::longitude();

        switch ($service) {
            case 'forecast':
                $key = "forecast_{$latitude}_{$longitude}";
                if (Cache::has($key)) {
                    // Check when cache was last updated (approximate from timestamp)
                    $lastPolled = Cache::get("poll_timestamp_{$service}");
                    if ($lastPolled) {
                        $time = is_string($lastPolled) ? \Carbon\Carbon::parse($lastPolled) : $lastPolled;
                        return $time->diffInMinutes(now());
                    }
                }
                return null;

            case 'airquality':
            case 'rainviewer':
            case 'aurora':
            case 'metar':
            case 'earthquake':
            case 'alerts':
            case 'iss':
            case 'astronomy':
            case 'solar_forecast':
            case 'pollen':
            case 'tide':
            case 'waves':
            case 'rivers':
                $lastPolled = Cache::get("poll_timestamp_{$service}");
                if ($lastPolled) {
                    $time = is_string($lastPolled) ? \Carbon\Carbon::parse($lastPolled) : $lastPolled;
                    return $time->diffInMinutes(now());
                }
                return null;

            default:
                return null;
        }
    }

    /**
     * Check if the actual data cache exists and is valid for a service
     * Self-healing: If data cache is missing or invalid, we should fetch immediately
     */
    private function hasDataCache(string $service): bool
    {
        $latitude = Setting::latitude();
        $longitude = Setting::longitude();

        switch ($service) {
            case 'forecast':
                // Check multiple possible cache keys (different sources)
                $source = Setting::getValue('forecast.default_source', 'fct_yrno_block.php');
                $keys = [
                    "forecast_{$latitude}_{$longitude}",
                    "yrno_forecast_{$latitude}_{$longitude}",
                    "openweathermap_forecast_{$latitude}_{$longitude}",
                    "wunderground_forecast_{$latitude}_{$longitude}",
                    "ec_forecast_{$latitude}_{$longitude}",
                ];
                // Also check source-specific key based on configured source
                $sourceKeys = [
                    'fct_yrno_block.php' => "yrno_forecast_{$latitude}_{$longitude}",
                    'fct_darksky_block.php' => "openweathermap_forecast_{$latitude}_{$longitude}",
                    'fct_wu_block.php' => "wunderground_forecast_{$latitude}_{$longitude}",
                    'fct_ec_block.php' => "ec_forecast_{$latitude}_{$longitude}",
                    'fct_aemet_block.php' => "aemet_forecast_" . Setting::getValue('aemet.municipio', ''),
                ];
                if (isset($sourceKeys[$source])) {
                    $keys[] = $sourceKeys[$source];
                }
                foreach ($keys as $key) {
                    $data = Cache::get($key);
                    // Check if cache exists AND has valid data (array with forecast entries)
                    if ($data && is_array($data) && isset($data['forecast']) && count($data['forecast']) > 0) {
                        return true;
                    }
                }
                return false;

            case 'airquality':
                $waqiKey = "waqi_{$latitude}_{$longitude}";
                $waqiData = Cache::get($waqiKey);
                $waqiValid = $waqiData && is_array($waqiData) && !empty($waqiData);
                
                $luftdatenKey = "luftdaten_" . Setting::getValue('luftdaten.sensor_id', '');
                $luftdatenData = Cache::get($luftdatenKey);
                $luftdatenValid = $luftdatenData && is_array($luftdatenData) && !empty($luftdatenData);
                
                $noiseSensorId = Setting::getValue('luftdaten_noise.sensor_id', '');
                $luftdatenNoiseData = $noiseSensorId ? Cache::get("luftdaten_noise_{$noiseSensorId}") : null;
                $luftdatenNoiseValid = $luftdatenNoiseData && is_array($luftdatenNoiseData) && !empty($luftdatenNoiseData);
                return $waqiValid || $luftdatenValid || $luftdatenNoiseValid;

            case 'airquality_noise':
                $noiseId = Setting::getValue('luftdaten_noise.sensor_id', '');
                $noiseData = $noiseId ? Cache::get("luftdaten_noise_{$noiseId}") : null;
                return $noiseData && is_array($noiseData) && !empty($noiseData);

            case 'rainviewer':
                $proxy = Cache::get('rainviewer_frames_proxy');
                $raw = Cache::get('rainviewer_frames');
                $proxyValid = $proxy && is_array($proxy)
                    && isset($proxy['data']['radar']['past'])
                    && is_array($proxy['data']['radar']['past']);
                $rawValid = $raw && is_array($raw)
                    && isset($raw['radar']['past'])
                    && is_array($raw['radar']['past']);
                return $proxyValid || $rawValid;

            case 'aurora':
                $data = Cache::get('aurora_kp_index');
                return $data && is_array($data) && isset($data['kp']);

            case 'metar':
                $icao = Setting::getValue('metar.primary_icao', 'EHAM');
                $data = Cache::get("metar_{$icao}");
                return $data && is_array($data) && !empty($data);

            case 'earthquake':
                $nearby = Cache::get("earthquakes_{$latitude}_{$longitude}");
                $all = Cache::get('earthquakes_all');
                // Empty arrays are valid (no earthquakes is valid data), but both caches
                // must exist so /earthquakes can render full list and widget can stay lean.
                $nearbyValid = $nearby !== null && is_array($nearby);
                $allValid = $all !== null && is_array($all);
                return $nearbyValid && $allValid;

            case 'alerts':
                $data = Cache::get('weather_alerts');
                // Empty array is valid (no alerts is valid data)
                return $data !== null && is_array($data);

            case 'iss':
                $passes = Cache::get('iss_passes');
                $location = Cache::get('iss_location');
                $tiangong = Cache::get('tiangong_passes');
                // At least one should exist and be valid
                return ($passes && is_array($passes) && !empty($passes)) ||
                       ($location && is_array($location) && !empty($location)) ||
                       ($tiangong && is_array($tiangong) && !empty($tiangong));

            case 'astronomy':
                $sun = Cache::get('astronomy_sun');
                $moon = Cache::get('astronomy_moon');
                // Both should exist and be valid
                $sunValid = $sun && is_array($sun) && isset($sun['sunrise']) && isset($sun['sunset']);
                $moonValid = $moon && is_array($moon) && isset($moon['phase_name']);
                return $sunValid && $moonValid;

            case 'knmi_nowcast':
                $data = Cache::get('knmi_nowcast_metadata');
                return $data && is_array($data) && isset($data['times']) && count($data['times']) > 0;

            case 'solar_forecast':
                $hours = Setting::getValue('solar_forecast.forecast_hours', 48);
                $cacheKey = "solar_forecast_{$hours}h";
                $data = Cache::get($cacheKey);
                return $data && is_array($data) && isset($data['times']) && count($data['times']) > 0;

            case 'knmi_wms':
                $data = Cache::get('knmi_wms_times');
                return $data !== null && is_array($data) && count($data) > 0;

            case 'pollen':
                $data = Cache::get('pollen_forecast');
                return $data && is_array($data) && !empty($data);

            case 'tide':
                $tideSource  = Setting::getValue('tide.source', 'rws');
                $stationCode = Setting::getValue("tide.{$tideSource}_station_code",
                               Setting::getValue('tide.station_code', TideService::DEFAULT_STATION));
                $data = Cache::get("tide_{$tideSource}_{$stationCode}");
                return $data && is_array($data) && !empty($data);

            case 'waves':
                $lat  = round((float) Setting::latitude(), 2);
                $lon  = round((float) Setting::longitude(), 2);
                $data = Cache::get("waves_{$lat}_{$lon}");
                return $data && is_array($data) && !empty($data);

            case 'rivers':
                foreach (\App\Services\River\RiverProviderRegistry::active() as $providerId => $providerMeta) {
                    if (Cache::has(\App\Services\River\RiverProviderRegistry::cacheKey($providerId))) {
                        return true;
                    }
                }
                return false;

            default:
                return true; // Unknown service, assume cache exists
        }
    }

    /**
     * Get the last poll time for a service (self-healing: cache first, then log file)
     * IMPORTANT: Only returns the timestamp of the last SUCCESSFUL poll, not when the command last ran
     */
    private function getLastPollTime(string $service): ?\Carbon\Carbon
    {
        // First, try cache (fast and accurate) - this is the timestamp of last SUCCESSFUL poll
        $cacheKey = "poll_timestamp_{$service}";
        $cached = Cache::get($cacheKey);
        if ($cached) {
            try {
                $timestamp = $cached instanceof \Carbon\Carbon
                    ? $cached
                    : \Carbon\Carbon::parse($cached);
                
                // Verify the timestamp is in the past (sanity check)
                if ($timestamp->isPast()) {
                    return $timestamp;
                }
            } catch (\Exception $e) {
                // Invalid cache value, fall through to log file
            }
        }

        // Self-healing: If cache is empty, check log file modification time
        // This handles cases where cache was cleared (server restart, cache:clear, etc.)
        // BUT: Only use log file time if it's older than the cache would be
        // (log file gets updated even when command skips, so it's not reliable for "last poll" time)
        $logPath = $this->getLogPathForService($service);
        if ($logPath && file_exists($logPath)) {
            try {
                $fileTime = filemtime($logPath);
                if ($fileTime !== false) {
                    $lastModified = \Carbon\Carbon::createFromTimestamp($fileTime);
                    // Only trust log file if it's recent (within last 24 hours)
                    // Older log files might be from a previous day
                    if ($lastModified->diffInHours(now()) < 24) {
                        // Only restore from log if cache is truly empty (not just invalid)
                        // This prevents overwriting a valid cache timestamp with a log file time
                        // that might be from a skipped run
                        if (!$cached) {
                            // Silently restore cache from log file (self-healing)
                            Cache::put($cacheKey, $lastModified, now()->addHours(24));
                            return $lastModified;
                        }
                    }
                }
            } catch (\Exception $e) {
                // Log file read failed, return null
            }
        }

        return null; // No cache, no valid log file
    }

    /**
     * Get the log file path for a service
     */
    private function getLogPathForService(string $service): ?string
    {
        $logMap = [
            'forecast' => storage_path('logs/poll-forecast.log'),
            'rainviewer' => storage_path('logs/poll-rainviewer.log'),
            'alerts' => storage_path('logs/poll-alerts.log'),
            'earthquake' => storage_path('logs/poll-earthquake.log'),
            'airquality' => storage_path('logs/poll-airquality.log'),
            'airquality_noise' => storage_path('logs/poll-airquality-noise.log'),
            'aurora' => storage_path('logs/poll-aurora.log'),
            'metar' => storage_path('logs/poll-metar.log'),
            'iss' => storage_path('logs/poll-iss.log'),
            'astronomy' => storage_path('logs/poll-astronomy.log'),
            'knmi_nowcast' => storage_path('logs/poll-knmi-nowcast.log'),
            'solar_forecast' => storage_path('logs/poll-solar-forecast.log'),
            'knmi_wms' => storage_path('logs/poll-knmi-wms.log'),
            'pollen' => storage_path('logs/poll-pollen.log'),
            'tide'   => storage_path('logs/poll-tide.log'),
            'waves'  => storage_path('logs/poll-waves.log'),
            'rivers' => storage_path('logs/poll-rivers.log'),
        ];

        return $logMap[$service] ?? null;
    }

    /**
     * Mark a service as polled (update timestamp)
     */
    private function markPolled(string $service): void
    {
        Cache::put("poll_timestamp_{$service}", now(), now()->addHours(24));
    }

    /**
     * Track the most recent poll attempt, even when it fails.
     */
    private function markAttempted(string $service): void
    {
        Cache::put("poll_attempt_timestamp_{$service}", now(), now()->addHours(24));
    }

    public function handle(): int
    {
        $source = $this->option('source') ?? 'all';
        $force = $this->option('force');
        $ignoreEnabled = $this->option('ignore-enabled');

        $this->info('Polling external data sources...');
        if ($force) {
            $this->info('(--force: bypassing interval tracking)');
        }
        $this->newLine();

        $results = [];
        $skipped = [];
        $notDue = [];

        // Determine which sources to poll
        $pollAll = $source === 'all';

        // 1. Weather Forecast (configurable source)
        if ($pollAll || $source === 'forecast') {
            if (!$this->shouldPoll('forecast')) {
                $notDue[] = 'forecast';
            } else {
                // Forecast is always enabled (uses configured source)
                $this->markAttempted('forecast');
                $results['forecast'] = $this->pollForecast();
                if ($results['forecast'] === true) {
                    $this->markPolled('forecast');
                }
            }
        }

        // 2. RainViewer radar frames
        if ($pollAll || $source === 'rainviewer') {
            if (!$this->shouldPoll('rainviewer')) {
                $notDue[] = 'rainviewer';
            } else {
                $this->markAttempted('rainviewer');
                $results['rainviewer'] = $this->pollRainviewer();
                if ($results['rainviewer'] === true) {
                    $this->markPolled('rainviewer');
                }
            }
        }

        // 3. Air Quality (WAQI)
        if ($pollAll || $source === 'airquality') {
            if (!$this->shouldPoll('airquality')) {
                $notDue[] = 'airquality';
            } else {
                $this->markAttempted('airquality');
                if ($ignoreEnabled || Setting::getValue('waqi.enabled', false)) {
                    $results['airquality_waqi'] = $this->pollAirQuality();
                } else {
                    $skipped[] = 'airquality_waqi (disabled)';
                }

                // Also poll Luftdaten if enabled
                if ($ignoreEnabled || Setting::getValue('luftdaten.enabled', false)) {
                    $results['airquality_luftdaten'] = $this->pollLuftdaten();
                }

                // Also poll Luftdaten noise sensor if enabled
                if ($ignoreEnabled || Setting::getValue('luftdaten_noise.enabled', false)) {
                    $results['airquality_luftdaten_noise'] = $this->pollLuftdatenNoise();
                }

                // Mark as polled if at least one succeeded
                if (($results['airquality_waqi'] ?? false) === true
                    || ($results['airquality_luftdaten'] ?? false) === true
                    || ($results['airquality_luftdaten_noise'] ?? false) === true) {
                    $this->markPolled('airquality');
                }
            }
        }

        // 3b. Noise sensor only (faster cadence; interval from luftdaten_noise.poll_interval_minutes)
        if ($pollAll || $source === 'airquality_noise') {
            if (!$this->shouldPoll('airquality_noise')) {
                $notDue[] = 'airquality_noise';
            } else {
                $this->markAttempted('airquality_noise');
                if ($ignoreEnabled || Setting::getValue('luftdaten_noise.enabled', false)) {
                    $results['airquality_noise'] = $this->pollLuftdatenNoise();
                    if ($results['airquality_noise'] === true) {
                        $this->markPolled('airquality_noise');
                    }
                } else {
                    $skipped[] = 'airquality_noise (disabled)';
                }
            }
        }

        // 4. Aurora / Kp-Index (NOAA) - always enabled (free API)
        if ($pollAll || $source === 'aurora') {
            if (!$this->shouldPoll('aurora')) {
                $notDue[] = 'aurora';
            } else {
                $this->markAttempted('aurora');
                $results['aurora'] = $this->pollAurora();
                if ($results['aurora'] === true) {
                    $this->markPolled('aurora');
                }
            }
        }

        // 5. ISS Passes - always enabled (free API)
        if ($pollAll || $source === 'iss') {
            if (!$this->shouldPoll('iss')) {
                $notDue[] = 'iss';
            } else {
                $this->markAttempted('iss');
                $results['iss'] = $this->pollISS();
                if ($results['iss'] === true) {
                    $this->markPolled('iss');
                }
            }
        }

        // 6. METAR Aviation Weather
        if ($pollAll || $source === 'metar') {
            if (!$this->shouldPoll('metar')) {
                $notDue[] = 'metar';
            } elseif ($ignoreEnabled || Setting::getValue('metar.enabled', false)) {
                $this->markAttempted('metar');
                $results['metar'] = $this->pollMetar();
                if ($results['metar'] === true) {
                    $this->markPolled('metar');
                }
            } else {
                $skipped[] = 'metar (disabled)';
            }
        }

        // 7. Earthquakes
        if ($pollAll || $source === 'earthquake') {
            if (!$this->shouldPoll('earthquake')) {
                $notDue[] = 'earthquake';
            } elseif ($ignoreEnabled || Setting::getValue('earthquakes.enabled', false)) {
                $this->markAttempted('earthquake');
                $results['earthquake'] = $this->pollEarthquakes();
                if ($results['earthquake'] === true) {
                    $this->markPolled('earthquake');
                }
            } else {
                $skipped[] = 'earthquake (disabled)';
            }
        }

        // 8. Weather Alerts
        if ($pollAll || $source === 'alerts') {
            if (!$this->shouldPoll('alerts')) {
                $notDue[] = 'alerts';
            } elseif ($ignoreEnabled || Setting::getValue('alerts.enabled', false)) {
                $this->markAttempted('alerts');
                $results['alerts'] = $this->pollAlerts();
                if ($results['alerts'] === true) {
                    $this->markPolled('alerts');
                }
            } else {
                $skipped[] = 'alerts (disabled)';
            }
        }

        // 9. Astronomy (Sun/Moon) - calculated, always enabled
        if ($pollAll || $source === 'astronomy') {
            if (!$this->shouldPoll('astronomy')) {
                $notDue[] = 'astronomy';
            } else {
                $this->markAttempted('astronomy');
                $results['astronomy'] = $this->pollAstronomy();
                if ($results['astronomy'] === true) {
                    $this->markPolled('astronomy');
                }
            }
        }

        // 10. KNMI Radar Nowcast
        if ($pollAll || $source === 'knmi_nowcast') {
            if (!$this->shouldPoll('knmi_nowcast')) {
                $notDue[] = 'knmi_nowcast';
            } elseif ($ignoreEnabled || Setting::getValue('radar.nowcast_enabled', false)) {
                $this->markAttempted('knmi_nowcast');
                $results['knmi_nowcast'] = $this->pollKnmiNowcast();
                if ($results['knmi_nowcast'] === true) {
                    $this->markPolled('knmi_nowcast');
                }
            } else {
                $skipped[] = 'knmi_nowcast (disabled)';
            }
        }

        // 11. Solar Forecast (Open-Meteo / Forecast.Solar / Open Quartz / Solcast)
        if ($pollAll || $source === 'solar_forecast') {
            if (!$this->shouldPoll('solar_forecast')) {
                $notDue[] = 'solar_forecast';
            } elseif ($ignoreEnabled || Setting::getValue('solar_forecast.enabled', false)) {
                $this->markAttempted('solar_forecast');
                $results['solar_forecast'] = $this->pollSolarForecast();
                if ($results['solar_forecast'] === true) {
                    $this->markPolled('solar_forecast');
                }
            } else {
                $skipped[] = 'solar_forecast (disabled)';
            }
        }

        // 12. KNMI WMS Layers
        if ($pollAll || $source === 'knmi_wms') {
            if (!$this->shouldPoll('knmi_wms')) {
                $notDue[] = 'knmi_wms';
            } elseif ($ignoreEnabled || Setting::getValue('satellite.wms_enabled', false)) {
                $this->markAttempted('knmi_wms');
                $results['knmi_wms'] = $this->pollKnmiWms();
                if ($results['knmi_wms'] === true) {
                    $this->markPolled('knmi_wms');
                }
            } else {
                $skipped[] = 'knmi_wms (disabled)';
            }
        }

        // 13. Pollen Forecast
        if ($pollAll || $source === 'pollen') {
            if (!$this->shouldPoll('pollen')) {
                $notDue[] = 'pollen';
            } else {
                $this->markAttempted('pollen');
                $results['pollen'] = $this->pollPollen();
                if ($results['pollen'] === true) {
                    $this->markPolled('pollen');
                }
            }
        }

        // 14. Tide Data (Rijkswaterstaat)
        if ($pollAll || $source === 'tide') {
            if (!$this->shouldPoll('tide')) {
                $notDue[] = 'tide';
            } elseif ($ignoreEnabled || Setting::getValue('tide.enabled', false)) {
                $this->markAttempted('tide');
                $results['tide'] = $this->pollTide();
                if ($results['tide'] === true) {
                    $this->markPolled('tide');
                }
            } else {
                $skipped[] = 'tide (disabled)';
            }
        }

        // 15. Wave & Sea Surface Temperature (Open-Meteo Marine — always free, no key)
        if ($pollAll || $source === 'waves') {
            if (!$this->shouldPoll('waves')) {
                $notDue[] = 'waves';
            } else {
                $this->markAttempted('waves');
                $results['waves'] = $this->pollWaves();
                if ($results['waves'] === true) {
                    $this->markPolled('waves');
                }
            }
        }

        // 16. River Levels (all enabled providers)
        if ($pollAll || $source === 'rivers') {
            if (!$this->shouldPoll('rivers')) {
                $notDue[] = 'rivers';
            } elseif ($ignoreEnabled || $this->anyRiverProviderEnabled()) {
                $this->markAttempted('rivers');
                $results['rivers'] = $this->pollRivers();
                if ($results['rivers'] === true) {
                    $this->markPolled('rivers');
                }
            } else {
                $skipped[] = 'rivers (no providers enabled)';
            }
        }

        // Summary
        $this->newLine();
        $this->info('=== Poll Summary ===');

        $successCount = 0;
        $failCount = 0;

        foreach ($results as $name => $success) {
            if ($success === true) {
                $this->line("  ✅ {$name}");
                $successCount++;
            } elseif ($success === 'skipped') {
                $this->line("  ⏭️  {$name} (skipped)");
            } else {
                $this->line("  ❌ {$name}");
                $failCount++;
            }
        }

        if (count($notDue) > 0) {
            $this->newLine();
            $this->line('Not due yet (interval not reached):');
            foreach ($notDue as $item) {
                $interval = $this->intervals[$item] ?? '?';
                $this->line("  ⏰ {$item} (every {$interval} min)");
            }
        }

        if (count($skipped) > 0) {
            $this->newLine();
            $this->line('Skipped (disabled in settings):');
            foreach ($skipped as $item) {
                $this->line("  ⏭️  {$item}");
            }
        }

        $this->newLine();
        $this->info("Completed: {$successCount} polled, {$failCount} failed, " . count($notDue) . " not due, " . count($skipped) . " disabled");

        $this->newLine();
        $this->line('💡 Run with --force to bypass interval tracking and poll everything');

        return $failCount > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function pollForecast(): bool
    {
        $source = Setting::getValue('forecast.default_source', 'fct_yrno_block.php');
        $sourceName = $this->getSourceName($source);
        $this->line("📍 Polling {$sourceName} forecast...");

        try {
            $latitude = Setting::latitude();
            $longitude = Setting::longitude();
            
            // Use a generic cache key that works for all sources
            $cacheKey = "forecast_{$latitude}_{$longitude}";
            $sourceCacheKey = $this->getSourceCacheKey($source, $latitude, $longitude);

            // Keep what we have. The forget below is what stops the service
            // handing back its own cached copy, but a failed fetch used to
            // leave both keys empty and the message below was a lie: one bad
            // poll turned into an empty forecast until the next good one.
            $previous = [
                $sourceCacheKey => Cache::get($sourceCacheKey),
                $cacheKey => Cache::get($cacheKey),
            ];

            // Force fresh API fetch: clear cache so the service doesn't return stale data
            // (otherwise we keep re-caching the same old forecast and "today" drifts, leaving fewer days)
            Cache::forget($sourceCacheKey);
            Cache::forget($cacheKey);

            try {
                $service = ForecastServiceFactory::make();
                $data = $service->fetchForecast();
            } catch (\Throwable $e) {
                // Throwable, not Exception: an unreadable API key used to reach
                // a typed property and throw a TypeError right here.
                $this->restoreForecast($previous);
                $this->error("   Error building {$sourceName}: {$e->getMessage()} (kept existing cache)");
                Log::error('Poll forecast failed', ['error' => $e->getMessage(), 'source' => $source]);

                return false;
            }

            if ($data && isset($data['forecast']) && count($data['forecast']) > 0) {
                CacheFreshness::put($sourceCacheKey, $data, now()->addMinutes($this->cacheTTLs['forecast']));
                CacheFreshness::put($cacheKey, $data, now()->addMinutes($this->cacheTTLs['forecast']));
                $this->line("   Cached " . count($data['forecast']) . " forecast entries from {$sourceName}");
                return true;
            }

            $this->restoreForecast($previous);
            $this->warn('   No forecast data received (kept existing cache)');
            return false;
        } catch (\Throwable $e) {
            $this->restoreForecast($previous ?? []);
            $this->error("   Error: {$e->getMessage()} (kept existing cache)");
            Log::error('Poll forecast failed', ['error' => $e->getMessage(), 'source' => $source]);
            return false;
        }
    }

    private function pollRainviewer(): bool
    {
        $this->line('🌧️ Polling RainViewer frame metadata...');

        try {
            $service = app(RainViewerService::class);
            // Bypass cache so we always fetch fresh frame list from RainViewer (otherwise we keep re-caching stale data)
            $frames = $service->getRadarFrames(bypassCache: true);

            if ($frames && isset($frames['radar']['past']) && is_array($frames['radar']['past'])) {
                Cache::put('rainviewer_frames', $frames, now()->addMinutes($this->cacheTTLs['rainviewer']));
                Cache::put('rainviewer_frames_proxy', [
                    'success' => true,
                    'data' => [
                        'version' => $frames['version'] ?? '2.0',
                        'generated' => $frames['generated'] ?? time(),
                        'host' => $frames['host'] ?? 'https://tilecache.rainviewer.com',
                        'radar' => [
                            'past' => $frames['radar']['past'] ?? [],
                        ],
                    ],
                ], now()->addMinutes($this->cacheTTLs['rainviewer']));

                $count = count($frames['radar']['past'] ?? []);
                $this->line("   Cached {$count} radar frames");
                return true;
            }

            $this->warn('   No RainViewer frame metadata received (keeping existing cache)');
            return false;
        } catch (\Exception $e) {
            $this->error("   Error: {$e->getMessage()} (keeping existing cache)");
            Log::error('Poll RainViewer failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function getSourceName(string $source): string
    {
        $names = [
            'fct_yrno_block.php' => 'Yr.no',
            'fct_darksky_block.php' => 'OpenWeatherMap',
            'fct_wu_block.php' => 'Weather Underground',
            'fct_wxsim_block.php' => 'WXSIM',
            'fct_ec_block.php' => 'Environment Canada',
            'fct_tempest_block.php' => 'WeatherFlow Tempest',
        ];
        return $names[$source] ?? 'forecast service';
    }

    /**
     * Put back what was there before the forced refresh, so a failed poll
     * costs nothing. Only non-empty payloads are restored: an empty one is
     * what we were trying to replace.
     *
     * @param array<string, mixed> $previous
     */
    private function restoreForecast(array $previous): void
    {
        foreach ($previous as $key => $payload) {
            if (is_array($payload) && !empty($payload['forecast'])) {
                CacheFreshness::put($key, $payload, now()->addMinutes($this->cacheTTLs['forecast']));
            }
        }
    }

    private function getSourceCacheKey(string $source, float $latitude, float $longitude): string
    {
        return \App\Support\ForecastCacheKeys::forSource($source, $latitude, $longitude);
    }

    private function pollAirQuality(): bool
    {
        $this->line('🌬️ Polling WAQI air quality...');

        try {
            $service = app(WaqiService::class);
            $latitude = Setting::latitude();
            $longitude = Setting::longitude();
            $stationMode = Setting::getValue('waqi.station_mode', 'auto');
            $stationId = Setting::getValue('waqi.station_id', '');

            $cacheKey = ($stationMode === 'manual' && !empty($stationId))
                ? "waqi_station_{$stationId}"
                : "waqi_{$latitude}_{$longitude}";

            $data = $service->fetchAirQuality();

            if ($data) {
                CacheFreshness::put($cacheKey, $data, now()->addMinutes($this->cacheTTLs['airquality']));
                $aqi = $data['aqi'] ?? 'N/A';
                $this->line("   Cached AQI: {$aqi}");
                return true;
            }

            $this->warn('   No data received (keeping existing cache)');
            return false;
        } catch (\Exception $e) {
            $this->error("   Error: {$e->getMessage()} (keeping existing cache)");
            Log::error('Poll air quality failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function pollAurora(): bool
    {
        $this->line('✨ Polling NOAA aurora/Kp-index...');

        try {
            $service = app(AuroraService::class);
            $data = $service->getKpIndex();

            if ($data) {
                CacheFreshness::put('aurora_kp_index', $data, now()->addMinutes($this->cacheTTLs['aurora']));
                $kp = $data['kp'] ?? 'N/A';
                $this->line("   Cached Kp: {$kp}");
                return true;
            }

            $this->warn('   No data received (keeping existing cache)');
            return false;
        } catch (\Exception $e) {
            $this->error("   Error: {$e->getMessage()} (keeping existing cache)");
            Log::error('Poll aurora failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function pollISS(): bool
    {
        $this->line('🛰️ Polling space station data...');

        try {
            $service = app(ISSService::class);
            $showISS = Setting::getValue('iss.show_iss', true);
            $showTiangong = Setting::getValue('iss.show_tiangong', true);

            // Poll ISS data if enabled
            if ($showISS) {
                $passesData = $service->getUpcomingPasses();
                if ($passesData && isset($passesData['passes']) && count($passesData['passes']) > 0) {
                    Cache::put('iss_passes', $passesData, now()->addMinutes($this->cacheTTLs['iss_passes']));
                    $source = $passesData['source'] ?? 'unknown';
                    $this->line("   Cached " . count($passesData['passes']) . " ISS upcoming passes (source: {$source})");
                }

                $location = $service->getCurrentLocation();
                if ($location && $location['success']) {
                    Cache::put('iss_location', $location, now()->addMinutes($this->cacheTTLs['iss_location']));
                    $this->line("   Cached ISS location: {$location['latitude']}, {$location['longitude']}");
                }
            }

            // Poll Tiangong data if enabled
            if ($showTiangong) {
                $passesData = $service->getTiangongPasses();
                if ($passesData && isset($passesData['passes']) && count($passesData['passes']) > 0) {
                    Cache::put('tiangong_passes', $passesData, now()->addMinutes($this->cacheTTLs['tiangong']));
                    $source = $passesData['source'] ?? 'unknown';
                    $this->line("   Cached " . count($passesData['passes']) . " Tiangong upcoming passes (source: {$source})");
                }

                $location = $service->getTiangongLocation();
                if ($location && $location['success']) {
                    Cache::put('tiangong_location', $location, now()->addMinutes($this->cacheTTLs['tiangong']));
                    $this->line("   Cached Tiangong location: {$location['latitude']}, {$location['longitude']}");
                }
            }

            // Poll astronauts in space
            $apiSource = Setting::getValue('iss.astronauts_api_source', 'corquaid');
            $cacheKey = "astros_in_space_{$apiSource}";
            $astronauts = $service->getPeopleInSpace();
            if ($astronauts && $astronauts['success']) {
                Cache::put($cacheKey, $astronauts, now()->addMinutes($this->cacheTTLs['astronauts']));
                $this->line("   Cached {$astronauts['number']} astronauts in space (source: {$astronauts['source']})");
            } else {
                $this->warn('   Failed to fetch astronauts data (keeping existing cache)');
            }

            return true;
        } catch (\Exception $e) {
            $this->error("   Error: {$e->getMessage()} (keeping existing cache)");
            Log::error('Poll ISS failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function pollMetar(): bool
    {
        $this->line('✈️ Polling METAR aviation weather...');

        try {
            $service = app(MetarService::class);
            $primaryIcao = Setting::getValue('metar.primary_icao', 'EHAM');
            $icaoArray = [$primaryIcao];
            $cacheKey = "metar_{$primaryIcao}";

            $data = $service->fetchMetar($icaoArray);

            if ($data && !empty($data)) {
                CacheFreshness::put($cacheKey, $data, now()->addMinutes($this->cacheTTLs['metar']));
                $this->line("   Cached METAR for {$primaryIcao}");
                return true;
            }

            $this->warn('   No METAR data received (keeping existing cache)');
            return false;
        } catch (\Exception $e) {
            $this->error("   Error: {$e->getMessage()} (keeping existing cache)");
            Log::error('Poll METAR failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function pollEarthquakes(): bool
    {
        $this->line('🌍 Polling earthquake data...');

        try {
            $service = app(EarthquakeService::class);
            $latitude = Setting::latitude();
            $longitude = Setting::longitude();
            $nearbyCacheKey = "earthquakes_{$latitude}_{$longitude}";
            $allCacheKey = 'earthquakes_all';

            // Nearby list powers dashboard widgets.
            $nearby = $service->getNearbyEarthquakes(10);
            // Full list powers /earthquakes page.
            $all = $service->getAllRecentEarthquakes(100);

            // Always cache, even empty results (no earthquakes is valid data).
            Cache::put($nearbyCacheKey, $nearby ?? [], now()->addMinutes($this->cacheTTLs['earthquake']));
            Cache::put($allCacheKey, $all ?? [], now()->addMinutes($this->cacheTTLs['earthquake']));

            if (($nearby && count($nearby) > 0) || ($all && count($all) > 0)) {
                $this->line('   Cached ' . count($nearby ?? []) . ' nearby earthquakes and ' . count($all ?? []) . ' total earthquakes');
            } else {
                $this->line('   No recent earthquakes (cached empty nearby + total lists)');
            }
            return true;
        } catch (\Exception $e) {
            $this->error("   Error: {$e->getMessage()} (keeping existing cache)");
            Log::error('Poll earthquakes failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function pollAstronomy(): bool
    {
        $this->line('🌙 Calculating astronomy data...');

        try {
            $service = app(SunMoonService::class);

            // Sun data
            $sunData = $service->getSunData();
            if ($sunData) {
                CacheFreshness::put('astronomy_sun', $sunData, now()->addMinutes($this->cacheTTLs['astronomy']));
                $this->line("   Cached sun data (rise: {$sunData['sunrise']}, set: {$sunData['sunset']})");
            }

            // Moon data
            $moonData = $service->getMoonData();
            if ($moonData) {
                Cache::put('astronomy_moon', $moonData, now()->addMinutes($this->cacheTTLs['astronomy']));
                $phase = $moonData['phase_name'] ?? 'Unknown';
                $this->line("   Cached moon data (phase: {$phase})");
            }

            // Prewarm events and meteors so /astronomy page stays fast (avoids recalculating 5 years on first request)
            $events = $service->getUpcomingEvents(1825);
            Cache::put('astronomy_events_1825', $events, now()->addMinutes(60));
            $this->line('   Cached astronomy events (5 years)');
            $meteors = $service->getMeteorShowers();
            Cache::put('astronomy_meteors_' . date('Y'), $meteors, now()->addHours(24));
            $this->line('   Cached meteor showers');

            return true;
        } catch (\Exception $e) {
            $this->error("   Error: {$e->getMessage()} (keeping existing cache)");
            Log::error('Poll astronomy failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function pollLuftdaten(): bool
    {
        $this->line('🌬️ Polling Luftdaten air quality...');

        try {
            $sensorId = Setting::getValue('luftdaten.sensor_id', '');

            if (empty($sensorId)) {
                $this->warn('   No Luftdaten sensor ID configured');
                return true;
            }

            $service = app(LuftdatenService::class);
            $cacheKey = "luftdaten_{$sensorId}";
            $data = $service->fetchBySensorId($sensorId);

            if ($data) {
                // Add noise_level for noise sensors (fetchBySensorId already adds it)
                if (($data['category'] ?? '') === 'noise' && isset($data['values']['noise_LAeq']) && empty($data['noise_level'] ?? null)) {
                    $data['noise_level'] = $service->getNoiseDescription((float) $data['values']['noise_LAeq']);
                }
                CacheFreshness::put($cacheKey, $data, now()->addMinutes($this->cacheTTLs['airquality']));
                $this->line("   Cached Luftdaten data (sensor: {$sensorId})");
                return true;
            }

            $this->line('   No Luftdaten data received (keeping existing cache)');
            return true;
        } catch (\Exception $e) {
            $this->error("   Error: {$e->getMessage()} (keeping existing cache)");
            Log::error('Poll Luftdaten failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function pollLuftdatenNoise(): bool
    {
        $this->line('🔊 Polling Luftdaten noise sensor...');

        try {
            $sensorId = trim(Setting::getValue('luftdaten_noise.sensor_id', ''));

            if (empty($sensorId)) {
                $this->warn('   No Luftdaten noise sensor ID configured');
                return true;
            }

            $service = app(LuftdatenService::class);
            $cacheKey = "luftdaten_noise_{$sensorId}";
            $data = $service->fetchBySensorId($sensorId);

            if ($data) {
                $data['cached_at'] = now()->toIso8601String();
                Cache::put($cacheKey, $data, now()->addMinutes($this->cacheTTLs['airquality_noise']));
                $this->line("   Cached Luftdaten noise data (sensor: {$sensorId})");
                return true;
            }

            $this->line('   No Luftdaten noise data received (keeping existing cache)');
            return true;
        } catch (\Exception $e) {
            $this->error("   Error: {$e->getMessage()} (keeping existing cache)");
            Log::error('Poll Luftdaten noise failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function pollAlerts(): bool
    {
        $source = Setting::getValue('alerts.source', 'europe');
        $this->line("⚠️ Polling weather alerts ({$source})...");

        try {
            $service = \App\Services\Alerts\AlertServiceFactory::make();
            $data = $service->getActiveAlerts();

            Cache::put('weather_alerts', $data, now()->addMinutes($this->cacheTTLs['alerts']));
            $count = is_array($data) ? count($data) : 0;
            $this->line("   Cached {$count} weather alerts from {$source}");
            return true;
        } catch (\Exception $e) {
            $this->error("   Error: {$e->getMessage()} (keeping existing cache)");
            Log::error('Poll alerts failed', ['error' => $e->getMessage(), 'source' => $source]);
            return false;
        }
    }

    private function pollKnmiNowcast(): bool
    {
        $this->line('🌧️ Polling KNMI Radar Nowcast...');

        try {
            $service = app(KnmiNowcastService::class);
            $times = $service->getAvailableTimes();
            
            if ($times && count($times) > 0) {
                // Generate URLs for each time step
                $urls = [];
                foreach ($times as $time) {
                    $urls[$time] = $service->getWmsUrl($time);
                }
                
                $metadata = [
                    'times' => $times,
                    'urls' => $urls,
                    'latest_time' => $times[count($times) - 1] ?? null,
                    'total_steps' => count($times),
                    'step_interval' => 5, // minutes
                    'forecast_hours' => 2,
                ];
                
                Cache::put('knmi_nowcast_metadata', $metadata, now()->addMinutes($this->cacheTTLs['knmi_nowcast']));
                $this->line("   Cached " . count($times) . " nowcast time steps with URLs (2-hour forecast)");
                return true;
            }

            $this->warn('   No nowcast data received (keeping existing cache)');
            return false;
        } catch (\Exception $e) {
            $this->error("   Error: {$e->getMessage()} (keeping existing cache)");
            Log::error('Poll KNMI nowcast failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function pollSolarForecast(): bool
    {
        $this->line('☀️ Polling Solar Forecast...');

        try {
            $factory = app(SolarForecastFactory::class);
            $hours = Setting::getValue('solar_forecast.forecast_hours', 48);
            $cacheKey = "solar_forecast_{$hours}h";

            $data = $factory->getSolarForecast($hours);

            if ($data && isset($data['times']) && count($data['times']) > 0) {
                Cache::put($cacheKey, $data, now()->addMinutes($this->cacheTTLs['solar_forecast']));
                $this->line("   Cached " . count($data['times']) . " solar forecast time steps ({$hours}h, " . ($data['source'] ?? 'unknown') . ")");
                return true;
            }

            $this->warn('   No solar forecast data available - keeping existing cache');
            return true;
        } catch (\Throwable $e) {
            $this->error("   Error: {$e->getMessage()} (keeping existing cache)");
            Log::error('Poll solar forecast failed', [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return false;
        }
    }

    private function pollKnmiWms(): bool
    {
        $this->line('🛰️ Polling KNMI WMS Layers metadata...');

        try {
            $service = app(KnmiWmsService::class);
            
            // Refresh capabilities
            $capabilities = $service->getCapabilities();
            if ($capabilities) {
                Cache::put('knmi_wms_capabilities', $capabilities, now()->addMinutes($this->cacheTTLs['knmi_wms']));
            }
            
            // Get available times (7 days history)
            $times = $service->getAvailableTimes(7);
            if ($times && count($times) > 0) {
                Cache::put('knmi_wms_times', $times, now()->addMinutes($this->cacheTTLs['knmi_wms']));
                $latestTime = $service->getLatestAvailableTime();
                Cache::put('knmi_wms_latest_time', $latestTime, now()->addMinutes($this->cacheTTLs['knmi_wms']));
                
                $this->line("   Cached " . count($times) . " WMS time steps (7 days history)");
                return true;
            }

            $this->warn('   No WMS data received (keeping existing cache)');
            return false;
        } catch (\Exception $e) {
            $this->error("   Error: {$e->getMessage()} (keeping existing cache)");
            Log::error('Poll KNMI WMS failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function pollPollen(): bool
    {
        $this->line('🌿 Polling Pollen Forecast...');

        try {
            $aggregator = app(PollenAggregator::class);
            $data = $aggregator->getData();

            if ($data) {
                $ttl = (int) Setting::getValue('pollen.cache_minutes', 60);
                Cache::put('pollen_forecast', $data, now()->addMinutes($ttl));
                $overall = $data['today']['overall_risk'] ?? 'N/A';
                $sources = implode(', ', $data['sources'] ?? []);
                $this->line("   Cached pollen data — overall risk: {$overall} (sources: {$sources})");
                return true;
            }

            $this->warn('   No pollen data received (keeping existing cache)');
            return false;
        } catch (\Exception $e) {
            $this->error("   Error: {$e->getMessage()} (keeping existing cache)");
            Log::error('Poll pollen failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function pollTide(): bool
    {
        $tideSource     = Setting::getValue('tide.source', 'rws');
        $tideSourceName = \App\Services\Tide\TideServiceFactory::make($tideSource)->getName();
        $this->line("🌊 Polling Tide Data ({$tideSourceName})...");

        try {
            $stationCode = Setting::getValue("tide.{$tideSource}_station_code",
                           Setting::getValue('tide.station_code', TideService::DEFAULT_STATION));
            $service     = app(TideService::class);
            $data        = $service->fetchTideData($stationCode);

            if ($data && !empty($data['series'])) {
                $cacheKey = "tide_{$tideSource}_{$stationCode}";
                Cache::put($cacheKey, $data, now()->addMinutes($this->cacheTTLs['tide']));
                $level  = $data['current_level_cm'] ?? 'N/A';
                $trend  = $data['trend'] ?? '?';
                $tides  = count($data['tides'] ?? []);
                $this->line("   Cached tide data for {$stationCode} [{$tideSource}] — level: {$level} cm, trend: {$trend}, {$tides} tide events");
                return true;
            }

            $this->warn('   No tide data received (keeping existing cache)');
            return false;
        } catch (\Exception $e) {
            $this->error("   Error: {$e->getMessage()} (keeping existing cache)");
            Log::error('Poll tide failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function pollWaves(): bool
    {
        $this->line('〰 Polling Wave & Sea Temperature data (Open-Meteo Marine)...');

        try {
            $service = app(OpenMeteoWaveService::class);
            $data    = $service->fetch();

            if ($data && !empty($data['wave_series'])) {
                $lat      = round((float) Setting::latitude(), 2);
                $lon      = round((float) Setting::longitude(), 2);
                $cacheKey = "waves_{$lat}_{$lon}";
                Cache::put($cacheKey, $data, now()->addMinutes($this->cacheTTLs['waves']));
                $height   = $data['current_wave_height_m'] ?? 'N/A';
                $beaufort = $data['beaufort_sea_state'] ?? '?';
                $sst      = $data['current_sst_c'] ?? 'N/A';
                $this->line("   Cached wave data — height: {$height} m (Beaufort {$beaufort}), SST: {$sst}°C");
                return true;
            }

            $this->warn('   No wave data received (keeping existing cache)');
            return false;
        } catch (\Exception $e) {
            $this->error("   Error: {$e->getMessage()} (keeping existing cache)");
            Log::error('Poll waves failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /** True if at least one river provider is enabled in settings. */
    private function anyRiverProviderEnabled(): bool
    {
        foreach (\App\Services\River\RiverProviderRegistry::active() as $providerId => $providerMeta) {
            if ((bool) \App\Services\River\RiverProviderRegistry::getSetting($providerId, 'enabled', false)) {
                return true;
            }
        }
        return false;
    }

    private function pollRivers(): bool
    {
        $this->line('🏞 Polling River Levels...');

        $anySuccess = false;

        foreach (\App\Services\River\RiverProviderRegistry::active() as $providerId => $providerMeta) {
            $enabled = (bool) \App\Services\River\RiverProviderRegistry::getSetting($providerId, 'enabled', false);
            if (!$enabled) {
                continue;
            }

            $this->line("   Provider: {$providerMeta['name']} [{$providerMeta['short']}]");

            try {
                // Selected stations
                $stationsRaw = \App\Services\River\RiverProviderRegistry::getSetting(
                    $providerId, 'stations', RijkswaterstaatRiverService::DEFAULT_STATIONS
                );
                $stations = is_string($stationsRaw)
                    ? (json_decode($stationsRaw, true) ?? RijkswaterstaatRiverService::DEFAULT_STATIONS)
                    : ($stationsRaw ?? RijkswaterstaatRiverService::DEFAULT_STATIONS);
                $stations = array_values(array_filter(
                    (array) $stations, fn ($v) => is_string($v) && !is_numeric($v) && $v !== ''
                ));
                if (empty($stations)) {
                    $stations = RijkswaterstaatRiverService::DEFAULT_STATIONS;
                }

                // Catalog metadata
                $catalogMeta = [];
                if (isset($providerMeta['catalog_service'])) {
                    $catalogMeta = app($providerMeta['catalog_service'])->getRiverStations();
                }

                // Custom stations
                $customRaw  = \App\Services\River\RiverProviderRegistry::getSetting($providerId, 'custom_stations', '[]');
                $customList = is_string($customRaw) ? (json_decode($customRaw, true) ?? []) : [];
                $customMeta = [];
                $customCodes = [];
                foreach ($customList as $entry) {
                    $code = $entry['code'] ?? null;
                    if (!$code) {
                        continue;
                    }
                    $customMeta[$code] = ['name' => $entry['name'] ?? $code, 'river' => $entry['river'] ?? '—'];
                    $customCodes[]     = $code;
                }
                $allCodes  = array_unique(array_merge($stations, $customCodes));
                $extraMeta = array_merge($catalogMeta, $customMeta);

                $service  = app($providerMeta['service']);
                $data     = $service->fetch($allCodes, $extraMeta);

                if (!empty($data)) {
                    $cacheKey = \App\Services\River\RiverProviderRegistry::cacheKey($providerId);
                    Cache::put($cacheKey, $data, now()->addMinutes($this->cacheTTLs['rivers']));
                    $summary = collect($data)->map(fn ($s, $code) =>
                        "{$s['name']}: " . ($s['level_cm'] ?? '--') . ' cm'
                    )->implode(', ');
                    $this->line("   ✓ Cached {$providerId} river data — {$summary}");
                    $anySuccess = true;
                } else {
                    $this->warn("   No data received from {$providerMeta['name']} (keeping existing cache)");
                }
            } catch (\Exception $e) {
                $this->error("   Error [{$providerId}]: {$e->getMessage()} (keeping existing cache)");
                Log::error("Poll rivers failed [{$providerId}]", ['error' => $e->getMessage()]);
            }
        }

        return $anySuccess;
    }
}
