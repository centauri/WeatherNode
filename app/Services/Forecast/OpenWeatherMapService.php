<?php

namespace App\Services\Forecast;

use App\Contracts\Forecast\ForecastServiceInterface;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OpenWeatherMapService implements ForecastServiceInterface
{
    private float $latitude;
    private float $longitude;
    private string $apiKey;
    private string $baseUrl = 'https://api.openweathermap.org/data/2.5/';

    public function __construct()
    {
        $this->latitude = Setting::latitude();
        $this->longitude = Setting::longitude();
        $this->apiKey = Setting::getValue('openweathermap.api_key', '');
    }

    /**
     * Fetch weather forecast from OpenWeatherMap
     */
    public function fetchForecast(): ?array
    {
        if (empty($this->apiKey)) {
            Log::error('OpenWeatherMap API key not configured');
            return null;
        }

        $cacheKey = "openweathermap_forecast_{$this->latitude}_{$this->longitude}";
        
        return Cache::remember($cacheKey, 1800, function () {
            try {
                // Get 5-day forecast with 3-hour intervals
                $response = Http::get($this->baseUrl . 'forecast', [
                    'lat' => round($this->latitude, 4),
                    'lon' => round($this->longitude, 4),
                    'appid' => $this->apiKey,
                    'units' => 'metric', // Returns temperature in Celsius, wind in m/s
                ]);

                if ($response->successful()) {
                    return $this->parseForecast($response->json());
                }

                Log::error('OpenWeatherMap API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

            } catch (\Exception $e) {
                Log::error('OpenWeatherMap API exception', ['error' => $e->getMessage()]);
            }

            return null;
        });
    }

    /**
     * Parse OpenWeatherMap API response into simplified structure
     */
    private function parseForecast(array $data): ?array
    {
        $list = $data['list'] ?? [];
        $forecast = [];

        foreach ($list as $entry) {
            $time = $entry['dt_txt'] ?? date('Y-m-d\TH:i:s\Z', $entry['dt'] ?? time());
            $main = $entry['main'] ?? [];
            $wind = $entry['wind'] ?? [];
            $weather = $entry['weather'][0] ?? [];
            $clouds = $entry['clouds'] ?? [];
            $rain = $entry['rain'] ?? [];
            $snow = $entry['snow'] ?? [];

            // Convert wind speed from m/s to km/h
            $windSpeedMs = $wind['speed'] ?? null;
            $windSpeedKmh = $windSpeedMs !== null ? $windSpeedMs * 3.6 : null;

            // Get precipitation (rain or snow)
            $precipitation = 0;
            if (isset($rain['3h'])) {
                $precipitation = $rain['3h'];
            } elseif (isset($snow['3h'])) {
                $precipitation = $snow['3h'];
            }

            $forecast[] = [
                'time' => $time,
                'temperature' => $main['temp'] ?? null,
                'humidity' => $main['humidity'] ?? null,
                'pressure' => $main['pressure'] ?? null,
                'wind_speed' => $windSpeedKmh,
                'wind_direction' => $wind['deg'] ?? null,
                'cloud_cover' => $clouds['all'] ?? null,
                'symbol' => $this->mapIconToSymbol($weather['icon'] ?? '', $weather['main'] ?? ''),
                'precipitation_1h' => $precipitation / 3, // Convert 3h to 1h estimate
                'precipitation_6h' => $precipitation * 2, // Estimate for 6h
            ];
        }

        // Same reason as Weather Underground: an empty forecast cached under the
        // source key is truthy, so it blocks the fallback to the last good one.
        if ($forecast === []) {
            return null;
        }

        return [
            'updated_at' => $data['list'][0]['dt_txt'] ?? now()->toIso8601String(),
            'forecast' => $forecast,
        ];
    }

    /**
     * Map OpenWeatherMap icon codes to Yr.no symbol codes
     */
    private function mapIconToSymbol(string $icon, string $main): string
    {
        // OpenWeatherMap icons: 01d, 01n (clear), 02d, 02n (few clouds), etc.
        $iconMap = [
            '01d' => 'clearsky_day',
            '01n' => 'clearsky_night',
            '02d' => 'partlycloudy_day',
            '02n' => 'partlycloudy_night',
            '03d' => 'partlycloudy_day',
            '03n' => 'partlycloudy_night',
            '04d' => 'cloudy',
            '04n' => 'cloudy',
            '09d' => 'heavyrain',
            '09n' => 'heavyrain',
            '10d' => 'rain',
            '10n' => 'rain',
            '11d' => 'heavyrainandthunder',
            '11n' => 'heavyrainandthunder',
            '13d' => 'heavysnow',
            '13n' => 'heavysnow',
            '50d' => 'fog',
            '50n' => 'fog',
        ];

        if (isset($iconMap[$icon])) {
            return $iconMap[$icon];
        }

        // Fallback to main weather condition
        $mainMap = [
            'Clear' => 'clearsky_day',
            'Clouds' => 'cloudy',
            'Rain' => 'rain',
            'Drizzle' => 'lightrain',
            'Thunderstorm' => 'heavyrainandthunder',
            'Snow' => 'heavysnow',
            'Mist' => 'fog',
            'Fog' => 'fog',
            'Haze' => 'fog',
        ];

        return $mainMap[$main] ?? 'partlycloudy_day';
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

        // OpenWeatherMap provides 3-hour intervals, so we need to interpolate or use what we have
        $forecast = $data['forecast'] ?? [];
        
        // Return up to the requested number of hours (each entry is 3 hours, so divide by 3)
        $entries = min($hours, count($forecast) * 3);
        return array_slice($forecast, 0, (int)ceil($entries / 3));
    }

    /**
     * Get daily forecast summary
     */
    public function getDailyForecast(int $days = 7): array
    {
        $data = $this->fetchForecast();
        if (!$data) {
            return [];
        }

        $forecast = $data['forecast'] ?? [];
        $daily = [];

        foreach ($forecast as $entry) {
            $date = substr($entry['time'], 0, 10);
            
            if (!isset($daily[$date])) {
                $daily[$date] = [
                    'date' => $date,
                    'temp_high' => $entry['temperature'],
                    'temp_low' => $entry['temperature'],
                    'temps' => [],
                    'symbols' => [],
                    'precipitation' => 0,
                    'wind_speeds' => [],
                    'wind_directions' => [],
                ];
            }

            $daily[$date]['temps'][] = $entry['temperature'];
            if ($entry['temperature'] > $daily[$date]['temp_high']) {
                $daily[$date]['temp_high'] = $entry['temperature'];
            }
            if ($entry['temperature'] < $daily[$date]['temp_low']) {
                $daily[$date]['temp_low'] = $entry['temperature'];
            }
            
            if ($entry['symbol']) {
                $daily[$date]['symbols'][] = $entry['symbol'];
            }
            
            // OpenWeatherMap gives 3h precipitation, accumulate it
            $daily[$date]['precipitation'] += ($entry['precipitation_1h'] ?? 0) * 3;
            
            // Collect wind data
            if (isset($entry['wind_speed']) && $entry['wind_speed'] !== null) {
                $daily[$date]['wind_speeds'][] = $entry['wind_speed'];
            }
            if (isset($entry['wind_direction']) && $entry['wind_direction'] !== null) {
                $daily[$date]['wind_directions'][] = $entry['wind_direction'];
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
