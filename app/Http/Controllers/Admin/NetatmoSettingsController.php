<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Weather\NetatmoClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The Netatmo page: the owner's own app, connecting to it, and which station to read.
 */
class NetatmoSettingsController extends Controller
{
    public function __construct(private readonly NetatmoClient $client)
    {
    }

    /** What the page needs besides the generic settings. */
    public static function pageData(): array
    {
        $client = app(NetatmoClient::class);
        $stations = $client->connected() ? ($client->stations() ?? []) : [];

        return [
            'clientId' => $client->clientId(),
            'hasSecret' => $client->clientSecret() !== '',
            'connected' => $client->connected(),
            'lastError' => $client->lastError(),
            'redirectUri' => route('admin.settings.netatmo.callback'),
            'deviceId' => (string) Setting::getValue('netatmo.device_id', ''),
            'stations' => array_map(fn (array $device) => [
                'id' => (string) ($device['_id'] ?? ''),
                'name' => trim((string) ($device['station_name'] ?? $device['module_name'] ?? $device['_id'] ?? '')),
                'city' => (string) ($device['place']['city'] ?? ''),
                'favorite' => !empty($device['favorite']),
            ], $stations),
        ];
    }

    public function save(Request $request): RedirectResponse
    {
        $request->validate([
            'netatmo_client_id' => ['nullable', 'string', 'max:100'],
            'netatmo_client_secret' => ['nullable', 'string', 'max:200'],
            'netatmo_refresh_token' => ['nullable', 'string', 'max:500'],
            'netatmo_device_id' => ['nullable', 'string', 'max:50'],
        ]);

        if ($request->has('netatmo_client_id')) {
            Setting::setValue('netatmo.client_id', trim((string) $request->input('netatmo_client_id')), 'string', 'netatmo');
        }
        if (($secret = self::secret($request->input('netatmo_client_secret'))) !== null) {
            Setting::setValue('netatmo.client_secret', $secret, 'encrypted', 'netatmo');
        }
        if ($request->has('netatmo_device_id')) {
            Setting::setValue('netatmo.device_id', trim((string) $request->input('netatmo_device_id')), 'string', 'netatmo');
        }

        if (($token = self::secret($request->input('netatmo_refresh_token'))) !== null) {
            if (($error = $this->client->useRefreshToken($token)) !== null) {
                return redirect()->route('admin.settings.group', 'netatmo')->with('error', $error);
            }

            return redirect()->route('admin.settings.group', 'netatmo')->with('success', __('Connected to Netatmo.'));
        }

        return redirect()->route('admin.settings.group', 'netatmo')->with('success', __('Settings saved successfully!'));
    }

    /** Send the owner to the Netatmo login page. */
    public function connect(Request $request): RedirectResponse
    {
        if (!$this->client->hasApp()) {
            return redirect()->route('admin.settings.group', 'netatmo')
                ->with('error', __('Enter the client ID and client secret of your Netatmo app first.'));
        }

        $state = Str::random(40);
        $request->session()->put('netatmo_oauth_state', $state);

        return redirect()->away($this->client->authorizeUrl(route('admin.settings.netatmo.callback'), $state));
    }

    /** Netatmo sends the owner back here with a code, or with an error. */
    public function callback(Request $request): RedirectResponse
    {
        $expected = (string) $request->session()->pull('netatmo_oauth_state', '');
        $state = (string) $request->query('state', '');
        if ($expected === '' || !hash_equals($expected, $state)) {
            return redirect()->route('admin.settings.group', 'netatmo')
                ->with('error', __('The Netatmo login did not match. Click Connect Netatmo to try again.'));
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            return redirect()->route('admin.settings.group', 'netatmo')
                ->with('error', __('Netatmo did not allow access.'));
        }

        if (($error = $this->client->exchangeCode($code, route('admin.settings.netatmo.callback'))) !== null) {
            return redirect()->route('admin.settings.group', 'netatmo')->with('error', $error);
        }

        return redirect()->route('admin.settings.group', 'netatmo')->with('success', __('Connected to Netatmo.'));
    }

    public function disconnect(): RedirectResponse
    {
        $this->client->disconnect();

        return redirect()->route('admin.settings.group', 'netatmo')->with('success', __('Disconnected from Netatmo.'));
    }

    private static function secret(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' || preg_match('/^\*+$/', $value) ? null : $value;
    }
}
