<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Forecast\DwdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * DWD publishes MOSMIX as a zipped KML per station. Berlin-Tegel (10382) is
 * captured in tests/Fixtures so the parsing is checked against a real file
 * rather than something hand-written to match the parser.
 */
class DwdForecastServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::setValue('station.latitude', '52.57', 'float', 'station');
        Setting::setValue('station.longitude', '13.32', 'float', 'station');
        Setting::setValue('dwd.station_id', '10382', 'string', 'dwd');

    }

    /**
     * Opt in rather than stubbing in setUp. Http::fake() merges, so a success
     * stub registered here would keep matching and quietly win over the 404
     * the failure test registers later.
     */
    private function fakeCatalogue(): void
    {
        Http::fake([
            'www.dwd.de/*' => Http::response(
                file_get_contents(base_path('tests/Fixtures/mosmix_stations.txt')),
                200
            ),
        ]);
    }

    private function fakeMosmix(): void
    {
        Http::fake([
            'opendata.dwd.de/*' => Http::response(
                file_get_contents(base_path('tests/Fixtures/mosmix_10382.kmz')),
                200,
                ['Content-Type' => 'application/vnd.google-earth.kmz']
            ),
        ]);
    }

    public function test_it_returns_hourly_entries_with_every_field_the_interface_promises(): void
    {
        $this->fakeMosmix();

        $hours = app(DwdService::class)->getHourlyForecast(24);

        $this->assertCount(24, $hours);

        foreach (['time', 'temperature', 'wind_speed', 'wind_direction', 'symbol',
            'precipitation_1h', 'humidity', 'cloud_cover'] as $field) {
            $this->assertArrayHasKey($field, $hours[0], "hourly entry is missing {$field}");
        }
    }

    /** MOSMIX gives temperatures in Kelvin. 289.95 K is 16.8 C, not 289.95. */
    public function test_temperatures_are_converted_from_kelvin(): void
    {
        $this->fakeMosmix();

        $first = app(DwdService::class)->getHourlyForecast(1)[0];

        $this->assertEqualsWithDelta(16.8, $first['temperature'], 0.05);
    }

    /** PPPP is in pascals. 101240 Pa is 1012.4 hPa. */
    public function test_pressure_is_converted_to_hectopascals(): void
    {
        $this->fakeMosmix();

        $first = app(DwdService::class)->getHourlyForecast(1)[0];

        $this->assertEqualsWithDelta(1012.4, $first['pressure'], 0.05);
    }

    /** ww 61 is light rain. The app draws icons from Yr.no style names. */
    public function test_the_wmo_code_becomes_a_symbol_the_icons_understand(): void
    {
        $this->fakeMosmix();

        $first = app(DwdService::class)->getHourlyForecast(1)[0];

        $this->assertStringContainsString('rain', $first['symbol']);
    }

    public function test_it_returns_daily_entries_with_a_high_and_a_low(): void
    {
        $this->fakeMosmix();

        $days = app(DwdService::class)->getDailyForecast(5);

        $this->assertCount(5, $days);
        foreach (['date', 'temp_high', 'temp_low', 'symbol', 'precipitation', 'wind_speed'] as $field) {
            $this->assertArrayHasKey($field, $days[0], "daily entry is missing {$field}");
        }
        $this->assertGreaterThanOrEqual($days[0]['temp_low'], $days[0]['temp_high']);
    }

    /**
     * The catalogue writes coordinates as degrees and minutes. Berlin-Tegel is
     * "52.34 13.19" and sits at 52.57, 13.32. Reading those as decimal degrees
     * moves the station about 25km and picks the wrong one.
     */
    public function test_it_finds_the_nearest_station_when_none_is_configured(): void
    {
        Setting::setValue('dwd.station_id', '', 'string', 'dwd');
        $this->fakeCatalogue();
        $this->fakeMosmix();

        $hours = app(DwdService::class)->getHourlyForecast(1);

        $this->assertNotEmpty($hours, 'no station was resolved, so no forecast came back');
        Http::assertSent(fn ($request) => str_contains($request->url(), '10382'));
    }

    /**
     * A station at 52.45, 13.45 is nearest Berlin-Tempelhof once the catalogue
     * is read as degrees and minutes. Read as decimal degrees it resolves to
     * Berlin-Tegel instead, which is the wrong airport and 12km away.
     */
    public function test_the_catalogue_is_read_as_degrees_and_minutes(): void
    {
        Setting::setValue('dwd.station_id', '', 'string', 'dwd');
        Setting::setValue('station.latitude', '52.45', 'float', 'station');
        Setting::setValue('station.longitude', '13.45', 'float', 'station');
        $this->fakeCatalogue();
        $this->fakeMosmix();

        app(DwdService::class)->getHourlyForecast(1);

        Http::assertSent(fn ($request) => str_contains($request->url(), '10384'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '10382'));
    }

    public function test_a_station_far_from_germany_still_resolves_to_its_nearest(): void
    {
        Setting::setValue('dwd.station_id', '', 'string', 'dwd');
        Setting::setValue('station.latitude', '52.31', 'float', 'station');   // Amsterdam
        Setting::setValue('station.longitude', '4.76', 'float', 'station');
        $this->fakeCatalogue();
        $this->fakeMosmix();

        app(DwdService::class)->getHourlyForecast(1);

        Http::assertSent(fn ($request) => str_contains($request->url(), '06240'));
    }

    public function test_a_failed_download_returns_nothing_rather_than_throwing(): void
    {
        Http::fake(['opendata.dwd.de/*' => Http::response('gone', 404)]);

        $this->assertSame([], app(DwdService::class)->getHourlyForecast(6));
    }
}
