<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Services\Nlg\ForecastNlgCacheService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #126. Forecast paragraphs are now written per temperature scale, and
 * the AI pass rewrites prose, which cannot be converted back into numbers. So
 * polishing both scales means asking the provider twice.
 *
 * That is a bill, not a detail, so the owner decides. Off by default: an
 * install that upgrades into this should not find its token use doubled
 * without anyone choosing it.
 */
class NlgBothScalesToggleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_it_is_off_on_a_fresh_install(): void
    {
        $this->seed(SettingsSeeder::class);

        $this->assertFalse((bool) Setting::getValue('nlg.rephrase_both_units'));
    }

    /** An install upgrading from before the setting existed pays no more. */
    public function test_an_install_without_the_row_pays_no_more(): void
    {
        $this->assertFalse(ForecastNlgCacheService::rephrasesBothScales());
    }

    public function test_the_admin_page_offers_the_toggle(): void
    {
        $this->seed(SettingsSeeder::class);

        $this->actingAs($this->admin())
            ->get(route('admin.settings.group', 'nlg'))
            ->assertOk()
            ->assertSee('nlg_rephrase_both_units', false);
    }

    public function test_the_owner_can_turn_it_on(): void
    {
        $this->seed(SettingsSeeder::class);

        $this->actingAs($this->admin())->post(route('admin.settings.update', 'nlg'), [
            'nlg_llm_enabled' => '1',
            'nlg_rephrase_both_units' => '1',
        ]);

        $this->assertTrue(ForecastNlgCacheService::rephrasesBothScales());
    }

    public function test_and_turn_it_off_again(): void
    {
        $this->seed(SettingsSeeder::class);
        Setting::setValue('nlg.rephrase_both_units', true, 'boolean', 'nlg');

        $this->actingAs($this->admin())->post(route('admin.settings.update', 'nlg'), [
            'nlg_llm_enabled' => '1',
        ]);

        $this->assertFalse(ForecastNlgCacheService::rephrasesBothScales());
    }

    /** Off means one scale, the one the site is set to. On means both. */
    public function test_the_setting_decides_which_scales_are_polished(): void
    {
        $this->seed(SettingsSeeder::class);
        Setting::setValue('display.unit_system', 'imperial', 'select', 'display');

        $this->assertSame(['imperial'], ForecastNlgCacheService::scalesToRephrase());

        Setting::setValue('nlg.rephrase_both_units', true, 'boolean', 'nlg');

        $this->assertSame(ForecastNlgCacheService::SCALE_UNITS, ForecastNlgCacheService::scalesToRephrase());
    }

    /** A Celsius site polishes the Celsius text, whichever Celsius system it is. */
    public function test_a_celsius_site_polishes_celsius(): void
    {
        $this->seed(SettingsSeeder::class);
        Setting::setValue('display.unit_system', 'uk', 'select', 'display');

        $this->assertSame(['uk'], ForecastNlgCacheService::scalesToRephrase());
    }
}
