<?php

namespace App\Services\River;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Keskkonnaagentuur (KAUR) river and lake gauge catalog.
 *
 * Stations come from the hourly observations XML, river names from the
 * hydrology API at keskkonnaandmed.envir.ee.
 */
class KaurStationCatalogService
{
    public const OBSERVATIONS_URL = 'https://www.ilmateenistus.ee/ilma_andmed/xml/observations.php';

    private const HYDRO_URL = 'https://keskkonnaandmed.envir.ee/f_hydroseire';

    public const CACHE_KEY = 'kaur_station_catalog_river';

    private const SNAPSHOT_CACHE_KEY = 'kaur_observations_snapshot';

    private const CACHE_HOURS = 24;

    /** @return array<string, array{name: string, river: string, latitude: float|null, longitude: float|null}> */
    public function getRiverStations(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        Cache::put(self::CACHE_KEY.'_fetched_at', now()->timestamp, now()->addHours(self::CACHE_HOURS + 1));

        try {
            $stations = $this->snapshot()['stations'];
        } catch (\Throwable $e) {
            Log::warning('KAUR catalog: observations unavailable', ['error' => $e->getMessage()]);

            return [];
        }

        $waterBodies = $this->waterBodies();
        $catalog = KaurObservations::riverStations($stations, $waterBodies);
        Cache::put(self::CACHE_KEY, $catalog, now()->addHours($waterBodies === null ? 1 : self::CACHE_HOURS));

        return $catalog;
    }

    public function refresh(): array
    {
        Cache::forget(self::CACHE_KEY);

        return $this->getRiverStations();
    }

    public function cachedAt(): ?\Carbon\Carbon
    {
        $ts = Cache::get(self::CACHE_KEY.'_fetched_at');

        return $ts ? \Carbon\Carbon::createFromTimestamp($ts) : null;
    }

    /**
     * The latest observations snapshot.
     *
     * @return array{timestamp: int|null, stations: array<string, array>}
     */
    public function snapshot(): array
    {
        return Cache::remember(self::SNAPSHOT_CACHE_KEY, now()->addMinutes(10), function () {
            $response = Http::timeout(15)->get(self::OBSERVATIONS_URL);
            if (! $response->successful()) {
                throw new \RuntimeException("KAUR observations HTTP {$response->status()}");
            }

            return KaurObservations::parse($response->body());
        });
    }

    /**
     * River or lake name per station code, or null when the hydrology API is unavailable.
     *
     * @return array<string, string>|null
     */
    private function waterBodies(): ?array
    {
        try {
            $hour = now('UTC')->subDays(3)->startOfHour()->format('Y-m-d\TH:i:s');
            $response = Http::timeout(30)
                ->withHeaders(['Accept-Profile' => 'apijahiala', 'Accept' => 'application/json'])
                ->get(self::HYDRO_URL, [
                    'timeline_ts_utc' => "eq.{$hour}",
                    'select' => 'jaam_kood,veekogu_nimi,valgala_nimi',
                ]);

            if (! $response->successful()) {
                Log::warning('KAUR catalog: hydrology API failed', ['status' => $response->status()]);

                return null;
            }

            $names = [];
            foreach ((array) $response->json() as $row) {
                $name = KaurObservations::waterBodyName($row['veekogu_nimi'] ?? null, $row['valgala_nimi'] ?? null);
                if ($name !== null && isset($row['jaam_kood'])) {
                    $names[KaurObservations::CODE_PREFIX.$row['jaam_kood']] = $name;
                }
            }

            return $names === [] ? null : $names;
        } catch (\Throwable $e) {
            Log::warning('KAUR catalog: hydrology API unavailable', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
