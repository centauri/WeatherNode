<?php

namespace App\Services\River;

use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Keskkonnaagentuur (KAUR) river and lake gauges, Estonia.
 *
 * Levels are in cm above each gauge's zero. KAUR publishes the current hour
 * only, so the series is built from successive polls.
 * No API key required.
 *
 * Data: https://keskkonnaportaal.ee/et/avaandmed/hudroloogilise-seire-andmestik
 */
class KaurRiverService
{
    private const HISTORY_CACHE_KEY = 'kaur_river_history';

    public function __construct(private readonly KaurStationCatalogService $catalog) {}

    /**
     * The three gauges nearest the station.
     *
     * @return string[]
     */
    public function defaultStations(): array
    {
        return KaurObservations::nearest($this->catalog->getRiverStations(), Setting::latitude(), Setting::longitude());
    }

    /**
     * @param  string[]  $stationCodes  "ee" + WMO code
     * @param  array  $extraMeta  Names for codes the catalog does not carry
     * @return array<string, array>
     */
    public function fetch(array $stationCodes, array $extraMeta = []): array
    {
        $snapshot = $this->catalog->snapshot();
        $catalog = $this->catalog->getRiverStations();

        $history = KaurObservations::remember(Cache::get(self::HISTORY_CACHE_KEY, []), $snapshot);
        Cache::put(self::HISTORY_CACHE_KEY, $history, now()->addHours(KaurObservations::HISTORY_HOURS));

        $stationCodes = array_values(array_filter($stationCodes, fn ($code) => str_starts_with($code, KaurObservations::CODE_PREFIX)));

        $observedAt = $snapshot['timestamp'] !== null
            ? Carbon::createFromTimestamp($snapshot['timestamp'])->toIso8601String()
            : now()->toIso8601String();

        $results = [];
        foreach ($stationCodes as $code) {
            $meta = $catalog[$code] ?? $extraMeta[$code] ?? null;
            $station = $snapshot['stations'][$code] ?? null;
            if ($meta === null && $station === null) {
                continue;
            }

            $points = $history[$code] ?? [];
            $change = KaurObservations::change($points);

            $results[$code] = [
                'name' => $meta['name'] ?? $station['name'],
                'river' => $meta['river'] ?? $station['name'],
                'station_code' => $code,
                'level_cm' => $station['water_level_cm'] ?? null,
                'water_temp_c' => $station['water_temp_c'] ?? null,
                'datum' => 'gauge zero',
                'provider' => 'kaur',
                'trend' => KaurObservations::trend($change),
                'status' => KaurObservations::status($change),
                'series' => array_map(fn ($p) => [
                    'timestamp' => Carbon::createFromTimestampMs($p['timestamp_unix'])->toIso8601String(),
                    'timestamp_unix' => $p['timestamp_unix'],
                    'value' => $p['value'],
                ], $points),
                'updated_at' => $observedAt,
            ];
        }

        return $results;
    }
}
