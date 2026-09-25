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
    /**
     * WS90 fields that carry a voltage rather than a 0/1 low-battery flag,
     * mapped to the names the Ecowitt cloud API uses for the same readings.
     * The dashboard only knows the cloud names, and judges them in volts.
     * The push and local-file paths send the left-hand names.
     */
    public const WS90_VOLTAGE_FIELDS = [
        'wh90batt' => 'haptic_array_battery',
        'ws90cap_volt' => 'haptic_array_capacitor',
    ];

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
                // Readings are always stored in metric (see convertLocalToApiFormat()
                // and EcowittPushParser for the other two data sources); request the
                // same units from the cloud API so extractValue() never has to guess.
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

            // Convert the local file format to the API-like structure
            $data = $this->convertLocalToApiFormat($rawData);
            Cache::put('ecowitt_realtime', $data, now()->addMinutes(5));
            
            return $data;
        } catch (\Exception $e) {
            Log::error('Error reading Ecowitt local file', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Convert local file format to API-like structure
     */
    /**
     * WS90 battery and capacitor voltages, under their cloud API names.
     * Only the ones present, so other stations do not get empty WS90 rows.
     */
    private function ws90Voltages(array $raw): array
    {
        $voltages = [];
        foreach (self::WS90_VOLTAGE_FIELDS as $field => $key) {
            if (isset($raw[$field]) && is_numeric($raw[$field])) {
                $voltages[$key] = round((float) $raw[$field], 2);
            }
        }

        return $voltages;
    }

    private function convertLocalToApiFormat(array $raw): array
    {
        // Convert Fahrenheit to Celsius
        $tempC = isset($raw['tempf']) ? round(($raw['tempf'] - 32) * 5 / 9, 1) : null;
        $tempInC = isset($raw['tempinf']) ? round(($raw['tempinf'] - 32) * 5 / 9, 1) : null;
        $temp1C = isset($raw['temp1f']) ? round(($raw['temp1f'] - 32) * 5 / 9, 1) : null;
        $temp2C = isset($raw['temp2f']) ? round(($raw['temp2f'] - 32) * 5 / 9, 1) : null;
        
        // Convert inHg to hPa
        $pressureHpa = isset($raw['baromrelin']) ? round($raw['baromrelin'] * 33.8639, 1) : null;
        
        // Convert mph to km/h
        $windSpeedKmh = isset($raw['windspeedmph']) ? round($raw['windspeedmph'] * 1.60934, 1) : null;
        $windGustKmh = isset($raw['windgustmph']) ? round($raw['windgustmph'] * 1.60934, 1) : null;
        $maxDailyGustKmh = isset($raw['maxdailygust']) ? round($raw['maxdailygust'] * 1.60934, 1) : null;
        
        // Convert inches to mm
        $rainRateMm = isset($raw['rainratein']) ? round($raw['rainratein'] * 25.4, 2) : null;
        $dailyRainMm = isset($raw['dailyrainin']) ? round($raw['dailyrainin'] * 25.4, 2) : null;
        $hourlyRainMm = isset($raw['hourlyrainin']) ? round($raw['hourlyrainin'] * 25.4, 2) : null;
        $eventRainMm = isset($raw['eventrainin']) ? round($raw['eventrainin'] * 25.4, 2) : null;
        $weeklyRainMm = isset($raw['weeklyrainin']) ? round($raw['weeklyrainin'] * 25.4, 2) : null;
        $monthlyRainMm = isset($raw['monthlyrainin']) ? round($raw['monthlyrainin'] * 25.4, 2) : null;
        $yearlyRainMm = isset($raw['yearlyrainin']) ? round($raw['yearlyrainin'] * 25.4, 2) : null;
        $totalRainMm = isset($raw['totalrainin']) ? round($raw['totalrainin'] * 25.4, 2) : null;
        
        return [
            'outdoor' => [
                'temperature' => ['value' => $tempC, 'unit' => '℃'],
                'humidity' => ['value' => isset($raw['humidity']) ? (int) $raw['humidity'] : null, 'unit' => '%'],
            ],
            'indoor' => [
                'temperature' => ['value' => $tempInC, 'unit' => '℃'],
                'humidity' => ['value' => isset($raw['humidityin']) ? (int) $raw['humidityin'] : null, 'unit' => '%'],
            ],
            'wind' => [
                'wind_speed' => ['value' => $windSpeedKmh, 'unit' => 'km/h'],
                'wind_gust' => ['value' => $windGustKmh, 'unit' => 'km/h'],
                'wind_direction' => ['value' => isset($raw['winddir']) ? (int) $raw['winddir'] : null, 'unit' => '°'],
                'wind_gust_day_max' => ['value' => $maxDailyGustKmh, 'unit' => 'km/h'],
            ],
            'pressure' => [
                'relative' => ['value' => $pressureHpa, 'unit' => 'hPa'],
                'absolute' => ['value' => isset($raw['baromabsin']) ? round($raw['baromabsin'] * 33.8639, 1) : null, 'unit' => 'hPa'],
            ],
            'rainfall' => [
                'rain_rate' => ['value' => $rainRateMm, 'unit' => 'mm/h'],
                'hourly' => ['value' => $hourlyRainMm, 'unit' => 'mm'],
                'daily' => ['value' => $dailyRainMm, 'unit' => 'mm'],
                'event' => ['value' => $eventRainMm, 'unit' => 'mm'],
                'weekly' => ['value' => $weeklyRainMm, 'unit' => 'mm'],
                'monthly' => ['value' => $monthlyRainMm, 'unit' => 'mm'],
                'yearly' => ['value' => $yearlyRainMm, 'unit' => 'mm'],
                'total' => ['value' => $totalRainMm, 'unit' => 'mm'],
            ],
            'solar_and_uvi' => [
                'solar' => ['value' => isset($raw['solarradiation']) ? (float) $raw['solarradiation'] : null, 'unit' => 'W/m²'],
                'uvi' => ['value' => isset($raw['uv']) ? (int) $raw['uv'] : null],
            ],
            'lightning' => [
                'distance' => ['value' => isset($raw['lightning']) ? (int) $raw['lightning'] : null, 'unit' => 'km'],
                'count' => ['value' => isset($raw['lightning_num']) ? (int) $raw['lightning_num'] : 0],
                'time' => ['value' => isset($raw['lightning_time']) ? (int) $raw['lightning_time'] : null],
            ],
            'extra_temp' => [
                'temp1' => ['value' => $temp1C, 'unit' => '℃'],
                'temp2' => ['value' => $temp2C, 'unit' => '℃'],
                'humidity1' => ['value' => isset($raw['humidity1']) ? (int) $raw['humidity1'] : null, 'unit' => '%'],
            ],
            'battery' => [
                'wh26batt' => isset($raw['wh26batt']) ? (int) $raw['wh26batt'] : null,
                'wh57batt' => isset($raw['wh57batt']) ? (int) $raw['wh57batt'] : null,
                'wh65batt' => isset($raw['wh65batt']) ? (int) $raw['wh65batt'] : null,
                'batt1' => isset($raw['batt1']) ? (int) $raw['batt1'] : null,
                'batt2' => isset($raw['batt2']) ? (int) $raw['batt2'] : null,
            ] + $this->ws90Voltages($raw),
            'station' => [
                'model' => $raw['model'] ?? null,
                'type' => $raw['stationtype'] ?? null,
                'runtime' => isset($raw['runtime']) ? (int) $raw['runtime'] : null,
                'freq' => $raw['freq'] ?? null,
            ],
            'dateutc' => $raw['dateutc'] ?? null,
        ];
    }

    /**
     * Parse Ecowitt data and save to database
     */
    public function saveReading(array $data): ?WeatherReading
    {
        $outdoor = $data['outdoor'] ?? [];
        $indoor = $data['indoor'] ?? [];
        $wind = $data['wind'] ?? [];
        $pressure = $data['pressure'] ?? [];
        $rainfall = $data['rainfall'] ?? [];
        $solar = $data['solar_and_uvi'] ?? [];
        $lightning = $data['lightning'] ?? [];
        $extraTemp = $data['extra_temp'] ?? [];
        $battery = $data['battery'] ?? [];
        $station = $data['station'] ?? [];

        // Use ecowitt timestamp if available, otherwise use now()
        $recordedAt = now();
        if (!empty($data['dateutc'])) {
            try {
                // Parse as UTC and convert to app timezone before storing
                $recordedAt = \Carbon\Carbon::parse($data['dateutc'], 'UTC')
                    ->setTimezone(config('app.timezone'));
            } catch (\Exception $e) {
                // Fall back to now() if parsing fails
            }
        }

        $reading = [
            'recorded_at' => $recordedAt,
            
            // Outdoor sensors
            'temperature' => $this->extractValue($outdoor, 'temperature'),
            'feels_like' => $this->extractValue($outdoor, 'feels_like'),
            'dew_point' => $this->extractValue($outdoor, 'dew_point'),
            'wet_bulb' => $this->extractValue($outdoor, 'wet_bulb'),
            'humidity' => $this->extractValue($outdoor, 'humidity'),
            
            // Indoor sensors
            'temperature_indoor' => $this->extractValue($indoor, 'temperature'),
            'humidity_indoor' => $this->extractValue($indoor, 'humidity'),
            'indoor_temperature' => $this->extractValue($indoor, 'temperature'), // Alias
            'indoor_humidity' => $this->extractValue($indoor, 'humidity'), // Alias
            
            // Pressure
            'pressure_abs' => $this->extractValue($pressure, 'absolute'),
            'pressure_rel' => $this->extractValue($pressure, 'relative'),
            
            // Wind
            'wind_speed' => $this->extractValue($wind, 'wind_speed'),
            'wind_gust' => $this->extractValue($wind, 'wind_gust'),
            'wind_direction' => $this->extractValue($wind, 'wind_direction'),
            'wind_gust_max_daily' => $this->extractValue($wind, 'wind_gust_day_max'),
            
            // Rainfall
            'rain_rate' => $this->extractValue($rainfall, 'rain_rate'),
            'rain_hourly' => $this->extractValue($rainfall, 'hourly'),
            'rain_daily' => $this->extractValue($rainfall, 'daily'),
            'rain_event' => $this->extractValue($rainfall, 'event'),
            'rain_weekly' => $this->extractValue($rainfall, 'weekly'),
            'rain_monthly' => $this->extractValue($rainfall, 'monthly'),
            'rain_yearly' => $this->extractValue($rainfall, 'yearly'),
            'rain_total' => $this->extractValue($rainfall, 'total'),
            
            // Solar & UV
            'uv_index' => $this->extractValue($solar, 'uvi'),
            'solar_radiation' => $this->extractValue($solar, 'solar'),
            
            // Lightning
            'lightning_distance' => $this->extractValue($lightning, 'distance'),
            'lightning_count_daily' => $this->extractValue($lightning, 'count'),
            'lightning_time' => isset($lightning['time']['value']) && $lightning['time']['value'] 
                ? \Carbon\Carbon::createFromTimestamp($lightning['time']['value']) 
                : null,
            
            // Extra temperature sensors
            'temp_1' => $this->extractValue($extraTemp, 'temp1'),
            'temp_2' => $this->extractValue($extraTemp, 'temp2'),
            'humidity_1' => $this->extractValue($extraTemp, 'humidity1'),
            
            // Battery status (array - Eloquent will handle JSON encoding)
            'battery_status' => !empty($battery) ? $battery : null,
            
            // Station info
            'station_type' => $station['type'] ?? null,
            'station_model' => $station['model'] ?? null,
            'station_runtime' => $station['runtime'] ?? null,
            'station_freq' => $station['freq'] ?? null,
        ];
        
        return $this->writer->store($reading);
    }

    /**
     * Extract numeric value from Ecowitt response
     */
    private function extractValue(array $data, string $key): ?float
    {
        if (!isset($data[$key])) {
            return null;
        }

        $value = $data[$key];

        // Handle nested value/unit structure
        if (is_array($value) && isset($value['value'])) {
            return (float) $value['value'];
        }

        return is_numeric($value) ? (float) $value : null;
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
