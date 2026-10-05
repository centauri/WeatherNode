<?php

namespace App\Services\Telemetry;

use App\Models\Setting;
use App\Services\UserAgentService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelemetryService
{
    /**
     * Collect station data for telemetry
     */
    /**
     * What would be sent, whether or not sending is switched on.
     *
     * The settings page shows this so an owner can see what sharing means
     * before agreeing to it. Building it is not sending it: collectStationData
     * below is the one the senders call, and it still refuses while the
     * setting is off.
     */
    public function previewStationData(): ?array
    {
        try {
            $name = Setting::stationName();
            $hardware = Setting::getValue('station.hardware', '');
            $manufacturer = Setting::getValue('station.manufacturer', '');
            $latitude = Setting::latitude();
            $longitude = Setting::longitude();
            // A blank row is not a missing one: getValue hands back the blank,
            // so the seeder's "leave empty to use APP_URL" never happened and a
            // station was shared with no address to link to.
            $serverUrl = trim((string) Setting::getValue('station.server_url', ''))
                ?: (string) config('app.url', '');
            
            // Generate unique station ID (hash of URL + name)
            $stationId = $this->generateStationId($serverUrl, $name);

            // Resolve country from real coordinates (before anonymization)
            $countryCode = $this->resolveCountryCode((float) $latitude, (float) $longitude);

            // Blur the location by up to ~100 m, the same way every time
            [$anonLat, $anonLon] = $this->anonymizeCoordinates((float) $latitude, (float) $longitude, $stationId);

            return [
                'id' => $stationId,
                'name' => $name,
                'hardware' => $hardware,
                'manufacturer' => $manufacturer,
                'latitude' => $anonLat,
                'longitude' => $anonLon,
                'country_code' => $countryCode,
                'url' => rtrim($serverUrl, '/'),
                'updated_at' => now()->toIso8601String(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to collect station telemetry data', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * The data to send, or null when sharing is switched off.
     */
    public function collectStationData(): ?array
    {
        if (!Setting::getValue('telemetry.enabled', false)) {
            return null;
        }

        return $this->previewStationData();
    }

    /**
     * Format station data for GitHub JSON structure
     */
    public function formatForGitHub(array $stationData): array
    {
        return [
            'stations' => [$stationData],
            'last_updated' => now()->toIso8601String(),
        ];
    }

    /**
     * Check if telemetry data has changed since last update
     */
    public function shouldUpdate(): bool
    {
        $enabled = Setting::getValue('telemetry.enabled', false);
        
        if (!$enabled) {
            return false;
        }

        $lastUpdated = Setting::getValue('telemetry.last_updated', '');
        
        // Always update if never updated before
        if (empty($lastUpdated)) {
            return true;
        }

        // Get current data
        $currentData = $this->collectStationData();
        if (!$currentData) {
            return false;
        }

        // Get last saved data hash
        $lastDataHash = Setting::getValue('telemetry.last_data_hash', '');
        $currentDataHash = $this->hashStationData($currentData);

        // Update if data changed
        return $lastDataHash !== $currentDataHash;
    }

    /**
     * Resolve ISO 3166-1 alpha-2 country code from coordinates via Nominatim.
     * Results are cached in Settings to avoid repeat API calls.
     */
    private function resolveCountryCode(float $lat, float $lon): ?string
    {
        // Round to 2 decimals (~1km) for cache key stability
        $coordHash = md5(round($lat, 2) . ',' . round($lon, 2));
        $cachedCode = Setting::getValue('telemetry.cached_country_code', '');
        $cachedCoordHash = Setting::getValue('telemetry.cached_country_coords', '');

        if (!empty($cachedCode) && $cachedCoordHash === $coordHash) {
            return $cachedCode;
        }

        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'User-Agent' => UserAgentService::forExternalApi(),
                ])
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'lat' => $lat,
                    'lon' => $lon,
                    'format' => 'json',
                    'zoom' => 3,
                    'addressdetails' => 1,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $cc = $data['address']['country_code'] ?? null;

                if ($cc) {
                    $cc = strtoupper($cc);
                    Setting::setValue('telemetry.cached_country_code', $cc, 'string', 'telemetry');
                    Setting::setValue('telemetry.cached_country_coords', $coordHash, 'string', 'telemetry');
                    return $cc;
                }
            }
        } catch (\Exception $e) {
            Log::warning('Nominatim reverse geocoding failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return $cachedCode ?: null;
    }

    /**
     * Blur coordinates by up to ~100 m. The offset comes from the station id,
     * so a station is always blurred to the same spot: a new random offset on
     * every send looked like a change to the aggregator each day, and over
     * many sends the offsets would average back to the real location.
     */
    private function anonymizeCoordinates(float $lat, float $lon, string $stationId): array
    {
        // ~100m in degrees latitude (1° lat ≈ 111 320 m)
        $maxOffsetLat = 100 / 111320;
        // ~100m in degrees longitude (varies with latitude)
        $cosLat = cos(deg2rad($lat));
        $maxOffsetLon = $cosLat > 0 ? 100 / (111320 * $cosLat) : $maxOffsetLat;

        $hash = hash('sha256', 'telemetry-blur|' . $stationId);
        $u1 = hexdec(substr($hash, 0, 8)) / 0xFFFFFFFF;
        $u2 = hexdec(substr($hash, 8, 8)) / 0xFFFFFFFF;

        // Uniform point in a circle: angle in [0, 2π], distance sqrt(U), at least a third of the way out
        $angle = $u1 * 2 * M_PI;
        $distance = 0.33 + 0.67 * sqrt($u2);

        $offsetLat = $distance * $maxOffsetLat * cos($angle);
        $offsetLon = $distance * $maxOffsetLon * sin($angle);

        return [
            round($lat + $offsetLat, 6),
            round($lon + $offsetLon, 6),
        ];
    }

    /**
     * Generate unique station ID from URL only.
     * Using only the URL ensures the ID stays stable when other
     * station details (e.g. name) are changed.
     */
    private function generateStationId(string $url, string $name): string
    {
        return substr(md5($url), 0, 16);
    }

    /**
     * Create hash of station data for change detection
     */
    private function hashStationData(array $data): string
    {
        // Coordinates are blurred the same way every time, so a moved station counts as a change.
        $dataToHash = $data;
        unset($dataToHash['updated_at']);

        ksort($dataToHash);
        return md5(json_encode($dataToHash));
    }

    /**
     * Send this station to the aggregator. The one path every sender uses:
     * the daily command, saving the station or telemetry page, and Update now.
     *
     * The entry's id is a hash of the site address, so a new address makes a
     * new entry. The old one is then removed, or it stayed listed for good.
     *
     * @return array{success: bool, message: string}
     */
    public function publish(?TelemetryAggregatorService $aggregator = null): array
    {
        if (!Setting::getValue('telemetry.enabled', false)) {
            return ['success' => false, 'message' => 'Telemetry is disabled. Enable it first.'];
        }

        $data = $this->previewStationData();
        if (!$data) {
            return ['success' => false, 'message' => 'Failed to collect station data.'];
        }

        if ($problem = self::publicUrlProblem($data['url'])) {
            return ['success' => false, 'message' => $problem];
        }

        $aggregator ??= app(TelemetryAggregatorService::class);
        if (!$aggregator->sendStationData($data)) {
            return ['success' => false, 'message' => 'Failed to send data to aggregator. Check aggregator URL and API key.'];
        }

        $previous = (string) Setting::getValue('telemetry.station_id', '');
        if ($previous !== '' && $previous !== $data['id']) {
            $aggregator->removeStation($previous);
        }

        $this->markAsUpdated($data);

        return ['success' => true, 'message' => 'Station data sent to aggregator successfully!'];
    }

    /** Take this station off the list, when sharing is switched off. */
    public function unpublish(?TelemetryAggregatorService $aggregator = null): bool
    {
        $id = $this->getStationId();
        if (!$id) {
            return true;
        }

        $removed = ($aggregator ?? app(TelemetryAggregatorService::class))->removeStation($id);
        if ($removed) {
            Setting::setValue('telemetry.station_id', '', 'string', 'telemetry');
            Setting::setValue('telemetry.last_data_hash', '', 'string', 'telemetry');
        }

        return $removed;
    }

    /**
     * Why an address cannot be shared, or null when it can. Visitors of the
     * community map cannot open a LAN or localhost address.
     */
    public static function publicUrlProblem(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $message = 'Set a public address in Station settings (Server URL) before sharing. ' . ($host ?: $url) . ' cannot be opened by visitors.';

        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.lan')
            || str_ends_with($host, '.internal') || str_ends_with($host, '.home.arpa')) {
            return $message;
        }

        $ip = trim($host, '[]');
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) ? null : $message;
        }

        return str_contains($host, '.') ? null : $message;
    }

    /**
     * Save last update timestamp and data hash
     */
    public function markAsUpdated(array $stationData): void
    {
        Setting::setValue('telemetry.last_updated', now()->toIso8601String(), 'string', 'telemetry');
        Setting::setValue('telemetry.last_data_hash', $this->hashStationData($stationData), 'string', 'telemetry');
        Setting::setValue('telemetry.station_id', $stationData['id'], 'string', 'telemetry');
    }

    /**
     * The id this station was last shared under. Read when sharing is being
     * switched off, so it must not depend on sharing being on: it did, and the
     * removal was never sent.
     */
    public function getStationId(): ?string
    {
        $stored = (string) Setting::getValue('telemetry.station_id', '');
        if ($stored !== '') {
            return $stored;
        }

        return $this->previewStationData()['id'] ?? null;
    }
}
