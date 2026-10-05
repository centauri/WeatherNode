<?php

/**
 * Real Ecowitt payloads, for testing against what stations actually send.
 *
 * The two push payloads are from aioecowitt's misc/example.data, the parser
 * Home Assistant uses (Apache-2.0):
 * https://github.com/home-assistant-libs/aioecowitt/blob/main/misc/example.data
 *
 * The cloud payload is reconstructed, not captured: the shape (every reading
 * wrapped as time, unit and value; a WS90's rain in rainfall_piezo; battery
 * names like lightning_sensor) is taken from CumulusMX's Ecowitt cloud API
 * models, and the zeroed tipping-bucket group from a WS90 owner's device
 * notes in sstjean/ecowitt-dashboard. A captured response from a WS90 owner
 * would be worth swapping in.
 */
return [
    // GW2000A with a WS90 and nothing else. No rainratein at all: its only
    // rain gauge is the piezo.
    'push_gw2000a_ws90' => [
        'PASSKEY' => '345544D8EAF42E1B8824A86D8250D5A3',
        'stationtype' => 'GW2000A_V2.1.5',
        'runtime' => '188738',
        'dateutc' => '2022-07-15 15:26:14',
        'tempinf' => '78.80',
        'humidityin' => '53',
        'baromrelin' => '27.885',
        'baromabsin' => '27.885',
        'tempf' => '87.08',
        'humidity' => '34',
        'winddir' => '289',
        'windspeedmph' => '2.91',
        'windgustmph' => '3.13',
        'maxdailygust' => '12.53',
        'solarradiation' => '530.62',
        'uv' => '4',
        'rrain_piezo' => '0.000',
        'erain_piezo' => '0.000',
        'hrain_piezo' => '0.000',
        'drain_piezo' => '0.000',
        'wrain_piezo' => '0.000',
        'mrain_piezo' => '0.000',
        'yrain_piezo' => '0.000',
        'ws90cap_volt' => '5.4',
        'ws90_ver' => '119',
        'wh90batt' => '2.74',
        'freq' => '868M',
        'model' => 'GW2000A',
    ],

    // WN1980B with a WH40 rain gauge, WH68, WH45 CO2 and a leaf sensor. Shows
    // all three battery kinds side by side: wh26batt and batt1 are flags,
    // co2_batt is a level (6 means it runs on mains), and wh68batt, wh40batt,
    // leaf_batt1 and console_batt are volts.
    'push_wn1980b_mixed' => [
        'stationtype' => 'WN1980B_V1.2.3',
        'runtime' => '1121860',
        'dateutc' => '2024-01-26 16:50:11',
        'tempinf' => '69.44',
        'humidityin' => '47',
        'baromrelin' => '30.215',
        'baromabsin' => '29.111',
        'tempf' => '63.14',
        'humidity' => '93',
        'winddir' => '56',
        'windspeedmph' => '0.45',
        'windgustmph' => '1.12',
        'maxdailygust' => '5.82',
        'solarradiation' => '171.59',
        'uv' => '1',
        'rainratein' => '0.000',
        'eventrainin' => '1.035',
        'hourlyrainin' => '0.000',
        'dailyrainin' => '0.000',
        'weeklyrainin' => '1.118',
        'monthlyrainin' => '4.945',
        'yearlyrainin' => '4.945',
        'totalrainin' => '4.945',
        'temp1f' => '64.76',
        'humidity1' => '59',
        'co2' => '882',
        'console_batt' => '2.51',
        'wh68batt' => '1.84',
        'wh40batt' => '1.2',
        'wh26batt' => '0',
        'batt1' => '0',
        'co2_batt' => '6',
        'leaf_batt1' => '1.36',
        'freq' => '915M',
        'model' => 'WN1980B_V1.2.3',
        'interval' => '60',
    ],

    // Cloud API real_time for a WS90 with a WH57 lightning sensor, during
    // light rain: the tipping-bucket group is present and reads zero, the
    // piezo group has the rain. This is dft601's station in #131 and #132.
    'cloud_ws90_lightning_raining' => [
        'outdoor' => [
            'temperature' => ['time' => '1759400000', 'unit' => '℃', 'value' => '12.4'],
            'humidity' => ['time' => '1759400000', 'unit' => '%', 'value' => '91'],
        ],
        'rainfall' => [
            'rain_rate' => ['time' => '1759400000', 'unit' => 'mm/hr', 'value' => '0.0'],
            'daily' => ['time' => '1759400000', 'unit' => 'mm', 'value' => '0.0'],
            'event' => ['time' => '1759400000', 'unit' => 'mm', 'value' => '0.0'],
            'hourly' => ['time' => '1759400000', 'unit' => 'mm', 'value' => '0.0'],
            'weekly' => ['time' => '1759400000', 'unit' => 'mm', 'value' => '0.0'],
            'monthly' => ['time' => '1759400000', 'unit' => 'mm', 'value' => '0.0'],
            'yearly' => ['time' => '1759400000', 'unit' => 'mm', 'value' => '0.0'],
        ],
        'rainfall_piezo' => [
            'rain_rate' => ['time' => '1759400000', 'unit' => 'mm/hr', 'value' => '0.8'],
            'daily' => ['time' => '1759400000', 'unit' => 'mm', 'value' => '2.3'],
            'event' => ['time' => '1759400000', 'unit' => 'mm', 'value' => '2.3'],
            'hourly' => ['time' => '1759400000', 'unit' => 'mm', 'value' => '0.6'],
            'weekly' => ['time' => '1759400000', 'unit' => 'mm', 'value' => '4.1'],
            'monthly' => ['time' => '1759400000', 'unit' => 'mm', 'value' => '4.1'],
            'yearly' => ['time' => '1759400000', 'unit' => 'mm', 'value' => '612.7'],
        ],
        'battery' => [
            'haptic_array_battery' => ['time' => '1759400000', 'unit' => 'V', 'value' => '3.12'],
            'haptic_array_capacitor' => ['time' => '1759400000', 'unit' => 'V', 'value' => '5.3'],
            'lightning_sensor' => ['time' => '1759400000', 'unit' => '', 'value' => '0'],
        ],
    ],
];
