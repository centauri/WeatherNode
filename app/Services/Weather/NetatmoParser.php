<?php

namespace App\Services\Weather;

use Carbon\Carbon;

/**
 * One Netatmo station from getstationsdata, as a WeatherNode reading.
 *
 * Netatmo answers in metric whatever the account's display units are: °C,
 * mbar, mm and km/h. The base station (NAMain) is indoors and has the
 * barometer. Modules: NAModule1 outdoor, NAModule2 wind, NAModule3 rain,
 * NAModule4 extra indoor. A module that lost contact has no dashboard_data.
 *
 * Field names: pyatmo's recorded getstationsdata response, and the Home
 * Assistant Netatmo integration for the units.
 */
class NetatmoParser
{
    public function parse(array $device): array
    {
        $main = $device['dashboard_data'] ?? [];
        $data = [];

        $this->copy($data, $main, [
            'Temperature' => 'temperature_indoor',
            'Humidity' => 'humidity_indoor',
            'Pressure' => 'pressure_rel',
            'AbsolutePressure' => 'pressure_abs',
            'CO2' => 'co2',
        ]);

        // The outdoor module's time when there is one, else the base station's.
        $time = $main['time_utc'] ?? null;

        $extra = 0;
        foreach ($device['modules'] ?? [] as $module) {
            $values = $module['dashboard_data'] ?? null;
            if (!is_array($values)) {
                continue;
            }

            $map = match ($module['type'] ?? '') {
                'NAModule1' => ['Temperature' => 'temperature', 'Humidity' => 'humidity'],
                'NAModule2' => [
                    'WindStrength' => 'wind_speed',
                    'GustStrength' => 'wind_gust',
                    'WindAngle' => 'wind_direction',
                    'max_wind_str' => 'wind_gust_max_daily',
                ],
                // sum_rain_24 is the rain since local midnight, as the Netatmo app shows it.
                'NAModule3' => ['sum_rain_1' => 'rain_hourly', 'sum_rain_24' => 'rain_daily'],
                'NAModule4' => $extra < 8
                    ? ['Temperature' => 'temp_' . ($extra + 1), 'Humidity' => 'humidity_' . ($extra + 1)]
                    : [],
                default => [],
            };
            if (($module['type'] ?? '') === 'NAModule4') {
                $extra++;
            }

            $this->copy($data, $values, $map);

            if (($module['type'] ?? '') === 'NAModule1' && isset($values['time_utc'])) {
                $time = $values['time_utc'];
            }
        }

        foreach (['temperature_indoor' => 'indoor_temperature', 'humidity_indoor' => 'indoor_humidity'] as $column => $alias) {
            if (isset($data[$column])) {
                $data[$alias] = $data[$column];
            }
        }
        $whole = ['humidity', 'humidity_indoor', 'indoor_humidity', 'wind_direction', 'co2'];
        for ($i = 1; $i <= 8; $i++) {
            $whole[] = "humidity_{$i}";
        }
        foreach ($whole as $column) {
            if (isset($data[$column])) {
                $data[$column] = (int) round($data[$column]);
            }
        }

        $data['recorded_at'] = is_numeric($time)
            ? Carbon::createFromTimestamp((int) $time, 'UTC')->setTimezone(config('app.timezone'))
            : now();

        if (!empty($device['station_name'])) {
            $data['station_model'] = mb_substr((string) $device['station_name'], 0, 50);
        }

        return $data;
    }

    private function copy(array &$data, array $values, array $map): void
    {
        foreach ($map as $field => $column) {
            if (isset($values[$field]) && is_numeric($values[$field])) {
                $data[$column] = (float) $values[$field];
            }
        }
    }
}
