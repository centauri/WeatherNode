<?php

declare(strict_types=1);

namespace Tests\Unit\Services\River;

use App\Services\River\KaurObservations;
use PHPUnit\Framework\TestCase;

class KaurObservationsTest extends TestCase
{
    private const XML = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<observations timestamp="1791447506">
	<station>
		<name>Tallinn-Harku</name>
		<wmocode>26038</wmocode>
		<longitude>24.602891666624284</longitude>
		<latitude>59.398122222355134</latitude>
		<airtemperature>10.2</airtemperature>
		<waterlevel></waterlevel>		<waterlevel_eh2000></waterlevel_eh2000>
		<watertemperature></watertemperature>
	</station>
	<station>
		<name>Pirita</name>
		<wmocode>86094</wmocode>
		<longitude>24.82</longitude>
		<latitude>59.47</latitude>
		<waterlevel></waterlevel>		<waterlevel_eh2000>24</waterlevel_eh2000>
		<watertemperature>13.3</watertemperature>
	</station>
	<station>
		<name>Keila</name>
		<wmocode>41107</wmocode>
		<longitude>24.43470638851105</longitude>
		<latitude>59.308790833155314</latitude>
		<waterlevel>92</waterlevel>		<waterlevel_eh2000></waterlevel_eh2000>
		<watertemperature>9.6</watertemperature>
	</station>
	<station>
		<name>Tartu</name>
		<wmocode>41025</wmocode>
		<longitude>26.73</longitude>
		<latitude>58.38</latitude>
		<waterlevel>83</waterlevel>		<waterlevel_eh2000></waterlevel_eh2000>
		<watertemperature>11.5</watertemperature>
	</station>
	<station>
		<name>Kaev-1052</name>
		<wmocode>1052</wmocode>
		<longitude>25.1</longitude>
		<latitude>59.1</latitude>
		<waterlevel>-26</waterlevel>		<waterlevel_eh2000></waterlevel_eh2000>
		<watertemperature></watertemperature>
	</station>
	<station>
		<name>No code</name>
		<wmocode></wmocode>
		<waterlevel>10</waterlevel>
	</station>
</observations>
XML;

    private const HOUR_MS = 3_600_000;

    private function points(array $values, int $startMs = 0): array
    {
        $points = [];
        foreach (array_values($values) as $i => $value) {
            $points[] = ['timestamp_unix' => $startMs + $i * self::HOUR_MS, 'value' => (float) $value];
        }

        return $points;
    }

    private function snapshot(int $timestamp, array $levels): array
    {
        $stations = [];
        foreach ($levels as $code => $level) {
            $stations[$code] = ['water_level_cm' => $level, 'water_level_eh2000_cm' => null];
        }

        return ['timestamp' => $timestamp, 'stations' => $stations];
    }

    // ---- parsing ----------------------------------------------------------

    public function test_parse_reads_the_snapshot_time_and_prefixes_codes(): void
    {
        $parsed = KaurObservations::parse(self::XML);

        $this->assertSame(1791447506, $parsed['timestamp']);
        $this->assertSame(['ee26038', 'ee86094', 'ee41107', 'ee41025', 'ee1052'], array_keys($parsed['stations']));
    }

    public function test_parse_turns_empty_elements_into_null(): void
    {
        $keila = KaurObservations::parse(self::XML)['stations']['ee41107'];

        $this->assertSame('Keila', $keila['name']);
        $this->assertSame(92.0, $keila['water_level_cm']);
        $this->assertNull($keila['water_level_eh2000_cm']);
        $this->assertSame(9.6, $keila['water_temp_c']);
    }

    public function test_parse_keeps_negative_levels(): void
    {
        $well = KaurObservations::parse(self::XML)['stations']['ee1052'];

        $this->assertSame(-26.0, $well['water_level_cm']);
    }

    public function test_parse_rejects_what_is_not_xml(): void
    {
        $this->expectException(\RuntimeException::class);

        KaurObservations::parse('<html><body>Service Unavailable');
    }

    // ---- which stations are rivers ----------------------------------------

    public function test_river_stations_drop_synoptic_and_coastal_gauges(): void
    {
        $catalog = KaurObservations::riverStations(KaurObservations::parse(self::XML)['stations']);

        $this->assertArrayNotHasKey('ee26038', $catalog);
        $this->assertArrayNotHasKey('ee86094', $catalog);
        $this->assertArrayHasKey('ee41107', $catalog);
    }

    public function test_without_water_bodies_every_plain_level_counts_and_names_itself(): void
    {
        $catalog = KaurObservations::riverStations(KaurObservations::parse(self::XML)['stations']);

        $this->assertArrayHasKey('ee1052', $catalog);
        $this->assertSame('Keila', $catalog['ee41107']['river']);
    }

    public function test_known_water_bodies_name_the_river_and_exclude_strays(): void
    {
        $catalog = KaurObservations::riverStations(
            KaurObservations::parse(self::XML)['stations'],
            ['ee41107' => 'Keila jõgi', 'ee41025' => 'Emajõgi'],
        );

        $this->assertSame(['ee41025', 'ee41107'], array_keys($catalog));
        $this->assertSame('Keila jõgi', $catalog['ee41107']['river']);
    }

    public function test_water_body_name_spells_out_the_abbreviation(): void
    {
        $this->assertSame('Keila jõgi', KaurObservations::waterBodyName('Keila j.', 'Keila jõgi'));
        $this->assertSame('Leivajõgi', KaurObservations::waterBodyName('Leivajõgi', 'Pirita jõgi'));
    }

    public function test_water_body_name_falls_back_to_the_basin_but_not_the_placeholder(): void
    {
        $this->assertSame('Pärnu jõgi', KaurObservations::waterBodyName(null, 'Pärnu jõgi'));
        $this->assertNull(KaurObservations::waterBodyName(null, '---'));
    }

    public function test_sea_level_stations_are_the_coastal_gauges(): void
    {
        $catalog = KaurObservations::seaLevelStations(KaurObservations::parse(self::XML)['stations']);

        $this->assertSame(['ee86094'], array_keys($catalog));
        $this->assertSame('Pirita', $catalog['ee86094']['name']);
    }

    // ---- defaults ---------------------------------------------------------

    public function test_nearest_orders_by_distance_from_the_station(): void
    {
        $catalog = KaurObservations::riverStations(KaurObservations::parse(self::XML)['stations']);

        $this->assertSame(['ee41107', 'ee1052'], KaurObservations::nearest($catalog, 59.437, 24.745, 2));
    }

    // ---- history ----------------------------------------------------------

    public function test_remember_records_one_point_per_snapshot(): void
    {
        $history = KaurObservations::remember([], $this->snapshot(1000, ['ee41107' => 92.0]));
        $history = KaurObservations::remember($history, $this->snapshot(1000, ['ee41107' => 92.0]));
        $history = KaurObservations::remember($history, $this->snapshot(4600, ['ee41107' => 94.0]));

        $this->assertSame([
            ['timestamp_unix' => 1_000_000, 'value' => 92.0],
            ['timestamp_unix' => 4_600_000, 'value' => 94.0],
        ], $history['ee41107']);
    }

    public function test_remember_skips_stations_without_the_field(): void
    {
        $snapshot = $this->snapshot(1000, ['ee41107' => null]);
        $snapshot['stations']['ee86094'] = ['water_level_cm' => null, 'water_level_eh2000_cm' => 24.0];

        $this->assertSame([], KaurObservations::remember([], $snapshot));
    }

    public function test_remember_can_record_sea_level(): void
    {
        $snapshot = $this->snapshot(1000, ['ee41107' => 92.0]);
        $snapshot['stations']['ee86094'] = ['water_level_cm' => null, 'water_level_eh2000_cm' => 24.0];

        $this->assertSame(
            ['ee86094' => [['timestamp_unix' => 1_000_000, 'value' => 24.0]]],
            KaurObservations::remember([], $snapshot, 'water_level_eh2000_cm'),
        );
    }

    public function test_remember_forgets_points_older_than_the_window(): void
    {
        $hours = KaurObservations::HISTORY_HOURS;
        $history = ['ee41107' => [['timestamp_unix' => 0, 'value' => 80.0]], 'ee41025' => [['timestamp_unix' => 0, 'value' => 70.0]]];

        $history = KaurObservations::remember($history, $this->snapshot(($hours + 1) * 3600, ['ee41107' => 90.0]));

        $this->assertSame([['timestamp_unix' => ($hours + 1) * self::HOUR_MS, 'value' => 90.0]], $history['ee41107']);
        $this->assertArrayNotHasKey('ee41025', $history);
    }

    public function test_remember_without_a_timestamp_changes_nothing(): void
    {
        $history = ['ee41107' => $this->points([90])];

        $this->assertSame($history, KaurObservations::remember($history, ['timestamp' => null, 'stations' => []]));
    }

    // ---- trend ------------------------------------------------------------

    public function test_change_compares_against_the_reading_three_hours_back(): void
    {
        $this->assertSame(12.0, KaurObservations::change($this->points([70, 80, 85, 90, 92])));
    }

    public function test_change_needs_a_reading_old_enough(): void
    {
        $this->assertNull(KaurObservations::change($this->points([80, 85, 90])));
        $this->assertNull(KaurObservations::change([]));
    }

    public function test_change_bridges_a_missed_hour(): void
    {
        $points = [
            ['timestamp_unix' => 0, 'value' => 80.0],
            ['timestamp_unix' => 4 * self::HOUR_MS, 'value' => 70.0],
        ];

        $this->assertSame(-10.0, KaurObservations::change($points));
    }

    public function test_trend_and_status_share_the_rijkswaterstaat_thresholds(): void
    {
        $this->assertSame('steady', KaurObservations::trend(null));
        $this->assertSame('steady', KaurObservations::trend(5.0));
        $this->assertSame('rising', KaurObservations::trend(6.0));
        $this->assertSame('falling', KaurObservations::trend(-6.0));

        $this->assertSame('normal', KaurObservations::status(null));
        $this->assertSame('normal', KaurObservations::status(-40.0));
        $this->assertSame('watch', KaurObservations::status(6.0));
        $this->assertSame('warning', KaurObservations::status(31.0));
    }
}
