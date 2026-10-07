<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The one place that knows how an Ecowitt sensor reports its battery.
 *
 * Sensors use one of three kinds, and which one depends on the sensor:
 *
 *   flag   0 is fine, 1 is low (shown as Good or Low)
 *   level  0 to 5, higher is better; 6 means it runs on mains power
 *   volts  a real voltage, low below a threshold for that battery
 *
 * The cloud API names the same sensors differently from the push protocol,
 * and wraps every value as time/unit/value. The kind travels with the name it
 * arrived under, and nothing is renamed across the two.
 *
 * Push kinds are from aioecowitt, the parser Home Assistant uses. Cloud names
 * and kinds are from Ecowitt's API v3 doc
 * (https://doc.ecowitt.net/web/#/apiv3en?page_id=17), checked against a
 * captured response. The voltage thresholds are from CumulusMX.
 *
 * Every Ecowitt path stores through fromPush() or normalise(), and both
 * dashboards read through classify(), so there is one answer everywhere.
 */
final class BatteryStatus
{
    /** One AA or similar cell. */
    private const SINGLE_CELL = 1.2;

    /** Two cells in series, such as the WS90 pack or a console. */
    private const TWO_CELL = 2.4;

    /**
     * WS90 and WS85 solar supercapacitors. They dip overnight without a fault,
     * so this only catches a panel that is not charging them.
     */
    private const CAPACITOR = 2.5;

    /**
     * Sensors with a fixed name: label, kind, and for volts the low threshold.
     *
     * @var array<string, array{0: string, 1: string, 2?: float}>
     */
    private const SENSORS = [
        // ── push ───────────────────────────────────────────────────────────
        'wh25batt' => ['Indoor Sensor (WH25)', 'flag'],
        'wh26batt' => ['Temperature/Humidity Sensor (WH26)', 'flag'],
        'wh65batt' => ['Outdoor Sensor (WH65)', 'flag'],
        'wh57batt' => ['Lightning Sensor (WH57)', 'level'],
        'co2_batt' => ['CO2 Sensor (WH45)', 'level'],
        'console_batt' => ['Console', 'volts', self::TWO_CELL],
        'wh40batt' => ['Rain Sensor (WH40)', 'volts', self::SINGLE_CELL],
        'wh68batt' => ['Solar/Wind Sensor (WH68)', 'volts', self::SINGLE_CELL],
        'wh80batt' => ['Ultrasonic Wind Sensor (WH80)', 'volts', self::SINGLE_CELL],
        'wh85batt' => ['WS85 Sensor Array', 'volts', self::SINGLE_CELL],
        'ws85cap_volt' => ['WS85 capacitor', 'volts', self::CAPACITOR],
        'bgtbatt' => ['Black Globe Thermometer (WN38)', 'volts', self::SINGLE_CELL],
        'wn20batt' => ['Sensor (WN20)', 'volts', self::SINGLE_CELL],

        // ── cloud ──────────────────────────────────────────────────────────
        't_rh_p_sensor' => ['Indoor Sensor', 'flag'],
        'outdoor_t_rh_sensor' => ['Temperature/Humidity Sensor', 'flag'],
        'sensor_array' => ['Outdoor Sensor Array', 'flag'],
        'lightning_sensor' => ['Lightning Sensor', 'level'],
        'aqi_combo_sensor' => ['Air Quality Combo Sensor', 'level'],
        'console' => ['Console', 'volts', self::TWO_CELL],
        'ws1900_console' => ['Console', 'volts', self::SINGLE_CELL],
        'ws1800_console' => ['Console', 'volts', self::SINGLE_CELL],
        'wind_sensor' => ['Ultrasonic Wind Sensor', 'volts', self::SINGLE_CELL],
        'sonic_array' => ['WS85 Sensor Array', 'volts', self::SINGLE_CELL],
        'rainfall_sensor' => ['Rain Sensor', 'volts', self::SINGLE_CELL],
        'bgt_sensor' => ['Black Globe Thermometer', 'volts', self::SINGLE_CELL],

        // ── both: the WS90, stored under its cloud names on every path ────
        'haptic_array_battery' => ['WS90 batteries', 'volts', self::TWO_CELL],
        'haptic_array_capacitor' => ['WS90 capacitor', 'volts', self::CAPACITOR],
    ];

    /**
     * Sensors that come in numbered channels: the pattern, the family used to
     * find an owner's own name for the channel, label, kind, threshold.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string, 4?: float}>
     */
    private const CHANNELS = [
        // push
        ['/^batt(\d+)$/', 'temps', 'Temperature/Humidity Sensor', 'flag'],
        ['/^pm25batt(\d+)$/', 'pm25', 'PM2.5 Sensor', 'level'],
        ['/^leakbatt(\d+)$/', 'leak', 'Leak Sensor', 'level'],
        ['/^soilbatt(\d+)$/', 'soil', 'Soil Sensor', 'volts', self::SINGLE_CELL],
        ['/^soil_ec_batt(\d+)$/', 'soil', 'Soil EC Sensor', 'volts', self::SINGLE_CELL],
        ['/^tf_batt(\d+)$/', 'temps', 'Temperature Probe', 'volts', self::SINGLE_CELL],
        // aioecowitt spells it leaf_batt; earlier versions of this app read leafbatt
        ['/^leaf_?batt(\d+)$/', 'leaf', 'Leaf Wetness Sensor', 'volts', self::SINGLE_CELL],
        ['/^ldsbatt_?(\d+)$/', 'lds', 'Water Level Sensor', 'volts', self::SINGLE_CELL],
        // cloud
        ['/^temp_humidity_sensor_ch(\d+)$/', 'temps', 'Temperature/Humidity Sensor', 'flag'],
        ['/^pm25_sensor_ch(\d+)$/', 'pm25', 'PM2.5 Sensor', 'level'],
        ['/^water_leak_sensor_ch(\d+)$/', 'leak', 'Leak Sensor', 'level'],
        ['/^soilmoisture_sensor_ch(\d+)$/', 'soil', 'Soil Sensor', 'volts', self::SINGLE_CELL],
        ['/^soilmoisture_ec_sensor_ch(\d+)$/', 'soil', 'Soil EC Sensor', 'volts', self::SINGLE_CELL],
        ['/^temperature_sensor_ch(\d+)$/', 'temps', 'Temperature Probe', 'volts', self::SINGLE_CELL],
        ['/^leaf_wetness_sensor_ch(\d+)$/', 'leaf', 'Leaf Wetness Sensor', 'volts', self::SINGLE_CELL],
    ];

    /**
     * Push names that arrive under one name and are stored under another, so
     * a WS90 reads the same whichever way its data comes in (#129).
     */
    /** Solar capacitors: they hold a charge but are not batteries. */
    private const CAPACITORS = ['haptic_array_capacitor', 'ws85cap_volt'];

    private const PUSH_RENAMES = [
        'wh90batt' => 'haptic_array_battery',
        'ws90cap_volt' => 'haptic_array_capacitor',
    ];

    /**
     * Every battery in a push or local-file payload, typed by kind: flags and
     * levels as whole numbers, volts as decimals. Rounding volts to whole
     * numbers is how a healthy 3.2 V sensor used to read 3 and then Low.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, int|float>
     */
    public static function fromPush(array $raw): array
    {
        $out = [];

        foreach ($raw as $field => $value) {
            $field = (string) $field;
            $key = self::PUSH_RENAMES[$field] ?? $field;
            $spec = self::spec($key);

            if ($spec === null || $spec['source'] === 'cloud' || !is_numeric($value)) {
                continue;
            }

            $out[$key] = $spec['type'] === 'volts'
                ? round((float) $value, 2)
                : (int) $value;
        }

        return $out;
    }

    /**
     * Flatten a battery group to plain numbers. The cloud wraps each reading
     * as time/unit/value; this takes the value and drops anything that is not
     * a number. Rows already stored wrapped are handled the same way on read.
     *
     * @return array<string, int|float>
     */
    public static function normalise(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $key => $value) {
            if (is_array($value)) {
                $value = $value['value'] ?? null;
            }
            if (!is_numeric($value)) {
                continue;
            }
            $number = (float) $value;
            $out[(string) $key] = floor($number) == $number && !str_contains((string) $value, '.')
                ? (int) $number
                : $number;
        }

        return $out;
    }

    /**
     * Judge every battery. The result is language-neutral, so it can sit in a
     * cached payload: label and status are English source strings for the
     * page to translate.
     *
     * icon is what the dashboard draws: a battery, a capacitor, or a plug for
     * a sensor on mains power.
     *
     * @return array<string, array{key: string, label: string, channel: ?string, family: ?string, type: string, value: int|float, unit: string, state: string, status: string, percentage: ?int, icon: string}>
     */
    public static function classify(mixed $raw): array
    {
        $out = [];

        foreach (self::normalise($raw) as $key => $value) {
            $spec = self::spec($key) ?? self::guess($value);
            $judged = self::judge($spec, $value);
            $out[$key] = ['key' => $key] + $spec + ['value' => $value] + $judged + [
                'icon' => match (true) {
                    in_array($key, self::CAPACITORS, true) => 'capacitor',
                    $judged['status'] === 'Mains' => 'mains',
                    default => 'battery',
                },
            ];
            unset($out[$key]['source']);
        }

        return $out;
    }

    /** Every label and status this class can hand out, for the page to translate. */
    public static function translatable(): array
    {
        $strings = array_column(self::SENSORS, 0);
        foreach (self::CHANNELS as $channel) {
            $strings[] = $channel[2];
        }

        return array_values(array_unique([...$strings, 'Low', 'Good', 'Moderate', 'Mains', 'Unknown']));
    }

    /** True when any of the given sensors reported, under either naming. */
    public static function has(mixed $raw, array $keys): bool
    {
        return array_intersect_key(self::normalise($raw), array_flip($keys)) !== [];
    }

    /**
     * @return array{label: string, channel: ?string, family: ?string, type: string, low: ?float, unit: string, source: string}|null
     */
    private static function spec(string $key): ?array
    {
        if (isset(self::SENSORS[$key])) {
            [$label, $type] = self::SENSORS[$key];

            return [
                'label' => $label,
                'channel' => null,
                'family' => null,
                'type' => $type,
                'low' => self::SENSORS[$key][2] ?? null,
                'unit' => $type === 'volts' ? 'V' : '',
                'source' => self::sourceOf($key),
            ];
        }

        foreach (self::CHANNELS as $channel) {
            if (preg_match($channel[0], $key, $m)) {
                return [
                    'label' => $channel[2],
                    'channel' => $m[1],
                    'family' => $channel[1],
                    'type' => $channel[3],
                    'low' => $channel[4] ?? null,
                    'unit' => $channel[3] === 'volts' ? 'V' : '',
                    'source' => str_contains($key, '_ch') ? 'cloud' : 'push',
                ];
            }
        }

        return null;
    }

    private static function sourceOf(string $key): string
    {
        static $cloud = [
            't_rh_p_sensor', 'outdoor_t_rh_sensor', 'sensor_array', 'lightning_sensor',
            'aqi_combo_sensor', 'console', 'ws1900_console', 'ws1800_console', 'wind_sensor',
            'sonic_array', 'rainfall_sensor', 'bgt_sensor',
        ];

        return in_array($key, $cloud, true) ? 'cloud' : 'push';
    }

    /**
     * A sensor nobody has described. 0 and 1 read as a flag, which is what
     * most of them are; anything else is shown, but not called low, because
     * nothing is known about what low would be.
     */
    private static function guess(int|float $value): array
    {
        $isFlag = $value === 0 || $value === 1;

        return [
            'label' => '',
            'channel' => null,
            'family' => null,
            'type' => $isFlag ? 'flag' : 'unknown',
            'low' => null,
            'unit' => '',
            'source' => 'unknown',
        ];
    }

    /** @return array{state: string, status: string, percentage: ?int} */
    private static function judge(array $spec, int|float $value): array
    {
        $result = match ($spec['type']) {
            'flag' => $value == 0
                // Good, like a level sensor: "OK" was translated as the generic
                // OK, which Spanish renders as "De acuerdo." (Agreed).
                ? ['good', 'Good', 100]
                : ['low', 'Low', 20],
            'level' => match (true) {
                $value >= 6 => ['good', 'Mains', 100],
                $value >= 4 => ['good', 'Good', (int) round($value * 20)],
                $value >= 2 => ['medium', 'Moderate', (int) round($value * 20)],
                default => ['low', 'Low', (int) round(max(0, $value) * 20)],
            },
            'volts' => $value < $spec['low']
                ? ['low', 'Low', null]
                : ['good', 'Good', null],
            default => ['unknown', 'Unknown', null],
        };

        return ['state' => $result[0], 'status' => $result[1], 'percentage' => $result[2]];
    }
}
