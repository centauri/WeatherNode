<?php

declare(strict_types=1);

namespace Tests\Feature\Water;

use App\Models\Setting;
use App\Models\User;
use App\Services\River\KaurRiverService;
use App\Services\River\RijkswaterstaatRiverService;
use App\Services\River\RiverProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KaurRiversTest extends TestCase
{
    use RefreshDatabase;

    private float $keilaLevel = 92;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::setValue('station.latitude', '59.437', 'string', 'station');
        Setting::setValue('station.longitude', '24.745', 'string', 'station');
        Setting::setValue('rivers.kaur.enabled', true, 'boolean', 'rivers');
    }

    private function observations(int $timestamp, float $keila = 92, float $vaana = 61): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<observations timestamp="{$timestamp}">
	<station><name>Tallinn-Harku</name><wmocode>26038</wmocode><longitude>24.6029</longitude><latitude>59.3981</latitude><waterlevel></waterlevel><waterlevel_eh2000></waterlevel_eh2000><watertemperature></watertemperature></station>
	<station><name>Pirita</name><wmocode>86094</wmocode><longitude>24.82</longitude><latitude>59.47</latitude><waterlevel></waterlevel><waterlevel_eh2000>24</waterlevel_eh2000><watertemperature>13.3</watertemperature></station>
	<station><name>Keila</name><wmocode>41107</wmocode><longitude>24.4347</longitude><latitude>59.3088</latitude><waterlevel>{$keila}</waterlevel><waterlevel_eh2000></waterlevel_eh2000><watertemperature>9.6</watertemperature></station>
	<station><name>Hüüru</name><wmocode>41103</wmocode><longitude>24.38</longitude><latitude>59.39</latitude><waterlevel>{$vaana}</waterlevel><waterlevel_eh2000></waterlevel_eh2000><watertemperature>9.3</watertemperature></station>
	<station><name>Kloostrimetsa</name><wmocode>41157</wmocode><longitude>24.88</longitude><latitude>59.46</latitude><waterlevel>116</waterlevel><waterlevel_eh2000></waterlevel_eh2000><watertemperature>10.2</watertemperature></station>
	<station><name>Tartu</name><wmocode>41025</wmocode><longitude>26.73</longitude><latitude>58.38</latitude><waterlevel>83</waterlevel><waterlevel_eh2000></waterlevel_eh2000><watertemperature>11.5</watertemperature></station>
	<station><name>Tilgu</name><wmocode>86099</wmocode><longitude>24.05</longitude><latitude>59.40</latitude><waterlevel>24</waterlevel><waterlevel_eh2000></waterlevel_eh2000><watertemperature>12.5</watertemperature></station>
</observations>
XML;
    }

    private function hydrology(): array
    {
        return [
            ['jaam_kood' => 41107, 'veekogu_nimi' => 'Keila j.', 'valgala_nimi' => 'Keila jõgi'],
            ['jaam_kood' => 41103, 'veekogu_nimi' => 'Vääna j.', 'valgala_nimi' => 'Vääna jõgi'],
            ['jaam_kood' => 41157, 'veekogu_nimi' => 'Pirita j.', 'valgala_nimi' => 'Pirita jõgi'],
            ['jaam_kood' => 41025, 'veekogu_nimi' => 'Emajõgi', 'valgala_nimi' => 'Emajõgi'],
            ['jaam_kood' => 86094, 'veekogu_nimi' => null, 'valgala_nimi' => '---'],
        ];
    }

    private function fakeKaur(?string $xml = null, bool $hydrologyUp = true): void
    {
        Http::fake([
            'www.ilmateenistus.ee/*' => fn () => Http::response($xml ?? $this->observations(now()->timestamp, keila: $this->keilaLevel), 200),
            'keskkonnaandmed.envir.ee/*' => $hydrologyUp
                ? Http::response($this->hydrology(), 200)
                : Http::response('', 503),
            '*' => Http::response([], 200),
        ]);
    }

    public function test_the_rivers_tab_shows_the_gauges_nearest_the_station(): void
    {
        $this->fakeKaur();

        $this->get(route('water.rivers'))
            ->assertOk()
            ->assertSee('Keila jõgi')
            ->assertSee('Vääna jõgi')
            ->assertSee('Pirita jõgi')
            ->assertDontSee('Emajõgi')
            ->assertSee('cm gauge zero')
            ->assertDontSee('cm NAP')
            ->assertSee('Water temperature');
    }

    public function test_the_rivers_tab_credits_keskkonnaagentuur_and_not_rijkswaterstaat(): void
    {
        $this->fakeKaur();

        $this->get(route('water.rivers'))
            ->assertOk()
            ->assertSee('https://keskkonnaportaal.ee/et/avaandmed/hudroloogilise-seire-andmestik', false)
            ->assertSeeInOrder(['River level data provided by', 'Keskkonnaagentuur</a>'], false)
            ->assertDontSee('waterinfo.rws.nl', false);
    }

    public function test_chosen_stations_replace_the_nearest(): void
    {
        $this->fakeKaur();
        Setting::setValue('rivers.kaur.stations', json_encode(['ee41025']), 'string', 'rivers');

        $data = app(KaurRiverService::class)->fetch(['ee41025']);

        $this->assertSame(['ee41025'], array_keys($data));
        $this->assertSame('Emajõgi', $data['ee41025']['river']);
        $this->assertSame(83.0, $data['ee41025']['level_cm']);
    }

    public function test_coastal_gauges_and_strays_stay_out_of_the_catalog(): void
    {
        $this->fakeKaur();

        $catalog = app(\App\Services\River\KaurStationCatalogService::class)->getRiverStations();

        $this->assertArrayNotHasKey('ee86094', $catalog);
        $this->assertArrayNotHasKey('ee86099', $catalog);
        $this->assertArrayNotHasKey('ee26038', $catalog);
        $this->assertCount(4, $catalog);
    }

    public function test_without_the_hydrology_api_stations_are_named_after_themselves(): void
    {
        $this->fakeKaur(hydrologyUp: false);

        $catalog = app(\App\Services\River\KaurStationCatalogService::class)->getRiverStations();

        $this->assertSame('Keila', $catalog['ee41107']['river']);
    }

    public function test_a_rise_over_three_hours_shows_as_rising(): void
    {
        $this->fakeKaur();
        $service = app(KaurRiverService::class);
        $start = now()->startOfHour();

        foreach ([92, 95, 99, 104] as $hour => $level) {
            $this->travelTo($start->copy()->addHours($hour));
            Cache::forget('kaur_observations_snapshot');
            $this->keilaLevel = $level;
            $data = $service->fetch(['ee41107']);
        }

        $this->assertSame('rising', $data['ee41107']['trend']);
        $this->assertSame('watch', $data['ee41107']['status']);
        $this->assertCount(4, $data['ee41107']['series']);
    }

    public function test_repeated_polls_within_the_hour_add_one_reading(): void
    {
        $this->fakeKaur($this->observations(1_791_447_506));
        $service = app(KaurRiverService::class);

        $service->fetch(['ee41107']);
        Cache::forget('kaur_observations_snapshot');
        $data = $service->fetch(['ee41107']);

        $this->assertCount(1, $data['ee41107']['series']);
        $this->assertSame('steady', $data['ee41107']['trend']);
    }

    public function test_kaur_defaults_to_the_three_gauges_nearest_the_station(): void
    {
        $this->fakeKaur();

        $this->assertSame(['ee41157', 'ee41103', 'ee41107'], RiverProviderRegistry::defaultStations('kaur'));
        $this->assertSame(RijkswaterstaatRiverService::DEFAULT_STATIONS, RiverProviderRegistry::defaultStations('rws'));
    }

    public function test_the_admin_ticks_the_stations_the_page_shows(): void
    {
        $this->fakeKaur();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.settings.group', 'rivers'))
            ->assertOk()
            ->assertSee('[&quot;ee41157&quot;,&quot;ee41103&quot;,&quot;ee41107&quot;]', false);
    }

    public function test_only_rijkswaterstaat_takes_custom_station_codes(): void
    {
        $this->fakeKaur();
        $admin = User::factory()->create(['is_admin' => true]);

        $page = $this->actingAs($admin)->get(route('admin.settings.group', 'rivers'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($page, 'Custom station codes'));
        $this->assertStringContainsString('stations from KAUR catalog', $page);
    }

    public function test_saving_new_stations_shows_them_on_the_next_page_load(): void
    {
        $this->fakeKaur();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->get(route('water.rivers'))->assertOk()->assertDontSee('Emajõgi');

        $this->actingAs($admin)->post(route('admin.settings.update', 'rivers'), [
            'providers' => ['kaur' => ['enabled' => '1', 'stations_json' => json_encode(['ee41025']), 'custom_json' => '[]']],
        ])->assertRedirect();

        $this->get(route('water.rivers'))->assertOk()->assertSee('Emajõgi')->assertDontSee('Keila jõgi');
    }

    public function test_the_poller_caches_kaur_data(): void
    {
        $this->fakeKaur();

        $this->artisan('weather:poll-external', ['--source' => 'rivers'])->assertSuccessful();

        $this->assertArrayHasKey('ee41107', Cache::get('rivers_kaur'));
    }
}
