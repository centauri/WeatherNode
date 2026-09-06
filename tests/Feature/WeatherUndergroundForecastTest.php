<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Forecast\WeatherUndergroundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The parser was written for the v1 API while the service calls v3.
 *
 * tests/Fixtures/wu_daily_5day.json is a real captured response. In v3 the
 * wind, icon and wind direction live under daypart[0], not at the top level,
 * so the old parser counted zero days and returned an empty forecast from a
 * perfectly good 200. Issue #99.
 */
class WeatherUndergroundForecastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::setValue('station.latitude', '52.57', 'float', 'station');
        Setting::setValue('station.longitude', '13.32', 'float', 'station');
        Setting::setValue('wunderground.api_key', 'test-key', 'encrypted', 'wunderground');
    }

    private function fakeApi(bool $hourlyWorks = false): void
    {
        Http::fake([
            '*/daily/5day*' => Http::response(
                json_decode(file_get_contents(base_path('tests/Fixtures/wu_daily_5day.json')), true)
            ),
            // Not every Weather Underground key can reach the hourly product.
            '*/hourly/15day*' => $hourlyWorks
                ? Http::response(['validTimeUtc' => [], 'temperature' => []])
                : Http::response(['errors' => [['error' => ['code' => 'AUT-0001']]]], 401),
        ]);
    }

    public function test_it_returns_days_from_a_real_response(): void
    {
        $this->fakeApi();

        $forecast = app(WeatherUndergroundService::class)->fetchForecast();

        $this->assertNotNull($forecast);
        $this->assertNotEmpty($forecast['forecast'], 'a good 200 produced no forecast at all');
    }

    /** Wind and icon come from daypart, which is where v3 puts them. */
    public function test_each_day_has_wind_and_a_symbol(): void
    {
        $this->fakeApi();

        $days = app(WeatherUndergroundService::class)->fetchForecast()['forecast'];

        $withWind = array_filter($days, fn ($d) => $d['wind_speed'] !== null);
        $withSymbol = array_filter($days, fn ($d) => !empty($d['symbol']));

        $this->assertNotEmpty($withWind, 'wind was read from the top level, where v3 has none');
        $this->assertNotEmpty($withSymbol, 'no day got an icon');
    }

    /**
     * The first day is special: after its daytime has passed the API sends null
     * for the day part and for temperatureMax, and only the night part is left.
     */
    public function test_the_current_day_survives_a_missing_day_part(): void
    {
        $this->fakeApi();

        $days = app(WeatherUndergroundService::class)->fetchForecast()['forecast'];

        $this->assertNotNull($days[0]['symbol'] ?? null, 'today fell back to nothing');
        $this->assertNotNull($days[0]['wind_speed'] ?? null);
    }

    public function test_temperatures_are_read_from_the_top_level(): void
    {
        $this->fakeApi();

        $days = app(WeatherUndergroundService::class)->fetchForecast()['forecast'];
        $temps = array_filter(array_column($days, 'temperature'), 'is_numeric');

        $this->assertNotEmpty($temps);
        foreach ($temps as $t) {
            $this->assertGreaterThan(-60, $t);
            $this->assertLessThan(60, $t);
        }
    }

    /** An empty result must not be cached as if it were data. */
    public function test_a_response_it_cannot_parse_returns_nothing(): void
    {
        Http::fake(['*' => Http::response(['dayOfWeek' => []])]);

        $this->assertNull(app(WeatherUndergroundService::class)->fetchForecast());
    }
}
