<?php

declare(strict_types=1);

namespace App\Services\Weather;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;

/**
 * WeatherLink v2 API Service
 * 
 * Provides integration with Davis WeatherLink v2 Cloud API.
 * Supports both production and demo mode authentication.
 * 
 * @see https://weatherlink.github.io/v2-api
 */
class WeatherLinkService
{
    private ?string $apiKey;
    private ?string $apiSecret;
    private ?string $stationId;
    private bool $demoMode;
    private string $apiUrl = 'https://api.weatherlink.com/v2/';
    
    // Demo station IDs (from WeatherLink documentation)
    private const DEMO_STATION_ID_INT = 2;
    private const DEMO_STATION_ID_UUID = '9722cfc3-a4ef-47b9-befb-72f52592d6ed';

    /**
     * Constructor - Loads configuration from settings
     */
    public function __construct()
    {
        $this->apiKey = $this->getDecryptedValue('weatherlink.api_key');
        $this->apiSecret = $this->getDecryptedValue('weatherlink.api_secret');
        $stationIdValue = Setting::getValue('weatherlink.station_id', '');
        // Cast to string - handle both integer and string types from settings
        // Note: 0 is a valid station ID, so we preserve it as '0'
        $this->stationId = $stationIdValue !== null && $stationIdValue !== '' ? (string) $stationIdValue : '';
        $this->demoMode = (bool) Setting::getValue('weatherlink.demo_mode', false);
    }

    /**
     * Get decrypted value from settings
     * 
     * Attempts to decrypt an encrypted setting value. If decryption fails,
     * returns the raw value (for backwards compatibility with unencrypted values).
     * 
     * @param string $key Setting key to retrieve
     * @return string|null Decrypted value or null if empty
     */
    private function getDecryptedValue(string $key): ?string
    {
        $encrypted = Setting::getValue($key, '');
        
        if (empty($encrypted)) {
            return null;
        }

        // If the value is already decrypted (not encrypted format), return as-is
        // Encrypted values typically start with base64-like patterns or are longer
        // For simplicity, try decryption first and fall back to raw value
        try {
            return Crypt::decryptString($encrypted);
        } catch (\Exception $e) {
            // If decryption fails, assume it's already unencrypted (backwards compatibility)
            Log::debug('WeatherLink setting decryption failed, using raw value', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return $encrypted;
        } catch (\Throwable $e) {
            // Catch any other errors (TypeError, etc.)
            Log::warning('WeatherLink setting value retrieval error', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Check if service is configured
     * 
     * For demo mode, API key and API secret are required.
     * For production mode, API key, secret, and station ID are required.
     * 
     * @return bool True if service is properly configured
     */
    public function isConfigured(): bool
    {
        if ($this->demoMode) {
            // Demo mode requires API key and API secret (secret is required for all API calls)
            return !empty($this->apiKey) && !empty($this->apiSecret);
        }
        
        return !empty($this->apiKey) && !empty($this->apiSecret) && !empty($this->stationId);
    }

    /**
     * Get the station ID to use for API calls
     * 
     * Returns demo station ID if demo mode is enabled, otherwise returns configured station ID.
     * 
     * @return string Station ID (UUID or integer)
     */
    private function getStationId(): string
    {
        if ($this->demoMode) {
            return self::DEMO_STATION_ID_UUID;
        }
        
        return $this->stationId;
    }

    /**
     * List the stations this API key can read.
     *
     * Not routed through makeRequest(): that refuses to run without a station
     * ID, and finding one is the point of this call. WeatherLink only returns
     * stations the account owns or has been given access to.
     *
     * Credentials typed on the settings page but not yet saved can be passed
     * in; anything left empty falls back to the saved value.
     *
     * @return array{success: bool, message: string, stations: array<int, array<string, mixed>>}
     */
    public function listStations(?string $apiKey = null, ?string $apiSecret = null): array
    {
        $apiKey = trim((string) $apiKey) !== '' ? trim((string) $apiKey) : $this->apiKey;
        $apiSecret = trim((string) $apiSecret) !== '' ? trim((string) $apiSecret) : $this->apiSecret;

        if (empty($apiKey) || empty($apiSecret)) {
            return ['success' => false, 'message' => 'Enter your API key and API secret first.', 'stations' => []];
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(['X-Api-Secret' => $apiSecret])
                ->get($this->apiUrl . 'stations', ['api-key' => $apiKey]);
        } catch (\Exception $e) {
            Log::warning('WeatherLink station list request failed', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Could not reach WeatherLink. Try again in a moment.', 'stations' => []];
        }

        if (in_array($response->status(), [401, 403], true)) {
            return ['success' => false, 'message' => 'WeatherLink rejected this API key and secret.', 'stations' => []];
        }

        if (!$response->successful()) {
            Log::warning('WeatherLink station list returned an error', ['status' => $response->status()]);

            return ['success' => false, 'message' => 'WeatherLink returned an error (HTTP ' . $response->status() . ').', 'stations' => []];
        }

        // Only what the picker shows. The response also carries the account's
        // e-mail, IMEI and subscription details, which the page has no use for.
        $stations = [];
        foreach ((array) ($response->json('stations') ?? []) as $station) {
            if (!is_array($station) || !isset($station['station_id'])) {
                continue;
            }
            $stations[] = [
                'station_id' => (string) $station['station_id'],
                'station_id_uuid' => isset($station['station_id_uuid']) ? (string) $station['station_id_uuid'] : null,
                'name' => (string) ($station['station_name'] ?? ''),
                'location' => implode(', ', array_filter([
                    $station['city'] ?? null,
                    $station['region'] ?? null,
                    $station['country'] ?? null,
                ], fn ($part) => is_string($part) && trim($part) !== '')),
                'active' => (bool) ($station['active'] ?? true),
                'relationship' => isset($station['relationship_type']) ? (string) $station['relationship_type'] : null,
            ];
        }

        if ($stations === []) {
            return [
                'success' => true,
                'message' => 'This account has no stations. WeatherLink only lists stations you own or that were shared with you.',
                'stations' => [],
            ];
        }

        return [
            'success' => true,
            'message' => count($stations) === 1 ? 'Found 1 station.' : 'Found ' . count($stations) . ' stations.',
            'stations' => $stations,
        ];
    }

    /**
     * Make API request to WeatherLink v2
     * 
     * Uses header-based authentication with X-Api-Secret header (current standard).
     * Includes demo=true parameter when demo mode is enabled.
     * 
     * @param string $endpoint API endpoint (e.g., 'current/{station-id}')
     * @param array $extraParams Additional query parameters
     * @return array|null API response as array, or null on failure
     */
    private function makeRequest(string $endpoint, array $extraParams = []): ?array
    {
        if (!$this->isConfigured()) {
            Log::warning('WeatherLink API not configured', [
                'demo_mode' => $this->demoMode,
                'has_api_key' => !empty($this->apiKey),
                'has_api_secret' => !empty($this->apiSecret),
                'has_station_id' => !empty($this->stationId),
            ]);
            return null;
        }

        // Ensure API key is not null/empty before using it
        if (empty($this->apiKey)) {
            Log::warning('WeatherLink API key is empty', [
                'demo_mode' => $this->demoMode,
            ]);
            return null;
        }

        // Build query parameters
        $params = [
            'api-key' => $this->apiKey,
        ];
        
        // Add demo parameter if demo mode is enabled
        if ($this->demoMode) {
            $params['demo'] = 'true';
        }
        
        // Merge any additional parameters
        $params = array_merge($params, $extraParams);

        // Build headers - API Secret goes in header, NOT query parameter
        // API Secret is required for ALL API calls, including demo mode
        $headers = [];
        if (!empty($this->apiSecret)) {
            $headers['X-Api-Secret'] = $this->apiSecret;
        }

        try {
            // Log request details for debugging (without sensitive data)
            if ($this->demoMode) {
                Log::debug('WeatherLink demo API request', [
                    'endpoint' => $endpoint,
                    'full_url' => $this->apiUrl . $endpoint,
                    'has_api_key' => !empty($this->apiKey),
                    'api_key_length' => strlen($this->apiKey ?? ''),
                    'params' => array_merge($params, ['api-key' => '[REDACTED]']), // Don't log actual key
                ]);
            }
            
            $response = Http::timeout(10)
                ->withHeaders($headers)
                ->get($this->apiUrl . $endpoint, $params);

            if (!$response->successful()) {
                $errorBody = $response->body();
                Log::error('WeatherLink API request failed', [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'demo_mode' => $this->demoMode,
                    'error_body' => substr($errorBody, 0, 500), // Limit log size
                    'request_url' => $this->apiUrl . $endpoint,
                    'has_demo_param' => isset($params['demo']),
                    // Never log credentials
                ]);
                return null;
            }

            $jsonResponse = $response->json();
            
            // Log response structure for debugging (without sensitive data)
            if ($this->demoMode) {
                Log::debug('WeatherLink demo API response', [
                    'endpoint' => $endpoint,
                    'has_sensors' => isset($jsonResponse['sensors']),
                    'response_keys' => array_keys($jsonResponse ?? []),
                ]);
            }
            
            return $jsonResponse;

        } catch (\Exception $e) {
            Log::error('WeatherLink API exception', [
                'error' => $e->getMessage(),
                'endpoint' => $endpoint,
                'demo_mode' => $this->demoMode,
            ]);
            return null;
        }
    }

    /**
     * Get current conditions for the configured station
     * 
     * Retrieves current weather conditions from WeatherLink API.
     * Results are cached for 60 seconds to reduce API calls.
     * 
     * @return array|null Parsed sensor data, or null on failure
     */
    public function getCurrentConditions(): ?array
    {
        $stationId = $this->getStationId();
        $cacheKey = "weatherlink_current_{$stationId}";

        return Cache::remember($cacheKey, 60, function () use ($stationId) {
            $response = $this->makeRequest("current/{$stationId}");
            
            if (!$response) {
                Log::warning('WeatherLink current conditions: no response', [
                    'station_id' => $stationId,
                    'demo_mode' => $this->demoMode,
                ]);
                return null;
            }
            
            if (!isset($response['sensors'])) {
                Log::warning('WeatherLink current conditions response invalid', [
                    'has_response' => !empty($response),
                    'has_sensors' => isset($response['sensors']),
                    'response_keys' => array_keys($response ?? []),
                    'station_id' => $stationId,
                    'demo_mode' => $this->demoMode,
                ]);
                return null;
            }

            return $this->parseSensorData($response['sensors']);
        });
    }

    /**
     * Parse sensor data from WeatherLink API response
     * 
     * Maps WeatherLink sensor data to standardized format.
     * Handles multiple sensor types: ISS (outdoor), Barometer, Indoor, AirLink.
     * 
     * Sensor Types:
     * - 23: EnviroMonitor Weather Station (data_structure_type 6)
     * - 45: Vantage Pro2 ISS
     * - 323: AirLink Air Quality Sensor (data_structure_type 16)
     * - 3: Barometer (data_structure_type 9)
     * - 242: Legacy barometer
     * - 243: Legacy indoor sensor
     * - 365: Vue indoor sensor
     * 
     * @param array $sensors Array of sensor objects from API response
     * @return array Parsed weather data with standardized keys
     */
    private function parseSensorData(array $sensors): array
    {
        $data = [
            'temperature' => null,
            'feels_like' => null,
            'humidity' => null,
            'dew_point' => null,
            'pressure' => null,
            'wind_speed' => null,
            'wind_gust' => null,
            'wind_direction' => null,
            'rain_rate' => null,
            'rain_daily' => null,
            'uv_index' => null,
            'solar_radiation' => null,
            'indoor_temperature' => null,
            'indoor_humidity' => null,
            'pm_2p5' => null,
            'pm_10' => null,
            'aqi' => null,
        ];

        foreach ($sensors as $sensor) {
            $sensorData = $sensor['data'][0] ?? [];
            $sensorType = $sensor['sensor_type'] ?? 0;
            $dataStructureType = $sensor['data_structure_type'] ?? 0;

            // EnviroMonitor Weather Station (sensor_type 23, data_structure_type 6)
            // This is the main weather data source for demo station
            if ($sensorType == 23 && $dataStructureType == 6) {
                $data['temperature'] = $this->fahrenheitToCelsius($sensorData['temp_out'] ?? null);
                $data['feels_like'] = $this->fahrenheitToCelsius($sensorData['heat_index'] ?? $sensorData['wind_chill'] ?? $sensorData['thsw_index'] ?? null);
                $data['humidity'] = $sensorData['hum_out'] ?? null;
                $data['dew_point'] = $this->fahrenheitToCelsius($sensorData['dew_point'] ?? null);
                $data['pressure'] = $this->inhgToHpa($sensorData['bar'] ?? null);
                $data['wind_speed'] = $this->mphToKmh($sensorData['wind_speed'] ?? $sensorData['wind_speed_10_min'] ?? null);
                $data['wind_gust'] = $this->mphToKmh($sensorData['wind_gust_10_min'] ?? null);
                $data['wind_direction'] = $sensorData['wind_dir'] ?? null;
                $data['rain_rate'] = $sensorData['rain_rate_mm'] ?? $this->inchToMm($sensorData['rain_rate_in'] ?? null);
                $data['rain_daily'] = $sensorData['rain_day_mm'] ?? $this->inchToMm($sensorData['rain_day_in'] ?? null);
                $data['uv_index'] = $sensorData['uv'] ?? null;
                $data['solar_radiation'] = $sensorData['solar_rad'] ?? null;
            }

            // Vantage Pro2/Vue ISS sensor (sensor_type 45)
            if ($sensorType == 45) {
                $data['temperature'] = $data['temperature'] ?? $this->fahrenheitToCelsius($sensorData['temp'] ?? null);
                $data['feels_like'] = $data['feels_like'] ?? $this->fahrenheitToCelsius($sensorData['heat_index'] ?? $sensorData['wind_chill'] ?? null);
                $data['humidity'] = $data['humidity'] ?? $sensorData['hum'] ?? null;
                $data['dew_point'] = $data['dew_point'] ?? $this->fahrenheitToCelsius($sensorData['dew_point'] ?? null);
                $data['wind_speed'] = $data['wind_speed'] ?? $this->mphToKmh($sensorData['wind_speed_last'] ?? null);
                $data['wind_gust'] = $data['wind_gust'] ?? $this->mphToKmh($sensorData['wind_speed_hi_last_10_min'] ?? null);
                $data['wind_direction'] = $data['wind_direction'] ?? $sensorData['wind_dir_last'] ?? null;
                $data['rain_rate'] = $data['rain_rate'] ?? $this->inchToMm($sensorData['rain_rate_last_mm'] ?? $sensorData['rain_rate_last'] ?? null);
                $data['rain_daily'] = $data['rain_daily'] ?? $this->inchToMm($sensorData['rainfall_daily_mm'] ?? $sensorData['rainfall_daily'] ?? null);
                $data['uv_index'] = $data['uv_index'] ?? $sensorData['uv_index'] ?? null;
                $data['solar_radiation'] = $data['solar_radiation'] ?? $sensorData['solar_rad'] ?? null;
            }

            // Barometer sensor (sensor_type 3 or 242)
            if ($sensorType == 3 || $sensorType == 242) {
                $data['pressure'] = $data['pressure'] ?? $this->inhgToHpa($sensorData['bar_sea_level'] ?? $sensorData['pressure_last'] ?? $sensorData['bar'] ?? null);
            }

            // Indoor sensor (sensor_type 243 or 365)
            if ($sensorType == 243 || $sensorType == 365) {
                $data['indoor_temperature'] = $this->fahrenheitToCelsius($sensorData['temp_in'] ?? null);
                $data['indoor_humidity'] = $sensorData['hum_in'] ?? null;
            }

            // AirLink Air Quality sensor (sensor_type 323, data_structure_type 16)
            if ($sensorType == 323 && $dataStructureType == 16) {
                // AirLink has its own temp/humidity which can supplement missing data
                $data['temperature'] = $data['temperature'] ?? $this->fahrenheitToCelsius($sensorData['temp'] ?? null);
                $data['humidity'] = $data['humidity'] ?? $sensorData['hum'] ?? null;
                $data['dew_point'] = $data['dew_point'] ?? $this->fahrenheitToCelsius($sensorData['dew_point'] ?? null);
                $data['feels_like'] = $data['feels_like'] ?? $this->fahrenheitToCelsius($sensorData['heat_index'] ?? null);
                
                // Air quality data
                $data['pm_2p5'] = $sensorData['pm_2p5'] ?? $sensorData['pm_2p5_nowcast'] ?? null;
                $data['pm_10'] = $sensorData['pm_10'] ?? $sensorData['pm_10_nowcast'] ?? null;
                $data['aqi'] = $sensorData['aqi_val'] ?? $sensorData['aqi_nowcast_val'] ?? null;
            }
        }

        return array_filter($data, fn($v) => $v !== null);
    }

    /**
     * Get historical data for the configured station
     * 
     * Retrieves historical weather data within the specified date range.
     * Results are cached for 1 hour.
     * 
     * @param string $startDate Start date (any format accepted by strtotime)
     * @param string $endDate End date (any format accepted by strtotime)
     * @return array|null Historical sensor data, or null on failure
     */
    public function getHistorical(string $startDate, string $endDate): ?array
    {
        $start = strtotime($startDate);
        $end = strtotime($endDate);
        
        if ($start === false || $end === false) {
            Log::error('WeatherLink invalid date range', [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);
            return null;
        }

        $stationId = $this->getStationId();
        $cacheKey = "weatherlink_history_{$stationId}_{$start}_{$end}";

        return Cache::remember($cacheKey, 3600, function () use ($stationId, $start, $end) {
            $response = $this->makeRequest("historic/{$stationId}", [
                'start-timestamp' => $start,
                'end-timestamp' => $end,
            ]);
            
            if (!$response || !isset($response['sensors'])) {
                Log::warning('WeatherLink historical data response invalid');
                return null;
            }

            return $response;
        });
    }

    /**
     * Get all stations accessible by this API key
     * 
     * Retrieves list of all weather stations available to the configured API key.
     * In demo mode, includes the demo station in the results.
     * Results are cached for 1 hour.
     * 
     * @return array|null Array of station objects, or null on failure
     */
    public function getStations(): ?array
    {
        $cacheKey = "weatherlink_stations";

        return Cache::remember($cacheKey, 3600, function () {
            $response = $this->makeRequest('stations');
            
            if (!$response || !isset($response['stations'])) {
                Log::warning('WeatherLink stations response invalid');
                return null;
            }
            
            return $response;
        });
    }

    /**
     * Convert Fahrenheit to Celsius
     * 
     * @param float|null $f Temperature in Fahrenheit
     * @return float|null Temperature in Celsius, rounded to 1 decimal place
     */
    private function fahrenheitToCelsius(?float $f): ?float
    {
        return $f !== null ? round(($f - 32) * 5/9, 1) : null;
    }

    /**
     * Convert miles per hour to kilometers per hour
     * 
     * @param float|null $mph Speed in mph
     * @return float|null Speed in km/h, rounded to 1 decimal place
     */
    private function mphToKmh(?float $mph): ?float
    {
        return $mph !== null ? round($mph * 1.60934, 1) : null;
    }

    /**
     * Convert inches to millimeters
     * 
     * @param float|null $inch Measurement in inches
     * @return float|null Measurement in mm, rounded to 2 decimal places
     */
    private function inchToMm(?float $inch): ?float
    {
        return $inch !== null ? round($inch * 25.4, 2) : null;
    }

    /**
     * Convert inches of mercury to hectopascals
     * 
     * @param float|null $inhg Pressure in inHg
     * @return float|null Pressure in hPa, rounded to 1 decimal place
     */
    private function inhgToHpa(?float $inhg): ?float
    {
        return $inhg !== null ? round($inhg * 33.8639, 1) : null;
    }
}
