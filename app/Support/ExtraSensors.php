<?php

namespace App\Support;

use App\Services\Weather\Normalization\UnitConverter;

/**
 * Newer Ecowitt sensors that have no columns of their own. They are stored
 * together in weather_readings.extra_sensors, in metric:
 *
 *   bgt, wbgt      WN38 black globe and wet bulb globe temperature, °C
 *   soil_ec        WH52 soil moisture (%), temperature (°C) and EC (µS/cm), per channel
 *   water_level    LDS depth and air gap (mm) and heater count, per channel
 *   probes         WN34 temperature probes, °C, per channel
 *   raining        whether a WS90's piezo gauge is wet right now
 *   wetness        wetness status, true when wet
 *   water_quality  WQT01: ec, tds, cod, toc, turbidity, co2, and leak/shortage/dirty alarms
 *
 * Push names are from aioecowitt, the parser Home Assistant uses. Cloud names
 * and units are from Ecowitt's API v3 doc. Push has no fields for wetness
 * status or the WQT01 yet.
 */
final class ExtraSensors
{
    /** @return array<string, mixed> */
    public static function fromPush(array $raw): array
    {
        $out = [];

        $out['bgt'] = self::celsius($raw['bgtc'] ?? null, false) ?? self::celsius($raw['bgt'] ?? null, true);
        $out['wbgt'] = self::celsius($raw['wbgtc'] ?? null, false) ?? self::celsius($raw['wbgt'] ?? null, true);

        for ($i = 1; $i <= 16; $i++) {
            $out['soil_ec'][$i] = self::filled([
                'moisture' => self::integer($raw["soil_ec_hum{$i}"] ?? null),
                'temperature' => self::celsius($raw["soil_ec_temp{$i}"] ?? null, true),
                'ec' => self::integer($raw["soil_ec{$i}"] ?? null),
            ]);
        }

        for ($i = 1; $i <= 8; $i++) {
            $out['probes'][$i] = self::celsius($raw["tf_ch{$i}c"] ?? null, false) ?? self::celsius($raw["tf_ch{$i}"] ?? null, true);
        }

        // A WS90 sends Wet/Dry, or 1/0 on some firmware.
        $state = strtolower(trim((string) ($raw['srain_piezo'] ?? '')));
        $out['raining'] = match ($state) {
            'wet', '1' => true,
            'dry', '0' => false,
            default => null,
        };

        for ($i = 1; $i <= 4; $i++) {
            $out['water_level'][$i] = self::filled([
                'depth' => self::number($raw["depth_ch{$i}"] ?? null),
                'air' => self::number($raw["air_ch{$i}"] ?? null),
                'heater' => self::integer($raw["ldsheat_ch{$i}"] ?? null),
            ]);
        }

        return self::clean($out);
    }

    /** @return array<string, mixed> */
    public static function fromCloud(array $data): array
    {
        $out = [];

        $globe = $data['black_globe_temperature'] ?? [];
        $out['bgt'] = self::cloudCelsius($globe['bgt'] ?? null);
        $out['wbgt'] = self::cloudCelsius($globe['wbgt'] ?? null);

        for ($i = 1; $i <= 16; $i++) {
            $channel = $data["soil_moisture_ec_ch{$i}"] ?? [];
            $out['soil_ec'][$i] = self::filled([
                'moisture' => self::integer(self::value($channel['soilmoisture'] ?? null)),
                'temperature' => self::cloudCelsius($channel['temperature'] ?? null),
                'ec' => self::integer(self::value($channel['ec'] ?? null)),
            ]);
        }

        for ($i = 1; $i <= 8; $i++) {
            $out['probes'][$i] = self::cloudCelsius($data["temp_ch{$i}"]['temperature'] ?? null);
        }

        for ($i = 1; $i <= 4; $i++) {
            $channel = $data["ch_lds{$i}"] ?? [];
            $out['water_level'][$i] = self::filled([
                'depth' => self::millimetres($channel["depth_ch{$i}"] ?? null),
                'air' => self::millimetres($channel["air_ch{$i}"] ?? null),
                'heater' => self::integer(self::value($channel["ldsheat_ch{$i}"] ?? null)),
            ]);
        }

        $wetness = self::integer(self::value($data['wetness_status']['rain_level'] ?? null));
        $out['wetness'] = $wetness === null ? null : $wetness === 1;

        $water = $data['wqt01'] ?? [];
        $alarm = fn (string $key) => ($v = self::integer(self::value($water[$key] ?? null))) === null ? null : $v === 1;
        $out['water_quality'] = self::filled([
            'ec' => self::number(self::value($water['ec'] ?? null)),
            'tds' => self::number(self::value($water['tds'] ?? null)),
            'cod' => self::number(self::value($water['cod'] ?? null)),
            'toc' => self::number(self::value($water['toc'] ?? null)),
            'turbidity' => self::number(self::value($water['turb'] ?? null)),
            'co2' => self::integer(self::value($water['co2'] ?? null)),
            'leak' => $alarm('leakalm'),
            'shortage' => $alarm('wateralm'),
            'dirty' => $alarm('dirtalm'),
        ]);

        return self::clean($out);
    }

    /**
     * Lines for the dashboard card. Labels are English source strings: the
     * payload is cached for every language and translated in the browser.
     *
     * @return list<array{label: string, channel: ?int, value: mixed, unit: string, kind: string}>
     */
    public static function lines(?array $sensors): array
    {
        $lines = [];
        $add = function (string $label, mixed $value, string $unit = '', string $kind = 'number', ?int $channel = null) use (&$lines): void {
            if ($value !== null) {
                $lines[] = ['label' => $label, 'channel' => $channel, 'value' => $value, 'unit' => $unit, 'kind' => $kind];
            }
        };

        $sensors ??= [];
        $add('Black globe', $sensors['bgt'] ?? null, '°C', 'temp');
        $add('WBGT', $sensors['wbgt'] ?? null, '°C', 'temp');

        foreach ($sensors['soil_ec'] ?? [] as $channel => $soil) {
            $add('Soil EC', $soil['ec'] ?? null, 'µS/cm', 'number', (int) $channel);
            $add('Soil moisture', $soil['moisture'] ?? null, '%', 'number', (int) $channel);
            $add('Soil temperature', $soil['temperature'] ?? null, '°C', 'temp', (int) $channel);
        }

        foreach ($sensors['probes'] ?? [] as $channel => $temperature) {
            $add('Temperature probe', $temperature, '°C', 'temp', (int) $channel);
        }

        foreach ($sensors['water_level'] ?? [] as $channel => $level) {
            $add('Water depth', $level['depth'] ?? null, 'mm', 'number', (int) $channel);
        }

        if (isset($sensors['raining'])) {
            $add('Rain sensor', $sensors['raining'] ? 'Wet' : 'Dry', '', 'state');
        }
        if (isset($sensors['wetness'])) {
            $add('Wetness', $sensors['wetness'] ? 'Wet' : 'Dry', '', 'state');
        }

        $water = $sensors['water_quality'] ?? [];
        $add('Water EC', $water['ec'] ?? null, 'µS/cm');
        $add('TDS', $water['tds'] ?? null, 'mg/L');
        $add('COD', $water['cod'] ?? null, 'mg/L');
        $add('TOC', $water['toc'] ?? null, 'mg/L');
        $add('Turbidity', $water['turbidity'] ?? null, 'NTU');
        $add('Water CO2', $water['co2'] ?? null, 'ppm');
        foreach (['leak' => 'Leak', 'shortage' => 'Water shortage', 'dirty' => 'Dirty'] as $key => $alarm) {
            if (!empty($water[$key])) {
                $add('Water quality alarm', $alarm, '', 'state');
            }
        }

        return $lines;
    }

    private static function value(mixed $entry): mixed
    {
        return is_array($entry) ? ($entry['value'] ?? null) : $entry;
    }

    private static function unit(mixed $entry): string
    {
        return is_array($entry) ? strtolower(trim((string) ($entry['unit'] ?? ''))) : '';
    }

    private static function number(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 1) : null;
    }

    private static function integer(mixed $value): ?int
    {
        return is_numeric($value) ? (int) round((float) $value) : null;
    }

    private static function celsius(mixed $value, bool $fahrenheit): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        return $fahrenheit ? UnitConverter::fahrenheitToCelsius((float) $value, 1) : round((float) $value, 1);
    }

    private static function cloudCelsius(mixed $entry): ?float
    {
        $unit = self::unit($entry);

        return self::celsius(self::value($entry), str_contains($unit, 'f') || str_contains($unit, '℉'));
    }

    private static function millimetres(mixed $entry): ?float
    {
        $value = self::value($entry);
        if (!is_numeric($value)) {
            return null;
        }

        $factor = match (self::unit($entry)) {
            'ft' => 304.8,
            'in' => 25.4,
            'm' => 1000.0,
            'cm' => 10.0,
            default => 1.0,
        };

        return round((float) $value * $factor, 1);
    }

    /** The fields that have a value, or null when none do. */
    private static function filled(array $fields): ?array
    {
        $fields = array_filter($fields, fn ($v) => $v !== null);

        return $fields === [] ? null : $fields;
    }

    private static function clean(array $out): array
    {
        foreach (['soil_ec', 'water_level', 'probes'] as $group) {
            $out[$group] = array_filter($out[$group] ?? [], fn ($v) => $v !== null);
        }

        return array_filter($out, fn ($v) => $v !== null && $v !== []);
    }
}
