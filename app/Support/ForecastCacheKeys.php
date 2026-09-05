<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;

/**
 * Where each forecast source keeps its cached payload.
 *
 * This map was copied into seven places and every copy disagreed. The reader
 * built the DWD key from the configured station while the writer used the one
 * it resolved, so with the station left empty, which is the default, the two
 * never met. Issue #99.
 */
class ForecastCacheKeys
{
    /** Every source falls back to this when it has no key of its own. */
    public static function generic(?float $latitude = null, ?float $longitude = null): string
    {
        $latitude ??= Setting::latitude();
        $longitude ??= Setting::longitude();

        return "forecast_{$latitude}_{$longitude}";
    }

    public static function forSource(string $source, ?float $latitude = null, ?float $longitude = null): string
    {
        $latitude ??= Setting::latitude();
        $longitude ??= Setting::longitude();

        return match ($source) {
            'fct_yrno_block.php' => "yrno_forecast_{$latitude}_{$longitude}",
            'fct_darksky_block.php' => "openweathermap_forecast_{$latitude}_{$longitude}",
            'fct_wu_block.php' => "wunderground_forecast_{$latitude}_{$longitude}",
            'fct_wxsim_block.php' => 'wxsim_forecast_'.md5((string) Setting::getValue('wxsim.file_path', '')),
            'fct_ec_block.php' => "ec_forecast_{$latitude}_{$longitude}",
            'fct_tempest_block.php' => 'tempest_forecast_'.self::orZero(Setting::getValue('weatherflow.station_id', '')),
            'fct_aemet_block.php' => 'aemet_forecast_'.Setting::getValue('aemet.municipio', ''),
            'fct_dwd_block.php' => 'dwd_forecast_'.self::dwdStation(),
            default => self::generic($latitude, $longitude),
        };
    }

    /**
     * The station the service will actually use, which is the configured one
     * or the nearest it resolved. Resolution is cached by the service, so this
     * does not go to the network on its own.
     */
    private static function dwdStation(): string
    {
        $configured = trim((string) Setting::getValue('dwd.station_id', ''));

        if ($configured !== '') {
            return $configured;
        }

        return (string) \Illuminate\Support\Facades\Cache::get(
            'dwd_nearest_station_'.round(Setting::latitude(), 3).'_'.round(Setting::longitude(), 3),
            ''
        );
    }

    private static function orZero(mixed $value): string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : '0';
    }
}
