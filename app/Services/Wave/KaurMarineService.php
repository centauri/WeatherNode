<?php

namespace App\Services\Wave;

use App\Models\Setting;
use App\Services\River\KaurObservations;
use App\Services\River\KaurStationCatalogService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Keskkonnaagentuur (KAUR) waves and sea temperature, Estonia.
 *
 * Waves come from the SWAN-EST model forecast at the grid cell nearest the
 * marine location. Sea temperature is measured at a coastal gauge, the one
 * chosen in settings or else the one nearest the marine location, and its
 * series is built from successive polls. SWAN has no swell split.
 *
 * Returns the same shape as OpenMeteoWaveService::fetch().
 */
class KaurMarineService
{
    private const SST_HISTORY_CACHE_KEY = 'kaur_sea_temperature_history';

    private const SEA_STATES = [
        [0.1, 0, 'wave_beaufort_0'],
        [0.5, 1, 'wave_beaufort_1'],
        [1.25, 2, 'wave_beaufort_2'],
        [2.5, 3, 'wave_beaufort_3'],
        [4.0, 4, 'wave_beaufort_4'],
        [6.0, 5, 'wave_beaufort_5'],
        [9.0, 6, 'wave_beaufort_6'],
    ];

    public function __construct(
        private readonly KaurSwanForecast $swan,
        private readonly KaurStationCatalogService $catalog,
    ) {}

    public function refreshForecast(): void
    {
        $this->swan->refresh();
    }

    /** @return array<string, array{name: string, latitude: float|null, longitude: float|null}> */
    public function seaTemperatureStations(): array
    {
        try {
            return array_filter(
                KaurObservations::seaLevelStations($this->catalog->snapshot()['stations']),
                fn ($code) => ($this->catalog->snapshot()['stations'][$code]['water_temp_c'] ?? null) !== null,
                ARRAY_FILTER_USE_KEY,
            );
        } catch (\Throwable) {
            return [];
        }
    }

    public function fetch(): array
    {
        $latitude = Setting::marineLatitude();
        $longitude = Setting::marineLongitude();
        $nowMs = now()->timestamp * 1000;

        $point = $this->swan->point($latitude, $longitude);
        $waveSeries = [];
        $current = null;
        foreach ($point['steps'] as $step) {
            $waveSeries[] = $this->seriesPoint($step['time'] * 1000, round($step['height_m'], 2));
            if ($nowMs >= $step['time'] * 1000) {
                $current = $step;
            }
        }
        if ($current === null) {
            throw new \RuntimeException('The KAUR wave forecast starts after the current time');
        }

        $sea = $this->seaTemperature($latitude, $longitude);
        $height = round($current['height_m'], 2);
        [$state, $labelKey] = $this->seaState($height);

        return [
            'current_wave_height_m' => $height,
            'current_wave_direction_deg' => $current['direction_deg'] !== null ? round($current['direction_deg']) : null,
            'current_wave_period_s' => $current['period_s'] !== null ? round($current['period_s'], 1) : null,
            'current_wind_wave_height_m' => null,
            'current_swell_height_m' => null,
            'current_swell_direction_deg' => null,
            'current_swell_period_s' => null,
            'current_sst_c' => $sea['current'],
            'beaufort_sea_state' => $state,
            'beaufort_label_key' => $labelKey,
            'wave_series' => $waveSeries,
            'sst_series' => $sea['series'],
            'location' => number_format($point['latitude'], 2).'°N, '.number_format($point['longitude'], 2).'°E',
            'updated_at' => now()->toIso8601String(),
            'has_swell' => false,
            'wave_period_label' => 'peak period',
            'wave_source_name' => 'Keskkonnaagentuur',
            'wave_source_url' => 'https://keskkonnaportaal.ee/et/avaandmed/ilma-mudelprognoosid',
            'sst_source_name' => 'Keskkonnaagentuur',
            'sst_source_url' => 'https://keskkonnaportaal.ee/et/avaandmed/hudroloogilise-seire-andmestik',
            'sst_location' => $sea['location'],
        ];
    }

    /** @return array{current: float|null, series: list<array>, location: string|null} */
    private function seaTemperature(float $latitude, float $longitude): array
    {
        try {
            $snapshot = $this->catalog->snapshot();
        } catch (\Throwable) {
            return ['current' => null, 'series' => [], 'location' => null];
        }

        $stations = $this->seaTemperatureStations();
        $code = (string) Setting::getValue('waves.kaur_station_code', '');
        if (! isset($stations[$code])) {
            $code = KaurObservations::nearest($stations, $latitude, $longitude, 1)[0] ?? null;
        }
        if ($code === null) {
            return ['current' => null, 'series' => [], 'location' => null];
        }

        $history = KaurObservations::remember(Cache::get(self::SST_HISTORY_CACHE_KEY, []), $snapshot, 'water_temp_c');
        Cache::put(self::SST_HISTORY_CACHE_KEY, $history, now()->addHours(KaurObservations::HISTORY_HOURS));

        return [
            'current' => $snapshot['stations'][$code]['water_temp_c'],
            'series' => array_map(fn ($p) => $this->seriesPoint($p['timestamp_unix'], $p['value']), $history[$code] ?? []),
            'location' => $stations[$code]['name'],
        ];
    }

    private function seriesPoint(int $timestampMs, float $value): array
    {
        return [
            'timestamp' => Carbon::createFromTimestampMs($timestampMs)->toIso8601String(),
            'timestamp_unix' => $timestampMs,
            'value' => $value,
        ];
    }

    /** @return array{0: int, 1: string} */
    private function seaState(float $height): array
    {
        foreach (self::SEA_STATES as [$threshold, $state, $labelKey]) {
            if ($height < $threshold) {
                return [$state, $labelKey];
            }
        }

        return [7, 'wave_beaufort_7'];
    }
}
