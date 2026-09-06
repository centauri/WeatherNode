<?php

namespace App\Services\Forecast;

use App\Contracts\Forecast\ForecastServiceInterface;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WeatherUndergroundService implements ForecastServiceInterface
{
    private float $latitude;
    private float $longitude;
    private string $apiKey;
    private string $baseUrl = 'https://api.weather.com/v3/wx/forecast/';

    public function __construct()
    {
        $this->latitude = Setting::latitude();
        $this->longitude = Setting::longitude();
        $this->apiKey = Setting::getValue('wunderground.api_key', '');
    }

    /**
     * Fetch weather forecast from Weather Underground
     */
    public function fetchForecast(): ?array
    {
        if (empty($this->apiKey)) {
            Log::error('Weather Underground API key not configured');
            return null;
        }

        $cacheKey = "wunderground_forecast_{$this->latitude}_{$this->longitude}";
        
        return Cache::remember($cacheKey, 1800, function () {
            try {
                // Weather Underground API v3 - daily forecast
                $response = Http::get($this->baseUrl . 'daily/5day', [
                    'geocode' => "{$this->latitude},{$this->longitude}",
                    'language' => 'en-US',
                    'units' => 'm', // metric
                    'format' => 'json',
                    'apiKey' => $this->apiKey,
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    
                    // Also get hourly forecast
                    $hourlyResponse = Http::get('https://api.weather.com/v3/wx/forecast/hourly/15day', [
                        'geocode' => "{$this->latitude},{$this->longitude}",
                        'language' => 'en-US',
                        'units' => 'm',
                        'format' => 'json',
                        'apiKey' => $this->apiKey,
                    ]);

                    $hourlyData = $hourlyResponse->successful() ? $hourlyResponse->json() : null;
                    
                    return $this->parseForecast($data, $hourlyData);
                }

                Log::error('Weather Underground API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

            } catch (\Exception $e) {
                Log::error('Weather Underground API exception', ['error' => $e->getMessage()]);
            }

            return null;
        });
    }

    /**
     * Parse Weather Underground API response into simplified structure
     */
    /**
     * One day's values out of daypart, which interleaves daytime and night.
     * Index 2i is the day, 2i+1 the night; the day is null once it has passed.
     *
     * @param array<string, array<int, mixed>> $daypart
     * @return array<string, mixed>
     */
    private function daypartValues(array $daypart, int $day): array
    {
        $fields = ['windSpeed', 'windDirectionCardinal', 'iconCode', 'cloudCover', 'relativeHumidity'];
        $values = [];

        foreach ($fields as $field) {
            $series = $daypart[$field] ?? [];
            $values[$field] = $series[$day * 2] ?? $series[$day * 2 + 1] ?? null;
        }

        return $values;
    }

    private function parseForecast(array $dailyData, ?array $hourlyData): ?array
    {
        $forecast = [];
        
        // Parse hourly data if available (more detailed)
        if ($hourlyData && isset($hourlyData['vt1hourlyForecasts'])) {
            $hourlyForecasts = $hourlyData['vt1hourlyForecasts'];
            $validTimeUtc = $hourlyForecasts['validTimeUtc'] ?? [];
            $temperature = $hourlyForecasts['temperature'] ?? [];
            $windSpeed = $hourlyForecasts['windSpeed'] ?? [];
            $windDirection = $hourlyForecasts['windDirectionCardinal'] ?? [];
            $windDirectionDeg = $hourlyForecasts['windDirection'] ?? [];
            $qpf = $hourlyForecasts['qpf'] ?? []; // Quantitative Precipitation Forecast (mm)
            $iconCode = $hourlyForecasts['iconCode'] ?? [];
            $cloudCover = $hourlyForecasts['cloudCover'] ?? [];
            $relativeHumidity = $hourlyForecasts['relativeHumidity'] ?? [];

            $count = min(
                count($validTimeUtc),
                count($temperature),
                count($windSpeed)
            );

            for ($i = 0; $i < $count; $i++) {
                $time = date('Y-m-d\TH:i:s\Z', $validTimeUtc[$i] ?? time());
                
                // Convert wind speed from km/h (WU uses metric units)
                $windSpeedKmh = $windSpeed[$i] ?? null;
                
                // Convert wind direction from cardinal to degrees if needed
                $windDir = $windDirectionDeg[$i] ?? $this->cardinalToDegrees($windDirection[$i] ?? null);

                $forecast[] = [
                    'time' => $time,
                    'temperature' => $temperature[$i] ?? null,
                    'humidity' => $relativeHumidity[$i] ?? null,
                    'pressure' => null, // Not in hourly forecast
                    'wind_speed' => $windSpeedKmh,
                    'wind_direction' => $windDir,
                    'cloud_cover' => $cloudCover[$i] ?? null,
                    'symbol' => $this->mapIconCodeToSymbol($iconCode[$i] ?? null),
                    'precipitation_1h' => $qpf[$i] ?? 0,
                    'precipitation_6h' => null,
                ];
            }
        } else {
            // Daily data. In API v3 the wind, icon and wind direction are not at the
            // top level: they sit in daypart, which holds two entries per day, the
            // daytime then the night. Reading them from the top level found nothing
            // and produced an empty forecast from a perfectly good response.
            $dayOfWeek = $dailyData['dayOfWeek'] ?? [];
            $temperatureMax = $dailyData['temperatureMax'] ?? [];
            $temperatureMin = $dailyData['temperatureMin'] ?? [];
            $qpf = $dailyData['qpf'] ?? [];
            $validTimeUtc = $dailyData['validTimeUtc'] ?? [];
            $daypart = $dailyData['daypart'][0] ?? [];

            $count = min(count($dayOfWeek), count($validTimeUtc));

            for ($i = 0; $i < $count; $i++) {
                // Today's daytime entry is null once the afternoon has passed, so
                // fall back to that night. Same reason temperatureMax can be null.
                $part = $this->daypartValues($daypart, $i);

                $high = $temperatureMax[$i] ?? null;
                $low = $temperatureMin[$i] ?? null;
                $mean = ($high !== null && $low !== null) ? ($high + $low) / 2 : ($high ?? $low);

                $dailyRain = $qpf[$i] ?? 0;

                $forecast[] = [
                    'time' => date('Y-m-d\TH:i:s\Z', $validTimeUtc[$i] ?? time()),
                    'temperature' => $mean,
                    'temp_high' => $high,
                    'temp_low' => $low,
                    'humidity' => $part['relativeHumidity'],
                    'pressure' => null,
                    'wind_speed' => $part['windSpeed'],
                    'wind_direction' => $this->cardinalToDegrees($part['windDirectionCardinal']),
                    'cloud_cover' => $part['cloudCover'],
                    'symbol' => $this->mapIconCodeToSymbol($part['iconCode']),
                    'precipitation_1h' => $dailyRain / 24,
                    'precipitation_6h' => $dailyRain / 4,
                ];
            }
        }

        // An empty forecast is a failure, not data. Returning the envelope
        // anyway got it cached for half an hour and, being truthy, it also
        // blocked the reader's fallback to the last good forecast.
        if ($forecast === []) {
            return null;
        }

        return [
            'updated_at' => now()->toIso8601String(),
            'forecast' => $forecast,
        ];
    }

    /**
     * Convert cardinal direction to degrees
     */
    private function cardinalToDegrees(?string $cardinal): ?int
    {
        if (!$cardinal) {
            return null;
        }

        $directions = [
            'N' => 0, 'NNE' => 22, 'NE' => 45, 'ENE' => 67,
            'E' => 90, 'ESE' => 112, 'SE' => 135, 'SSE' => 157,
            'S' => 180, 'SSW' => 202, 'SW' => 225, 'WSW' => 247,
            'W' => 270, 'WNW' => 292, 'NW' => 315, 'NNW' => 337,
        ];

        return $directions[strtoupper($cardinal)] ?? null;
    }

    /**
     * Map Weather Underground icon codes to Yr.no symbol codes
     */
    private function mapIconCodeToSymbol(?int $iconCode): ?string
    {
        if ($iconCode === null) {
            return null;
        }

        // Weather Underground icon code mapping
        $iconMap = [
            1 => 'clearsky_day',      // Clear
            2 => 'clearsky_day',      // Mostly Clear
            3 => 'partlycloudy_day',  // Partly Cloudy
            4 => 'partlycloudy_day',  // Mostly Cloudy
            5 => 'cloudy',            // Cloudy
            6 => 'fog',               // Hazy
            7 => 'fog',               // Fog
            8 => 'rain',              // Showers
            9 => 'lightrain',         // Light Rain
            10 => 'rain',             // Rain
            11 => 'heavyrain',        // Heavy Rain
            12 => 'rain',             // Rain Showers
            13 => 'lightsnow',        // Light Snow
            14 => 'snow',             // Snow
            15 => 'heavysnow',        // Heavy Snow
            16 => 'snow',             // Snow Showers
            17 => 'sleet',            // Sleet
            18 => 'sleet',            // Sleet Showers
            19 => 'heavyrainandthunder', // Thunderstorms
            20 => 'heavyrainandthunder', // Thunderstorms
            21 => 'heavyrainandthunder', // Thunderstorms
            22 => 'heavyrainandthunder', // Thunderstorms
            23 => 'heavyrainandthunder', // Thunderstorms
            24 => 'fog',              // Freezing Fog
            25 => 'sleet',            // Freezing Rain
            26 => 'sleet',            // Freezing Drizzle
            27 => 'sleet',            // Freezing Rain
            28 => 'sleet',            // Freezing Drizzle
            29 => 'sleet',            // Freezing Rain
            30 => 'sleet',            // Freezing Drizzle
            31 => 'sleet',            // Ice Pellets
            32 => 'sleet',            // Ice Pellet Showers
            33 => 'sleet',            // Hail
            34 => 'sleet',            // Hail Showers
        ];

        return $iconMap[$iconCode] ?? 'partlycloudy_day';
    }

    /**
     * Get hourly forecast for specified number of hours
     */
    public function getHourlyForecast(int $hours = 48): array
    {
        $data = $this->fetchForecast();
        if (!$data) {
            return [];
        }

        return array_slice($data['forecast'], 0, $hours);
    }

    /**
     * Get daily forecast summary
     */
    public function getDailyForecast(int $days = 7): array
    {
        $hourly = $this->getHourlyForecast(24 * $days);
        $daily = [];

        foreach ($hourly as $hour) {
            $date = substr($hour['time'], 0, 10);
            
            if (!isset($daily[$date])) {
                $daily[$date] = [
                    'date' => $date,
                    'temp_high' => $hour['temperature'],
                    'temp_low' => $hour['temperature'],
                    'temps' => [],
                    'symbols' => [],
                    'precipitation' => 0,
                    'wind_speeds' => [],
                    'wind_directions' => [],
                ];
            }

            $daily[$date]['temps'][] = $hour['temperature'];
            if ($hour['temperature'] > $daily[$date]['temp_high']) {
                $daily[$date]['temp_high'] = $hour['temperature'];
            }
            if ($hour['temperature'] < $daily[$date]['temp_low']) {
                $daily[$date]['temp_low'] = $hour['temperature'];
            }
            
            if ($hour['symbol']) {
                $daily[$date]['symbols'][] = $hour['symbol'];
            }
            
            $daily[$date]['precipitation'] += $hour['precipitation_1h'] ?? 0;
            
            // Collect wind data
            if (isset($hour['wind_speed']) && $hour['wind_speed'] !== null) {
                $daily[$date]['wind_speeds'][] = $hour['wind_speed'];
            }
            if (isset($hour['wind_direction']) && $hour['wind_direction'] !== null) {
                $daily[$date]['wind_directions'][] = $hour['wind_direction'];
            }
        }

        // Calculate dominant symbol and average wind for each day
        foreach ($daily as &$day) {
            $day['temp_avg'] = count($day['temps']) > 0 ? array_sum($day['temps']) / count($day['temps']) : null;
            $day['symbol'] = $this->getDominantSymbol($day['symbols']);
            
            // Calculate average wind speed
            $day['wind_speed'] = !empty($day['wind_speeds']) 
                ? array_sum($day['wind_speeds']) / count($day['wind_speeds']) 
                : null;
            
            // Calculate average wind direction (circular mean)
            $day['wind_direction'] = null;
            if (!empty($day['wind_directions'])) {
                $sinSum = 0;
                $cosSum = 0;
                foreach ($day['wind_directions'] as $dir) {
                    $rad = deg2rad($dir);
                    $sinSum += sin($rad);
                    $cosSum += cos($rad);
                }
                $avgRad = atan2($sinSum / count($day['wind_directions']), $cosSum / count($day['wind_directions']));
                $day['wind_direction'] = round(rad2deg($avgRad));
                // Normalize to 0-360
                if ($day['wind_direction'] < 0) {
                    $day['wind_direction'] += 360;
                }
            }
            
            unset($day['temps'], $day['symbols'], $day['wind_speeds'], $day['wind_directions']);
        }

        return array_slice(array_values($daily), 0, $days);
    }

    /**
     * Get most common weather symbol for a day
     */
    private function getDominantSymbol(array $symbols): ?string
    {
        if (empty($symbols)) {
            return null;
        }

        $counts = array_count_values($symbols);
        arsort($counts);
        return array_key_first($counts);
    }
}
