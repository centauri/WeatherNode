<?php

namespace App\Services\Weather;

use App\Services\Weather\Normalization\UnitConverter;
use App\Support\BatteryStatus;
use App\Support\ExtraSensors;
use App\Support\RainGauge;
use Carbon\Carbon;

/**
 * Turns an Ecowitt cloud API v3 real_time response into the same columns
 * EcowittPushParser fills, so a station reads the same whichever source it
 * uses. Every reading arrives as {time, unit, value}; the unit is checked, so
 * an account that gets the API's imperial defaults is converted too.
 *
 * Field names and units: https://doc.ecowitt.net/web/#/apiv3en?page_id=17
 */
class EcowittCloudParser
{
    public function parse(array $data): array
    {
        $out = ['recorded_at' => now()];

        $outdoor = $data['outdoor'] ?? [];
        $this->put($out, 'temperature', self::temperature($outdoor['temperature'] ?? null));
        $this->put($out, 'feels_like', self::temperature($outdoor['feels_like'] ?? null));
        $this->put($out, 'dew_point', self::temperature($outdoor['dew_point'] ?? null));
        $this->put($out, 'humidity', self::integer($outdoor['humidity'] ?? null));

        $indoor = $data['indoor'] ?? [];
        $this->put($out, 'temperature_indoor', self::temperature($indoor['temperature'] ?? null));
        $this->put($out, 'indoor_temperature', $out['temperature_indoor'] ?? null);
        $this->put($out, 'humidity_indoor', self::integer($indoor['humidity'] ?? null));
        $this->put($out, 'indoor_humidity', $out['humidity_indoor'] ?? null);

        $pressure = $data['pressure'] ?? [];
        $this->put($out, 'pressure_rel', self::pressure($pressure['relative'] ?? null));
        $this->put($out, 'pressure_abs', self::pressure($pressure['absolute'] ?? null));

        $wind = $data['wind'] ?? [];
        $this->put($out, 'wind_speed', self::speed($wind['wind_speed'] ?? null));
        $this->put($out, 'wind_gust', self::speed($wind['wind_gust'] ?? null));
        $this->put($out, 'wind_direction', self::integer($wind['wind_direction'] ?? null));

        foreach (RainGauge::choose(RainGauge::fromCloud($data)) as $column => $mm) {
            $out[$column] = $mm;
        }

        $solar = $data['solar_and_uvi'] ?? [];
        $radiation = self::number($solar['solar'] ?? null);
        if ($radiation !== null && self::unit($solar['solar'] ?? null) === 'lux') {
            $this->put($out, 'lux', (int) round($radiation));
            $radiation = round($radiation / 126.7, 1);
        } elseif ($radiation !== null) {
            $this->put($out, 'lux', (int) round($radiation * 126.7));
        }
        $this->put($out, 'solar_radiation', $radiation);
        $this->put($out, 'uv_index', self::number($solar['uvi'] ?? null));

        // The time on the distance reading is when the last strike was detected.
        $lightning = $data['lightning'] ?? [];
        $distance = $lightning['distance'] ?? null;
        $km = self::number($distance);
        if ($km !== null && str_starts_with(self::unit($distance), 'mi')) {
            $km *= 1.609344;
        }
        $this->put($out, 'lightning_distance', $km === null ? null : (int) round($km));
        if ($km !== null && is_numeric($distance['time'] ?? null) && (int) $distance['time'] > 0) {
            $out['lightning_time'] = Carbon::createFromTimestamp((int) $distance['time']);
        }
        $count = self::integer($lightning['count'] ?? null);
        $this->put($out, 'lightning_count', $count);
        $this->put($out, 'lightning_count_daily', $count);

        for ($i = 1; $i <= 8; $i++) {
            $channel = $data["temp_and_humidity_ch{$i}"] ?? [];
            $this->put($out, "temp_{$i}", self::temperature($channel['temperature'] ?? null));
            $this->put($out, "humidity_{$i}", self::integer($channel['humidity'] ?? null));
            $this->put($out, "soil_moisture_{$i}", self::integer($data["soil_ch{$i}"]['soilmoisture'] ?? null));
            $this->put($out, "leaf_wetness_{$i}", self::integer($data["leaf_ch{$i}"]['leaf_wetness'] ?? null));
        }

        for ($i = 1; $i <= 4; $i++) {
            $this->put($out, "pm25_ch{$i}", self::number($data["pm25_ch{$i}"]['pm25'] ?? null));

            // 0 is normal and 1 is leaking; 2 means the sensor is offline,
            // which says nothing about a leak.
            $leak = self::integer($data['water_leak']["leak_ch{$i}"] ?? null);
            if ($leak === 0 || $leak === 1) {
                $out["leak_ch{$i}"] = $leak === 1;
            }
        }

        // The WH45 air quality combo, read as push reads its *_co2 fields.
        $this->put($out, 'co2', self::integer($data['co2_aqi_combo']['co2'] ?? $data['indoor_co2']['co2'] ?? null));
        $this->put($out, 'co2_avg_24h', self::integer(
            $data['co2_aqi_combo']['24_hours_average'] ?? $data['indoor_co2']['24_hours_average'] ?? null
        ));
        $this->put($out, 'co2_temp', self::temperature($data['t_rh_aqi_combo']['temperature'] ?? null));
        $this->put($out, 'co2_humidity', self::integer($data['t_rh_aqi_combo']['humidity'] ?? null));
        $this->put($out, 'pm10', self::number($data['pm10_aqi_combo']['pm10'] ?? null));
        if (!isset($out['pm25_ch1'])) {
            $this->put($out, 'pm25_ch1', self::number($data['pm25_aqi_combo']['pm25'] ?? null));
        }

        $extra = ExtraSensors::fromCloud($data);
        if ($extra !== []) {
            $out['extra_sensors'] = $extra;
        }

        $batteries = BatteryStatus::normalise($data['battery'] ?? []);
        if ($batteries !== []) {
            $out['battery_status'] = $batteries;
        }

        return $out;
    }

    private function put(array &$out, string $column, mixed $value): void
    {
        if ($value !== null) {
            $out[$column] = $value;
        }
    }

    private static function number(mixed $entry): ?float
    {
        $value = is_array($entry) ? ($entry['value'] ?? null) : $entry;

        return is_numeric($value) ? (float) $value : null;
    }

    private static function integer(mixed $entry): ?int
    {
        $value = self::number($entry);

        return $value === null ? null : (int) round($value);
    }

    private static function unit(mixed $entry): string
    {
        return is_array($entry) ? strtolower(trim((string) ($entry['unit'] ?? ''))) : '';
    }

    private static function temperature(mixed $entry): ?float
    {
        $value = self::number($entry);
        if ($value === null) {
            return null;
        }

        $unit = self::unit($entry);

        return str_contains($unit, 'f') || str_contains($unit, '℉')
            ? UnitConverter::fahrenheitToCelsius($value, 2)
            : round($value, 2);
    }

    private static function pressure(mixed $entry): ?float
    {
        $value = self::number($entry);

        return match (true) {
            $value === null => null,
            self::unit($entry) === 'inhg' => UnitConverter::inHgToHpa($value, 1),
            self::unit($entry) === 'mmhg' => round($value * 1.333224, 1),
            default => round($value, 1),
        };
    }

    private static function speed(mixed $entry): ?float
    {
        $value = self::number($entry);

        return match (true) {
            $value === null => null,
            self::unit($entry) === 'mph' => UnitConverter::mphToKmh($value, 1),
            self::unit($entry) === 'm/s' => UnitConverter::msToKmh($value, 1),
            in_array(self::unit($entry), ['knots', 'kn', 'kt'], true) => UnitConverter::knotsToKmh($value, 1),
            default => round($value, 1),
        };
    }
}
