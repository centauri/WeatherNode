<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;
use App\Services\Weather\Normalization\UnitConverter;

/**
 * Which rain gauge an Ecowitt station's rain comes from.
 *
 * A tipping bucket (WH40, the WS69 array) and the piezo sensor in a WS90 or
 * WS85 report under different names: rainfall and rainfall_piezo on the cloud
 * API, rainratein and rrain_piezo (and so on) in push. Only the bucket used to
 * be read, so a WS90 station recorded no rain at all (#132).
 *
 * Every Ecowitt path hands both readings here and stores what choose() picks.
 *
 * In auto, the piezo is used unless the bucket has recorded rain this year.
 * Simply preferring the bucket when present would keep the bug: on the cloud
 * a WS90 station can get a bucket group that reads zero right through a storm
 * (sstjean/ecowitt-dashboard found this on a real WS90). A station with both
 * gauges, whose bucket is in use, keeps the bucket it has always shown.
 * Owners can pin either one on the Ecowitt settings page, like the Ecowitt
 * app's own primary-gauge setting.
 *
 * Field names for push are from aioecowitt (Home Assistant); cloud group names
 * from CumulusMX.
 */
final class RainGauge
{
    public const SETTING = 'ecowitt.rain_gauge';

    public const AUTO = 'auto';

    public const TIPPING = 'tipping';

    public const PIEZO = 'piezo';

    /** Stored column => push field, for the tipping bucket (inches). */
    private const PUSH_TIPPING = [
        'rain_rate' => 'rainratein',
        'rain_hourly' => 'hourlyrainin',
        'rain_daily' => 'dailyrainin',
        'rain_event' => 'eventrainin',
        'rain_weekly' => 'weeklyrainin',
        'rain_monthly' => 'monthlyrainin',
        'rain_yearly' => 'yearlyrainin',
        'rain_total' => 'totalrainin',
    ];

    /** Stored column => push field stem, for the piezo (inches, or mm with "mm"). */
    private const PUSH_PIEZO = [
        'rain_rate' => 'rrain_piezo',
        'rain_hourly' => 'hrain_piezo',
        'rain_daily' => 'drain_piezo',
        'rain_event' => 'erain_piezo',
        'rain_weekly' => 'wrain_piezo',
        'rain_monthly' => 'mrain_piezo',
        'rain_yearly' => 'yrain_piezo',
    ];

    /** Stored column => key (or keys, first found wins) inside a cloud rain group. */
    private const CLOUD = [
        'rain_rate' => 'rain_rate',
        // The API doc says hourly; a captured response sends 1_hour.
        'rain_hourly' => ['hourly', '1_hour'],
        'rain_daily' => 'daily',
        'rain_event' => 'event',
        'rain_weekly' => 'weekly',
        'rain_monthly' => 'monthly',
        'rain_yearly' => 'yearly',
        'rain_total' => 'total',
    ];

    /**
     * Both gauges from a push or local-file payload, in mm. A gauge the
     * station does not have comes back as null.
     *
     * @param  array<string, mixed>  $raw
     * @return array{tipping: ?array<string, float>, piezo: ?array<string, float>}
     */
    public static function fromPush(array $raw): array
    {
        $tipping = [];
        foreach (self::PUSH_TIPPING as $column => $field) {
            if (isset($raw[$field]) && is_numeric($raw[$field])) {
                $tipping[$column] = UnitConverter::inchesToMm((float) $raw[$field], 2);
            }
        }

        $piezo = [];
        foreach (self::PUSH_PIEZO as $column => $field) {
            if (isset($raw[$field . 'mm']) && is_numeric($raw[$field . 'mm'])) {
                $piezo[$column] = round((float) $raw[$field . 'mm'], 2);
            } elseif (isset($raw[$field]) && is_numeric($raw[$field])) {
                $piezo[$column] = UnitConverter::inchesToMm((float) $raw[$field], 2);
            }
        }

        return ['tipping' => $tipping ?: null, 'piezo' => $piezo ?: null];
    }

    /**
     * Both gauges from a cloud API (or local-file, which is shaped like it)
     * payload, in mm. Each value may be wrapped as time/unit/value; a unit in
     * inches is converted, since the cloud answers in the account's units
     * unless it was asked for metric.
     *
     * @param  array<string, mixed>  $data
     * @return array{tipping: ?array<string, float>, piezo: ?array<string, float>}
     */
    public static function fromCloud(array $data): array
    {
        return [
            'tipping' => self::cloudGroup($data['rainfall'] ?? null),
            'piezo' => self::cloudGroup($data['rainfall_piezo'] ?? null),
        ];
    }

    /**
     * The readings to store, as column => mm. Empty when the station has no
     * rain gauge at all.
     *
     * @param  array{tipping: ?array<string, float>, piezo: ?array<string, float>}  $gauges
     * @return array<string, float>
     */
    public static function choose(array $gauges, ?string $mode = null): array
    {
        $tipping = $gauges['tipping'] ?? null;
        $piezo = $gauges['piezo'] ?? null;

        // With one gauge or none there is nothing to choose, and no reason to
        // read the setting on every push.
        if ($tipping === null || $piezo === null) {
            return $tipping ?? $piezo ?? [];
        }

        $mode ??= (string) Setting::getValue(self::SETTING, self::AUTO);

        $chosen = match ($mode) {
            self::TIPPING => $tipping ?? $piezo,
            self::PIEZO => $piezo ?? $tipping,
            default => self::auto($tipping, $piezo),
        };

        return $chosen ?? [];
    }

    /** Which gauge choose() would use, for the settings page. */
    public static function chosenName(array $gauges, ?string $mode = null): ?string
    {
        $chosen = self::choose($gauges, $mode);
        if ($chosen === []) {
            return null;
        }

        return $chosen === ($gauges['piezo'] ?? null) ? self::PIEZO : self::TIPPING;
    }

    private static function auto(?array $tipping, ?array $piezo): ?array
    {
        if ($piezo === null) {
            return $tipping;
        }
        if ($tipping === null) {
            return $piezo;
        }

        return self::hasRecordedRain($tipping) ? $tipping : $piezo;
    }

    /**
     * Whether the bucket has caught anything. The year's total is the signal,
     * since it does not reset during a dry spell; the all-time total if that
     * is all there is; otherwise any reading at all.
     */
    private static function hasRecordedRain(array $gauge): bool
    {
        foreach (['rain_yearly', 'rain_total'] as $column) {
            if (isset($gauge[$column])) {
                return $gauge[$column] > 0;
            }
        }

        return max($gauge) > 0;
    }

    /** @return array<string, float>|null */
    private static function cloudGroup(mixed $group): ?array
    {
        if (!is_array($group)) {
            return null;
        }

        $out = [];
        foreach (self::CLOUD as $column => $key) {
            $entry = null;
            foreach ((array) $key as $name) {
                if (isset($group[$name])) {
                    $entry = $group[$name];
                    break;
                }
            }
            $value = is_array($entry) ? ($entry['value'] ?? null) : $entry;
            if (!is_numeric($value)) {
                continue;
            }

            $unit = is_array($entry) ? strtolower(trim((string) ($entry['unit'] ?? ''))) : '';
            $out[$column] = str_starts_with($unit, 'in')
                ? UnitConverter::inchesToMm((float) $value, 2)
                : round((float) $value, 2);
        }

        return $out ?: null;
    }
}
