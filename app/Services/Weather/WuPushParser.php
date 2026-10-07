<?php

namespace App\Services\Weather;

use App\Services\Weather\Normalization\UnitConverter;
use Carbon\Carbon;

/**
 * One upload in the Weather Underground format, as WeeWX, Meteobridge and
 * many consoles send it. Everything arrives in US units.
 *
 * Fields: support.weather.com "PWS Upload Protocol", and WeeWX restx.py
 * (AmbientThread._FORMATS), which is what most senders copy.
 */
class WuPushParser
{
    public function parse(array $raw): array
    {
        $data = ['recorded_at' => $this->recordedAt($raw['dateutc'] ?? null)];

        $fahrenheit = [
            'tempf' => 'temperature',
            'dewptf' => 'dew_point',
            'indoortempf' => 'temperature_indoor',
            'soiltempf' => 'soil_temp_1',
            'soiltemp2f' => 'soil_temp_2',
            'soiltemp3f' => 'soil_temp_3',
            'soiltemp4f' => 'soil_temp_4',
        ];
        foreach ($fahrenheit as $field => $column) {
            if (($value = $this->number($raw, $field)) !== null) {
                $data[$column] = UnitConverter::fahrenheitToCelsius($value, 2);
            }
        }

        $mph = [
            'windspeedmph' => 'wind_speed',
            'windgustmph' => 'wind_gust',
            'windspdmph_avg10m' => 'wind_speed_avg_10m',
        ];
        foreach ($mph as $field => $column) {
            if (($value = $this->number($raw, $field)) !== null) {
                $data[$column] = UnitConverter::mphToKmh($value, 1);
            }
        }

        // WU's rainin is the rain of the last hour, not a rate.
        $inches = [
            'rainin' => 'rain_hourly',
            'rainratein' => 'rain_rate',
            'dailyrainin' => 'rain_daily',
            'weeklyrainin' => 'rain_weekly',
            'monthlyrainin' => 'rain_monthly',
            'yearlyrainin' => 'rain_yearly',
        ];
        foreach ($inches as $field => $column) {
            if (($value = $this->number($raw, $field)) !== null) {
                $data[$column] = UnitConverter::inchesToMm($value, 2);
            }
        }

        $whole = [
            'humidity' => 'humidity',
            'indoorhumidity' => 'humidity_indoor',
            'winddir' => 'wind_direction',
            'winddir_avg10m' => 'wind_direction_avg_10m',
            'soilmoisture' => 'soil_moisture_1',
            'soilmoisture2' => 'soil_moisture_2',
            'soilmoisture3' => 'soil_moisture_3',
            'soilmoisture4' => 'soil_moisture_4',
            'leafwetness' => 'leaf_wetness_1',
            'leafwetness2' => 'leaf_wetness_2',
        ];
        foreach ($whole as $field => $column) {
            if (($value = $this->number($raw, $field)) !== null) {
                $data[$column] = (int) round($value);
            }
        }

        if (isset($data['temperature_indoor'])) {
            $data['indoor_temperature'] = $data['temperature_indoor'];
        }
        if (isset($data['humidity_indoor'])) {
            $data['indoor_humidity'] = $data['humidity_indoor'];
        }

        if (($value = $this->number($raw, 'baromin')) !== null) {
            $data['pressure_rel'] = UnitConverter::inHgToHpa($value, 1);
        }

        if (($value = $this->number($raw, 'solarradiation')) !== null) {
            $data['solar_radiation'] = $value;
            $data['lux'] = (int) round($value * 126.7);
        }

        if (($value = $this->number($raw, 'UV') ?? $this->number($raw, 'uv')) !== null) {
            $data['uv_index'] = $value;
        }

        // PHP turns the dot in AqPM2.5 into an underscore.
        if (($value = $this->number($raw, 'AqPM2_5') ?? $this->number($raw, 'AqPM2.5')) !== null) {
            $data['pm25_ch1'] = $value;
        }
        if (($value = $this->number($raw, 'AqPM10')) !== null) {
            $data['pm10'] = $value;
        }

        if (isset($raw['softwaretype']) && trim((string) $raw['softwaretype']) !== '') {
            $data['station_type'] = mb_substr(trim((string) $raw['softwaretype']), 0, 50);
        }

        return $data;
    }

    private function recordedAt(mixed $value): Carbon
    {
        $value = trim(urldecode((string) $value));
        if ($value === '' || strtolower($value) === 'now') {
            return now();
        }

        try {
            return Carbon::parse($value, 'UTC')->setTimezone(config('app.timezone'));
        } catch (\Exception $e) {
            return now();
        }
    }

    /** WU senders use -9999 for a sensor that has no value. */
    private function number(array $raw, string $field): ?float
    {
        if (!isset($raw[$field]) || !is_numeric($raw[$field])) {
            return null;
        }

        $value = (float) $raw[$field];

        return $value <= -999 ? null : $value;
    }
}
