<?php

namespace App\Services\Tide;

use App\Services\River\KaurObservations;
use App\Services\River\KaurStationCatalogService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Keskkonnaagentuur (KAUR) coastal gauges, Estonia.
 *
 * Measured sea level in cm against EH2000, with water temperature. There is no
 * forecast, so tides is always empty and the series ends at the latest reading.
 * KAUR publishes the current hour only, so the series is built from successive
 * polls. No API key required.
 *
 * Data: https://keskkonnaportaal.ee/et/avaandmed/hudroloogilise-seire-andmestik
 */
class KaurSeaLevelSource extends AbstractTideSource
{
    private const HISTORY_CACHE_KEY = 'kaur_sea_level_history';

    public function __construct(private readonly KaurStationCatalogService $catalog) {}

    public function getName(): string
    {
        return 'Keskkonnaagentuur';
    }

    public function getRegion(): string
    {
        return 'EE';
    }

    public function getSourceKey(): string
    {
        return 'kaur';
    }

    public function isImplemented(): bool
    {
        return true;
    }

    public function requiresApiKey(): bool
    {
        return false;
    }

    public function getApiDocUrl(): ?string
    {
        return 'https://keskkonnaportaal.ee/et/avaandmed/hudroloogilise-seire-andmestik';
    }

    public function getCoverageArea(): string
    {
        return 'Estonian coast (measured sea level, no forecast)';
    }

    public function isStationBased(): bool
    {
        return true;
    }

    public function getStations(): array
    {
        try {
            return array_map(
                fn ($station) => ['name' => $station['name']],
                KaurObservations::seaLevelStations($this->catalog->snapshot()['stations']),
            );
        } catch (\Throwable) {
            return [];
        }
    }

    public function fetchTideData(string $stationCode = ''): array
    {
        $snapshot = $this->catalog->snapshot();
        $station = $snapshot['stations'][$stationCode] ?? null;
        if ($station === null || $station['water_level_eh2000_cm'] === null) {
            throw new \RuntimeException("KAUR has no sea level for station {$stationCode}");
        }

        $history = KaurObservations::remember(
            Cache::get(self::HISTORY_CACHE_KEY, []),
            $snapshot,
            'water_level_eh2000_cm',
        );
        Cache::put(self::HISTORY_CACHE_KEY, $history, now()->addHours(KaurObservations::HISTORY_HOURS));

        $points = $history[$stationCode] ?? [];
        $observedAt = $snapshot['timestamp'] !== null
            ? Carbon::createFromTimestamp($snapshot['timestamp'])->toIso8601String()
            : now()->toIso8601String();

        return [
            'station' => $station['name'],
            'station_code' => $stationCode,
            'current_level_cm' => $station['water_level_eh2000_cm'],
            'current_timestamp' => $observedAt,
            'trend' => KaurObservations::trend(KaurObservations::change($points)),
            'tides' => [],
            'series' => array_map(fn ($p) => [
                'timestamp' => Carbon::createFromTimestampMs($p['timestamp_unix'])->toIso8601String(),
                'timestamp_unix' => $p['timestamp_unix'],
                'value' => $p['value'],
            ], $points),
            'datum' => 'EH2000',
            'water_temp_c' => $station['water_temp_c'],
            'source' => 'kaur',
            'updated_at' => $observedAt,
        ];
    }
}
