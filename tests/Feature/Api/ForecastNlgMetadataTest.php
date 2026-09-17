<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\ApiKeyMiddleware;
use App\Models\Setting;
use App\Services\Nlg\ForecastNlgCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ForecastNlgMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_forecast_footer_uses_model_name_instead_of_provider_label(): void
    {
        $this->withoutMiddleware(ApiKeyMiddleware::class);

        Setting::setValue('display.language', 'en-us', 'string', 'display');
        Setting::setValue('display.unit_system', 'metric', 'select', 'display');
        Setting::setValue('station.timezone', 'UTC', 'string', 'station');
        Setting::setValue('station.latitude', 52.5, 'float', 'station');
        Setting::setValue('station.longitude', 4.7, 'float', 'station');
        Setting::setValue('forecast.default_source', 'fct_yrno_block.php', 'string', 'forecast');
        Setting::setValue('nlg.llm_enabled', true, 'boolean', 'nlg');
        Setting::setValue('nlg.provider', 'cerebras', 'string', 'nlg');
        Setting::setValue('nlg.model', 'custom-forecast-model', 'string', 'nlg');
        Setting::setValue('nlg.ai_days', 3, 'integer', 'nlg');
        Setting::setValue('nlg.ai_locales', ['en-us'], 'json', 'nlg');

        $now = now('UTC')->startOfHour()->addHour();
        $date = $now->toDateString();

        Cache::put('yrno_forecast_52.5_4.7', [
            'forecast' => [
                [
                    'time' => $now->toIso8601String(),
                    'temperature' => 10.0,
                    'symbol' => 'cloudy',
                    'precipitation_1h' => 0.0,
                    'wind_speed' => 14.0,
                    'wind_direction' => 220,
                    'cloud_cover' => 92,
                ],
                [
                    'time' => $now->copy()->addHours(6)->toIso8601String(),
                    'temperature' => 12.0,
                    'symbol' => 'cloudy',
                    'precipitation_1h' => 0.2,
                    'wind_speed' => 17.0,
                    'wind_direction' => 225,
                    'cloud_cover' => 88,
                ],
            ],
        ], now()->addHour());

        Cache::put(
            ForecastNlgCacheService::draftCacheKey('en-us', $date, 'metric'),
            'Expect overcast conditions with a chance of rain.',
            now()->addMinutes(ForecastNlgCacheService::CACHE_TTL_MINUTES)
        );
        Cache::put(
            ForecastNlgCacheService::finalCacheKey('en-us', $date, 'metric'),
            'Cloudy through the day with a light chance of rain and a moderate southwest breeze.',
            now()->addMinutes(ForecastNlgCacheService::CACHE_TTL_MINUTES)
        );

        $response = $this->getJson('/api/weather/forecast');

        $response->assertOk();
        $response->assertJsonPath('data.daily.0.nlg_meta.status_label', 'Enhanced with custom-forecast-model');
        $response->assertJsonPath('meta.nlg.ai_model', 'custom-forecast-model');

        $statusLabel = (string) $response->json('data.daily.0.nlg_meta.status_label');
        $this->assertStringNotContainsString('Cerebras', $statusLabel);
    }
}
