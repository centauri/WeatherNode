<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Models\WeatherReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The More sensors card, for the newer Ecowitt sensors in extra_sensors. */
class MoreSensorsWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_can_switch_it_on(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.settings.widgets'))
            ->assertOk()
            ->assertSee('More Sensors');
    }

    public function test_the_page_shows_it_when_switched_on(): void
    {
        Setting::setValue('dashboard.hybrid_ssr_enabled', true, 'boolean', 'advanced');
        Setting::setValue('widgets.enabled', ['current', 'more_sensors'], 'json', 'widgets');
        WeatherReading::query()->create([
            'recorded_at' => now(),
            'temperature' => 20,
            'extra_sensors' => ['bgt' => 22.2, 'wetness' => true],
        ]);

        $content = (string) $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/ssr-fallback-block[^>]*data-widget="more_sensors"/', $content);
        $this->assertStringContainsString('Black globe: 22.2 °C', $content);
        $this->assertStringContainsString('data-widget="more_sensors"', $content);
    }
}
