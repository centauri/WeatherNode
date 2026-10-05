<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\WeatherReading;
use App\Services\Weather\EcowittBackfill;
use App\Services\Weather\EcowittCloudApi;
use App\Services\Weather\EcowittCloudApiException;
use App\Services\Weather\EcowittService;
use App\Support\EcowittSource;
use App\Support\RainGauge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The Ecowitt settings page: how the station sends data, the set-up for that
 * source, the rain gauge and the names of extra sensor channels. Also the
 * cloud station finder and status the page calls.
 */
class EcowittSettingsController extends Controller
{
    private const MAC = '/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/';

    public const PUSH_SECURITY_FIELDS = [
        'ecowitt_passkey', 'ecowitt_secure_mode', 'ecowitt_secure_token', 'ecowitt_ip_filter_enabled',
        'ecowitt_ip_allowlist', 'ecowitt_name_filter_enabled', 'ecowitt_name_allowlist',
    ];

    /**
     * Every nameable channel: setting key => [family, channel, reading column
     * shown beside it, unit]. The dashboard reads these same keys.
     *
     * @return array<string, array{0: string, 1: int|null, 2: string, 3: string}>
     */
    public static function channels(): array
    {
        $channels = [];
        for ($i = 1; $i <= 8; $i++) {
            $channels["ecowitt.temp{$i}_label"] = ['temps', $i, "temp_{$i}", '°C'];
        }
        for ($i = 1; $i <= 8; $i++) {
            $channels["ecowitt.soil{$i}_label"] = ['soil', $i, "soil_moisture_{$i}", '%'];
        }
        for ($i = 1; $i <= 4; $i++) {
            $channels["ecowitt.pm25_{$i}_label"] = ['pm25', $i, "pm25_ch{$i}", 'µg/m³'];
        }
        for ($i = 1; $i <= 4; $i++) {
            $channels["ecowitt.leak_{$i}_label"] = ['leak', $i, "leak_ch{$i}", ''];
        }
        $channels['ecowitt.co2_label'] = ['co2', null, 'co2', 'ppm'];

        return $channels;
    }

    /** What the page needs besides the generic settings. */
    public static function pageData(): array
    {
        $secureMode = (bool) Setting::getValue('ecowitt.secure_mode', false);
        $secureToken = (string) Setting::getValue('ecowitt.secure_token', '');
        $endpoint = '/api/ecowitt/receive' . ($secureMode && $secureToken !== '' ? '/' . $secureToken : '');
        $latest = WeatherReading::mostRecent();

        $channels = [];
        foreach (self::channels() as $key => [$family, $channel, $column, $unit]) {
            $value = $latest?->{$column};
            $label = (string) Setting::getValue($key, '');
            $channels[$family][] = [
                'key' => $key,
                'field' => str_replace('.', '_', $key),
                'channel' => $channel,
                'label' => $label,
                'reading' => match (true) {
                    $value === null => null,
                    $family === 'leak' => $value ? __('Leak') : __('Dry'),
                    default => rtrim(rtrim(number_format((float) $value, 1, '.', ''), '0'), '.') . ($unit !== '' ? ' ' . $unit : ''),
                },
            ];
        }

        $format = (string) Setting::getValue('livedata.format', 'ecoLcl');

        return [
            'source' => EcowittSource::current(),
            'liveFormat' => $format,
            'push' => [
                'passkey' => (string) Setting::getValue('ecowitt.passkey', ''),
                'secureMode' => $secureMode,
                'secureToken' => $secureToken,
                'ipFilterEnabled' => (bool) Setting::getValue('ecowitt.ip_filter_enabled', false),
                'ipAllowlist' => (string) Setting::getValue('ecowitt.ip_allowlist', ''),
                'nameFilterEnabled' => (bool) Setting::getValue('ecowitt.name_filter_enabled', false),
                'nameAllowlist' => (string) Setting::getValue('ecowitt.name_allowlist', ''),
                'endpoint' => $endpoint,
                'host' => request()->getHost(),
                'port' => request()->getPort(),
            ],
            'cloud' => [
                'hasApplicationKey' => trim((string) Setting::getValue('ecowitt.application_key', '')) !== '',
                'hasApiKey' => trim((string) Setting::getValue('ecowitt.api_key', '')) !== '',
                'mac' => (string) Setting::getValue('ecowitt.mac_address', ''),
                'baseUrl' => (string) Setting::getValue('ecowitt.api_base_url', EcowittCloudApi::DEFAULT_BASE_URL),
            ],
            'localFile' => (string) Setting::getValue('ecowitt.local_file', './ecowitt/ecco_lcl.arr'),
            'rainGauge' => Setting::where('key', RainGauge::SETTING)->first(),
            'backfill' => [
                'enabled' => filter_var(Setting::getValue('ecowitt.backfill_enabled', '1'), FILTER_VALIDATE_BOOLEAN),
                'days' => (int) Setting::getValue('ecowitt.backfill_days', 7),
                'lastRun' => is_array($last = Setting::getValue('ecowitt.backfill_last_run')) ? $last : null,
                'maxDays' => EcowittBackfill::MAX_DAYS,
            ],
            'channels' => $channels,
            'latestAt' => $latest?->recorded_at,
        ];
    }

    public function save(Request $request): RedirectResponse
    {
        $source = $request->input('ecowitt_source', 'keep');

        $request->validate([
            'ecowitt_source' => ['nullable', Rule::in([...EcowittSource::ALL, 'keep'])],
            'ecowitt_mac_address' => ['nullable', 'string', 'max:17'],
            'ecowitt_api_base_url' => ['nullable', 'url', 'starts_with:https://', 'max:255'],
            'ecowitt_local_file' => ['nullable', 'string', 'max:255', 'not_regex:/\.\./'],
            'ecowitt_rain_gauge' => ['nullable', Rule::in([RainGauge::AUTO, RainGauge::TIPPING, RainGauge::PIEZO])],
            'ecowitt_backfill_days' => ['nullable', 'integer', 'between:1,' . EcowittBackfill::MAX_DAYS],
            'ecowitt_station_latitude' => ['exclude_unless:ecowitt_apply_location,1', 'required', 'numeric', 'between:-90,90'],
            'ecowitt_station_longitude' => ['exclude_unless:ecowitt_apply_location,1', 'required', 'numeric', 'between:-180,180'],
            'ecowitt_station_timezone' => ['exclude_unless:ecowitt_apply_location,1', 'required', Rule::in(\DateTimeZone::listIdentifiers())],
        ], [
            'ecowitt_local_file.not_regex' => __('The file path may not contain "..".'),
        ]);

        $mac = strtoupper(trim((string) $request->input('ecowitt_mac_address', '')));
        if ($request->has('ecowitt_mac_address') && $mac !== '' && !preg_match(self::MAC, $mac)) {
            throw ValidationException::withMessages([
                'ecowitt_mac_address' => __('A MAC address looks like AA:BB:CC:DD:EE:FF. Use Find my stations to fill it in.'),
            ]);
        }

        $applicationKey = self::secret($request->input('ecowitt_application_key'));
        $apiKey = self::secret($request->input('ecowitt_api_key'));

        if ($source === EcowittSource::CLOUD) {
            $errors = [];
            if ($applicationKey === null && trim((string) Setting::getValue('ecowitt.application_key', '')) === '') {
                $errors['ecowitt_application_key'] = __('The Ecowitt cloud needs an application key.');
            }
            if ($apiKey === null && trim((string) Setting::getValue('ecowitt.api_key', '')) === '') {
                $errors['ecowitt_api_key'] = __('The Ecowitt cloud needs an API key.');
            }
            $savedMac = $request->has('ecowitt_mac_address') ? $mac : (string) Setting::getValue('ecowitt.mac_address', '');
            if ($savedMac === '') {
                $errors['ecowitt_mac_address'] = __('The Ecowitt cloud needs the MAC address of your station.');
            }
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
        }

        if ($applicationKey !== null) {
            Setting::setValue('ecowitt.application_key', $applicationKey, 'encrypted', 'ecowitt');
        }
        if ($apiKey !== null) {
            Setting::setValue('ecowitt.api_key', $apiKey, 'encrypted', 'ecowitt');
        }
        if ($request->has('ecowitt_mac_address')) {
            Setting::setValue('ecowitt.mac_address', $mac, 'string', 'ecowitt');
        }
        if ($request->filled('ecowitt_api_base_url')) {
            Setting::setValue('ecowitt.api_base_url', trim((string) $request->input('ecowitt_api_base_url')), 'string', 'ecowitt');
        }
        if ($request->filled('ecowitt_local_file')) {
            $file = trim((string) $request->input('ecowitt_local_file'));
            Setting::setValue('ecowitt.local_file', $file, 'string', 'ecowitt');
            // The scheduler's staleness check reads this one.
            Setting::setValue('livedata.file_path', $file, 'string', 'livedata');
        }
        if ($request->filled('ecowitt_rain_gauge')) {
            Setting::setValue(RainGauge::SETTING, $request->input('ecowitt_rain_gauge'), 'select', 'ecowitt');
        }

        if ($request->hasAny(self::PUSH_SECURITY_FIELDS)) {
            self::savePushSecurity($request);
        }

        if ($request->has('ecowitt_backfill_enabled')) {
            Setting::setValue('ecowitt.backfill_enabled', $request->boolean('ecowitt_backfill_enabled'), 'boolean', 'ecowitt');
        }
        if ($request->filled('ecowitt_backfill_days')) {
            Setting::setValue('ecowitt.backfill_days', (int) $request->input('ecowitt_backfill_days'), 'integer', 'ecowitt');
        }

        foreach (array_keys(self::channels()) as $key) {
            $field = str_replace('.', '_', $key);
            if ($request->has($field)) {
                Setting::setValue($key, mb_substr(trim((string) $request->input($field, '')), 0, 50), 'string', 'ecowitt');
            }
        }

        if ($request->input('ecowitt_apply_location') === '1') {
            Setting::setValue('station.latitude', (string) (float) $request->input('ecowitt_station_latitude'), 'float', 'station');
            Setting::setValue('station.longitude', (string) (float) $request->input('ecowitt_station_longitude'), 'float', 'station');
            Setting::setValue('station.timezone', $request->input('ecowitt_station_timezone'), 'string', 'station');
        }

        if (in_array($source, EcowittSource::ALL, true)) {
            EcowittSource::apply($source);
        }

        Cache::forget('ecowitt_realtime');
        Cache::forget('current_conditions');

        return redirect()
            ->route('admin.settings.group', 'ecowitt')
            ->with('success', __('Settings saved successfully!'));
    }

    /**
     * Passkey, secure mode and the allowlists for the push receiver. Used by
     * this page and, for forms posted by older pages, the Live Data page.
     */
    public static function savePushSecurity(Request $request): void
    {
        if ($request->has('ecowitt_passkey')) {
            Setting::setValue('ecowitt.passkey', trim((string) $request->input('ecowitt_passkey')), 'string', 'ecowitt');
        }

        Setting::setValue('ecowitt.secure_mode', $request->boolean('ecowitt_secure_mode'), 'boolean', 'ecowitt');
        $token = preg_replace('/[^A-Za-z0-9_-]/', '', trim((string) $request->input('ecowitt_secure_token', ''))) ?? '';
        Setting::setValue('ecowitt.secure_token', $token, 'string', 'ecowitt');

        Setting::setValue('ecowitt.ip_filter_enabled', $request->boolean('ecowitt_ip_filter_enabled'), 'boolean', 'ecowitt');
        Setting::setValue('ecowitt.ip_allowlist', self::allowlist((string) $request->input('ecowitt_ip_allowlist', '')), 'text', 'ecowitt');
        Setting::setValue('ecowitt.name_filter_enabled', $request->boolean('ecowitt_name_filter_enabled'), 'boolean', 'ecowitt');
        Setting::setValue('ecowitt.name_allowlist', self::allowlist((string) $request->input('ecowitt_name_allowlist', '')), 'text', 'ecowitt');
    }

    /** Fill gaps now, from the button on the page. */
    public function backfill(EcowittBackfill $backfill): RedirectResponse
    {
        $report = $backfill->run((int) Setting::getValue('ecowitt.backfill_days', 7));
        $back = redirect()->route('admin.settings.group', 'ecowitt');

        if ($report['skipped']) {
            return $back->with('error', __('Set the Ecowitt cloud keys and MAC address first.'));
        }
        if ($report['error']) {
            return $back->with('error', __('Filling gaps stopped: :error. Readings added: :count.', [
                'error' => $report['error'],
                'count' => $report['inserted'],
            ]));
        }

        return $back->with('success', $report['gaps'] === 0
            ? __('No gaps found.')
            : __('Gaps found: :gaps. Readings added: :count.', ['gaps' => $report['gaps'], 'count' => $report['inserted']]));
    }

    /** The stations on an Ecowitt account, with the keys typed on the page or else the saved ones. */
    public function stations(Request $request): JsonResponse
    {
        $request->validate([
            'application_key' => ['nullable', 'string', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:255'],
        ]);

        $api = new EcowittCloudApi(
            self::secret($request->input('application_key')) ?? trim((string) Setting::getValue('ecowitt.application_key', '')),
            self::secret($request->input('api_key')) ?? trim((string) Setting::getValue('ecowitt.api_key', '')),
            (string) Setting::getValue('ecowitt.api_base_url', EcowittCloudApi::DEFAULT_BASE_URL),
        );

        if (!$api->hasKeys()) {
            return response()->json(['success' => false, 'message' => __('Enter your application key and API key first.'), 'stations' => []]);
        }

        try {
            $stations = $api->devices();
        } catch (EcowittCloudApiException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'stations' => []]);
        }

        return response()->json([
            'success' => true,
            'message' => $stations === []
                ? __('This Ecowitt account has no weather stations.')
                : trans_choice('{1} Found 1 station.|[2,*] Found :count stations.', count($stations)),
            'stations' => $stations,
        ]);
    }

    /** Whether Ecowitt hears from the saved station, and when it last did. */
    public function status(): JsonResponse
    {
        $api = EcowittCloudApi::fromSettings();
        $mac = trim((string) Setting::getValue('ecowitt.mac_address', ''));

        if (!$api->hasKeys() || $mac === '') {
            return response()->json(['success' => false, 'message' => __('Enter the application key, API key and MAC address.')]);
        }

        try {
            $device = $api->device($mac);
        } catch (EcowittCloudApiException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }

        return response()->json([
            'success' => true,
            'online' => $device['online'],
            'name' => $device['name'],
            'model' => $device['model'],
            'timezone' => $device['timezone'],
            'last_update' => $device['last_update']?->toIso8601String(),
            'message' => self::describe($device),
        ]);
    }

    /**
     * The test button for a file or cloud source: read once and say what
     * happened, including whether Ecowitt still hears from a cloud station.
     *
     * @return array{success: bool, message: string}
     */
    public static function testConnection(): array
    {
        $service = app(EcowittService::class);
        $data = $service->fetchRealTimeData();

        if (EcowittSource::reader() === EcowittSource::FILE) {
            return $data
                ? ['success' => true, 'message' => __('The local file was read.')]
                : ['success' => false, 'message' => __('The local file could not be read. Check the path and that something is writing it.')];
        }

        if (!$data || $service->lastError() !== null) {
            return ['success' => false, 'message' => __('The Ecowitt cloud did not send data: :error', [
                'error' => $service->lastError() ?? __('no readings in the answer'),
            ])];
        }

        try {
            $device = EcowittCloudApi::fromSettings()->device(trim((string) Setting::getValue('ecowitt.mac_address', '')));
        } catch (EcowittCloudApiException) {
            return ['success' => true, 'message' => __('The Ecowitt cloud sent data.')];
        }

        if ($device['online'] === false) {
            return ['success' => false, 'message' => __('The keys work, but Ecowitt says the station is offline (:status).', [
                'status' => self::describe($device),
            ])];
        }

        return ['success' => true, 'message' => __('The Ecowitt cloud sent data. :status.', ['status' => self::describe($device)])];
    }

    /** One line about a cloud station, for the status line and the test button. */
    public static function describe(array $device): string
    {
        $state = match ($device['online']) {
            true => __('Online'),
            false => __('Offline'),
            null => __('Status unknown'),
        };

        return $device['last_update']
            ? __(':state, last data :ago', ['state' => $state, 'ago' => $device['last_update']->diffForHumans()])
            : $state;
    }

    /** A secret typed into a field, or null when the field was left empty or holds only a mask. */
    private static function secret(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' || preg_match('/^\*+$/', $value) ? null : $value;
    }

    private static function allowlist(string $raw): string
    {
        $values = [];
        foreach (preg_split('/[\r\n,;]+/', $raw) ?: [] as $part) {
            $candidate = trim($part);
            if ($candidate !== '' && !in_array($candidate, $values, true)) {
                $values[] = $candidate;
            }
        }

        return implode("\n", $values);
    }
}
