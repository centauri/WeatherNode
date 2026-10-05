<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Support\RainGauge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #132. Auto picks the gauge for most stations, but a station with both a
 * WS90 and a WH40 is the owner's call, as it is in the Ecowitt app.
 */
class EcowittRainGaugeSettingTest extends TestCase
{
    use RefreshDatabase;

    /** Existing installs get the setting from the migration, set to auto. */
    public function test_existing_installs_get_the_setting_on_auto(): void
    {
        $this->assertSame(RainGauge::AUTO, Setting::getValue(RainGauge::SETTING));
    }

    public function test_the_ecowitt_page_offers_it(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.settings.group', 'ecowitt'))
            ->assertOk()
            ->assertSee('name="ecowitt_rain_gauge"', false)
            ->assertSee('Piezo (WS90 / WS85)');
    }

    public function test_the_owner_can_change_it(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.settings.update', 'ecowitt'), [
            'ecowitt_rain_gauge' => RainGauge::PIEZO,
        ]);

        $this->assertSame(RainGauge::PIEZO, Setting::getValue(RainGauge::SETTING));
    }
}
