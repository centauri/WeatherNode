<?php

namespace App\Support;

use App\Http\Controllers\Admin\SetupController;
use App\Models\Setting;
use App\Models\WeatherReading;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * What the admin dashboard shows about the live data source in use.
 *
 * Each source gets its data one of three ways: the station sends it to us
 * (push), WeatherNode asks a cloud service for it (fetch), or WeatherNode
 * reads a file or local address that something else keeps up to date (file).
 */
final class LiveSource
{
    /** Newer than this and the data counts as coming in. */
    public const OK_MINUTES = 5;

    /** Older than this and nothing is coming in. */
    public const LATE_MINUTES = 15;

    public const LAST_ERROR = 'livedata:last_error';

    /** The name of each source and the settings page that holds its set-up. */
    private const SOURCES = [
        'ecoLcl' => ['Ecowitt Local (push)', 'ecowitt'],
        'ecowittAPI' => ['Ecowitt Cloud API', 'ecowitt'],
        'wu' => ['Weather Underground', 'wunderground'],
        WuPush::FORMAT => ['Wunderground upload (push)', 'livedata'],
        'DWL' => ['WeatherLink Cloud v1', 'weatherlink'],
        'DWL_v2api' => ['WeatherLink Cloud v2', 'weatherlink'],
        'DWL_v2api_demo' => ['WeatherLink Cloud v2 (Demo Mode)', 'weatherlink'],
        'wf' => ['WeatherFlow', 'weatherflow'],
        'AWapi' => ['Ambient Weather API', 'ambient'],
        'netatmo' => ['Netatmo', 'netatmo'],
        'cumulus' => ['Cumulus', 'livedata'],
        'weewx' => ['WeeWX', 'livedata'],
        'weathercat' => ['WeatherCat', 'livedata'],
        'meteohub' => ['Meteohub', 'livedata'],
        'wswin' => ['WSWIN', 'livedata'],
        'weatherlink' => ['WeatherLink Local', 'livedata'],
        'wifilogger' => ['WiFiLogger', 'livedata'],
        'MB_rt' => ['Meteobridge (realtime.txt)', 'livedata'],
        'wd' => ['Weather Display', 'livedata'],
    ];

    /**
     * @return array{format: string, name: string, kind: string, address: ?string,
     *               details: array<string, string>, settingsGroup: string, health: array}
     */
    public static function card(): array
    {
        $format = (string) Setting::getValue('livedata.format', 'ecoLcl');
        [$name, $settingsGroup] = self::SOURCES[$format] ?? [$format, 'livedata'];

        $card = [
            'format' => $format,
            'name' => __($name),
            'kind' => 'fetch',
            'address' => null,
            'details' => [],
            'settingsGroup' => $settingsGroup,
            'health' => self::health($format),
        ];

        if ($format === WuPush::FORMAT) {
            $last = WuPush::lastReceived();

            return array_merge($card, [
                'kind' => 'push',
                'address' => WuPush::url(),
                'details' => [
                    __('Protocol') => 'Weather Underground (HTTP GET)',
                    __('Station ID') => __('Anything you like'),
                    __('Station key') => WuPush::stationKey() !== '' ? WuPush::stationKey() : __('Click Save Changes to make your station key.'),
                    __('Last upload') => $last ? $last->diffForHumans() : __('Nothing received yet'),
                ],
            ]);
        }

        if ($format === 'ecoLcl' || $format === 'ecowittAPI') {
            return match (EcowittSource::current()) {
                EcowittSource::PUSH => array_merge($card, self::ecowittPush()),
                EcowittSource::FILE => array_merge($card, [
                    'kind' => 'file',
                    'address' => (string) Setting::getValue('ecowitt.local_file', './ecowitt/ecco_lcl.arr'),
                ]),
                default => $card,
            };
        }

        if ($settingsGroup === 'livedata' && isset(self::SOURCES[$format])) {
            $fromUrl = Setting::getValue('livedata.fetch_mode', 'file') === 'local_api';

            return array_merge($card, [
                'kind' => 'file',
                'address' => (string) Setting::getValue($fromUrl ? 'livedata.api_url' : 'livedata.file_path', ''),
            ]);
        }

        return $card;
    }

    private static function ecowittPush(): array
    {
        $secureMode = (bool) Setting::getValue('ecowitt.secure_mode', false);
        $secureToken = trim((string) Setting::getValue('ecowitt.secure_token', ''));
        $path = '/api/ecowitt/receive' . ($secureMode && $secureToken !== '' ? '/' . $secureToken : '');

        return [
            'kind' => 'push',
            'address' => rtrim(SetupController::currentSiteAddress(), '/') . $path,
            'details' => [
                __('Protocol') => 'Ecowitt (HTTP POST)',
                __('Path') => $path,
                __('Secure Mode') => $secureMode ? __('Enabled') : __('Disabled'),
                __('Interval') => __('60 seconds recommended'),
            ],
        ];
    }

    /**
     * Whether readings are coming in, judged by the age of the newest one,
     * plus the last error the fetch saw for this source.
     *
     * @return array{state: string, lastReading: ?\Carbon\CarbonInterface, error: ?array{message: string, at: \Carbon\CarbonInterface}}
     */
    public static function health(string $format): array
    {
        $lastReading = WeatherReading::mostRecent()?->recorded_at;
        $minutes = $lastReading?->diffInMinutes(now());

        $state = match (true) {
            $minutes === null || $minutes >= self::LATE_MINUTES => 'down',
            $minutes >= self::OK_MINUTES => 'late',
            default => 'ok',
        };

        $error = Cache::get(self::LAST_ERROR);
        $error = is_array($error) && ($error['format'] ?? null) === $format
            ? ['message' => (string) $error['message'], 'at' => \Carbon\Carbon::parse($error['at'])]
            : null;

        return ['state' => $state, 'lastReading' => $lastReading, 'error' => $error];
    }

    /** Remember why the last fetch failed. Query strings are cut, as they can hold API keys. */
    public static function recordError(string $format, string $message): void
    {
        $message = preg_replace('/\?\S*/', '?...', $message) ?? $message;

        Cache::put(self::LAST_ERROR, [
            'format' => $format,
            'message' => Str::limit(trim($message), 300),
            'at' => now()->toIso8601String(),
        ], now()->addDays(7));
    }

    public static function clearError(): void
    {
        Cache::forget(self::LAST_ERROR);
    }
}
