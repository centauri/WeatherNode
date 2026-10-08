<?php

declare(strict_types=1);

namespace Tests\Feature\Water;

use App\Models\Setting;
use App\Models\User;
use App\Services\Wave\KaurMarineService;
use App\Services\Wave\KaurSwanForecast;
use App\Services\Wave\OpenMeteoWaveService;
use App\Services\Wave\WaveServiceFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KaurMarineTest extends TestCase
{
    use RefreshDatabase;

    private const FILL = -32768;

    private int $runTime;

    private array $kaiaRuns = [];

    protected function setUp(): void
    {
        parent::setUp();
        Setting::setValue('station.latitude', '59.437', 'string', 'station');
        Setting::setValue('station.longitude', '24.745', 'string', 'station');
        Setting::setValue('waves.enabled', true, 'boolean', 'waves');
        Setting::setValue('waves.source', 'kaur', 'string', 'waves');
        $this->runTime = now('UTC')->startOfHour()->subHour()->timestamp;

        Http::fake([
            'www.ilmateenistus.ee/*' => fn () => Http::response($this->observations(), 200),
            'avaandmed.keskkonnaportaal.ee/api/lists/active/items/query' => fn () => Http::response(['documents' => array_map(fn ($run, $id) => [
                'id' => $id,
                'metadata' => ['RMTitle' => 'swan_'.gmdate('YmdH', $run).'.nc'],
                'fileMetadata' => [['id' => 1, 'name' => 'swan_'.gmdate('YmdH', $run).'.nc', 'size' => strlen($this->swanFile($run))]],
            ], $this->kaiaRuns, array_keys($this->kaiaRuns))], 200),
            'avaandmed.keskkonnaportaal.ee/api/lists/active/items/*/files/1' => fn ($request) => Http::response(
                $this->swanFile($this->kaiaRuns[(int) explode('/', $request->url())[7]]),
                200,
            ),
            '*' => Http::response([], 200),
        ]);
    }

    protected function tearDown(): void
    {
        @unlink(app(KaurSwanForecast::class)->path());
        parent::tearDown();
    }

    private function observations(): string
    {
        $timestamp = now()->timestamp;

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<observations timestamp="{$timestamp}">
	<station><name>Pirita</name><wmocode>86094</wmocode><longitude>24.82</longitude><latitude>59.47</latitude><waterlevel></waterlevel><waterlevel_eh2000>24</waterlevel_eh2000><watertemperature>13.3</watertemperature></station>
	<station><name>Paldiski (Põhjasadam)</name><wmocode>86100</wmocode><longitude>24.05</longitude><latitude>59.35</latitude><waterlevel></waterlevel><waterlevel_eh2000>25</waterlevel_eh2000><watertemperature>12.7</watertemperature></station>
	<station><name>Keila</name><wmocode>41107</wmocode><longitude>24.4347</longitude><latitude>59.3088</latitude><waterlevel>92</waterlevel><waterlevel_eh2000></waterlevel_eh2000><watertemperature>9.6</watertemperature></station>
</observations>
XML;
    }

    /**
     * A SWAN-shaped NetCDF classic (CDF-2) file: 2 by 2 grid, three hourly
     * records from $runTime, the south-west cell land.
     */
    private function swanFile(?int $runTime = null): string
    {
        $runTime ??= $this->runTime;
        $latitudes = [59.4, 59.5];
        $longitudes = [24.7, 24.8];
        $records = 3;

        $name = fn (string $s) => pack('N', strlen($s)).$s.str_repeat("\0", (4 - strlen($s) % 4) % 4);
        $attribute = function (string $attr, int $type, array $values) use ($name) {
            $data = match ($type) {
                2 => $values[0],
                3 => implode('', array_map(fn ($v) => pack('n', $v & 0xFFFF), $values)),
                6 => implode('', array_map(fn ($v) => pack('E', $v), $values)),
            };

            return $name($attr).pack('NN', $type, $type === 2 ? strlen($data) : count($values)).$data.str_repeat("\0", (4 - strlen($data) % 4) % 4);
        };
        $attributes = fn (array $list) => $list === [] ? pack('NN', 0, 0) : pack('NN', 12, count($list)).implode('', $list);
        $packed = fn (string $units) => $attributes([
            $attribute('units', 2, [$units]),
            $attribute('scale_factor', 6, [0.01]),
            $attribute('add_offset', 6, [0.0]),
            $attribute('_FillValue', 3, [self::FILL]),
        ]);

        $variables = [
            ['latitude', [1], $attributes([$attribute('units', 2, ['degrees_north'])]), 5, 8],
            ['longitude', [2], $attributes([$attribute('units', 2, ['degrees_east'])]), 5, 8],
            ['time', [0], $attributes([$attribute('units', 2, ['seconds since 1970-01-01'])]), 4, 4],
            ['hs', [0, 1, 2], $packed('m'), 3, 8],
            ['tps', [0, 1, 2], $packed('s'), 3, 8],
            ['thetap', [0, 1, 2], $packed('degrees'), 3, 8],
        ];

        $dimensions = pack('NN', 10, 3).$name('time').pack('N', 0).$name('latitude').pack('N', 2).$name('longitude').pack('N', 2);
        $headerLength = fn (array $offsets) => strlen('CDF'."\x02".pack('N', $records).$dimensions.pack('NN', 0, 0).$this->variableList($variables, $offsets, $name));
        $length = $headerLength(array_fill(0, count($variables), 0));

        $offsets = [];
        $offset = $length;
        foreach ($variables as $i => $variable) {
            if ($variable[1][0] !== 0) {
                $offsets[$i] = $offset;
                $offset += $variable[4];
            }
        }
        foreach ($variables as $i => $variable) {
            if ($variable[1][0] === 0) {
                $offsets[$i] = $offset;
                $offset += $variable[4];
            }
        }

        $body = pack('G*', ...$latitudes).pack('G*', ...$longitudes);
        for ($record = 0; $record < $records; $record++) {
            $height = fn (int $cell) => $cell === 0 ? self::FILL : 40 + 10 * $record + $cell;
            $body .= pack('N', $runTime + 3600 * $record);
            $body .= implode('', array_map(fn ($cell) => pack('n', $height($cell) & 0xFFFF), range(0, 3)));
            $body .= implode('', array_map(fn ($cell) => pack('n', ($cell === 0 ? self::FILL : 250) & 0xFFFF), range(0, 3)));
            $body .= implode('', array_map(fn ($cell) => pack('n', ($cell === 0 ? self::FILL : 9000) & 0xFFFF), range(0, 3)));
        }

        return 'CDF'."\x02".pack('N', $records).$dimensions.pack('NN', 0, 0).$this->variableList($variables, $offsets, $name).$body;
    }

    private function variableList(array $variables, array $offsets, \Closure $name): string
    {
        $list = pack('NN', 11, count($variables));
        foreach ($variables as $i => [$variableName, $dimensionIds, $attributes, $type, $size]) {
            $list .= $name($variableName).pack('N', count($dimensionIds)).pack('N*', ...$dimensionIds).$attributes
                .pack('NN', $type, $size).pack('J', $offsets[$i]);
        }

        return $list;
    }

    private function storeSwanFile(): void
    {
        $path = app(KaurSwanForecast::class)->path();
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        file_put_contents($path, $this->swanFile());
    }

    public function test_open_meteo_stays_the_default(): void
    {
        Setting::where('key', 'waves.source')->delete();

        $this->assertInstanceOf(OpenMeteoWaveService::class, WaveServiceFactory::make());
    }

    public function test_the_wave_forecast_comes_from_the_nearest_sea_cell(): void
    {
        $this->storeSwanFile();

        $data = app(KaurMarineService::class)->fetch();

        $this->assertSame('59.40°N, 24.80°E', $data['location']);
        $this->assertSame(0.51, $data['current_wave_height_m']);
        $this->assertSame(2.5, $data['current_wave_period_s']);
        $this->assertEquals(90, $data['current_wave_direction_deg']);
        $this->assertCount(3, $data['wave_series']);
        $this->assertFalse($data['has_swell']);
    }

    public function test_sea_temperature_comes_from_the_nearest_coastal_gauge(): void
    {
        $this->storeSwanFile();

        $data = app(KaurMarineService::class)->fetch();

        $this->assertSame(13.3, $data['current_sst_c']);
        $this->assertSame('Pirita', $data['sst_location']);
        $this->assertCount(1, $data['sst_series']);
    }

    public function test_a_chosen_gauge_replaces_the_nearest(): void
    {
        $this->storeSwanFile();
        Setting::setValue('waves.kaur_station_code', 'ee86100', 'string', 'waves');

        $data = app(KaurMarineService::class)->fetch();

        $this->assertSame(12.7, $data['current_sst_c']);
        $this->assertSame('Paldiski (Põhjasadam)', $data['sst_location']);
    }

    public function test_the_waves_tab_credits_kaur_and_drops_swell(): void
    {
        $this->storeSwanFile();

        $this->get(route('water.waves'))
            ->assertOk()
            ->assertSee('https://keskkonnaportaal.ee/et/avaandmed/ilma-mudelprognoosid', false)
            ->assertSee('peak period')
            ->assertDontSee('Swell Height')
            ->assertDontSee('Wind Waves')
            ->assertDontSee('Open-Meteo Marine</a>', false);
    }

    public function test_the_sea_temperature_tab_names_the_gauge(): void
    {
        $this->storeSwanFile();

        $this->get(route('water.temp'))
            ->assertOk()
            ->assertSee('Pirita')
            ->assertSee('Measured readings')
            ->assertSee('https://keskkonnaportaal.ee/et/avaandmed/hudroloogilise-seire-andmestik', false)
            ->assertDontSee('Open-Meteo Marine</a>', false);
    }

    public function test_without_a_downloaded_forecast_the_tab_waits(): void
    {
        $this->get(route('water.waves'))
            ->assertOk()
            ->assertSee('No wave data yet');
    }

    public function test_a_location_outside_the_grid_is_an_error(): void
    {
        $this->storeSwanFile();
        Setting::setValue('marine.latitude', '58.0', 'string', 'marine');

        $this->expectException(\RuntimeException::class);

        app(KaurMarineService::class)->fetch();
    }

    public function test_refresh_downloads_the_newest_run_once(): void
    {
        $this->kaiaRuns = [1 => $this->runTime - 12 * 3600, 2 => $this->runTime];

        $forecast = app(KaurSwanForecast::class);
        $forecast->refresh();
        $forecast->refresh();

        $this->assertFileExists($forecast->path());
        $this->assertSame($this->runTime, app(KaurMarineService::class)->fetch()['wave_series'][0]['timestamp_unix'] / 1000);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/items/2/files/1'));
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/items/1/files/1'));
        $this->assertCount(1, Http::recorded(fn ($request) => str_contains($request->url(), '/files/')));
    }

    public function test_saving_the_source_clears_the_cached_marine_data(): void
    {
        $key = 'waves_'.round(59.437, 2).'_'.round(24.745, 2);
        Cache::put($key, ['wave_series' => [1]], now()->addHour());
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.settings.update', 'waves'), [
            'waves_enabled' => '1',
            'waves_source' => 'open_meteo',
        ])->assertRedirect();

        $this->assertSame('open_meteo', Setting::getValue('waves.source'));
        $this->assertNull(Cache::get($key));
    }

    public function test_the_admin_lists_coastal_gauges_for_kaur(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.settings.group', 'waves'))
            ->assertOk()
            ->assertSee('Sea temperature gauge')
            ->assertSee('Pirita')
            ->assertDontSee('>Keila<', false);
    }
}
