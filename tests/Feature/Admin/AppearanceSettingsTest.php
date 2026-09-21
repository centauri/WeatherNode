<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Support\PublicAppearance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppearanceSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_and_invalid_stored_values_are_safe(): void
    {
        $this->assertSame(['palette' => 'weathernode', 'mode' => 'dark'], PublicAppearance::settings());
        Setting::setValue('appearance.palette', ['unexpected'], 'json', 'appearance');
        Setting::setValue('appearance.color_mode', 'invalid', 'string', 'appearance');
        $this->assertSame(['palette' => 'weathernode', 'mode' => 'dark'], PublicAppearance::settings());
    }

    public function test_admin_can_save_every_palette_and_mode_independently_of_effects(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Setting::setValue('display.theme', 'light');
        foreach (array_keys(PublicAppearance::PALETTES) as $palette) {
            foreach (PublicAppearance::MODES as $mode) {
                $this->post(route('admin.settings.appearance.update'), [
                    'appearance_theme' => 'flat',
                    'appearance_palette' => $palette,
                    'appearance_color_mode' => $mode,
                ])->assertRedirect(route('admin.settings.appearance'))->assertSessionHasNoErrors();
                $this->assertSame(['palette' => $palette, 'mode' => $mode], PublicAppearance::settings());
                $this->assertSame('flat', Setting::getValue('appearance.theme'));
                $this->assertSame('light', Setting::getValue('display.theme'));
            }
        }
    }

    public function test_legacy_fx_only_update_preserves_colours(): void
    {
        Setting::setValue('appearance.palette', 'forest');
        Setting::setValue('appearance.color_mode', 'system');
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post(route('admin.settings.appearance.update'), ['appearance_theme' => 'fx'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['palette' => 'forest', 'mode' => 'system'], PublicAppearance::settings());
    }

    public function test_invalid_input_does_not_partially_save(): void
    {
        Setting::setValue('appearance.theme', 'flat');
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post(route('admin.settings.appearance.update'), [
                'appearance_theme' => 'fx',
                'appearance_palette' => '<script>alert(1)</script>',
                'appearance_color_mode' => 'invalid',
            ])->assertSessionHasErrors(['appearance_palette', 'appearance_color_mode']);
        $this->assertSame('flat', Setting::getValue('appearance.theme'));
    }

    public function test_non_admin_cannot_change_appearance(): void
    {
        $payload = ['appearance_theme' => 'flat', 'appearance_palette' => 'forest', 'appearance_color_mode' => 'light'];
        $this->postJson(route('admin.settings.appearance.update'), $payload)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->post(route('admin.settings.appearance.update'), $payload)->assertRedirect(route('dashboard'));
        $this->assertSame('fx', Setting::getValue('appearance.theme', 'fx'));
        $this->assertSame('weathernode', PublicAppearance::settings()['palette']);
    }

    public function test_admin_form_shows_saved_preferences_without_public_bootstrap(): void
    {
        Setting::setValue('appearance.palette', 'ocean');
        Setting::setValue('appearance.color_mode', 'system');
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.settings.appearance'))->assertOk()
            ->assertSee('appearance_palette')->assertSee('Ocean')->assertSee('appearance_color_mode')
            ->assertDontSee('js/public-theme.js', false);
    }

    public function test_guest_pages_render_owner_settings_and_mode_controls(): void
    {
        Setting::setValue('appearance.palette', 'ocean');
        Setting::setValue('appearance.color_mode', 'light');
        foreach (['/', '/forecast', '/login'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('data-public-theme="ocean"', false)
                ->assertSee('data-color-mode="light"', false)
                ->assertSee('data-public-theme-select', false);
        }
        $this->get('/missing-theme-test')->assertNotFound()
            ->assertSee('data-public-theme="ocean"', false)
            ->assertSee('data-color-mode="light"', false);
    }
}
