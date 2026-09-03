<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\WeatherReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The payload carries ch1..ch4 next to avg_24h_ch1 and level. Rendering the
 * whole map produced a row per key, including "Sensor 24" for avg_24h_ch1,
 * and every row read "--".
 */
class Pm25WidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The card only renders when the owner has switched the widget on,
        // and the server-rendered copy only exists with hybrid SSR turned on.
        Setting::setValue('widgets.enabled', json_encode(['current', 'pm25']), 'string', 'widgets');
        Setting::setValue('dashboard.hybrid_ssr_enabled', true, 'boolean', 'dashboard');
    }

    private function reading(array $attributes): void
    {
        WeatherReading::create(array_merge([
            'recorded_at' => now(),
            'temperature' => 18.0,
        ], $attributes));
    }

    public function test_a_channel_with_a_reading_is_shown(): void
    {
        $this->reading(['pm25_ch1' => 8.5, 'pm25_avg_24h_ch1' => 9.1]);

        $this->get('/')
            ->assertOk()
            ->assertSee('ch1: 8.5 µg/m³', false);
    }

    public function test_channels_without_a_sensor_are_left_out(): void
    {
        $this->reading(['pm25_ch1' => 8.5]);

        $response = $this->get('/');

        foreach (['ch2:', 'ch3:', 'ch4:'] as $absent) {
            $response->assertDontSee($absent, false);
        }
    }

    /** The 24 hour average and the level are not sensors and were labelled as such. */
    public function test_the_average_and_level_are_not_listed_as_sensors(): void
    {
        $this->reading(['pm25_ch1' => 8.5, 'pm25_avg_24h_ch1' => 9.1]);

        $this->get('/')
            ->assertDontSee('avg_24h_ch1:', false)
            ->assertDontSee('level:', false);
    }

    /** Zero is a real reading, not a missing sensor. */
    public function test_a_zero_reading_is_shown(): void
    {
        $this->reading(['pm25_ch1' => 0]);

        $this->get('/')->assertSee('ch1: 0 µg/m³', false);
    }
}
