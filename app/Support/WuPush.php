<?php

namespace App\Support;

use App\Http\Controllers\Admin\SetupController;
use App\Models\Setting;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Readings pushed in the Weather Underground upload format.
 *
 * WeeWX, Meteobridge and many consoles can send to a Wunderground-style
 * address of your choosing. They send a station ID and a password with every
 * upload. The station ID can be anything; the password must be the station
 * key WeatherNode made.
 */
final class WuPush
{
    public const FORMAT = 'wuPush';

    public const PATH = '/api/wu/receive';

    public const KEY_SETTING = 'wupush.station_key';

    /** WeeWX rapidfire sends every 2.5 seconds. One reading a minute is plenty. */
    public const MIN_SECONDS_BETWEEN_SAVES = 55;

    public const SAVE_LOCK = 'wupush:save_lock';

    public const LAST_RECEIVED = 'wupush:last_received';

    public static function active(): bool
    {
        return Setting::getValue('livedata.format', '') === self::FORMAT;
    }

    public static function stationKey(): string
    {
        return trim((string) Setting::getValue(self::KEY_SETTING, ''));
    }

    public static function makeStationKey(): string
    {
        $key = Str::random(32);
        Setting::setValue(self::KEY_SETTING, $key, 'string', 'livedata');

        return $key;
    }

    public static function url(): string
    {
        return rtrim(SetupController::currentSiteAddress(), '/') . self::PATH;
    }

    public static function markReceived(): void
    {
        Cache::put(self::LAST_RECEIVED, now()->toIso8601String(), now()->addDays(7));
    }

    public static function lastReceived(): ?CarbonInterface
    {
        $value = Cache::get(self::LAST_RECEIVED);

        return is_string($value) ? Carbon::parse($value) : null;
    }
}
