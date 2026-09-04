<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DwdService shipped without any way to reach it: the forecast picker is built
 * from a seeded options string that did not list DWD, there was no settings
 * group for the station id, and the scheduler pointed at a group that 404s.
 */
class DwdConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_dwd_is_offered_in_the_forecast_source_picker(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $options = Setting::where('key', 'forecast.default_source')->value('options');

        $this->assertStringContainsString('fct_dwd_block.php:DWD', (string) $options);
    }

    public function test_an_admin_can_select_dwd_and_it_is_the_service_that_answers(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $this->actingAs($this->admin())
            ->post('/admin/settings/forecast', ['forecast_default_source' => 'fct_dwd_block.php'])
            ->assertRedirect();

        $this->assertSame('fct_dwd_block.php', Setting::getValue('forecast.default_source'));
        $this->assertInstanceOf(
            \App\Services\Forecast\DwdService::class,
            \App\Services\Forecast\ForecastServiceFactory::make()
        );
    }

    public function test_the_dwd_settings_page_exists(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $this->actingAs($this->admin())
            ->get('/admin/settings/dwd')
            ->assertOk()
            ->assertSee('DWD');
    }

    /** The station is optional, but it has to be reachable to be optional. */
    public function test_the_station_id_is_a_setting_an_admin_can_write(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $this->assertNotNull(Setting::where('key', 'dwd.station_id')->first());

        $this->actingAs($this->admin())
            ->post('/admin/settings/dwd', ['dwd_station_id' => '10382'])
            ->assertRedirect();

        $this->assertSame('10382', Setting::getValue('dwd.station_id'));
    }

    /**
     * The sidebar is hardcoded and the group registry is separate, so they
     * drift apart quietly. aemet, and now dwd, were registered pages with no
     * link, reachable only by typing the URL.
     */
    public function test_every_data_source_page_is_reachable_from_the_sidebar(): void
    {
        $sidebar = file_get_contents(resource_path('views/layouts/admin.blade.php'));

        foreach (['dwd', 'aemet'] as $group) {
            $this->assertStringContainsString(
                "route('admin.settings.group', '{$group}')",
                $sidebar,
                "{$group} has a settings page but no way to reach it"
            );
        }
    }

    /** An install that upgrades gets the option too, without reseeding. */
    public function test_the_migration_adds_dwd_to_an_existing_options_string(): void
    {
        Setting::where('key', 'forecast.default_source')->delete();
        Setting::create([
            'key' => 'forecast.default_source',
            'value' => 'fct_yrno_block.php',
            'type' => 'select',
            'group' => 'forecast',
            'description' => 'Default forecast source',
            'options' => 'fct_yrno_block.php:Yr.no,fct_aemet_block.php:AEMET',
        ]);

        (require base_path('database/migrations/2026_09_04_120000_add_dwd_forecast_settings.php'))->up();

        $options = (string) Setting::where('key', 'forecast.default_source')->value('options');
        $this->assertStringContainsString('fct_dwd_block.php:DWD', $options);
        $this->assertStringContainsString('fct_aemet_block.php:AEMET', $options, 'existing options were lost');
        $this->assertSame(1, substr_count($options, 'fct_dwd_block.php'), 'running twice must not duplicate');
    }
}
