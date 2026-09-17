<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\ApiKeyMiddleware;
use App\Models\Setting;
use App\Services\Nlg\ForecastNlgCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Issue #126. The forecast paragraph was written once per language per day and
 * handed to everybody, so the temperatures in it were always Celsius however
 * the site or the reader had things set.
 *
 * The trap is that readers choose units individually. Writing the paragraph in
 * whoever asked first would have been worse than the bug: one Fahrenheit
 * reader would have flipped the text for every Celsius reader after them.
 */
class ForecastNlgUnitsTest extends TestCase
{
    use RefreshDatabase;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ApiKeyMiddleware::class);

        Setting::setValue('display.language', 'en-us', 'string', 'display');
        Setting::setValue('station.timezone', 'UTC', 'string', 'station');
        Setting::setValue('station.latitude', 52.5, 'float', 'station');
        Setting::setValue('station.longitude', 4.7, 'float', 'station');
        Setting::setValue('forecast.default_source', 'fct_yrno_block.php', 'string', 'forecast');

        $now = now('UTC')->startOfHour()->addHour();
        $this->date = $now->toDateString();

        Cache::put('yrno_forecast_52.5_4.7', [
            'forecast' => [
                [
                    'time' => $now->toIso8601String(),
                    'temperature' => 12.0,
                    'symbol' => 'cloudy',
                    'precipitation_1h' => 0.0,
                    'wind_speed' => 14.0,
                    'wind_direction' => 220,
                    'cloud_cover' => 92,
                ],
                [
                    'time' => $now->copy()->addHours(6)->toIso8601String(),
                    'temperature' => 18.0,
                    'symbol' => 'cloudy',
                    'precipitation_1h' => 0.0,
                    'wind_speed' => 17.0,
                    'wind_direction' => 225,
                    'cloud_cover' => 88,
                ],
            ],
        ], now()->addHour());
    }

    private function narrativeFor(string $units): string
    {
        $response = $this->getJson('/api/weather/forecast?units=' . $units);
        $response->assertOk();

        return (string) $response->json('data.daily.0.nlg_text');
    }

    public function test_an_imperial_reader_is_told_fahrenheit(): void
    {
        $this->assertStringContainsString('°F', $this->narrativeFor('imperial'));
    }

    public function test_a_metric_reader_is_told_celsius(): void
    {
        $this->assertStringContainsString('°C', $this->narrativeFor('metric'));
    }

    /**
     * The heart of it. One reader's choice must not be served to the next, and
     * the cache is shared, so this is the test that would have caught a naive
     * fix.
     */
    public function test_one_readers_units_are_not_served_to_the_next(): void
    {
        $imperial = $this->narrativeFor('imperial');
        $metric = $this->narrativeFor('metric');

        $this->assertStringContainsString('°F', $imperial);
        $this->assertStringNotContainsString('°C', $imperial);

        $this->assertStringContainsString('°C', $metric);
        $this->assertStringNotContainsString('°F', $metric);
    }

    /** And the other way round, in case the order of arrival matters. */
    public function test_it_holds_when_the_metric_reader_arrives_first(): void
    {
        $metric = $this->narrativeFor('metric');
        $imperial = $this->narrativeFor('imperial');

        $this->assertStringContainsString('°C', $metric);
        $this->assertStringContainsString('°F', $imperial);
    }

    /** Two scales means two cached texts, not one overwriting the other. */
    public function test_the_two_are_cached_apart(): void
    {
        $this->narrativeFor('imperial');
        $this->narrativeFor('metric');

        $celsius = Cache::get(ForecastNlgCacheService::draftCacheKey('en-us', $this->date, 'metric'));
        $fahrenheit = Cache::get(ForecastNlgCacheService::draftCacheKey('en-us', $this->date, 'imperial'));

        $this->assertNotNull($celsius);
        $this->assertNotNull($fahrenheit);
        $this->assertNotSame($celsius, $fahrenheit);
    }

    /** UK and Scandinavia read Celsius, so they share the Celsius text. */
    public function test_celsius_systems_share_one_cached_text(): void
    {
        $this->assertSame(
            ForecastNlgCacheService::draftCacheKey('en-us', $this->date, 'metric'),
            ForecastNlgCacheService::draftCacheKey('en-us', $this->date, 'uk'),
        );
        $this->assertNotSame(
            ForecastNlgCacheService::draftCacheKey('en-us', $this->date, 'metric'),
            ForecastNlgCacheService::draftCacheKey('en-us', $this->date, 'imperial'),
        );
    }
}
