<?php

namespace App\Console\Commands;

use App\Services\Weather\SensorTrackerService;
use Illuminate\Console\Command;
use App\Models\WeatherReading;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
        $failMinutes = (int) Setting::getValue('sensor_health.fail_minutes', 120);
        $failMinutes = max(15, min(10080, $failMinutes)); // 15 min .. 7 days

        $tracker = app(SensorTrackerService::class);
        $failed = $tracker->getFailedSensors($trackDays, $failMinutes);

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

    private function sendSensorFailureAlertIfNeeded(array $failed, string $details): void
    {
        $cacheKey = 'alert_sent_sensor_failures';

        if (!Cache::get($cacheKey, false)) {
            $this->sendAlert(
                (string) __("One or more weather sensors have stopped reporting"),
                $details
            );
            Cache::put($cacheKey, true, now()->addHours(24));
        }
    }

    private function clearSensorFailureAlertIfNeeded(): void
    {
        $cacheKey = 'alert_sent_sensor_failures';

        if (Cache::get($cacheKey, false)) {
            $this->sendAlert(
                (string) __("Weather sensors back to normal"),
                (string) __("All previously failing sensors are reporting again.")
            );
            Cache::forget($cacheKey);
        }
    }

    private function checkForecastData()
    {
        $latitude = Setting::latitude();
        $longitude = Setting::longitude();
        $cacheKey = "yrno_forecast_{$latitude}_{$longitude}";

        $forecastData = Cache::get($cacheKey);
        $lastUpdate = Cache::get("{$cacheKey}_updated_at");

        $isStale = !$forecastData || ($lastUpdate && now()->diffInMinutes($lastUpdate) > 60);

        $this->healthStatus['forecast'] = [
            'is_stale' => (bool) $isStale,
            'age_minutes' => $lastUpdate ? round(abs(now()->diffInMinutes($lastUpdate)), 1) : null,
            'last_update' => $lastUpdate ? $lastUpdate->toIso8601String() : null,
        ];

        if ($isStale && $forecastData === null) {
            $this->sendAlertIfNeeded('forecast', 'Forecast Data (Yr.no)', 'Forecast data not available.');
        } else {
            $this->clearAlertIfNeeded('forecast', 'Forecast Data');
        }
    }

    private function checkAstronomyData()
    {
        $sunData = Cache::get('astronomy_sun');
        $sunUpdated = Cache::get('astronomy_sun_updated_at');

        $isStale = !$sunData || ($sunUpdated && now()->diffInMinutes($sunUpdated) > 60);

        $this->healthStatus['astronomy'] = [
            'is_stale' => (bool) $isStale,
            'age_minutes' => $sunUpdated ? round(abs(now()->diffInMinutes($sunUpdated)), 1) : null,
            'last_update' => $sunUpdated ? $sunUpdated->toIso8601String() : null,
        ];
    }

    private function checkAuroraData()
    {
        $auroraData = Cache::get('aurora_kp_index');
        $auroraUpdated = Cache::get('aurora_kp_index_updated_at');

        $isStale = !$auroraData || ($auroraUpdated && now()->diffInMinutes($auroraUpdated) > 60);

        $this->healthStatus['aurora'] = [
            'is_stale' => (bool) $isStale,
            'age_minutes' => $auroraUpdated ? round(abs(now()->diffInMinutes($auroraUpdated)), 1) : null,
            'last_update' => $auroraUpdated ? $auroraUpdated->toIso8601String() : null,
        ];
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

        $airQuality = Cache::get($cacheKey);
        $lastUpdate = Cache::get("{$cacheKey}_updated_at");

        $isStale = !$airQuality || ($lastUpdate && now()->diffInMinutes($lastUpdate) > 60);

        $this->healthStatus['airquality'] = [
            'is_stale' => (bool) $isStale,
            'age_minutes' => $lastUpdate ? round(abs(now()->diffInMinutes($lastUpdate)), 1) : null,
            'last_update' => $lastUpdate ? $lastUpdate->toIso8601String() : null,
        ];
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
        $metarData = Cache::get("metar_{$primaryIcao}");
        $lastUpdate = Cache::get("metar_{$primaryIcao}_updated_at");

        $isStale = !$metarData || ($lastUpdate && now()->diffInMinutes($lastUpdate) > 60);

        $this->healthStatus['metar'] = [
            'is_stale' => (bool) $isStale,
            'age_minutes' => $lastUpdate ? round(abs(now()->diffInMinutes($lastUpdate)), 1) : null,
            'last_update' => $lastUpdate ? $lastUpdate->toIso8601String() : null,
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
     * Send alert via configured notification channel
     */
    private function sendAlert(string $subject, string $message)
    {
        // Log to Laravel log
        Log::warning($subject . ': ' . $message);

        // Get notification settings
        $alertEmail = Setting::getValue('alerts.email');
        $alertsEnabled = Setting::getValue('alerts.enabled', false);

        if (!$alertsEnabled) {
            $this->warn('Alerts are disabled in settings. Enable in admin panel to receive notifications.');
            return;
        }

        if (!$alertEmail) {
            $this->warn('No alert email configured. Set alerts.email in admin panel.');
            return;
        }

        // Send email alert
        try {
            Mail::raw($message, function ($mail) use ($subject, $alertEmail) {
                $mail->to($alertEmail)
                     ->subject("[WeatherNode] {$subject}");
            });

            $this->info("Alert sent to: {$alertEmail}");
        } catch (\Exception $e) {
            $this->error("Failed to send email alert: " . $e->getMessage());
            Log::error('Failed to send sensor health alert email', [
                'error' => $e->getMessage(),
                'subject' => $subject,
            ]);
        }
    }
}
