<?php

namespace App\Console\Commands;

use App\Services\Notifications\NotificationDispatcher;
use App\Support\CacheFreshness;
use App\Services\Weather\SensorTrackerService;
use Illuminate\Console\Command;
use App\Models\WeatherReading;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\Setting;

class CheckSensorHealth extends Command
{
    protected $signature = 'weather:check-sensor-health';
    protected $description = 'Check health of all weather data sources and send alerts';

    private array $healthStatus = [];

    public function handle()
    {
        $this->info('Checking health of all data sources...');

        // Check all data sources
        $this->checkSensorData();
        $this->checkIndividualSensors();
        $this->checkForecastData();
        $this->checkAstronomyData();
        $this->checkAuroraData();
        $this->checkAirQualityData();
        $this->checkMetarData();

        // Store health status in cache for frontend
        Cache::put('data_source_health', $this->healthStatus, now()->addMinutes(10));

        // Display summary
        $this->info("\n=== Health Check Summary ===");
        foreach ($this->healthStatus as $source => $status) {
            if (isset($status['is_stale'])) {
                $icon = $status['is_stale'] ? '🔴' : '✅';
                $ageInfo = $status['age_minutes'] !== null ? " ({$status['age_minutes']} min)" : '';
                $this->info("{$icon} {$source}: " . ($status['is_stale'] ? 'OFFLINE' : 'Online') . $ageInfo);
            }
        }
        if (!empty($this->healthStatus['sensor_failures']['failed'])) {
            $this->warn('Sensor failures: ' . implode(', ', array_column($this->healthStatus['sensor_failures']['failed'], 'id')));
        }

        return 0;
    }

    private function checkSensorData()
    {
        $latestReading = WeatherReading::latest('recorded_at')->first();

        if (!$latestReading) {
            $this->healthStatus['sensor'] = [
                'is_stale' => true,
                'age_minutes' => null,
                'last_update' => null,
            ];
            $this->sendAlertIfNeeded('sensor', 'Weather Sensor', 'No weather readings found in database.');
            return;
        }

        $ageMinutes = abs(now()->diffInMinutes($latestReading->recorded_at));
        $isStale = $ageMinutes > 5;

        $this->healthStatus['sensor'] = [
            'is_stale' => (bool) $isStale,
            'age_minutes' => round($ageMinutes, 1),
            'last_update' => $latestReading->recorded_at->toIso8601String(),
        ];

        if ($isStale) {
            $this->sendAlertIfNeeded(
                'sensor',
                'Weather Sensor',
                "No new data for {$ageMinutes} minutes.\nLast reading: {$latestReading->recorded_at}"
            );
        } else {
            $this->clearAlertIfNeeded('sensor', 'Weather Sensor');
        }
    }

    /**
     * Track individual sensors over time; alert if one that was active stops reporting
     * (e.g. empty battery, lost contact). Uses sensor_health.track_days and
     * sensor_health.fail_minutes settings.
     */
    private function checkIndividualSensors(): void
    {
        $enabled = (bool) Setting::getValue('sensor_health.enabled', true);
        if (!$enabled) {
            $this->healthStatus['sensor_failures'] = [
                'enabled' => false,
                'failed' => [],
                'track_days' => null,
                'fail_minutes' => null,
            ];
            return;
        }

        $trackDays = (int) Setting::getValue('sensor_health.track_days', 7);
        $trackDays = max(1, min(30, $trackDays));
        $failMinutes = (int) Setting::getValue('sensor_health.fail_minutes', 30);
        $failMinutes = max(15, min(10080, $failMinutes)); // 15 min .. 7 days

        // One scan drives both the alert and the states the admin UI reads.
        $states = app(SensorTrackerService::class)->refreshSensorStates($trackDays, $failMinutes);

        $failed = [];
        foreach ($states as $state) {
            if ($state['state'] === 'failed') {
                $failed[] = ['id' => $state['id'], 'last_seen' => $state['last_seen']];
            }
        }

        $failedForCache = array_map(function ($item) {
            return [
                'id' => $item['id'],
                'label' => SensorTrackerService::sensorIdToLabel($item['id']),
                'last_seen' => $item['last_seen']->toIso8601String(),
            ];
        }, $failed);

        $this->healthStatus['sensor_failures'] = [
            'enabled' => true,
            'failed' => $failedForCache,
            'track_days' => $trackDays,
            'fail_minutes' => $failMinutes,
        ];

        if (!empty($failed)) {
            $lines = [];
            foreach ($failed as $item) {
                $label = SensorTrackerService::sensorIdToLabel($item['id']);
                $lines[] = "  - {$label} ({$item['id']}): last seen " . $item['last_seen']->diffForHumans();
            }
            $details = "The following sensors were active in the last {$trackDays} days but have not reported in the last {$failMinutes} minutes:\n\n" . implode("\n", $lines);
            $this->sendSensorFailureAlertIfNeeded($failed, $details);
        } else {
            $this->clearSensorFailureAlertIfNeeded();
        }
    }

    private const ALERTED_CACHE_KEY = 'alert_sent_sensor_failures';

    /**
     * Sensor IDs we have already sent an alert for. Older builds stored a bare
     * boolean here; treat that as "nothing recorded" so it re-alerts once.
     */
    private function alreadyAlertedSensorIds(): array
    {
        $alerted = Cache::get(self::ALERTED_CACHE_KEY, []);

        return is_array($alerted) ? $alerted : [];
    }

    private function sendSensorFailureAlertIfNeeded(array $failed, string $details): void
    {
        $alerted = $this->alreadyAlertedSensorIds();
        $currentIds = array_column($failed, 'id');

        // Only stay quiet while the failing set is one we have already reported;
        // a newly failing sensor is worth another alert.
        if (empty(array_diff($currentIds, $alerted))) {
            return;
        }

        // Record the alert only once it has actually gone out, otherwise a
        // failure detected before notifications are configured is never sent.
        $delivered = $this->sendAlert(
            (string) __("One or more weather sensors have stopped reporting"),
            $details
        );

        if ($delivered) {
            Cache::put(
                self::ALERTED_CACHE_KEY,
                array_values(array_unique(array_merge($alerted, $currentIds))),
                now()->addHours(24)
            );
        }
    }

    private function clearSensorFailureAlertIfNeeded(): void
    {
        if ($this->alreadyAlertedSensorIds() === [] && !Cache::has(self::ALERTED_CACHE_KEY)) {
            return;
        }

        $this->sendAlert(
            (string) __("Weather sensors back to normal"),
            (string) __("All previously failing sensors are reporting again.")
        );
        Cache::forget(self::ALERTED_CACHE_KEY);
    }

    private function checkForecastData()
    {
        $latitude = Setting::latitude();
        $longitude = Setting::longitude();
        // Whatever source is configured, not always Yr.no. This is why every
        // other source showed "Offline" about an hour after being selected,
        // and sent an alert titled "Forecast Data (Yr.no)". Issue #99.
        $cacheKey = \App\Support\ForecastCacheKeys::forSource(
            (string) Setting::getValue('forecast.default_source', 'fct_yrno_block.php'),
            $latitude,
            $longitude
        );

        $forecastData = Cache::get($cacheKey);

        $this->healthStatus['forecast'] = $this->freshness($cacheKey, $forecastData);
        $isStale = $this->healthStatus['forecast']['is_stale'];

        if ($isStale && $forecastData === null) {
            $this->sendAlertIfNeeded('forecast', 'Forecast Data', 'Forecast data not available.');
        } else {
            $this->clearAlertIfNeeded('forecast', 'Forecast Data');
        }
    }

    private function checkAstronomyData()
    {
        $this->healthStatus['astronomy'] = $this->freshness('astronomy_sun', Cache::get('astronomy_sun'));
    }

    private function checkAuroraData()
    {
        $this->healthStatus['aurora'] = $this->freshness('aurora_kp_index', Cache::get('aurora_kp_index'));
    }

    private function checkAirQualityData()
    {
        $latitude = Setting::latitude();
        $longitude = Setting::longitude();
        $stationMode = Setting::getValue('waqi.station_mode', 'auto');
        $stationId = Setting::getValue('waqi.station_id', '');

        $cacheKey = ($stationMode === 'manual' && !empty($stationId))
            ? "waqi_station_{$stationId}"
            : "waqi_{$latitude}_{$longitude}";

        $this->healthStatus['airquality'] = $this->freshness($cacheKey, Cache::get($cacheKey));
    }

    private function checkMetarData()
    {
        if (!Setting::getValue('metar.enabled', false)) {
            $this->healthStatus['metar'] = [
                'is_stale' => false,
                'age_minutes' => null,
                'last_update' => null,
            ];
            return;
        }

        $primaryIcao = Setting::getValue('metar.primary_icao', 'EHAM');
        $this->healthStatus['metar'] = $this->freshness("metar_{$primaryIcao}", Cache::get("metar_{$primaryIcao}"));
    }

    /**
     * Freshness of a cached data source, from the write time recorded alongside
     * the payload. A source is stale when the payload is gone, or when it was
     * written longer than $maxAgeMinutes ago. This matters because payload TTLs
     * are longer than the threshold, so an abandoned entry lingers as "healthy"
     * for hours if only its presence is checked.
     *
     * @return array{is_stale: bool, age_minutes: float|null, last_update: string|null}
     */
    private function freshness(string $cacheKey, mixed $payload, int $maxAgeMinutes = 60): array
    {
        $lastUpdate = CacheFreshness::updatedAt($cacheKey);

        // Carbon 3 returns a signed diff, so compare the absolute age.
        $ageMinutes = $lastUpdate ? round(abs(now()->diffInMinutes($lastUpdate)), 1) : null;

        return [
            'is_stale' => !$payload || ($ageMinutes !== null && $ageMinutes > $maxAgeMinutes),
            'age_minutes' => $ageMinutes,
            'last_update' => $lastUpdate?->toIso8601String(),
        ];
    }

    private function sendAlertIfNeeded(string $source, string $sourceName, string $details)
    {
        $cacheKey = "alert_sent_{$source}";

        if (!Cache::get($cacheKey, false)) {
            $this->sendAlert(
                "{$sourceName} Offline",
                "Data source '{$sourceName}' is not responding or has stale data.\n\n{$details}"
            );
            Cache::put($cacheKey, true, now()->addHours(24));
        }
    }

    private function clearAlertIfNeeded(string $source, string $sourceName)
    {
        $cacheKey = "alert_sent_{$source}";

        if (Cache::get($cacheKey, false)) {
            $this->sendAlert(
                "{$sourceName} Back Online",
                "Data source '{$sourceName}' is reporting data again."
            );
            Cache::forget($cacheKey);
        }
    }

    /**
     * Send alert through the shared notification channel, so the recipient
     * configured in Admin → Settings → Notifications applies here too.
     */
    private function sendAlert(string $subject, string $message): bool
    {
        Log::warning($subject . ': ' . $message);

        $delivered = app(NotificationDispatcher::class)
            ->send($subject, ['message' => $message], 'sensor_offline');

        if ($delivered) {
            $this->info("Alert notification sent: {$subject}");
        } else {
            $this->warn("Alert not delivered (check Admin → Settings → Notifications): {$subject}");
        }

        return $delivered;
    }
}
