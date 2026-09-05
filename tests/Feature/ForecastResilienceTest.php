<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Forecast\ForecastServiceFactory;
use App\Services\Forecast\OpenWeatherMapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Issue #99: every forecast source except Yr.no stopped updating.
 *
 * An encrypted setting that is empty, or that cannot be decrypted because
 * APP_KEY changed, reads back as null rather than the default. That null went
 * into a typed string property and threw a TypeError, which the factory did
 * not catch because it only catches Exception. The poller clears the cache
 * before it builds the service, so every run deleted the forecast and then
 * died before writing anything back.
 */
class ForecastResilienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::setValue('station.latitude', '52.57', 'float', 'station');
        Setting::setValue('station.longitude', '13.32', 'float', 'station');
    }

    private function encryptedRow(string $key, string $rawValue): void
    {
        Setting::create([
            'key' => $key,
            'value' => $rawValue,
            'type' => 'encrypted',
            'group' => 'openweathermap',
        ]);
    }

    public function test_an_empty_api_key_reads_as_the_default_not_null(): void
    {
        $this->encryptedRow('openweathermap.api_key', '');

        $this->assertSame('', Setting::getValue('openweathermap.api_key', ''));
    }

    /** APP_KEY changed between container restarts, so the stored key is gibberish. */
    public function test_an_undecryptable_api_key_reads_as_the_default_not_null(): void
    {
        $this->encryptedRow('openweathermap.api_key', 'not-actually-encrypted');

        $this->assertSame('', Setting::getValue('openweathermap.api_key', ''));
    }

    public function test_a_key_that_still_decrypts_is_untouched(): void
    {
        $this->encryptedRow('openweathermap.api_key', Crypt::encryptString('real-key'));

        $this->assertSame('real-key', Setting::getValue('openweathermap.api_key', ''));
    }

    public function test_building_the_service_with_an_unreadable_key_does_not_throw(): void
    {
        $this->encryptedRow('openweathermap.api_key', 'not-actually-encrypted');

        $service = new OpenWeatherMapService();

        $this->assertNull($service->fetchForecast(), 'no key means no forecast, not a crash');
    }

    public function test_the_factory_survives_a_source_it_cannot_build(): void
    {
        $this->encryptedRow('openweathermap.api_key', 'not-actually-encrypted');
        Setting::setValue('forecast.default_source', 'fct_darksky_block.php', 'string', 'forecast');

        $this->assertInstanceOf(OpenWeatherMapService::class, ForecastServiceFactory::make());
    }

    /** The poller cleared the cache first, so a failed fetch lost the last good forecast. */
    public function test_a_failed_poll_keeps_the_forecast_it_already_had(): void
    {
        Setting::setValue('forecast.default_source', 'fct_yrno_block.php', 'string', 'forecast');

        $good = ['updated_at' => now()->toIso8601String(), 'forecast' => [['time' => 'now', 'temperature' => 12]]];
        Cache::put('forecast_52.57_13.32', $good, now()->addHours(2));
        Cache::put('yrno_forecast_52.57_13.32', $good, now()->addHours(2));

        Http::fake(['*' => Http::response('upstream is down', 500)]);

        // A failed poll reports failure, which is right. What matters is what it left behind.
        $this->artisan('weather:poll-external --source=forecast')->run();

        $this->assertSame($good, Cache::get('forecast_52.57_13.32'), 'the good forecast was destroyed');
    }
}
