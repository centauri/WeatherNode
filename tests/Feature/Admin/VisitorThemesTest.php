<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Support\CustomTheme;
use App\Support\PublicAppearance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitorThemesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    public function test_palette_choices_are_opt_in_and_safe_for_invalid_settings(): void
    {
        $this->assertSame([], PublicAppearance::visitorPalettes());
        $this->get('/')->assertOk()->assertDontSee('data-public-palette-select', false);
        foreach (['ocean', true, ['unknown', ['ocean'], 'custom']] as $invalid) {
            Setting::setValue('appearance.visitor_palettes', $invalid, 'json', 'appearance');
            $this->assertSame([], PublicAppearance::visitorPalettes());
        }
    }

    public function test_admin_can_offer_selected_palettes_and_disable_them_without_changing_defaults(): void
    {
        $this->admin();
        $payload = ['appearance_theme' => 'fx', 'appearance_visitor_palettes_present' => '1', 'appearance_visitor_palettes' => ['ocean', 'solar-flare']];
        $this->post(route('admin.settings.appearance.update'), $payload)->assertSessionHasNoErrors();
        $this->assertSame(['ocean', 'solar-flare'], array_keys(PublicAppearance::visitorPalettes()));
        $this->assertSame(['palette' => 'weathernode', 'mode' => 'dark'], PublicAppearance::settings());
        // Older clients omit these settings and must not disable visitors' choices.
        $this->post(route('admin.settings.appearance.update'), ['appearance_theme' => 'flat'])->assertSessionHasNoErrors();
        $this->assertCount(2, PublicAppearance::visitorPalettes());
        unset($payload['appearance_visitor_palettes']);
        $this->post(route('admin.settings.appearance.update'), $payload)->assertSessionHasNoErrors();
        $this->assertSame([], PublicAppearance::visitorPalettes());
    }

    public function test_rejected_choices_do_not_partially_write_settings(): void
    {
        $this->admin();
        foreach ([['missing'], ['custom'], ['ocean', 'ocean'], [['bad']], 'ocean'] as $choices) {
            $this->post(route('admin.settings.appearance.update'), ['appearance_theme' => 'flat', 'appearance_visitor_palettes' => $choices])->assertSessionHasErrors();
            $this->assertSame('fx', Setting::getValue('appearance.theme', 'fx'));
            $this->assertSame([], PublicAppearance::visitorPalettes());
        }
    }

    public function test_visitors_and_non_admins_cannot_change_the_allowlist(): void
    {
        $payload = ['appearance_theme' => 'fx', 'appearance_visitor_palettes' => ['ocean']];
        $this->postJson(route('admin.settings.appearance.update'), $payload)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin' => false]))->post(route('admin.settings.appearance.update'), $payload)->assertRedirect(route('dashboard'));
        $this->assertSame([], PublicAppearance::visitorPalettes());
    }

    public function test_public_pages_offer_only_allowed_palettes_and_keep_default_available(): void
    {
        Setting::setValue('appearance.visitor_palettes', ['ocean', 'forest'], 'json', 'appearance');
        foreach (['/', '/forecast', '/login'] as $url) {
            $this->get($url)->assertOk()->assertSee('data-public-palette-select', false)
                ->assertSee('"palettes":{"ocean":"ocean","forest":"forest"}', false)
                ->assertSee('<option value="default">', false)->assertDontSee('<option value="solar-flare">', false);
        }
        $this->get('/missing-visitor-theme')->assertNotFound()->assertSee('public-theme-config', false);
        $this->get('/widget')->assertOk()->assertSee('public-theme-config', false);
    }

    public function test_custom_choice_uses_published_snapshot_and_never_the_draft(): void
    {
        $published = CustomTheme::presets()['ocean'];
        $published['name'] = 'Published ocean';
        Setting::setValue('appearance.active_custom_theme', $published, 'json', 'appearance');
        $draft = $published;
        $draft['name'] = 'Private draft';
        $draft['modes']['dark']['card'] = '#123456';
        Setting::setValue('appearance.custom_theme', $draft, 'json', 'appearance');
        $this->admin();
        $this->post(route('admin.settings.appearance.update'), ['appearance_theme' => 'fx', 'appearance_visitor_palettes' => ['custom']])->assertSessionHasNoErrors();
        $this->assertSame(['custom' => 'Published ocean'], PublicAppearance::visitorPalettes());
        $this->get('/')->assertOk()->assertSee('Published ocean')->assertDontSee('Private draft')
            ->assertSee('html[data-public-theme][data-custom-theme][data-color-mode=', false)
            ->assertDontSee('--wn-card:18 52 86;', false);
        $this->assertSame('weathernode', PublicAppearance::settings()['palette']);
        Setting::setValue('appearance.active_custom_theme', ['invalid'], 'json', 'appearance');
        $this->get('/')->assertOk()->assertDontSee('data-public-palette-select', false)->assertDontSee('id="public-custom-theme"', false);
    }

    public function test_saving_permissions_when_station_uses_custom_does_not_publish_the_draft(): void
    {
        $published = CustomTheme::presets()['forest'];
        $published['name'] = 'Published forest';
        $draft = $published;
        $draft['name'] = 'Unpublished edit';
        $draft['modes']['dark']['card'] = '#123456';
        Setting::setValue('appearance.palette', 'custom');
        Setting::setValue('appearance.active_custom_theme', $published, 'json', 'appearance');
        Setting::setValue('appearance.custom_theme', $draft, 'json', 'appearance');
        $this->admin();
        $this->get(route('admin.settings.appearance'))->assertOk()->assertSee('Published forest')->assertDontSee('Unpublished edit');
        $this->post(route('admin.settings.appearance.update'), [
            'appearance_theme' => 'fx', 'appearance_palette' => 'custom',
            'appearance_color_mode' => 'light', 'appearance_visitor_palettes' => ['custom', 'ocean'],
        ])->assertSessionHasNoErrors();
        $this->assertSame($published, CustomTheme::stored(true));
        $this->assertSame($draft, CustomTheme::stored());
    }
}
