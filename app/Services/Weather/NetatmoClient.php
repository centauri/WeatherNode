<?php

namespace App\Services\Weather;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Talks to the Netatmo weather API for the owner's own app.
 *
 * Every owner makes a free app at dev.netatmo.com and enters its client ID
 * and secret here. Since April 2023 every token refresh hands out a new
 * refresh token and kills the old one, so the new one is saved at once and
 * two refreshes never run side by side.
 */
class NetatmoClient
{
    public const API = 'https://api.netatmo.com/';

    public const SCOPE = 'read_station';

    /** Stations send about every ten minutes; a few minutes of cache keeps us far below the rate limit. */
    private const STATIONS_CACHE = 'netatmo:stations';

    private const STATIONS_SECONDS = 180;

    public function clientId(): string
    {
        return trim((string) Setting::getValue('netatmo.client_id', ''));
    }

    public function clientSecret(): string
    {
        return trim((string) Setting::getValue('netatmo.client_secret', ''));
    }

    public function hasApp(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    public function connected(): bool
    {
        return $this->hasApp() && trim((string) Setting::getValue('netatmo.refresh_token', '')) !== '';
    }

    public function authorizeUrl(string $redirectUri, string $state): string
    {
        return self::API . 'oauth2/authorize?' . http_build_query([
            'client_id' => $this->clientId(),
            'redirect_uri' => $redirectUri,
            'scope' => self::SCOPE,
            'state' => $state,
        ]);
    }

    /** Swap the code from the Netatmo login page for tokens. Returns an error message, or null when it worked. */
    public function exchangeCode(string $code, string $redirectUri): ?string
    {
        return $this->requestTokens([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'scope' => self::SCOPE,
        ]);
    }

    /** Use a refresh token from the token generator on dev.netatmo.com. Returns an error message, or null when it worked. */
    public function useRefreshToken(string $refreshToken): ?string
    {
        return $this->requestTokens([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);
    }

    public function disconnect(): void
    {
        foreach (['netatmo.refresh_token', 'netatmo.access_token'] as $key) {
            Setting::setValue($key, '', 'encrypted', 'netatmo');
        }
        Setting::setValue('netatmo.access_expires_at', '', 'string', 'netatmo');
        Cache::forget(self::STATIONS_CACHE);
    }

    public function lastError(): string
    {
        return (string) Setting::getValue('netatmo.last_error', '');
    }

    /**
     * Every station the account can read: its own first, then the public
     * stations it marked as favorite on weathermap.netatmo.com.
     *
     * @return array<int, array<string, mixed>>|null
     */
    public function stations(bool $fresh = false): ?array
    {
        if (!$fresh && is_array($cached = Cache::get(self::STATIONS_CACHE))) {
            return $cached;
        }

        $body = $this->get('api/getstationsdata', ['get_favorites' => 'true']);
        if (!is_array($body) || !isset($body['devices']) || !is_array($body['devices'])) {
            return null;
        }

        $devices = $body['devices'];
        usort($devices, fn (array $a, array $b) => (int) !empty($a['favorite']) <=> (int) !empty($b['favorite']));

        Cache::put(self::STATIONS_CACHE, $devices, self::STATIONS_SECONDS);

        return $devices;
    }

    /** The chosen station, or the first one the account owns. */
    public function station(): ?array
    {
        $devices = $this->stations();
        if (!$devices) {
            return null;
        }

        $chosen = trim((string) Setting::getValue('netatmo.device_id', ''));
        foreach ($devices as $device) {
            if ($chosen !== '' && ($device['_id'] ?? null) === $chosen) {
                return $device;
            }
        }

        return $chosen === '' ? $devices[0] : null;
    }

    private function get(string $path, array $query): ?array
    {
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $token = $this->accessToken($attempt === 2);
            if ($token === null) {
                return null;
            }

            try {
                $response = Http::timeout(15)->withToken($token)->get(self::API . $path, $query);
            } catch (\Exception $e) {
                Log::warning('Netatmo: request failed', ['path' => $path, 'error' => $e->getMessage()]);

                return null;
            }

            if ($response->successful()) {
                return $response->json('body');
            }

            // 2 = invalid token, 3 = expired token: refresh once and try again.
            $code = (int) $response->json('error.code');
            if ($attempt === 1 && in_array($code, [2, 3], true)) {
                continue;
            }

            $this->fail('Netatmo answered ' . $response->status() . ': ' . ($response->json('error.message') ?? 'unknown error'));

            return null;
        }

        return null;
    }

    private function accessToken(bool $forceRefresh = false): ?string
    {
        if (!$this->connected()) {
            return null;
        }

        if (!$forceRefresh && ($token = $this->validAccessToken()) !== null) {
            return $token;
        }

        // One refresh at a time: a second one would use a refresh token the first already spent.
        return Cache::lock('netatmo:refresh', 30)->block(20, function () use ($forceRefresh) {
            if (!$forceRefresh && ($token = $this->validAccessToken()) !== null) {
                return $token;
            }

            $refreshToken = trim((string) Setting::getValue('netatmo.refresh_token', ''));
            if ($refreshToken === '' || $this->useRefreshToken($refreshToken) !== null) {
                return null;
            }

            return $this->validAccessToken();
        });
    }

    private function validAccessToken(): ?string
    {
        $token = trim((string) Setting::getValue('netatmo.access_token', ''));
        $expiresAt = (int) Setting::getValue('netatmo.access_expires_at', 0);

        return $token !== '' && $expiresAt > now()->addMinute()->timestamp ? $token : null;
    }

    private function requestTokens(array $params): ?string
    {
        if (!$this->hasApp()) {
            return __('Enter the client ID and client secret of your Netatmo app first.');
        }

        try {
            $response = Http::asForm()->timeout(15)->post(self::API . 'oauth2/token', $params + [
                'client_id' => $this->clientId(),
                'client_secret' => $this->clientSecret(),
            ]);
        } catch (\Exception $e) {
            return $this->fail('Could not reach Netatmo: ' . $e->getMessage());
        }

        $access = (string) $response->json('access_token', '');
        $refresh = (string) $response->json('refresh_token', '');
        if (!$response->successful() || $access === '' || $refresh === '') {
            $error = (string) ($response->json('error') ?? $response->status());
            // A spent or revoked refresh token cannot come back: stop trying until the owner reconnects.
            if ($error === 'invalid_grant' && ($params['grant_type'] ?? '') === 'refresh_token') {
                $this->disconnect();
            }

            return $this->fail('Netatmo refused the login: ' . $error);
        }

        Setting::setValue('netatmo.access_token', $access, 'encrypted', 'netatmo');
        Setting::setValue('netatmo.refresh_token', $refresh, 'encrypted', 'netatmo');
        Setting::setValue('netatmo.access_expires_at', (string) (now()->timestamp + (int) $response->json('expires_in', 10800)), 'string', 'netatmo');
        Setting::setValue('netatmo.last_error', '', 'string', 'netatmo');

        return null;
    }

    private function fail(string $message): string
    {
        Log::warning('Netatmo: ' . $message);
        Setting::setValue('netatmo.last_error', $message, 'string', 'netatmo');

        return $message;
    }
}
