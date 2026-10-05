<?php

namespace App\Services\Weather;

use App\Models\WeatherReading;
use App\Models\Setting;
use App\Services\Weather\Normalization\WeatherReadingWriter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EcowittService
{
    private string $applicationKey;
    private string $apiKey;
    private string $macAddress;
    private string $dataSource;
    private string $localFile;
    private string $baseUrl;
    private WeatherReadingWriter $writer;

    public function __construct(WeatherReadingWriter $writer)
    {
        $this->writer = $writer;
        $this->applicationKey = Setting::getValue('ecowitt.application_key', '') ?? '';
        $this->apiKey = Setting::getValue('ecowitt.api_key', '') ?? '';
        $this->macAddress = Setting::getValue('ecowitt.mac_address', '') ?? '';
        $this->dataSource = Setting::getValue('ecowitt.data_source', 'local_file') ?? 'local_file';
        $this->localFile = Setting::getValue('ecowitt.local_file', '') ?? '';
        $this->baseUrl = rtrim(Setting::getValue('ecowitt.api_base_url', 'https://api.ecowitt.net/api/v3/'), '/') . '/';
    }

    /**
     * Fetch real-time data from Ecowitt API or local file
     */
    public function fetchRealTimeData(): ?array
    {
        // Check if local file mode is enabled
        if (in_array($this->dataSource, ['local', 'local_file'], true)) {
            return $this->fetchFromLocalFile();
        }

        // API mode
        if (empty($this->applicationKey) || empty($this->apiKey) || empty($this->macAddress)) {
            Log::warning('Ecowitt API credentials not configured');
            return null;
        }

        try {
            $response = Http::get($this->baseUrl . 'device/real_time', [
                'application_key' => $this->applicationKey,
                'api_key' => $this->apiKey,
                'mac' => $this->macAddress,
                'call_back' => 'all',
                // Readings are stored in metric. Ask for metric too, although
                // EcowittCloudParser converts by unit if the API ignores this.
                'temp_unitid' => 1,               // °C
                'pressure_unitid' => 3,            // hPa
                'wind_speed_unitid' => 7,          // km/h
                'rainfall_unitid' => 12,           // mm
                'solar_irradiance_unitid' => 16,   // W/m²
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['data'])) {
                    Cache::put('ecowitt_realtime', $data['data'], now()->addMinutes(5));
                    return $data['data'];
                }
            }

            Log::error('Ecowitt API request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

        } catch (\Exception $e) {
            Log::error('Ecowitt API exception', ['error' => $e->getMessage()]);
        }

        return Cache::get('ecowitt_realtime');
    }

    /**
     * Fetch data from local file (PHP serialized array format)
     */
    private function fetchFromLocalFile(): ?array
    {
        if (empty($this->localFile)) {
            Log::warning('Ecowitt local file path not configured');
            return null;
        }

        // Reject path-traversal sequences before touching the filesystem. The
        // configured path is expected to live inside the project; a `..` segment
        // would let a tampered setting read arbitrary files.
        if (str_contains($this->localFile, '..')) {
            Log::warning('Ecowitt local file path rejected (path traversal)', ['path' => $this->localFile]);
            return null;
        }

        $filePath = base_path($this->localFile);

        if (!file_exists($filePath)) {
            Log::debug('Ecowitt local file not found (may be mid-write)', ['path' => $filePath]);
            return null;
        }

        try {
            $content = file_get_contents($filePath);
            // Ecowitt writes a plain serialized array. Disallowing classes prevents
            // PHP object injection if the file content is ever attacker-controlled.
            $rawData = @unserialize($content, ['allowed_classes' => false]);

            if ($rawData === false) {
                Log::error('Failed to unserialize Ecowitt local file');
                return null;
            }

            // The file holds the same fields as a push, and is stored the same way.
            Cache::put('ecowitt_realtime', $rawData, now()->addMinutes(5));

            return $rawData;
        } catch (\Exception $e) {
            Log::error('Error reading Ecowitt local file', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Store a reading from either Ecowitt source. The local file carries the
     * same fields as a push (tempf, dateutc, ...) and goes through the push
     * parser; a cloud response is grouped (outdoor, indoor, ...) and goes
     * through the cloud parser. Both fill the same columns.
     */
    public function saveReading(array $data): ?WeatherReading
    {
        $reading = self::isPushFormat($data)
            ? app(EcowittPushParser::class)->parse($data)
            : app(EcowittCloudParser::class)->parse($data);

        return $this->writer->store($reading);
    }

    private static function isPushFormat(array $data): bool
    {
        foreach (['tempf', 'tempinf', 'dateutc', 'stationtype', 'PASSKEY'] as $field) {
            if (array_key_exists($field, $data)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get cached or fresh current conditions
     */
    public function getCurrentConditions(): ?array
    {
        // Cache for 5 minutes (300 sec) instead of 60 sec for better resilience
        return Cache::remember('current_conditions', 300, function () {
            $data = $this->fetchRealTimeData();
            if (!$data) {
                // Fall back to latest database reading
                $reading = WeatherReading::mostRecent();
                return $reading ? $this->readingToArray($reading) : null;
            }
            return $data;
        });
    }

    /**
     * Convert reading model to array format
     */
    private function readingToArray(WeatherReading $reading): array
    {
        return [
            'recorded_at' => $reading->recorded_at->toIso8601String(),
            'temperature' => $reading->temperature,
            'feels_like' => $reading->feels_like,
            'humidity' => $reading->humidity,
            'dew_point' => $reading->dew_point,
            'pressure' => $reading->pressure_rel,
            'wind_speed' => $reading->wind_speed,
            'wind_gust' => $reading->wind_gust,
            'wind_direction' => $reading->wind_direction,
            'wind_direction_compass' => $reading->wind_direction_compass,
            'beaufort' => $reading->beaufort,
            'rain_rate' => $reading->rain_rate,
            'rain_daily' => $reading->rain_daily,
            'uv_index' => $reading->uv_index,
            'solar_radiation' => $reading->solar_radiation,
        ];
    }
}
