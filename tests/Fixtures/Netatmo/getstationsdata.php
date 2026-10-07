<?php

/*
 * A getstationsdata response: one owned station with every module type, and
 * one public station marked as favorite. The owned station is the response
 * recorded in pyatmo (github.com/jabesq-org/pyatmo, fixtures/getstationsdata.json),
 * trimmed to the fields WeatherNode reads.
 */
return [
    'status' => 'ok',
    'time_server' => 1559413602,
    'body' => [
        'devices' => [
            [
                '_id' => '12:34:56:00:00:01',
                'favorite' => true,
                'read_only' => true,
                'station_name' => 'Neighbour (Garden)',
                'type' => 'NAMain',
                'place' => ['city' => 'Uitgeest', 'country' => 'NL', 'location' => [4.71, 52.53]],
                'dashboard_data' => ['time_utc' => 1559413000, 'Pressure' => 1012.0],
                'modules' => [
                    ['_id' => '02:00:00:00:00:01', 'type' => 'NAModule1', 'dashboard_data' => ['time_utc' => 1559413010, 'Temperature' => 15.5, 'Humidity' => 70]],
                ],
            ],
            [
                '_id' => '12:34:56:37:11:ca',
                'station_name' => 'MyStation',
                'module_name' => 'NetatmoIndoor',
                'type' => 'NAMain',
                'place' => ['city' => 'Frankfurt', 'country' => 'DE', 'timezone' => 'Europe/Berlin', 'location' => [52.516263, 13.377726]],
                'dashboard_data' => [
                    'time_utc' => 1559413171, 'Temperature' => 24.6, 'CO2' => 749, 'Humidity' => 36, 'Noise' => 37,
                    'Pressure' => 1017.3, 'AbsolutePressure' => 939.7, 'min_temp' => 23.4, 'max_temp' => 25.6,
                    'temp_trend' => 'stable', 'pressure_trend' => 'down',
                ],
                'modules' => [
                    ['_id' => '12:34:56:36:fc:de', 'type' => 'NAModule1', 'battery_percent' => 87, 'reachable' => true,
                     'dashboard_data' => ['time_utc' => 1559413157, 'Temperature' => 28.6, 'Humidity' => 24, 'min_temp' => 16.9, 'max_temp' => 30.3]],
                    ['_id' => '12:34:56:07:bb:3e', 'type' => 'NAModule4', 'battery_percent' => 83, 'reachable' => true,
                     'dashboard_data' => ['time_utc' => 1559413125, 'Temperature' => 28, 'CO2' => 503, 'Humidity' => 26]],
                    ['_id' => '12:34:56:07:bb:0e', 'type' => 'NAModule4', 'battery_percent' => 79, 'reachable' => true,
                     'dashboard_data' => ['time_utc' => 1559413093, 'Temperature' => 26.4, 'CO2' => 451, 'Humidity' => 31]],
                    ['_id' => '12:34:56:03:1b:e4', 'type' => 'NAModule2', 'battery_percent' => 85, 'reachable' => true,
                     'dashboard_data' => ['time_utc' => 1559413170, 'WindStrength' => 4, 'WindAngle' => 217, 'GustStrength' => 9, 'GustAngle' => 206, 'max_wind_str' => 21, 'max_wind_angle' => 217]],
                    ['_id' => '12:34:56:05:51:20', 'type' => 'NAModule3', 'battery_percent' => 93, 'reachable' => true,
                     'dashboard_data' => ['time_utc' => 1559413170, 'Rain' => 0.2, 'sum_rain_1' => 0.5, 'sum_rain_24' => 3.1]],
                    ['_id' => '12:34:56:05:51:21', 'type' => 'NAModule4', 'reachable' => false],
                ],
            ],
        ],
        'user' => ['administrative' => ['unit' => 0, 'windunit' => 0, 'pressureunit' => 0]],
    ],
];
