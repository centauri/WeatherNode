<?php

declare(strict_types=1);

namespace App\Services\River;

/**
 * Parsing and trend logic for Keskkonnaagentuur (KAUR) gauge observations.
 *
 * Station codes are the WMO code prefixed with "ee".
 */
class KaurObservations
{
    public const CODE_PREFIX = 'ee';

    /** Hours of snapshots kept per station. */
    public const HISTORY_HOURS = 30;

    /** Hours between the readings compared for trend and status. */
    public const TREND_HOURS = 3;

    /**
     * Parse the observations XML into stations keyed by code.
     *
     * @return array{timestamp: int|null, stations: array<string, array>}
     */
    public static function parse(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        $root = simplexml_load_string($xml);
        libxml_use_internal_errors($previous);

        if ($root === false) {
            throw new \RuntimeException('KAUR observations: response is not XML');
        }

        $timestamp = isset($root['timestamp']) ? (int) $root['timestamp'] : null;
        $stations = [];

        foreach ($root->station as $node) {
            $wmo = trim((string) $node->wmocode);
            if ($wmo === '') {
                continue;
            }

            $stations[self::CODE_PREFIX.$wmo] = [
                'name' => trim((string) $node->name),
                'latitude' => self::number($node->latitude),
                'longitude' => self::number($node->longitude),
                'water_level_cm' => self::number($node->waterlevel),
                'water_level_eh2000_cm' => self::number($node->waterlevel_eh2000),
                'water_temp_c' => self::number($node->watertemperature),
            ];
        }

        return ['timestamp' => $timestamp, 'stations' => $stations];
    }

    /**
     * River and lake gauges: a level against the gauge zero and none against EH2000.
     * With $waterBodies given, only stations it names are kept.
     *
     * @param  array<string, array>  $stations  From parse()
     * @param  array<string, string>|null  $waterBodies  Code => river or lake name, null when unknown
     * @return array<string, array{name: string, river: string, latitude: float|null, longitude: float|null}>
     */
    public static function riverStations(array $stations, ?array $waterBodies = null): array
    {
        $catalog = [];

        foreach ($stations as $code => $station) {
            if ($station['water_level_cm'] === null || $station['water_level_eh2000_cm'] !== null) {
                continue;
            }
            if ($waterBodies !== null && ! isset($waterBodies[$code])) {
                continue;
            }

            $catalog[$code] = [
                'name' => $station['name'],
                'river' => $waterBodies[$code] ?? $station['name'],
                'latitude' => $station['latitude'],
                'longitude' => $station['longitude'],
            ];
        }

        uasort($catalog, fn ($a, $b) => [$a['river'], $a['name']] <=> [$b['river'], $b['name']]);

        return $catalog;
    }

    /**
     * Coastal gauges: a level against EH2000.
     *
     * @param  array<string, array>  $stations  From parse()
     * @return array<string, array{name: string, latitude: float|null, longitude: float|null}>
     */
    public static function seaLevelStations(array $stations): array
    {
        $catalog = [];

        foreach ($stations as $code => $station) {
            if ($station['water_level_eh2000_cm'] === null) {
                continue;
            }

            $catalog[$code] = [
                'name' => $station['name'],
                'latitude' => $station['latitude'],
                'longitude' => $station['longitude'],
            ];
        }

        uasort($catalog, fn ($a, $b) => $a['name'] <=> $b['name']);

        return $catalog;
    }

    /**
     * River or lake name from a hydrology API row, with "j." spelled out as "jõgi".
     */
    public static function waterBodyName(?string $waterBody, ?string $basin): ?string
    {
        $name = trim((string) $waterBody);
        if ($name === '') {
            $name = trim((string) $basin);
        }
        if ($name === '' || $name === '---') {
            return null;
        }

        return preg_replace('/ j\.$/u', ' jõgi', $name);
    }

    /**
     * The gauges closest to a point.
     *
     * @param  array<string, array>  $catalog  From riverStations()
     * @return string[]
     */
    public static function nearest(array $catalog, float $latitude, float $longitude, int $count = 3): array
    {
        $distances = [];
        foreach ($catalog as $code => $station) {
            if ($station['latitude'] === null || $station['longitude'] === null) {
                continue;
            }
            $distances[$code] = self::distanceKm($latitude, $longitude, $station['latitude'], $station['longitude']);
        }

        asort($distances);

        return array_slice(array_keys($distances), 0, $count);
    }

    /**
     * Add a snapshot's $field to the history, once per snapshot timestamp.
     *
     * @param  array<string, list<array{timestamp_unix: int, value: float}>>  $history
     * @param  array{timestamp: int|null, stations: array<string, array>}  $snapshot
     * @return array<string, list<array{timestamp_unix: int, value: float}>>
     */
    public static function remember(array $history, array $snapshot, string $field = 'water_level_cm'): array
    {
        $at = $snapshot['timestamp'];
        if ($at === null) {
            return $history;
        }
        $atMs = $at * 1000;
        $cutoffMs = $atMs - self::HISTORY_HOURS * 3_600_000;

        foreach ($snapshot['stations'] as $code => $station) {
            $level = $station[$field];
            if ($level === null) {
                continue;
            }

            $points = $history[$code] ?? [];
            $last = end($points);
            if ($last === false || $last['timestamp_unix'] < $atMs) {
                $points[] = ['timestamp_unix' => $atMs, 'value' => $level];
            }
            $history[$code] = $points;
        }

        foreach ($history as $code => $points) {
            $kept = array_values(array_filter($points, fn ($p) => $p['timestamp_unix'] >= $cutoffMs));
            if ($kept === []) {
                unset($history[$code]);
            } else {
                $history[$code] = $kept;
            }
        }

        return $history;
    }

    /**
     * Change in level over TREND_HOURS, or null without a reading that old.
     *
     * @param  list<array{timestamp_unix: int, value: float}>  $points
     */
    public static function change(array $points): ?float
    {
        $last = end($points);
        if ($last === false) {
            return null;
        }

        $windowStartMs = $last['timestamp_unix'] - self::TREND_HOURS * 3_600_000;
        $base = null;
        foreach ($points as $point) {
            if ($point['timestamp_unix'] <= $windowStartMs) {
                $base = $point;
            }
        }

        return $base === null ? null : $last['value'] - $base['value'];
    }

    public static function trend(?float $change): string
    {
        return match (true) {
            $change === null => 'steady',
            $change > 5 => 'rising',
            $change < -5 => 'falling',
            default => 'steady',
        };
    }

    public static function status(?float $change): string
    {
        return match (true) {
            $change === null => 'normal',
            $change > 30 => 'warning',
            $change > 5 => 'watch',
            default => 'normal',
        };
    }

    private static function number(\SimpleXMLElement $node): ?float
    {
        $text = trim((string) $node);

        return is_numeric($text) ? (float) $text : null;
    }

    private static function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
