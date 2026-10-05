<?php

namespace App\Services\Weather;

use App\Models\Setting;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;

/**
 * The Ecowitt cloud API v3, read with the owner's application and API keys.
 * https://doc.ecowitt.net/web/#/apiv3en?page_id=17
 */
class EcowittCloudApi
{
    public const DEFAULT_BASE_URL = 'https://api.ecowitt.net/api/v3/';

    /**
     * Readings are stored in metric, so ask for metric. EcowittCloudParser
     * still converts by unit in case an answer comes back otherwise.
     */
    private const METRIC = [
        'temp_unitid' => 1,               // °C
        'pressure_unitid' => 3,           // hPa
        'wind_speed_unitid' => 7,         // km/h
        'rainfall_unitid' => 12,          // mm
        'solar_irradiance_unitid' => 16,  // W/m²
    ];

    private string $baseUrl;

    public function __construct(
        private string $applicationKey,
        private string $apiKey,
        ?string $baseUrl = null,
    ) {
        $this->baseUrl = rtrim($baseUrl ?: self::DEFAULT_BASE_URL, '/') . '/';
    }

    public static function fromSettings(): self
    {
        return new self(
            trim((string) Setting::getValue('ecowitt.application_key', '')),
            trim((string) Setting::getValue('ecowitt.api_key', '')),
            (string) Setting::getValue('ecowitt.api_base_url', self::DEFAULT_BASE_URL),
        );
    }

    public function hasKeys(): bool
    {
        return $this->applicationKey !== '' && $this->apiKey !== '';
    }

    /** The latest reading of every sensor, grouped as the API groups them. */
    public function realTime(string $mac): array
    {
        return $this->get('device/real_time', ['mac' => $mac, 'call_back' => 'all'] + self::METRIC);
    }

    /**
     * Readings between two moments, each field as {unit, list: {timestamp: value}}.
     * The API reads the dates in the station's own time zone.
     */
    public function history(
        string $mac,
        CarbonInterface $start,
        CarbonInterface $end,
        string $timezone,
        string $cycle = '5min',
        string $callBack = 'all',
    ): array {
        return $this->get('device/history', [
            'mac' => $mac,
            'start_date' => $start->copy()->setTimezone($timezone)->format('Y-m-d H:i:s'),
            'end_date' => $end->copy()->setTimezone($timezone)->format('Y-m-d H:i:s'),
            'cycle_type' => $cycle,
            'call_back' => $callBack,
        ] + self::METRIC);
    }

    /**
     * The weather stations on the account (cameras are left out).
     *
     * @return list<array{name: string, mac: string, model: string, timezone: string, latitude: ?float, longitude: ?float}>
     */
    public function devices(): array
    {
        $stations = [];
        $page = 1;

        do {
            $data = $this->get('device/list', ['limit' => 50, 'page' => $page]);

            foreach ($data['list'] ?? [] as $device) {
                if ((int) ($device['type'] ?? 1) !== 1) {
                    continue;
                }

                $stations[] = [
                    'name' => (string) ($device['name'] ?? ''),
                    'mac' => (string) ($device['mac'] ?? ''),
                    'model' => (string) ($device['stationtype'] ?? ''),
                    'timezone' => (string) ($device['date_zone_id'] ?? ''),
                    'latitude' => is_numeric($device['latitude'] ?? null) ? (float) $device['latitude'] : null,
                    'longitude' => is_numeric($device['longitude'] ?? null) ? (float) $device['longitude'] : null,
                ];
            }
        } while ($page++ < (int) ($data['totalPage'] ?? 1) && $page <= 20);

        return $stations;
    }

    /**
     * One station's details, including whether Ecowitt hears from it.
     *
     * @return array{name: string, model: string, timezone: string, online: ?bool, last_update: ?Carbon}
     */
    public function device(string $mac): array
    {
        $data = $this->get('device/info', ['mac' => $mac]);
        $status = strtolower((string) ($data['device_status'] ?? ''));
        $last = $data['last_update_time'] ?? null;

        return [
            'name' => (string) ($data['name'] ?? ''),
            'model' => (string) ($data['stationtype'] ?? ''),
            'timezone' => (string) ($data['date_zone_id'] ?? ''),
            'online' => $status === '' ? null : $status === 'online',
            'last_update' => is_numeric($last) && (int) $last > 0 ? Carbon::createFromTimestamp((int) $last) : null,
        ];
    }

    private function get(string $path, array $query): array
    {
        try {
            $response = Http::timeout(20)->get($this->baseUrl . $path, [
                'application_key' => $this->applicationKey,
                'api_key' => $this->apiKey,
            ] + $query);
        } catch (\Throwable $e) {
            throw new EcowittCloudApiException('Could not reach the Ecowitt cloud: ' . $e->getMessage());
        }

        if (!$response->successful()) {
            throw new EcowittCloudApiException("The Ecowitt cloud answered HTTP {$response->status()}.");
        }

        $json = $response->json();
        if (!is_array($json) || (int) ($json['code'] ?? -1) !== 0) {
            throw new EcowittCloudApiException(
                (string) ($json['msg'] ?? 'The Ecowitt cloud sent an answer it could not read.'),
                isset($json['code']) ? (int) $json['code'] : null,
            );
        }

        return is_array($json['data'] ?? null) ? $json['data'] : [];
    }
}
