<?php

namespace App\Services\Weather\LocalFiles;

use App\Services\Weather\Normalization\UnitConverter;
use Carbon\Carbon;

class RealtimeTxtParser
{
    public function parse(string $filePath, string $format): ?array
    {
        if (!is_file($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        return $this->parseContent($content, $format);
    }

    public function parseContent(string $content, string $format): ?array
    {
        if ($format === 'weathercat') {
            $content = str_replace([' PM ', ' AM ', ' am ', ' pm '], ['_PM ', '_AM ', '_am ', '_pm '], $content);
        }

        $parts = preg_split('/\s+/', trim($content));
        if (!$parts || count($parts) < 46) {
            return null;
        }

        $tempUnits = strtoupper($parts[14] ?? 'C');
        $baroUnits = strtolower($parts[15] ?? 'hpa');
        $windUnits = strtolower($parts[13] ?? 'km/h');
        $rainUnits = strtolower($parts[16] ?? 'mm');

        $recordedAt = $this->parseDateTime($parts[0] ?? '', $parts[1] ?? '') ?? now();

        $temperature = $this->convertTemp($this->getFloat($parts, 2), $tempUnits);
        $humidity = $this->getFloat($parts, 3);
        $dewPoint = $this->convertTemp($this->getFloat($parts, 4), $tempUnits);

        $windSpeed = $this->convertWind($this->getFloat($parts, 6), $windUnits);
        $windAvg = $this->convertWind($this->getFloat($parts, 5), $windUnits);
        $windGust = $this->convertWind($this->getFloat($parts, 40), $windUnits);
        $windGustMaxDaily = $this->convertWind($this->getFloat($parts, 32), $windUnits);
        $windDirectionAvg10m = $this->getInt($parts, 46);

        $rainRate = $this->convertRain($this->getFloat($parts, 8), $rainUnits);
        $rainToday = $this->convertRain($this->getFloat($parts, 9), $rainUnits);
        $rainMonth = $this->convertRain($this->getFloat($parts, 19), $rainUnits);
        $rainYear = $this->convertRain($this->getFloat($parts, 20), $rainUnits);
        $rainHour = $this->convertRain($this->getFloat($parts, 47), $rainUnits);

        $pressure = $this->convertPressure($this->getFloat($parts, 10), $baroUnits);

        $uv = $this->normalizeUv($this->getFloat($parts, 43));
        $solar = $this->getFloat($parts, 45);
        // Cumulus realtime.txt field 56 (1-based) = SunshineHours so far today.
        $solarHours = $this->getFloat($parts, 55);

        $data = [
            'recorded_at' => $recordedAt,
            'temperature' => $temperature,
            'humidity' => $humidity,
            'dew_point' => $dewPoint,
            'pressure_rel' => $pressure,
            'wind_speed' => $windSpeed,
            'wind_speed_avg_10m' => $windAvg,
            'wind_gust' => $windGust,
            'wind_gust_max_daily' => $windGustMaxDaily,
            'wind_direction' => $this->getInt($parts, 7),
            'wind_direction_avg_10m' => $windDirectionAvg10m,
            'rain_rate' => $rainRate,
            'rain_daily' => $rainToday,
            'rain_monthly' => $rainMonth,
            'rain_yearly' => $rainYear,
            'rain_hourly' => $rainHour,
            'temperature_indoor' => $this->convertTemp($this->getFloat($parts, 22), $tempUnits),
            'humidity_indoor' => $this->getFloat($parts, 23),
            'indoor_temperature' => $this->convertTemp($this->getFloat($parts, 22), $tempUnits),
            'indoor_humidity' => $this->getFloat($parts, 23),
            'wind_chill' => $this->convertTemp($this->getFloat($parts, 24), $tempUnits),
            'heat_index' => $this->convertTemp($this->getFloat($parts, 41), $tempUnits),
            'uv_index' => $uv,
            'solar_radiation' => $solar,
            'solar_hours' => $solarHours,
        ];

        if ($solar !== null) {
            $data['lux'] = (int) round($solar * 126.7);
        }

        return array_filter($data, static fn ($value) => $value !== null);
    }

    private function parseDateTime(string $date, string $time): ?Carbon
    {
        if ($date === '' || $time === '') {
            return null;
        }

        $date = trim($date);
        $time = trim($time);

        if (preg_match('/^\d{4}[-\/]/', $date)) {
            try {
                return Carbon::parse("{$date} {$time}");
            } catch (\Exception $e) {
                return null;
            }
        }

        $separator = str_contains($date, '-') ? '-' : '/';
        [$a, $b, $c] = array_pad(explode($separator, $date), 3, '');

        if ($c === '') {
            return null;
        }

        $year = strlen($c) === 2 ? (int) ('20' . $c) : (int) $c;
        $first = (int) $a;
        $second = (int) $b;

        if ($first > 12) {
            $day = $first;
            $month = $second;
        } elseif ($second > 12) {
            $month = $first;
            $day = $second;
        } else {
            $day = $first;
            $month = $second;
        }

        [$hour, $minute, $secondTime] = array_pad(explode(':', $time), 3, '0');

        return Carbon::create($year, $month, $day, (int) $hour, (int) $minute, (int) $secondTime);
    }

    private function normalizeUv(?float $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if ($value > 10) {
            return round($value / 10, 1);
        }

        return $value;
    }

    private function convertTemp(?float $value, string $units): ?float
    {
        if ($value === null) {
            return null;
        }

        if (str_contains($units, 'F')) {
            return UnitConverter::fahrenheitToCelsius($value, 1);
        }

        return $value;
    }

    private function convertPressure(?float $value, string $units): ?float
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtolower($units);
        if ($normalized === 'in' || $normalized === 'inhg') {
            return UnitConverter::inHgToHpa($value, 1);
        }

        return $value;
    }

    private function convertWind(?float $value, string $units): ?float
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtolower($units);
        if ($normalized === 'mph') {
            return UnitConverter::mphToKmh($value, 1);
        }
        if ($normalized === 'm/s' || $normalized === 'ms') {
            return UnitConverter::msToKmh($value, 1);
        }
        if ($normalized === 'kts' || $normalized === 'kt') {
            return UnitConverter::knotsToKmh($value, 1);
        }

        return $value;
    }

    private function convertRain(?float $value, string $units): ?float
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtolower($units);
        if ($normalized === 'in' || $normalized === 'inch' || $normalized === 'inches') {
            return UnitConverter::inchesToMm($value, 2);
        }

        return $value;
    }

    private function getFloat(array $parts, int $index): ?float
    {
        if (!array_key_exists($index, $parts)) {
            return null;
        }

        $value = trim((string) $parts[$index]);
        if ($value === '' || $value === '---' || $value === '--' || strtolower($value) === 'n/a') {
            return null;
        }

        return (float) str_replace(',', '.', $value);
    }

    private function getInt(array $parts, int $index): ?int
    {
        $value = $this->getFloat($parts, $index);
        return $value === null ? null : (int) round($value);
    }
}
