<?php

namespace App\Services\Weather;

use App\Models\Setting;
use App\Models\WeatherReading;
use App\Services\Weather\Normalization\WeatherDerivedMetrics;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Fills gaps in the readings from the Ecowitt cloud's history.
 *
 * Whatever the live source, the Ecowitt cloud keeps its own copy of every
 * reading the console uploads: 5-minute data for 90 days. When this site was
 * down, the network dropped or the push stopped, the missing stretch is
 * fetched from there and stored, and the days it touched get a fresh summary.
 *
 * Filled rows are stored with created_at set to when they were measured, so
 * nothing that looks for the newest row by id or created_at mistakes them for
 * the current weather.
 */
class EcowittBackfill
{
    /** A pause longer than this is a gap. Readings normally arrive every minute. */
    public const GAP_MINUTES = 15;

    /** The cloud keeps 5-minute data this long. */
    public const MAX_DAYS = 90;

    /** One request per day of a gap; a long outage is filled over several runs. */
    private const MAX_REQUESTS = 30;

    /**
     * The history endpoint refuses call_back=all ("all is invalid"), so the
     * groups are named. Groups a station does not have are left out of the answer.
     */
    public static function historyGroups(): string
    {
        $groups = [
            'outdoor', 'indoor', 'solar_and_uvi', 'rainfall', 'rainfall_piezo', 'wind', 'pressure', 'lightning',
            'indoor_co2', 'co2_aqi_combo', 'pm25_aqi_combo', 'pm10_aqi_combo', 't_rh_aqi_combo', 'water_leak',
        ];
        for ($i = 1; $i <= 8; $i++) {
            array_push($groups, "temp_and_humidity_ch{$i}", "soil_ch{$i}", "leaf_ch{$i}");
        }
        for ($i = 1; $i <= 4; $i++) {
            $groups[] = "pm25_ch{$i}";
        }

        return implode(',', $groups);
    }

    /** How close a history point may come to an existing reading. */
    private const MARGIN_SECONDS = 150;

    public function __construct(private EcowittCloudParser $parser)
    {
    }

    /**
     * @return array{gaps: int, inserted: int, days: list<string>, skipped: ?string, error: ?string}
     */
    public function run(int $days = 7, ?CarbonInterface $now = null): array
    {
        $now = Carbon::instance($now ?? now())->utc();
        $days = max(1, min(self::MAX_DAYS, $days));
        $report = ['gaps' => 0, 'inserted' => 0, 'days' => [], 'skipped' => null, 'error' => null];

        $api = EcowittCloudApi::fromSettings();
        $mac = trim((string) Setting::getValue('ecowitt.mac_address', ''));
        if (!$api->hasKeys() || $mac === '') {
            $report['skipped'] = 'Ecowitt cloud keys and MAC address are not set.';

            return $report;
        }

        $gaps = $this->gaps($now->copy()->subDays($days), $now->copy()->subMinutes(10));
        $report['gaps'] = count($gaps);
        if ($gaps === []) {
            return $this->remember($report, $now);
        }

        try {
            $timezone = $api->device($mac)['timezone'] ?: 'UTC';
            $requests = 0;
            $touched = [];

            foreach ($gaps as [$from, $to]) {
                // The API serves at most one day of 5-minute data per request.
                for ($start = $from->copy(); $start < $to && $requests < self::MAX_REQUESTS; $start->addDay()) {
                    $end = $start->copy()->addDay()->min($to);
                    $requests++;

                    $history = $api->history($mac, $start, $end, $timezone, '5min', self::historyGroups());
                    foreach ($this->points($history) as $timestamp => $payload) {
                        if ($timestamp <= $from->timestamp + self::MARGIN_SECONDS || $timestamp >= $to->timestamp - self::MARGIN_SECONDS) {
                            continue;
                        }

                        $at = Carbon::createFromTimestamp($timestamp)->setTimezone(config('app.timezone'));
                        $this->store($payload, $at);
                        $report['inserted']++;
                        $touched[$at->toDateString()] = true;
                    }
                }
            }

            $report['days'] = array_keys($touched);
            foreach ($report['days'] as $date) {
                Artisan::call('weather:summarize', ['date' => $date]);
            }
        } catch (EcowittCloudApiException $e) {
            $report['error'] = $e->getMessage();
            Log::warning('Ecowitt backfill stopped', ['error' => $e->getMessage(), 'inserted' => $report['inserted']]);
        }

        return $this->remember($report, $now);
    }

    /**
     * Stretches between $since and $until with no reading for longer than
     * GAP_MINUTES. A gap at the start counts only when there was a reading
     * before $since, so a new install does not pull in history it never had.
     *
     * @return list<array{0: Carbon, 1: Carbon}>
     */
    private function gaps(Carbon $since, Carbon $until): array
    {
        // recorded_at is stored in the app's time zone, so compare in it too.
        $local = $since->copy()->setTimezone(config('app.timezone'));
        $before = WeatherReading::query()->where('recorded_at', '<', $local)->max('recorded_at');
        $times = WeatherReading::query()
            ->where('recorded_at', '>=', $local)
            ->orderBy('recorded_at')
            ->pluck('recorded_at')
            ->map(fn ($t) => Carbon::parse($t, config('app.timezone'))->utc())
            ->all();

        if ($before !== null) {
            array_unshift($times, $since->copy());
        }
        // An outage still going on: from the last reading up to a few minutes ago.
        if ($times !== [] && end($times) < $until) {
            $times[] = $until->copy();
        }

        $gaps = [];
        for ($i = 1; $i < count($times); $i++) {
            if ($times[$i - 1]->diffInMinutes($times[$i]) > self::GAP_MINUTES) {
                $gaps[] = [$times[$i - 1], $times[$i]];
            }
        }

        return $gaps;
    }

    /**
     * Turn {group: {field: {unit, list: {timestamp: value}}}} into one
     * real-time-shaped payload per timestamp, so the cloud parser reads it.
     *
     * @return array<int, array>
     */
    private function points(array $history): array
    {
        $points = [];
        foreach ($history as $group => $fields) {
            if (!is_array($fields) || $group === 'battery') {
                continue;
            }
            foreach ($fields as $field => $series) {
                foreach ((array) ($series['list'] ?? []) as $timestamp => $value) {
                    if (is_numeric($timestamp) && is_numeric($value)) {
                        $points[(int) $timestamp][$group][$field] = ['unit' => $series['unit'] ?? '', 'value' => $value];
                    }
                }
            }
        }
        ksort($points);

        return $points;
    }

    private function store(array $payload, Carbon $at): void
    {
        $row = $this->parser->parse($payload);
        // A strike count and time at a past moment say nothing about it.
        unset($row['lightning_time']);
        $row['recorded_at'] = $at;

        $reading = new WeatherReading(WeatherDerivedMetrics::apply($row));
        $reading->created_at = $at;
        $reading->updated_at = now();
        $reading->save();
    }

    private function remember(array $report, Carbon $now): array
    {
        Setting::setValue('ecowitt.backfill_last_run', [
            'at' => $now->toIso8601String(),
            'gaps' => $report['gaps'],
            'inserted' => $report['inserted'],
            'error' => $report['error'],
        ], 'json', 'ecowitt');

        return $report;
    }
}
