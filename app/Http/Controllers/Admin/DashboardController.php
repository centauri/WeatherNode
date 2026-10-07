<?php

namespace App\Http\Controllers\Admin;

use App\Support\BatteryStatus;
use App\Support\LiveSource;
use App\Http\Controllers\Controller;
use App\Models\WeatherReading;
use App\Models\DailySummary;
use App\Models\Setting;
use App\Models\User;
use App\Services\Weather\SensorTrackerService;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Show the admin dashboard.
     */
    public function index()
    {
        $lastReading = WeatherReading::latest('recorded_at')->first();
        
        $stats = [
            'total_readings' => WeatherReading::count(),
            'daily_summaries' => DailySummary::count(),
            'users' => User::count(),
            'last_reading' => $lastReading?->recorded_at,
            'database_size' => $this->getDatabaseSize(),
        ];

        $recentReadings = WeatherReading::latest('recorded_at')
            ->take(10)
            ->get();

        // Battery status from most recent reading
        $batteryStatus = $this->parseBatteryStatus($lastReading?->battery_status);
        
        // Every sensor known from the tracking window, with its current state,
        // so one that has gone quiet stays visible instead of disappearing.
        $sensorStates = $this->getSensorStates();

        // Station info
        $stationInfo = [
            'type' => $lastReading?->station_type,
            'model' => $lastReading?->station_model,
            'runtime_hours' => $lastReading?->station_runtime ? round($lastReading->station_runtime / 3600, 1) : null,
            'freq' => $lastReading?->station_freq,
        ];

        // Telemetry status
        $telemetryEnabled = Setting::getValue('telemetry.enabled', false);
        $telemetryLastUpdated = Setting::getValue('telemetry.last_updated', '');
        $telemetryService = app(\App\Services\Telemetry\TelemetryService::class);
        $telemetryData = $telemetryService->collectStationData();

        $liveSource = LiveSource::card();

        return view('admin.dashboard', compact(
            'stats',
            'recentReadings',
            'batteryStatus',
            'sensorStates',
            'stationInfo',
            'telemetryEnabled',
            'telemetryLastUpdated',
            'telemetryData',
            'liveSource'
        ));
    }

    /**
     * Batteries for the admin card, judged by BatteryStatus like the public
     * dashboard is, so the two can no longer disagree (#131).
     */
    private function parseBatteryStatus(mixed $batteries): array
    {
        $status = [];

        foreach (BatteryStatus::classify($batteries) as $key => $battery) {
            $name = $battery['label'] !== '' ? __($battery['label']) : $key;
            if ($battery['channel'] !== null) {
                $name .= ' ' . $battery['channel'];
            }

            $display = $battery['type'] === 'volts'
                ? __($battery['status']) . ' (' . $battery['value'] . ' V)'
                : __($battery['status']);

            $status[] = [
                'key' => $key,
                'name' => $name,
                'type' => $battery['type'],
                'value' => $battery['value'],
                'state' => $battery['state'],
                // Volts have no meaningful percentage; show a full or near-empty bar.
                'percentage' => $battery['percentage'] ?? match ($battery['state']) {
                    'good' => 100,
                    'low' => 20,
                    default => 50,
                },
                'display' => $display,
                'icon' => $battery['icon'],
            ];
        }

        return $status;
    }

    /**
     * Sensor states for display, served from the cache the scheduled health
     * check refreshes. Scanning the reading window here would cost seconds.
     */
    private function getSensorStates(): array
    {
        $trackDays = max(1, min(30, (int) Setting::getValue('sensor_health.track_days', 7)));
        $failMinutes = max(15, min(10080, (int) Setting::getValue('sensor_health.fail_minutes', 30)));

        return app(SensorTrackerService::class)->getCachedSensorStates($trackDays, $failMinutes);
    }

    /**
     * Get database size in MB.
     */
    private function getDatabaseSize(): ?float
    {
        try {
            if (config('database.default') === 'mysql') {
                $result = DB::select("
                    SELECT SUM(data_length + index_length) / 1024 / 1024 AS size 
                    FROM information_schema.tables 
                    WHERE table_schema = ?
                ", [config('database.connections.mysql.database')]);
                return round($result[0]->size ?? 0, 2);
            } elseif (config('database.default') === 'sqlite') {
                $path = config('database.connections.sqlite.database');
                if (file_exists($path)) {
                    return round(filesize($path) / 1024 / 1024, 2);
                }
            }
        } catch (\Exception $e) {
            // Ignore errors
        }
        return null;
    }
}
