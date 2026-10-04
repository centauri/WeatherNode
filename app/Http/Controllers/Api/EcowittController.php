<?php

namespace App\Http\Controllers\Api;

use App\Support\BatteryStatus;
use App\Http\Controllers\Controller;
use App\Models\WeatherReading;
use App\Models\Setting;
use App\Services\Weather\EcowittPushParser;
use App\Services\Weather\Normalization\WeatherReadingWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class EcowittController extends Controller
{
    /**
     * Receive data push from Ecowitt device
     * 
     * Configure your Ecowitt GW1000/GW2000 to send data to:
     * https://your-domain.com/api/ecowitt/receive
     * (or /api/ecowitt/receive/{token} when Secure Push Mode is enabled)
     * 
     * Supported devices: GW1000, GW1100, GW2000, WH2650, HP2551, HP3500
     */
    public function receive(Request $request, EcowittPushParser $parser, WeatherReadingWriter $writer, ?string $token = null)
    {
        try {
            $secureMode = (bool) Setting::getValue('ecowitt.secure_mode', false);
            if ($secureMode) {
                $expectedToken = trim((string) Setting::getValue('ecowitt.secure_token', ''));
                if ($expectedToken === '') {
                    Log::error('Ecowitt: Secure mode enabled but endpoint token is missing');
                    return response('Secure endpoint token not configured', 503);
                }

                $incomingToken = trim((string) ($token ?? ''));
                if (!$this->safeEquals($expectedToken, $incomingToken)) {
                    Log::warning('Ecowitt: Invalid endpoint token received', [
                        'ip' => $request->ip(),
                    ]);
                    return response('Invalid endpoint token', 403);
                }
            }

            $rawPayload = $request->all();
            $sourceIp = (string) ($request->ip() ?? '');

            if (!$this->passesIpFilter($sourceIp)) {
                Log::warning('Ecowitt: Source IP rejected by allowlist', [
                    'ip' => $sourceIp,
                ]);
                return response('Source IP not allowed', 403);
            }

            if (!$this->passesNameFilter($rawPayload)) {
                Log::warning('Ecowitt: Station identifier rejected by allowlist', [
                    'ip' => $sourceIp,
                    'stationtype' => $rawPayload['stationtype'] ?? null,
                    'model' => $rawPayload['model'] ?? null,
                    'stationname' => $rawPayload['stationname'] ?? ($rawPayload['station_name'] ?? null),
                    'name' => $rawPayload['name'] ?? null,
                ]);
                return response('Station name/model not allowed', 403);
            }

            // Get stored passkey (with error handling for database issues)
            $storedPasskey = null;
            try {
                $storedPasskey = trim((string) Setting::getValue('ecowitt.passkey', ''));
            } catch (\Exception $e) {
                Log::warning('Ecowitt: Could not read passkey from database', [
                    'error' => $e->getMessage(),
                ]);
            }

            $incomingPasskey = trim((string) $request->input('PASSKEY', ''));

            // Secure mode: strict passkey requirement (no auto-learn).
            if ($secureMode) {
                if ($storedPasskey === '') {
                    Log::error('Ecowitt: Secure mode enabled but passkey is missing');
                    return response('Passkey not configured', 503);
                }

                if (!$this->matchesStoredPasskey($incomingPasskey, $storedPasskey)) {
                    Log::warning('Ecowitt: Invalid passkey received', [
                        'ip' => $sourceIp,
                    ]);
                    return response('Invalid passkey', 403);
                }
            } else {
                // Legacy mode: keep backward-compatible behavior.
                if ($storedPasskey !== '') {
                    if (!$this->matchesStoredPasskey($incomingPasskey, $storedPasskey)) {
                        Log::warning('Ecowitt: Invalid passkey received', [
                            'ip' => $sourceIp,
                        ]);
                        return response('Invalid passkey', 403);
                    }
                } else {
                    // First-time auto-learn (legacy behavior).
                    if ($incomingPasskey !== '') {
                        try {
                            Setting::setValue('ecowitt.passkey', base64_encode($incomingPasskey), 'string', 'ecowitt');
                            Log::info('Ecowitt: First passkey stored');
                        } catch (\Exception $e) {
                            Log::warning('Ecowitt: Could not store passkey', [
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            }

            // Parse and convert the incoming data
            try {
                $data = $parser->parse($rawPayload);
            } catch (\Exception $e) {
                Log::error('Ecowitt: Failed to parse data', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'raw' => $rawPayload,
                ]);
                return response('Parse error: ' . $e->getMessage(), 500);
            }

            if (empty($data) || !isset($data['temperature'])) {
                Log::warning('Ecowitt: No valid data received', ['raw' => $request->all()]);
                return response('Invalid data', 400);
            }

            // Store the reading
            try {
                $reading = $writer->store($data);

                Log::debug('Ecowitt: Data stored successfully', ['id' => $reading->id ?? 'unknown']);

                return response('OK', 200);

            } catch (\Exception $e) {
                Log::error('Ecowitt: Failed to store data', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'data' => $data,
                ]);
                return response('Storage error: ' . $e->getMessage(), 500);
            }

        } catch (\Exception $e) {
            // Catch-all for any unexpected errors
            Log::error('Ecowitt: Unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);
            return response('Server error: ' . $e->getMessage(), 500);
        }
    }

    private function matchesStoredPasskey(string $incomingPasskey, string $storedPasskey): bool
    {
        if ($incomingPasskey === '' || $storedPasskey === '') {
            return false;
        }

        if ($this->safeEquals($storedPasskey, $incomingPasskey)) {
            return true;
        }

        $decodedStored = base64_decode($storedPasskey, true);
        if ($decodedStored === false || $decodedStored === '') {
            return false;
        }

        return $this->safeEquals($decodedStored, $incomingPasskey);
    }

    private function safeEquals(string $expected, string $actual): bool
    {
        if ($expected === '' || $actual === '') {
            return false;
        }

        return hash_equals($expected, $actual);
    }

    private function passesIpFilter(string $sourceIp): bool
    {
        $enabled = (bool) Setting::getValue('ecowitt.ip_filter_enabled', false);
        if (!$enabled) {
            return true;
        }

        $allowlist = $this->parseAllowlist((string) Setting::getValue('ecowitt.ip_allowlist', ''));
        if ($allowlist === []) {
            Log::error('Ecowitt: IP allowlist filter enabled but no entries are configured');
            return false;
        }

        if ($sourceIp === '') {
            return false;
        }

        foreach ($allowlist as $entry) {
            if ($this->ipMatchesEntry($sourceIp, $entry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function passesNameFilter(array $payload): bool
    {
        $enabled = (bool) Setting::getValue('ecowitt.name_filter_enabled', false);
        if (!$enabled) {
            return true;
        }

        $allowlist = $this->parseAllowlist((string) Setting::getValue('ecowitt.name_allowlist', ''));
        if ($allowlist === []) {
            Log::error('Ecowitt: Station name/model filter enabled but no entries are configured');
            return false;
        }

        $candidates = $this->extractNameCandidates($payload);
        if ($candidates === []) {
            return false;
        }

        foreach ($allowlist as $allowed) {
            $needle = mb_strtolower(trim($allowed));
            if ($needle === '') {
                continue;
            }

            foreach ($candidates as $candidate) {
                $haystack = mb_strtolower($candidate);
                if ($haystack === $needle || str_contains($haystack, $needle)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function parseAllowlist(string $raw): array
    {
        $parts = preg_split('/[\r\n,;]+/', $raw) ?: [];
        $values = [];

        foreach ($parts as $part) {
            $candidate = trim($part);
            if ($candidate === '' || in_array($candidate, $values, true)) {
                continue;
            }
            $values[] = $candidate;
        }

        return $values;
    }

    private function ipMatchesEntry(string $sourceIp, string $entry): bool
    {
        $entry = trim($entry);
        if ($entry === '') {
            return false;
        }

        if (str_contains($entry, '/')) {
            return $this->ipInCidrRange($sourceIp, $entry);
        }

        $sourceBin = @inet_pton($sourceIp);
        $entryBin = @inet_pton($entry);

        if ($sourceBin === false || $entryBin === false) {
            return false;
        }

        if (strlen($sourceBin) !== strlen($entryBin)) {
            return false;
        }

        return hash_equals($sourceBin, $entryBin);
    }

    private function ipInCidrRange(string $sourceIp, string $cidr): bool
    {
        [$network, $prefixRaw] = array_pad(explode('/', $cidr, 2), 2, '');
        $network = trim($network);
        $prefixRaw = trim($prefixRaw);

        if ($network === '' || $prefixRaw === '' || !ctype_digit($prefixRaw)) {
            return false;
        }

        $sourceBin = @inet_pton($sourceIp);
        $networkBin = @inet_pton($network);

        if ($sourceBin === false || $networkBin === false || strlen($sourceBin) !== strlen($networkBin)) {
            return false;
        }

        $prefix = (int) $prefixRaw;
        $maxBits = strlen($sourceBin) * 8;
        if ($prefix < 0 || $prefix > $maxBits) {
            return false;
        }

        $wholeBytes = intdiv($prefix, 8);
        $remainingBits = $prefix % 8;

        if ($wholeBytes > 0 && !hash_equals(substr($sourceBin, 0, $wholeBytes), substr($networkBin, 0, $wholeBytes))) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = ~(0xFF >> $remainingBits) & 0xFF;
        return (ord($sourceBin[$wholeBytes]) & $mask) === (ord($networkBin[$wholeBytes]) & $mask);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, string>
     */
    private function extractNameCandidates(array $payload): array
    {
        $keys = ['stationname', 'station_name', 'name', 'stationtype', 'model'];
        $values = [];

        foreach ($keys as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }

            $value = trim((string) $payload[$key]);
            if ($value === '' || in_array($value, $values, true)) {
                continue;
            }
            $values[] = $value;
        }

        return $values;
    }

    /**
     * Get the latest Ecowitt reading status
     */
    public function status()
    {
        $lastUpdate = Cache::get('weather:last_update');
        $current = Cache::get('weather:current');
        $reading = $current ? WeatherReading::find($current['id'] ?? null) : null;

        return response()->json([
            'status' => $lastUpdate ? 'online' : 'no_data',
            'last_update' => $lastUpdate,
            'has_data' => !empty($current),
            'station' => [
                'type' => $current['station_type'] ?? null,
                'model' => $current['station_model'] ?? null,
                'runtime_hours' => isset($current['station_runtime']) ? round($current['station_runtime'] / 3600, 1) : null,
            ],
            'sensors' => $reading ? [
                'has_indoor' => $reading->temperature_indoor !== null,
                'has_extra_temps' => $reading->hasExtraTemperatureSensors(),
                'has_soil' => $reading->hasSoilSensors(),
                'has_pm25' => $reading->hasPm25Sensors(),
                'has_co2' => $reading->co2 !== null,
                // Lightning sensor is present if we have distance, count, or time data
                // Also check battery status: push calls it wh57batt, the cloud lightning_sensor
                'has_lightning' => $reading->lightning_distance !== null 
                    || $reading->lightning_count !== null 
                    || $reading->lightning_time !== null
                    || BatteryStatus::has($current['battery_status'] ?? null, ['wh57batt', 'lightning_sensor']),
                'has_leak' => $reading->hasLeakAlert(),
            ] : null,
            'battery_status' => $current['battery_status'] ?? null,
        ]);
    }

}
