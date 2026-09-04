<?php

declare(strict_types=1);

namespace App\Services\Forecast;

use App\Contracts\Forecast\ForecastServiceInterface;
use App\Models\Setting;
use App\Support\CacheFreshness;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;
use ZipArchive;

/**
 * Deutscher Wetterdienst MOSMIX, the statistical forecast DWD publishes for
 * each of its stations. Open data, no key.
 *
 * The file is a zipped KML: one series per parameter, each a space separated
 * list matching the shared list of timestamps. Values are SI, so Kelvin and
 * pascals, and a missing entry is a bare "-".
 */
class DwdService implements ForecastServiceInterface
{
    private const BASE_URL = 'https://opendata.dwd.de/weather/local_forecasts/mos/MOSMIX_L/single_stations';

    /** Fixed width list of every MOSMIX station, worldwide, not only German ones. */
    private const CATALOGUE_URL = 'https://www.dwd.de/DE/leistungen/opendata/help/stationen/mosmix_stationskatalog.cfg?view=nasPublication&nn=16102';

    /** MOSMIX is recomputed every few hours; DWD asks that it not be polled harder. */
    private const CACHE_TTL = 1800;

    public function fetchForecast(): ?array
    {
        $station = $this->stationId();

        if ($station === '') {
            return null;
        }

        return CacheFreshness::remember("dwd_forecast_{$station}", self::CACHE_TTL, function () use ($station) {
            $url = self::BASE_URL."/{$station}/kml/MOSMIX_L_LATEST_{$station}.kmz";

            try {
                $response = Http::timeout(20)->get($url);

                if (!$response->successful()) {
                    Log::warning('DWD MOSMIX request failed', ['station' => $station, 'status' => $response->status()]);

                    return null;
                }

                return $this->parse($response->body());
            } catch (\Throwable $e) {
                Log::warning('DWD MOSMIX exception', ['station' => $station, 'error' => $e->getMessage()]);

                return null;
            }
        });
    }

    public function getHourlyForecast(int $hours = 48): array
    {
        $forecast = $this->fetchForecast();

        if (!is_array($forecast) || empty($forecast['hourly'])) {
            return [];
        }

        return array_slice($forecast['hourly'], 0, $hours);
    }

    public function getDailyForecast(int $days = 7): array
    {
        $forecast = $this->fetchForecast();

        if (!is_array($forecast) || empty($forecast['hourly'])) {
            return [];
        }

        return array_slice($this->summariseByDay($forecast['hourly']), 0, $days);
    }

    /**
     * The configured station wins. Without one, pick whichever MOSMIX station
     * is closest, so enabling DWD is enough to get a forecast.
     */
    private function stationId(): string
    {
        $configured = trim((string) Setting::getValue('dwd.station_id', ''));

        if ($configured !== '') {
            return $configured;
        }

        $latitude = Setting::latitude();
        $longitude = Setting::longitude();

        return (string) CacheFreshness::remember(
            'dwd_nearest_station_'.round($latitude, 3).'_'.round($longitude, 3),
            86400 * 7,
            fn () => $this->nearestStation($latitude, $longitude) ?? ''
        );
    }

    private function nearestStation(float $latitude, float $longitude): ?string
    {
        $catalogue = CacheFreshness::remember('dwd_station_catalogue', 86400 * 30, function () {
            try {
                $response = Http::timeout(20)->get(self::CATALOGUE_URL);

                return $response->successful() ? $response->body() : null;
            } catch (\Throwable $e) {
                Log::warning('DWD station catalogue failed', ['error' => $e->getMessage()]);

                return null;
            }
        });

        if (!is_string($catalogue) || $catalogue === '') {
            return null;
        }

        $best = null;
        $bestDistance = INF;

        foreach (explode("\n", $catalogue) as $line) {
            if (!preg_match('/^(\d{5})\s+\S+\s+.{1,25}?\s+(-?\d+\.\d+)\s+(-?\d+\.\d+)\s+(-?\d+)\s*$/', $line, $m)) {
                continue;
            }

            $distance = $this->distance($latitude, $longitude, $this->degrees($m[2]), $this->degrees($m[3]));

            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $m[1];
            }
        }

        return $best;
    }

    /**
     * The catalogue is degrees and minutes, not decimal degrees: Berlin-Tegel
     * is written 52.34 and sits at 52.57. Reading it as decimal picks the
     * wrong station, and by tens of kilometres.
     */
    private function degrees(string $value): float
    {
        $number = (float) $value;
        $whole = (int) $number;
        $minutes = abs($number - $whole) * 100;

        return $whole + ($number < 0 ? -1 : 1) * ($minutes / 60);
    }

    private function distance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1) * cos(deg2rad(($lat1 + $lat2) / 2));

        return sqrt($dLat * $dLat + $dLon * $dLon);
    }

    /** @return array{station: array<string, mixed>, hourly: list<array<string, mixed>>}|null */
    private function parse(string $kmz): ?array
    {
        $kml = $this->unzip($kmz);

        if ($kml === null) {
            return null;
        }

        $xml = new SimpleXMLElement($kml);
        $ns = $xml->getNamespaces(true);
        $dwd = $ns['dwd'] ?? null;

        if ($dwd === null) {
            return null;
        }

        $xml->registerXPathNamespace('dwd', $dwd);
        $xml->registerXPathNamespace('kml', 'http://www.opengis.net/kml/2.2');

        $times = array_map(
            static fn ($step): string => (string) $step,
            $xml->xpath('//dwd:ForecastTimeSteps/dwd:TimeStep') ?: []
        );

        if ($times === []) {
            return null;
        }

        $series = [];
        foreach ($xml->xpath('//dwd:Forecast') ?: [] as $node) {
            $name = (string) $node->attributes($dwd)['elementName'];
            $raw = trim((string) $node->children($dwd)->value);
            $series[$name] = preg_split('/\s+/', $raw) ?: [];
        }

        $hourly = [];
        foreach ($times as $i => $time) {
            $hourly[] = [
                'time' => Carbon::parse($time)->toIso8601String(),
                'temperature' => $this->kelvin($series['TTT'][$i] ?? null),
                'dew_point' => $this->kelvin($series['Td'][$i] ?? null),
                'wind_speed' => $this->number($series['FF'][$i] ?? null),
                'wind_gust' => $this->number($series['FX1'][$i] ?? null),
                'wind_direction' => $this->number($series['DD'][$i] ?? null),
                'precipitation_1h' => $this->number($series['RR1c'][$i] ?? null),
                'precipitation_probability' => $this->number($series['R101'][$i] ?? null),
                'cloud_cover' => $this->number($series['Neff'][$i] ?? $series['N'][$i] ?? null),
                'pressure' => $this->pascals($series['PPPP'][$i] ?? null),
                'humidity' => $this->humidity($series['TTT'][$i] ?? null, $series['Td'][$i] ?? null),
                'symbol' => $this->symbol($series['ww'][$i] ?? null, $time),
            ];
        }

        return [
            'station' => [
                'id' => (string) (($xml->xpath('//kml:Placemark/kml:name')[0] ?? '')),
                'name' => (string) (($xml->xpath('//kml:Placemark/kml:description')[0] ?? '')),
            ],
            'hourly' => $hourly,
        ];
    }

    private function unzip(string $kmz): ?string
    {
        $path = tempnam(sys_get_temp_dir(), 'mosmix');

        if ($path === false) {
            return null;
        }

        try {
            file_put_contents($path, $kmz);

            $zip = new ZipArchive();
            if ($zip->open($path) !== true) {
                return null;
            }

            $kml = $zip->getFromIndex(0);
            $zip->close();

            return is_string($kml) && $kml !== '' ? $kml : null;
        } finally {
            @unlink($path);
        }
    }

    /** @param list<array<string, mixed>> $hourly */
    private function summariseByDay(array $hourly): array
    {
        $byDate = [];

        foreach ($hourly as $hour) {
            $date = Carbon::parse($hour['time'])->format('Y-m-d');
            $byDate[$date][] = $hour;
        }

        $days = [];
        foreach ($byDate as $date => $hours) {
            $temps = array_values(array_filter(array_column($hours, 'temperature'), 'is_numeric'));
            $winds = array_values(array_filter(array_column($hours, 'wind_speed'), 'is_numeric'));
            $rain = array_values(array_filter(array_column($hours, 'precipitation_1h'), 'is_numeric'));

            $days[] = [
                'date' => $date,
                'temp_high' => $temps === [] ? null : round(max($temps), 1),
                'temp_low' => $temps === [] ? null : round(min($temps), 1),
                'symbol' => $this->dominantSymbol($hours),
                'precipitation' => $rain === [] ? null : round(array_sum($rain), 1),
                'wind_speed' => $winds === [] ? null : round(max($winds), 1),
                'wind_direction' => $hours[0]['wind_direction'] ?? null,
            ];
        }

        return $days;
    }

    /** The midday symbol reads better on a daily row than the most frequent one. */
    private function dominantSymbol(array $hours): ?string
    {
        foreach ($hours as $hour) {
            if (Carbon::parse($hour['time'])->hour === 12 && $hour['symbol'] !== null) {
                return $hour['symbol'];
            }
        }

        return $hours[0]['symbol'] ?? null;
    }

    private function number(?string $value): ?float
    {
        if ($value === null || $value === '-' || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }

    private function kelvin(?string $value): ?float
    {
        $kelvin = $this->number($value);

        return $kelvin === null ? null : round($kelvin - 273.15, 1);
    }

    private function pascals(?string $value): ?float
    {
        $pascals = $this->number($value);

        return $pascals === null ? null : round($pascals / 100, 1);
    }

    /** MOSMIX has no humidity field, but it follows from temperature and dew point. */
    private function humidity(?string $temperature, ?string $dewPoint): ?int
    {
        $t = $this->kelvin($temperature);
        $d = $this->kelvin($dewPoint);

        if ($t === null || $d === null) {
            return null;
        }

        $relative = 100 * (exp((17.625 * $d) / (243.04 + $d)) / exp((17.625 * $t) / (243.04 + $t)));

        return (int) round(max(0, min(100, $relative)));
    }

    /**
     * WMO present weather code to the Yr.no style names the icon service reads.
     * Only the ranges MOSMIX actually emits are covered.
     */
    private function symbol(?string $code, string $time): ?string
    {
        $ww = $this->number($code);

        if ($ww === null) {
            return null;
        }

        $ww = (int) $ww;
        $hour = Carbon::parse($time)->hour;
        $suffix = ($hour >= 6 && $hour < 20) ? '_day' : '_night';

        return match (true) {
            $ww === 0 => 'clearsky'.$suffix,
            $ww <= 2 => 'fair'.$suffix,
            $ww === 3 => 'cloudy',
            $ww >= 40 && $ww <= 49 => 'fog',
            $ww >= 50 && $ww <= 59 => 'lightrain',
            $ww >= 60 && $ww <= 65 => 'rain',
            $ww >= 66 && $ww <= 69 => 'sleet',
            $ww >= 70 && $ww <= 79 => 'snow',
            $ww >= 80 && $ww <= 82 => 'rainshowers'.$suffix,
            $ww >= 83 && $ww <= 88 => 'sleetshowers'.$suffix,
            $ww >= 89 && $ww <= 94 => 'snowshowers'.$suffix,
            $ww >= 95 => 'rainandthunder',
            default => 'cloudy',
        };
    }
}
