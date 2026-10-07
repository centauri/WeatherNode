<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Models\WeatherReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The admin battery tiles draw the same icons as the dashboard card. */
class AdminBatteryCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_tiles_use_battery_capacitor_and_plug_icons(): void
    {
        WeatherReading::query()->create(['recorded_at' => now(), 'temperature' => 20, 'battery_status' => [
            'haptic_array_battery' => 3.2, 'haptic_array_capacitor' => 4.2, 'pm25batt1' => 6, 'wh65batt' => 0,
        ]]);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-battery-icon="battery"', false)
            ->assertSee('data-battery-icon="capacitor"', false)
            ->assertSee('data-battery-icon="mains"', false);
    }

    public function test_spanish_admins_no_longer_read_agreed(): void
    {
        Setting::setValue('display.language', 'es-es', 'select', 'display');
        app()->setLocale('es-es');
        WeatherReading::query()->create(['recorded_at' => now(), 'temperature' => 20, 'battery_status' => ['wh65batt' => 0]]);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('De acuerdo.');
    }
}
