<?php

use App\Models\Setting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
|
| Weather data collection and maintenance tasks
| 
| To activate the scheduler, add this single cron entry:
| * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
|
*/

$loggedSchedulerTask = static function (string $task, string $artisan, string $logFile) {
    return Schedule::command('scheduler:run-task', [
        '--task' => $task,
        '--artisan' => $artisan,
        '--log' => $logFile,
    ]);
};

// =============================================================================
// LOCAL WEATHER STATION DATA (high frequency)
// =============================================================================

// Scheduler heartbeat (used by admin to detect cron health)
Schedule::call(function () {
    Cache::put('scheduler:last_run', now()->toDateTimeString(), now()->addMinutes(10));
})
    ->everyMinute()
    ->name('scheduler:heartbeat');

// Self-healing: Check for missing critical data caches and fetch immediately
// Runs every 5 minutes to catch cache clears quickly
Schedule::call(function () {
    try {
        $latitude = Setting::latitude();
        $longitude = Setting::longitude();
        $missing = [];
        
        // Check all critical caches
        // Forecast
        $source = Setting::getValue('forecast.default_source', 'fct_yrno_block.php');
        $forecastKeys = [
            "forecast_{$latitude}_{$longitude}",
            "yrno_forecast_{$latitude}_{$longitude}",
        ];
        $forecastValid = false;
        foreach ($forecastKeys as $k) {
            $d = Cache::get($k);
            if ($d && is_array($d) && isset($d['forecast']) && count($d['forecast']) > 0) {
                $forecastValid = true;
                break;
            }
        }
        if (!$forecastValid) {
            $missing[] = 'forecast';
        }
        
        // Astronomy (sun + moon)
        $sun = Cache::get('astronomy_sun');
        $moon = Cache::get('astronomy_moon');
        $sunValid = $sun && is_array($sun) && isset($sun['sunrise']) && isset($sun['sunset']);
        $moonValid = $moon && is_array($moon) && isset($moon['phase_name']);
        if (!$sunValid || !$moonValid) {
            $missing[] = 'astronomy';
        }
        
        // Air Quality
        $waqiKey = "waqi_{$latitude}_{$longitude}";
        $waqi = Cache::get($waqiKey);
        $waqiValid = $waqi && is_array($waqi) && !empty($waqi);
        $luftdatenKey = "luftdaten_" . Setting::getValue('luftdaten.sensor_id', '');
        $luftdaten = Cache::get($luftdatenKey);
        $luftdatenValid = $luftdaten && is_array($luftdaten) && !empty($luftdaten);
        $noiseId = Setting::getValue('luftdaten_noise.sensor_id', '');
        $luftdatenNoise = $noiseId ? Cache::get("luftdaten_noise_{$noiseId}") : null;
        $luftdatenNoiseValid = $luftdatenNoise && is_array($luftdatenNoise) && !empty($luftdatenNoise);
        if (!$waqiValid && !$luftdatenValid && !$luftdatenNoiseValid) {
            // Only check if at least one is enabled
            if (Setting::getValue('waqi.enabled', false) || Setting::getValue('luftdaten.enabled', false) || Setting::getValue('luftdaten_noise.enabled', false)) {
                $missing[] = 'airquality';
            }
        }

        // Pollen forecast
        $pollen = Cache::get('pollen_forecast');
        $pollenValid = $pollen && is_array($pollen) && !empty($pollen);
        $pollenEnabled = (bool) Setting::getValue('pollen.openmeteo_enabled', true)
            || (bool) Setting::getValue('pollen.google_enabled', false)
            || (bool) Setting::getValue('pollen.ambee_enabled', false);
        if (!$pollenValid && $pollenEnabled) {
            $missing[] = 'pollen';
        }

        // Tide data (cache key must match WaterController / PollExternalData: tide_{source}_{stationCode})
        if ((bool) Setting::getValue('tide.enabled', false)) {
            $tideSource   = Setting::getValue('tide.source', \App\Services\Tide\TideServiceFactory::DEFAULT_SOURCE);
            $tideStation  = Setting::getValue("tide.{$tideSource}_station_code",
                              Setting::getValue('tide.station_code', ''));
            $tide = Cache::get('tide_' . $tideSource . '_' . $tideStation);
            $tideValid = $tide && is_array($tide) && !empty($tide);
            if (!$tideValid) {
                $missing[] = 'tide';
            }
        }

        // Wave + sea surface temperature (Open-Meteo Marine)
        $wavesKey = 'waves_' . round((float) $latitude, 2) . '_' . round((float) $longitude, 2);
        $waves = Cache::get($wavesKey);
        if (!($waves && is_array($waves) && !empty($waves))) {
            $missing[] = 'waves';
        }

        // River levels (only if at least one river provider is enabled)
        $riversEnabled = false;
        foreach (\App\Services\River\RiverProviderRegistry::active() as $providerId => $providerMeta) {
            if ((bool) \App\Services\River\RiverProviderRegistry::getSetting($providerId, 'enabled', false)) {
                $riversEnabled = true;
                break;
            }
        }
        if ($riversEnabled) {
            $riversValid = false;
            foreach (\App\Services\River\RiverProviderRegistry::active() as $providerId => $providerMeta) {
                if ((bool) \App\Services\River\RiverProviderRegistry::getSetting($providerId, 'enabled', false)
                    && Cache::has(\App\Services\River\RiverProviderRegistry::cacheKey($providerId))) {
                    $riversValid = true;
                    break;
                }
            }
            if (!$riversValid) {
                $missing[] = 'rivers';
            }
        }

        // Aurora
        $aurora = Cache::get('aurora_kp_index');
        if (!($aurora && is_array($aurora) && isset($aurora['kp']))) {
            $missing[] = 'aurora';
        }
        
        // Fetch missing data immediately (self-healing)
        if (!empty($missing)) {
            foreach ($missing as $service) {
                try {
                    Artisan::call('weather:poll-external', ['--source' => $service]);
                    \Illuminate\Support\Facades\Log::info("Self-healing: Fetched missing {$service} data");
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Self-healing: Failed to fetch {$service}", ['error' => $e->getMessage()]);
                }
            }
            \Illuminate\Support\Facades\Log::info('Self-healing: Health check found and fetched missing data', ['services' => $missing]);
        }
    } catch (\Illuminate\Database\QueryException $e) {
        // Silently skip if database tables don't exist yet (during migrations)
        // This allows migrations to run without errors
    }
})
    ->name('scheduler:health-check')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Fetch Ecowitt data every MINUTE (local station, no rate limits)
$loggedSchedulerTask('weather-fetch', 'weather:fetch --save', 'weather-fetch.log')
    ->everyMinute()
    ->withoutOverlapping();

// Refresh per-source health status (drives stale/offline overlays on dashboard cards)
$loggedSchedulerTask('check-sensor-health', 'weather:check-sensor-health', 'check-sensor-health.log')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// =============================================================================
// EXTERNAL APIs - WEATHER & FORECAST (fair usage intervals)
// =============================================================================

// Yr.no forecast - every 30 minutes (respects their rate limits)
$loggedSchedulerTask('poll-forecast', 'weather:poll-external --source=forecast --scheduled', 'poll-forecast.log')
    ->everyThirtyMinutes()
    ->withoutOverlapping();

// RainViewer radar frame metadata - every 10 minutes
// Used by /radar and the radar widget when RainViewer API mode is enabled.
$loggedSchedulerTask('poll-rainviewer', 'weather:poll-external --source=rainviewer --scheduled', 'poll-rainviewer.log')
    ->everyTenMinutes()
    ->withoutOverlapping();

// Generate deterministic NLG text for all languages (runs 2 minutes after forecast fetch)
// Runs at :02 and :32 (2 minutes after the forecast polling at :00 and :30)
$loggedSchedulerTask('generate-nlg', 'weather:generate-nlg', 'generate-nlg.log')
    ->cron('2,32 * * * *')
    ->withoutOverlapping();

// Rephrase the admin-selected cached subset with the configured LLM in a separate pass.
// This keeps the main NLG generation fast and caps token usage to the selected AI languages + selected forecast window.
$loggedSchedulerTask('rephrase-nlg', 'weather:rephrase-nlg', 'rephrase-nlg.log')
    ->cron('5,35 * * * *')
    ->withoutOverlapping();

// Weather alerts - every 15 minutes
$loggedSchedulerTask('poll-alerts', 'weather:poll-external --source=alerts --scheduled', 'poll-alerts.log')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

// =============================================================================
// EXTERNAL APIs - AIR QUALITY (fair usage intervals)
// =============================================================================

// Air quality (WAQI + Luftdaten) - every 30 minutes
$loggedSchedulerTask('poll-airquality', 'weather:poll-external --source=airquality --scheduled', 'poll-airquality.log')
    ->everyThirtyMinutes()
    ->withoutOverlapping();

// Noise sensor only - every 5 min (actual interval from luftdaten_noise.poll_interval_minutes)
$loggedSchedulerTask('poll-airquality-noise', 'weather:poll-external --source=airquality_noise --scheduled', 'poll-airquality-noise.log')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Pollen forecast - hourly (pollen levels change slowly)
$loggedSchedulerTask('poll-pollen', 'weather:poll-external --source=pollen --scheduled', 'poll-pollen.log')
    ->hourly()
    ->withoutOverlapping();

// Tide data (Rijkswaterstaat) - hourly (predictions are stable, real-time level updates slowly)
$loggedSchedulerTask('poll-tide', 'weather:poll-external --source=tide --scheduled', 'poll-tide.log')
    ->hourly()
    ->withoutOverlapping();

// Wave + sea surface temperature (Open-Meteo Marine) - hourly
$loggedSchedulerTask('poll-waves', 'weather:poll-external --source=waves --scheduled', 'poll-waves.log')
    ->hourly()
    ->withoutOverlapping();

// River levels (Rijkswaterstaat) - every 15 minutes (real-time gauge data)
$loggedSchedulerTask('poll-rivers', 'weather:poll-external --source=rivers --scheduled', 'poll-rivers.log')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

// =============================================================================
// EXTERNAL APIs - AVIATION & EARTHQUAKES
// =============================================================================

// METAR aviation weather - every 30 minutes
$loggedSchedulerTask('poll-metar', 'weather:poll-external --source=metar --scheduled', 'poll-metar.log')
    ->everyThirtyMinutes()
    ->withoutOverlapping();

// Earthquakes - every 15 minutes
$loggedSchedulerTask('poll-earthquake', 'weather:poll-external --source=earthquake --scheduled', 'poll-earthquake.log')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

// =============================================================================
// EXTERNAL APIs - ASTRONOMY (free APIs, fair intervals)
// =============================================================================

// Aurora/Kp-index - every 30 minutes
$loggedSchedulerTask('poll-aurora', 'weather:poll-external --source=aurora --scheduled', 'poll-aurora.log')
    ->everyThirtyMinutes()
    ->withoutOverlapping();

// ISS passes - every hour (data doesn't change frequently)
$loggedSchedulerTask('poll-iss', 'weather:poll-external --source=iss --scheduled', 'poll-iss.log')
    ->hourly()
    ->withoutOverlapping();

// Sun/moon calculations - every hour
$loggedSchedulerTask('poll-astronomy', 'weather:poll-external --source=astronomy --scheduled', 'poll-astronomy.log')
    ->hourly()
    ->withoutOverlapping();

// KNMI radar nowcast metadata - every 10 minutes
$loggedSchedulerTask('poll-knmi-nowcast', 'weather:poll-external --source=knmi_nowcast --scheduled', 'poll-knmi-nowcast.log')
    ->everyTenMinutes()
    ->withoutOverlapping();

// Solar forecast (Open-Meteo / Forecast.Solar / etc.) - every 30 minutes
$loggedSchedulerTask('poll-solar-forecast', 'weather:poll-external --source=solar_forecast --scheduled', 'poll-solar-forecast.log')
    ->everyThirtyMinutes()
    ->withoutOverlapping();

// KNMI WMS metadata - hourly
$loggedSchedulerTask('poll-knmi-wms', 'weather:poll-external --source=knmi_wms --scheduled', 'poll-knmi-wms.log')
    ->hourly()
    ->withoutOverlapping();

// =============================================================================
// MAINTENANCE TASKS
// =============================================================================

// Generate daily summary at midnight
$loggedSchedulerTask('weather-summary', 'weather:summarize', 'weather-summary.log')
    ->dailyAt('00:05');

// Record the newest release once a day so the admin area can show an update
// banner without any page load having to call GitHub. Off-peak, and the minute
// is derived from the app key so installs do not all hit the API at once.
// Deliberately not read from settings: this file is evaluated on every artisan
// call, including `migrate` on a database that has no settings table yet.
// The sign-bit mask matters: crc32() returns a signed int on 32-bit PHP, and a
// negative minute would render an invalid cron expression that kills every
// schedule:run on that host — shared-hosting installs can still be 32-bit.
Schedule::command('updater:check --notify')
    ->dailyAt(sprintf('03:%02d', (crc32((string) config('app.key')) & 0x7FFFFFFF) % 60))
    ->withoutOverlapping();

// Warm phenology + OG caches after the daily summary is written (00:05) so the first
// visitor/share after midnight never hits a cold cache. (Fire weather is refreshed on a
// 15-minute cadence below, because it tracks today's DailySummary as the day warms up.)
Schedule::call(function () {
    $expiry = now()->addDay()->startOfDay()->addMinutes(10);

    // Phenology (current year and previous year for compare tool)
    $year = now()->year;
    foreach ([$year, $year - 1] as $y) {
        $key = "phenology_{$y}";
        Cache::forget($key);
        try {
            $phCalc = app(\App\Services\PhenologyCalculator::class);
            Cache::remember($key, $expiry, fn () => $phCalc->getForYear((string) $y));
        } catch (\Exception $e) {
            // Non-fatal
        }
    }

    // OG statistics cards — rebuild the (daily-changing) statistics cards so the first
    // share after midnight always hits a warm cache. The fire-weather OG card is warmed
    // separately every 6 hours below, because it tracks the day as it warms up.
    if (Setting::getValue('og.enabled', false)) {
        foreach (["og_statistics_{$year}", "og_statistics_" . ($year - 1)] as $ogKey) {
            Cache::forget($ogKey);
        }
        try {
            // Calling the controller methods directly populates their Cache::remember() keys.
            $ogCtrl = app(\App\Http\Controllers\OgImageController::class);
            $ogCtrl->statistics((string) $year);
            $ogCtrl->statistics((string) ($year - 1));
        } catch (\Exception $e) {
            // Non-fatal — cache will be populated on first page request instead
        }
    }
})
    ->dailyAt('00:10')
    ->name('daily-cache-warm')
    ->withoutOverlapping();

// Keep the live fire-weather caches fresh through the day. They derive from today's
// DailySummary, which weather:fetch updates every minute with the running daily max
// temperature / min humidity, so a 15-minute refresh lets the index track the rising
// daytime temperature instead of freezing the cold overnight snapshot.
Schedule::call(function () {
    $ttl = now()->addMinutes(15);
    try {
        $fwCalc = app(\App\Services\FireWeatherCalculator::class);
        Cache::put('fire_weather_current',    $fwCalc->currentIndices(),  $ttl);
        Cache::put('fire_weather_history_90', $fwCalc->historicalData(90), $ttl);
    } catch (\Throwable $e) {
        // Non-fatal — the page rebuilds the cache on demand.
    }
})
    ->everyFifteenMinutes()
    ->name('warm-fire-weather')
    ->withoutOverlapping();

// Re-render the fire-weather OG share card ~4x/day (00:15, 06:15, 12:15, 18:15) so the
// social preview tracks the day's fire danger. Image rendering is comparatively expensive,
// so it runs far less often than the 15-minute on-page refresh above. The 00:15 run also
// covers the "first share after midnight is warm" case.
Schedule::call(function () {
    if (! Setting::getValue('og.enabled', false)) {
        return;
    }
    try {
        Cache::forget('og_fire_weather');
        app(\App\Http\Controllers\OgImageController::class)->fireWeather();
    } catch (\Throwable $e) {
        // Non-fatal — the card rebuilds on the next share request.
    }
})
    ->cron('15 */6 * * *')
    ->name('warm-fire-weather-og')
    ->withoutOverlapping();

// Recalculate climate normals weekly (after daily summaries accumulate)
$loggedSchedulerTask('climate-normals', 'weather:recalculate-normals', 'climate-normals.log')
    ->weeklyOn(0, '03:30');

// Sync recent WU history (enabled via settings)
// Wrap in try-catch to allow migrations to run (table may not exist yet)
try {
    $wuSyncTime = Setting::getValue('history.wu_sync_time', '02:10');
    if (!is_string($wuSyncTime) || !preg_match('/^\d{2}:\d{2}$/', $wuSyncTime)) {
        $wuSyncTime = '02:10';
    }
} catch (\Exception $e) {
    // During migrations, table doesn't exist yet - use default
    $wuSyncTime = '02:10';
}
$loggedSchedulerTask('wu-history-sync', 'weather:sync-wu', 'wu-history-sync.log')
    ->dailyAt($wuSyncTime)
    ->withoutOverlapping();

// Aggregate visitor logs and purge raw data (runs nightly)
$loggedSchedulerTask('visitor-rollup', 'visitorlog:rollup', 'visitor-rollup.log')
    ->dailyAt('00:15');

// Update GeoLite2 Country database weekly
$loggedSchedulerTask('geoip-update', 'geoip:update', 'geoip-update.log')
    ->weeklyOn(1, '02:30');

// Clean expired cache entries daily (for database cache driver)
$loggedSchedulerTask('cache-cleanup', 'cache:clean-expired', 'cache-cleanup.log')
    ->dailyAt('03:00');

// Clean old radar tiles hourly (tiles older than 2 hours)
$loggedSchedulerTask('radar-cleanup', 'radar:clean-tiles', 'radar-cleanup.log')
    ->hourly();

// Return space freed by the purges above to the filesystem. SQLite reuses
// deleted pages but never shrinks the file, so a long-running install can be
// mostly empty space. No-ops unless enough has built up to be worth the
// rewrite, and weekly + off-peak because VACUUM takes an exclusive lock.
$loggedSchedulerTask('db-reclaim-space', 'db:reclaim-space', 'db-reclaim-space.log')
    ->weeklyOn(0, '04:10')
    ->withoutOverlapping();

// The update check is scheduled unconditionally further up, near the other
// maintenance tasks. There used to be a second copy here behind
// updater.notify_email; with that setting on, both ran and admins got two
// emails a day. The command sends the notification itself when the setting is
// enabled, so one schedule covers both the banner and the email.

// =============================================================================
// TELEMETRY (community stations)
// =============================================================================

// Send station data to community aggregator once daily at a random time.
// The random minute/hour is seeded per-installation so it stays consistent
// across restarts but differs between stations (avoids thundering herd).
$telemetrySeed = crc32(config('app.url', 'default'));
$telemetryHour = ($telemetrySeed & 0x7FFFFFFF) % 24;
$telemetryMinute = (($telemetrySeed >> 8) & 0x7FFFFFFF) % 60;

$loggedSchedulerTask('telemetry-send', 'telemetry:send', 'telemetry-send.log')
    ->dailyAt(sprintf('%02d:%02d', $telemetryHour, $telemetryMinute))
    ->withoutOverlapping();

// =============================================================================
// ONE RUN PER SLOT
// =============================================================================

// Every task runs at most once per scheduled minute, however many schedulers
// fire. The Docker image can run the scheduler inside the app container
// (DOCKER_RUN_SCHEDULER=true, used by the Unraid template). Someone who turns
// that on and keeps the separate scheduler container from docker-compose.yml
// would otherwise run every task twice, and withoutOverlapping() does not stop
// that: it only blocks a second run while the first is still going, and most
// of these tasks finish in seconds.
//
// The lock lives in the cache. Both containers share the storage volume, so a
// file cache is shared too. With only one scheduler this changes nothing.
//
// A closure without ->name() cannot take a lock, and onOneServer() throws on
// one, which would take down every schedule:run. Those are skipped instead.
foreach (Schedule::events() as $event) {
    if ($event instanceof \Illuminate\Console\Scheduling\CallbackEvent && empty($event->description)) {
        continue;
    }

    $event->onOneServer();
}
