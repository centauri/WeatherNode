<?php

namespace App\Services\Weather;

use App\Services\Weather\Normalization\UnitConverter;
use Carbon\Carbon;

class EcowittPushParser
{
    public function parse(array $raw): array
    {
        $data = [];

        if (isset($raw['dateutc'])) {
            try {
                // Ecowitt sends UTC timestamps (dateutc). Parse as UTC and convert to app timezone.
                $data['recorded_at'] = Carbon::parse(urldecode($raw['dateutc']), 'UTC')
                    ->setTimezone(config('app.timezone'));
            } catch (\Exception $e) {
                $data['recorded_at'] = now();
            }
        } else {
            $data['recorded_at'] = now();
        }

        if (isset($raw['tempf'])) {
            $data['temperature'] = UnitConverter::fahrenheitToCelsius((float) $raw['tempf'], 2);
        }

        if (isset($raw['tempinf'])) {
            $data['temperature_indoor'] = UnitConverter::fahrenheitToCelsius((float) $raw['tempinf'], 2);
            $data['indoor_temperature'] = $data['temperature_indoor'];
        }

        for ($i = 1; $i <= 8; $i++) {
            if (isset($raw["temp{$i}f"])) {
                $data["temp_{$i}"] = UnitConverter::fahrenheitToCelsius((float) $raw["temp{$i}f"], 2);
            }
        }

        if (isset($raw['humidity'])) {
            $data['humidity'] = (int) $raw['humidity'];
        }
        if (isset($raw['humidityin'])) {
            $data['humidity_indoor'] = (int) $raw['humidityin'];
            $data['indoor_humidity'] = $data['humidity_indoor'];
        }

        for ($i = 1; $i <= 8; $i++) {
            if (isset($raw["humidity{$i}"])) {
                $data["humidity_{$i}"] = (int) $raw["humidity{$i}"];
            }
        }

        if (isset($raw['baromrelin'])) {
            $data['pressure_rel'] = UnitConverter::inHgToHpa((float) $raw['baromrelin'], 1);
        }
        if (isset($raw['baromabsin'])) {
            $data['pressure_abs'] = UnitConverter::inHgToHpa((float) $raw['baromabsin'], 1);
        }

        if (isset($raw['windspeedmph'])) {
            $data['wind_speed'] = UnitConverter::mphToKmh((float) $raw['windspeedmph'], 1);
        }
        if (isset($raw['windgustmph'])) {
            $data['wind_gust'] = UnitConverter::mphToKmh((float) $raw['windgustmph'], 1);
        }
        if (isset($raw['maxdailygust'])) {
            $data['wind_gust_max_daily'] = UnitConverter::mphToKmh((float) $raw['maxdailygust'], 1);
        }
        if (isset($raw['winddir'])) {
            $data['wind_direction'] = (int) $raw['winddir'];
        }
        if (isset($raw['windspdmph_avg10m'])) {
            $data['wind_speed_avg_10m'] = UnitConverter::mphToKmh((float) $raw['windspdmph_avg10m'], 1);
        }
        if (isset($raw['winddir_avg10m'])) {
            $data['wind_direction_avg_10m'] = (int) $raw['winddir_avg10m'];
        }

        if (isset($raw['rainratein'])) {
            $data['rain_rate'] = UnitConverter::inchesToMm((float) $raw['rainratein'], 2);
        }
        if (isset($raw['hourlyrainin'])) {
            $data['rain_hourly'] = UnitConverter::inchesToMm((float) $raw['hourlyrainin'], 2);
        }
        if (isset($raw['dailyrainin'])) {
            $data['rain_daily'] = UnitConverter::inchesToMm((float) $raw['dailyrainin'], 2);
        }
        if (isset($raw['weeklyrainin'])) {
            $data['rain_weekly'] = UnitConverter::inchesToMm((float) $raw['weeklyrainin'], 2);
        }
        if (isset($raw['monthlyrainin'])) {
            $data['rain_monthly'] = UnitConverter::inchesToMm((float) $raw['monthlyrainin'], 2);
        }
        if (isset($raw['yearlyrainin'])) {
            $data['rain_yearly'] = UnitConverter::inchesToMm((float) $raw['yearlyrainin'], 2);
        }
        if (isset($raw['eventrainin'])) {
            $data['rain_event'] = UnitConverter::inchesToMm((float) $raw['eventrainin'], 2);
        }
        if (isset($raw['totalrainin'])) {
            $data['rain_total'] = UnitConverter::inchesToMm((float) $raw['totalrainin'], 2);
        }

        if (isset($raw['solarradiation'])) {
            $data['solar_radiation'] = (float) $raw['solarradiation'];
            $data['lux'] = (int) round((float) $raw['solarradiation'] * 126.7);
        }
        if (isset($raw['uv'])) {
            $data['uv_index'] = (float) $raw['uv'];
        }

        if (isset($raw['lightning'])) {
            $data['lightning_distance'] = (int) $raw['lightning'];
        }
        if (isset($raw['lightning_num'])) {
            $data['lightning_count'] = (int) $raw['lightning_num'];
            $data['lightning_count_daily'] = (int) $raw['lightning_num'];
        }
        if (isset($raw['lightning_time']) && $raw['lightning_time'] > 0) {
            $data['lightning_time'] = Carbon::createFromTimestamp((int) $raw['lightning_time']);
        }

        for ($i = 1; $i <= 8; $i++) {
            if (isset($raw["soilmoisture{$i}"])) {
                $data["soil_moisture_{$i}"] = (int) $raw["soilmoisture{$i}"];
            }
        }
        for ($i = 1; $i <= 8; $i++) {
            if (isset($raw["soiltemp{$i}f"])) {
                $data["soil_temp_{$i}"] = UnitConverter::fahrenheitToCelsius((float) $raw["soiltemp{$i}f"], 2);
            }
        }

        for ($i = 1; $i <= 8; $i++) {
            if (isset($raw["leafwetness{$i}"])) {
                $data["leaf_wetness_{$i}"] = (int) $raw["leafwetness{$i}"];
            }
            if (isset($raw["leaf_wetness{$i}"])) {
                $data["leaf_wetness_{$i}"] = (int) $raw["leaf_wetness{$i}"];
            }
        }

        for ($i = 1; $i <= 4; $i++) {
            if (isset($raw["pm25_ch{$i}"])) {
                $data["pm25_ch{$i}"] = (float) $raw["pm25_ch{$i}"];
            }
            if (isset($raw["pm25_avg_24h_ch{$i}"])) {
                $data["pm25_avg_24h_ch{$i}"] = (float) $raw["pm25_avg_24h_ch{$i}"];
            }
        }

        if (isset($raw['co2'])) {
            $data['co2'] = (int) $raw['co2'];
        }
        if (isset($raw['co2_24h'])) {
            $data['co2_avg_24h'] = (int) $raw['co2_24h'];
        }
        if (isset($raw['tf_co2'])) {
            $data['co2_temp'] = UnitConverter::fahrenheitToCelsius((float) $raw['tf_co2'], 2);
        }
        if (isset($raw['humi_co2'])) {
            $data['co2_humidity'] = (int) $raw['humi_co2'];
        }
        if (isset($raw['pm25_co2']) && !isset($data['pm25_ch1'])) {
            $data['pm25_ch1'] = (float) $raw['pm25_co2'];
        }
        if (isset($raw['pm10'])) {
            $data['pm10'] = (float) $raw['pm10'];
        }
        if (isset($raw['pm10_24h'])) {
            $data['pm10_avg_24h'] = (float) $raw['pm10_24h'];
        }
        if (isset($raw['pm10_co2']) && !isset($data['pm10'])) {
            $data['pm10'] = (float) $raw['pm10_co2'];
        }
        if (isset($raw['pm10_24h_co2']) && !isset($data['pm10_avg_24h'])) {
            $data['pm10_avg_24h'] = (float) $raw['pm10_24h_co2'];
        }

        for ($i = 1; $i <= 4; $i++) {
            if (isset($raw["leak_ch{$i}"])) {
                $data["leak_ch{$i}"] = (bool) $raw["leak_ch{$i}"];
            }
            if (isset($raw["leakage{$i}"])) {
                $data["leak_ch{$i}"] = (bool) $raw["leakage{$i}"];
            }
        }

        $batteries = [];
        $batteryFields = [
            'wh26batt', 'wh40batt', 'wh57batt', 'wh65batt', 'wh68batt', 'wh80batt',
            'batt1', 'batt2', 'batt3', 'batt4', 'batt5', 'batt6', 'batt7', 'batt8',
            'soilbatt1', 'soilbatt2', 'soilbatt3', 'soilbatt4', 'soilbatt5', 'soilbatt6', 'soilbatt7', 'soilbatt8',
            'pm25batt1', 'pm25batt2', 'pm25batt3', 'pm25batt4',
            'leakbatt1', 'leakbatt2', 'leakbatt3', 'leakbatt4',
            'co2_batt', 'leafbatt1', 'leafbatt2', 'leafbatt3', 'leafbatt4',
        ];
        foreach ($batteryFields as $field) {
            if (isset($raw[$field])) {
                $batteries[$field] = (int) $raw[$field];
            }
        }
        foreach (EcowittService::WS90_VOLTAGE_FIELDS as $field => $key) {
            if (isset($raw[$field]) && is_numeric($raw[$field])) {
                $batteries[$key] = round((float) $raw[$field], 2);
            }
        }
        if (!empty($batteries)) {
            $data['battery_status'] = $batteries;
        }

        if (isset($raw['stationtype'])) {
            $data['station_type'] = $raw['stationtype'];
        }
        if (isset($raw['model'])) {
            $data['station_model'] = $raw['model'];
        }
        if (isset($raw['runtime'])) {
            $data['station_runtime'] = (int) $raw['runtime'];
        }
        if (isset($raw['freq'])) {
            $data['station_freq'] = $raw['freq'];
        }

        return $data;
    }
}
