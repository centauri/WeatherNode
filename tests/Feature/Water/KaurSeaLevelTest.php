<?php

declare(strict_types=1);

namespace Tests\Feature\Water;

use App\Models\Setting;
use App\Models\User;
use App\Services\Tide\KaurSeaLevelSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KaurSeaLevelTest extends TestCase
{
    use RefreshDatabase;

    private float $piritaLevel = 24;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::setValue('station.latitude', '59.437', 'string', 'station');
        Setting::setValue('station.longitude', '24.745', 'string', 'station');
        Setting::setValue('tide.enabled', true, 'boolean', 'tide');
        Setting::setValue('tide.source', 'kaur', 'string', 'tide');
        Setting::setValue('tide.kaur_station_code', 'ee86094', 'string', 'tide');
        Setting::setValue('tide.station_name', 'Pirita', 'string', 'tide');

        Http::fake([
            'www.ilmateenistus.ee/*' => fn () => Http::response($this->observations(now()->timestamp), 200),
            '*' => Http::response([], 200),
        ]);
    }

    private function observations(int $timestamp): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<observations timestamp="{$timestamp}">
	<station><name>Pirita</name><wmocode>86094</wmocode><longitude>24.82</longitude><latitude>59.47</latitude><waterlevel></waterlevel><waterlevel_eh2000>{$this->piritaLevel}</waterlevel_eh2000><watertemperature>13.3</watertemperature></station>
	<station><name>Paldiski (Põhjasadam)</name><wmocode>86100</wmocode><longitude>24.05</longitude><latitude>59.35</latitude><waterlevel></waterlevel><waterlevel_eh2000>25</waterlevel_eh2000><watertemperature>13</watertemperature></station>
	<station><name>Keila</name><wmocode>41107</wmocode><longitude>24.4347</longitude><latitude>59.3088</latitude><waterlevel>92</waterlevel><waterlevel_eh2000></waterlevel_eh2000><watertemperature>9.6</watertemperature></station>
</observations>
XML;
    }

    public function test_the_stations_are_the_coastal_gauges(): void
    {
        $stations = app(KaurSeaLevelSource::class)->getStations();

        $this->assertSame(['ee86100' => ['name' => 'Paldiski (Põhjasadam)'], 'ee86094' => ['name' => 'Pirita']], $stations);
    }

    public function test_the_tides_tab_shows_measured_sea_level_without_forecast_blocks(): void
    {
        $this->get(route('water'))
            ->assertOk()
            ->assertSee('EH2000')
            ->assertSee('About EH2000')
            ->assertSee('Water temperature')
            ->assertDontSee('Next High Tide')
            ->assertDontSee('Tide Forecast')
            ->assertDontSee('About MSL');
    }

    public function test_a_station_without_sea_level_is_an_error(): void
    {
        $this->expectException(\RuntimeException::class);

        app(KaurSeaLevelSource::class)->fetchTideData('ee41107');
    }

    public function test_a_fall_over_three_hours_shows_as_falling(): void
    {
        $source = app(KaurSeaLevelSource::class);
        $start = now()->startOfHour();

        foreach ([30, 26, 21, 17] as $hour => $level) {
            $this->travelTo($start->copy()->addHours($hour));
            Cache::forget('kaur_observations_snapshot');
            $this->piritaLevel = $level;
            $data = $source->fetchTideData('ee86094');
        }

        $this->assertSame('falling', $data['trend']);
        $this->assertSame(17.0, $data['current_level_cm']);
        $this->assertSame([], $data['tides']);
        $this->assertCount(4, $data['series']);
    }

    public function test_the_admin_offers_the_coastal_gauges(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.settings.group', 'tide'))
            ->assertOk()
            ->assertSee('Pirita (ee86094)')
            ->assertDontSee('(ee41107)');
    }
}
