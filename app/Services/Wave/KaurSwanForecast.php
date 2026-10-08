<?php

namespace App\Services\Wave;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Keskkonnaagentuur (KAUR) SWAN-EST wave forecast, Estonian waters.
 *
 * Each model run is one NetCDF file of about 130 MB on KAIA, with hourly
 * significant wave height, peak period and direction on a grid. The API has
 * no point query and no range requests, so refresh() keeps the newest run on
 * disk and point() reads single values out of it.
 *
 * Data: https://keskkonnaportaal.ee/et/avaandmed/ilma-mudelprognoosid
 */
class KaurSwanForecast
{
    private const API = 'https://avaandmed.keskkonnaportaal.ee/api/lists/active/items';

    private const MAX_FILE_BYTES = 200_000_000;

    private const MAX_CELL_DISTANCE_KM = 25;

    public function path(): string
    {
        return storage_path('app/kaur-swan.nc');
    }

    /** Download the newest run unless it is already on disk. */
    public function refresh(): void
    {
        Cache::lock('kaur_swan_download', 600)->get(function () {
            $run = $this->newestRun();
            if ($run === null || $this->cachedRunTime() >= $run['time']) {
                return;
            }
            $this->download($run);
        });
    }

    /**
     * Hourly values at the grid cell nearest the point that has sea in it.
     *
     * @return array{latitude: float, longitude: float, steps: list<array{time: int, height_m: float, period_s: float|null, direction_deg: float|null}>}
     */
    public function point(float $latitude, float $longitude): array
    {
        if (! is_file($this->path())) {
            throw new \RuntimeException('No KAUR wave forecast downloaded yet');
        }

        $file = new KaurNetCdf($this->path());
        $this->validate($file);

        $longitudes = $this->axis($file, 'longitude');
        $latitudes = $this->axis($file, 'latitude');
        if ($longitude < min($longitudes) || $longitude > max($longitudes)
            || $latitude < min($latitudes) || $latitude > max($latitudes)) {
            throw new \RuntimeException('Location is outside the KAUR wave forecast area');
        }

        $times = $this->axis($file, 'time');
        $cell = $this->nearestSeaCell($file, $latitudes, $longitudes, $latitude, $longitude);
        $width = count($longitudes);

        $steps = [];
        foreach ($times as $record => $time) {
            $height = $file->value('hs', $record, $cell);
            if ($height === null) {
                continue;
            }
            $steps[] = [
                'time' => (int) $time,
                'height_m' => $height,
                'period_s' => $file->value('tps', $record, $cell),
                'direction_deg' => $file->value('thetap', $record, $cell),
            ];
        }

        return [
            'latitude' => $latitudes[intdiv($cell, $width)],
            'longitude' => $longitudes[$cell % $width],
            'steps' => $steps,
        ];
    }

    private function nearestSeaCell(KaurNetCdf $file, array $latitudes, array $longitudes, float $latitude, float $longitude): int
    {
        $width = count($longitudes);
        $distances = [];
        foreach ($latitudes as $y => $cellLatitude) {
            if (abs($cellLatitude - $latitude) > 0.25) {
                continue;
            }
            foreach ($longitudes as $x => $cellLongitude) {
                $distance = $this->distanceKm($latitude, $longitude, $cellLatitude, $cellLongitude);
                if ($distance <= self::MAX_CELL_DISTANCE_KM) {
                    $distances[$y * $width + $x] = $distance;
                }
            }
        }

        asort($distances);
        foreach (array_keys($distances) as $cell) {
            if ($file->value('hs', 0, $cell) !== null) {
                return $cell;
            }
        }

        throw new \RuntimeException('KAUR has no wave forecast within '.self::MAX_CELL_DISTANCE_KM.' km of this location');
    }

    private function axis(KaurNetCdf $file, string $name): array
    {
        $values = [];
        for ($i = 0; $i < $file->length($name); $i++) {
            $values[] = $name === 'time' ? $file->value($name, $i) : $file->value($name, 0, $i);
        }

        return $values;
    }

    private function validate(KaurNetCdf $file): void
    {
        foreach (['hs' => 'm', 'tps' => 's', 'thetap' => 'degree'] as $name => $unit) {
            $variable = $file->metadata($name);
            $dimensions = array_column($variable['dimensions'], 'name');
            if ($dimensions !== ['time', 'latitude', 'longitude'] || ! str_starts_with($variable['attributes']['units'] ?? '', $unit)) {
                throw new \RuntimeException("Unexpected layout for KAUR wave variable {$name}");
            }
        }

        if (! str_starts_with($file->metadata('time')['attributes']['units'] ?? '', 'seconds since 1970-01-01')) {
            throw new \RuntimeException('Unexpected KAUR wave time axis');
        }
    }

    private function cachedRunTime(): int
    {
        try {
            return is_file($this->path()) ? (int) (new KaurNetCdf($this->path()))->value('time') : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return array{time: int, document: int, file: int, size: int}|null */
    private function newestRun(): ?array
    {
        for ($daysBack = 0; $daysBack < 3; $daysBack++) {
            $day = now('UTC')->subDays($daysBack)->format('Ymd');
            $documents = Http::timeout(30)->post(self::API.'/query', [
                'filter' => ['contains' => ['field' => 'RMTitle', 'value' => "swan_{$day}"]],
                'pageSize' => 100,
                'fields' => ['RMTitle'],
                'includeFileMetadata' => true,
            ])->throw()->json('documents') ?? [];

            $runs = [];
            foreach ($documents as $document) {
                $title = $document['metadata']['RMTitle'] ?? '';
                if (! preg_match('/^swan_(\d{10})\.nc$/', $title, $match)) {
                    continue;
                }
                foreach ($document['fileMetadata'] ?? [] as $file) {
                    if (($file['name'] ?? '') === $title && ($file['size'] ?? 0) > 0 && $file['size'] <= self::MAX_FILE_BYTES) {
                        $runs[] = [
                            'time' => Carbon::createFromFormat('!YmdH', $match[1], 'UTC')->timestamp,
                            'document' => (int) $document['id'],
                            'file' => (int) $file['id'],
                            'size' => (int) $file['size'],
                        ];
                    }
                }
            }

            if ($runs !== []) {
                usort($runs, fn ($a, $b) => $b['time'] <=> $a['time']);

                return $runs[0];
            }
        }

        return null;
    }

    private function download(array $run): void
    {
        $directory = dirname($this->path());
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $temporary = tempnam($directory, 'kaur-swan-');
        try {
            Http::connectTimeout(15)->timeout(300)
                ->withOptions(['sink' => $temporary])
                ->get(self::API."/{$run['document']}/files/{$run['file']}")
                ->throw();

            clearstatcache(true, $temporary);
            if (filesize($temporary) !== $run['size']) {
                throw new \RuntimeException('Incomplete KAUR wave forecast download');
            }

            $file = new KaurNetCdf($temporary);
            $this->validate($file);
            if ((int) $file->value('time') !== $run['time']) {
                throw new \RuntimeException('KAUR wave forecast does not start at its run time');
            }
            unset($file);

            if (! rename($temporary, $this->path())) {
                throw new \RuntimeException('Cannot store the KAUR wave forecast');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    private function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $a = sin(deg2rad($lat2 - $lat1) / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin(deg2rad($lon2 - $lon1) / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
