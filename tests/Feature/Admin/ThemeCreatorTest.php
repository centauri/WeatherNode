<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Support\CustomTheme;
use App\Support\PublicAppearance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeCreatorTest extends TestCase
{
    use RefreshDatabase;

    private function theme(): array
    {
        $theme = CustomTheme::presets()['weathernode'];
        $theme['name'] = 'Evening sky';
        $theme['modes']['dark']['card'] = '#312345';

        return $theme;
    }

    private function admin(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    public function test_creator_and_all_endpoints_require_an_admin(): void
    {
        $this->get(route('admin.settings.theme-creator'))->assertRedirect(route('login'));
        $this->get(route('admin.settings.theme-creator.preview'))->assertRedirect(route('login'));
        foreach (['save', 'validate', 'export', 'reset'] as $action) {
            $this->postJson(route('admin.settings.theme-creator.'.$action), ['theme' => json_encode($this->theme()), 'action' => 'apply'])->assertUnauthorized();
        }
        $this->actingAs(User::factory()->create(['is_admin' => false]));
        foreach (['save', 'validate', 'export', 'reset'] as $action) {
            $this->post(route('admin.settings.theme-creator.'.$action), ['theme' => json_encode($this->theme()), 'action' => 'apply'])->assertRedirect(route('dashboard'));
        }
        $this->assertNull(CustomTheme::stored());
    }

    public function test_presets_copy_the_original_shades_and_creator_renders(): void
    {
        $presets = CustomTheme::presets();
        $this->assertSame('#1a2332', $presets['weathernode']['modes']['dark']['card']);
        $this->assertSame('#6b7280', $presets['weathernode']['modes']['dark']['subtle']);
        $this->assertSame('#ffffff', $presets['weathernode']['modes']['light']['card']);
        $this->assertSame('#0f766e', $presets['ocean']['modes']['dark']['accent-strong']);
        $this->admin();
        $this->get(route('admin.settings.theme-creator'))->assertOk()->assertSee('theme-creator-config', false);
        $this->get(route('admin.settings.theme-creator.preview'))->assertOk()->assertSee('preview-tokens', false);
    }

    public function test_import_is_validated_without_saving_and_export_round_trips(): void
    {
        $this->admin();
        $theme = $this->theme();
        $this->postJson(route('admin.settings.theme-creator.validate'), ['theme' => json_encode($theme)])->assertOk()->assertJson(['theme' => $theme]);
        $this->assertNull(CustomTheme::stored());
        $response = $this->postJson(route('admin.settings.theme-creator.export'), ['theme' => json_encode($theme)])->assertOk();
        $this->assertSame($theme, json_decode($response->getContent(), true));
        $this->assertStringContainsString('evening-sky.json', $response->headers->get('Content-Disposition'));
        $this->assertSame('weathernode', PublicAppearance::settings()['palette']);
    }

    public function test_save_is_a_draft_and_apply_uses_a_snapshot_until_applied_again(): void
    {
        $this->admin();
        $theme = $this->theme();
        Setting::setValue('appearance.color_mode', 'system');
        Setting::setValue('appearance.theme', 'flat');
        Setting::setValue('display.theme', 'light');
        $this->post(route('admin.settings.theme-creator.save'), ['theme' => json_encode($theme), 'action' => 'save'])->assertSessionHasNoErrors();
        $this->assertArrayNotHasKey('custom', PublicAppearance::settings());
        $this->post(route('admin.settings.theme-creator.save'), ['theme' => json_encode($theme), 'action' => 'apply'])->assertSessionHasNoErrors();
        $this->assertSame($theme, PublicAppearance::settings()['custom']);
        $theme['modes']['dark']['card'] = '#123456';
        $this->post(route('admin.settings.theme-creator.save'), ['theme' => json_encode($theme), 'action' => 'save'])->assertSessionHasNoErrors();
        $this->assertSame('#312345', PublicAppearance::settings()['custom']['modes']['dark']['card']);
        $this->assertSame('#123456', CustomTheme::stored()['modes']['dark']['card']);
        $this->assertSame('system', PublicAppearance::settings()['mode']);
        $this->assertSame('flat', Setting::getValue('appearance.theme'));
        $this->assertSame('light', Setting::getValue('display.theme'));
        $this->post(route('admin.settings.theme-creator.reset'))->assertSessionHasNoErrors();
        $this->assertSame(['palette' => 'weathernode', 'mode' => 'dark'], PublicAppearance::settings());
        $this->assertSame($theme, CustomTheme::stored());
        $this->assertSame('flat', Setting::getValue('appearance.theme'));
    }

    public function test_appearance_can_apply_saved_custom_theme_and_switch_back(): void
    {
        $this->admin();
        $this->post(route('admin.settings.appearance.update'), ['appearance_theme' => 'fx', 'appearance_palette' => 'custom'])->assertSessionHasErrors('appearance_palette');
        Setting::setValue('appearance.custom_theme', $this->theme(), 'json', 'appearance');
        $this->get(route('admin.settings.appearance'))->assertOk()->assertSee('Evening sky');
        $this->post(route('admin.settings.appearance.update'), ['appearance_theme' => 'fx', 'appearance_palette' => 'custom'])->assertSessionHasNoErrors();
        $this->assertSame($this->theme(), PublicAppearance::settings()['custom']);
        $this->post(route('admin.settings.appearance.update'), ['appearance_theme' => 'fx', 'appearance_palette' => 'ocean'])->assertSessionHasNoErrors();
        $this->assertSame(['palette' => 'ocean', 'mode' => 'dark'], PublicAppearance::settings());
    }

    public function test_bad_imports_cannot_change_saved_or_active_settings(): void
    {
        $this->admin();
        $original = $this->theme();
        $this->post(route('admin.settings.theme-creator.save'), ['theme' => json_encode($original), 'action' => 'apply']);
        $invalid = [];
        $theme = $original;
        $theme['version'] = 2;
        $invalid[] = json_encode($theme);
        $theme = $original;
        $theme['version'] = '1';
        $invalid[] = json_encode($theme);
        $theme = $original;
        $theme['modes']['dark']['card'] = 'red;}body{display:none}';
        $invalid[] = json_encode($theme);
        $theme = $original;
        $theme['modes']['dark']['data-red-500'] = '#ffffff';
        $invalid[] = json_encode($theme);
        $theme = $original;
        unset($theme['modes']['light']);
        $invalid[] = json_encode($theme);
        $theme = $original;
        $theme['css'] = '</style><script>alert(1)</script>';
        $invalid[] = json_encode($theme);
        $theme = $original;
        $theme['base'] = '../unknown';
        $invalid[] = json_encode($theme);
        $theme = $original;
        $theme['name'] = "Bad\nname";
        $invalid[] = json_encode($theme);
        $invalid = array_merge($invalid, ['not json', 'null', '[]', str_repeat(' ', 20001)]);
        foreach ($invalid as $json) {
            $this->postJson(route('admin.settings.theme-creator.validate'), ['theme' => $json])->assertUnprocessable();
            $this->postJson(route('admin.settings.theme-creator.save'), ['theme' => $json, 'action' => 'apply'])->assertUnprocessable();
        }
        $this->assertSame($original, CustomTheme::stored());
        $this->assertSame($original, PublicAppearance::settings()['custom']);
    }

    public function test_public_shells_apply_validated_css_and_corrupt_storage_falls_back(): void
    {
        Setting::setValue('appearance.palette', 'custom');
        Setting::setValue('appearance.active_custom_theme', $this->theme(), 'json', 'appearance');
        foreach (['/', '/forecast', '/login', '/widget'] as $url) {
            $this->get($url)->assertOk()->assertSee('data-custom-theme', false)->assertSee('--wn-card:49 35 69;', false);
        }
        $this->get('/missing-custom-theme')->assertNotFound()->assertSee('--wn-card:49 35 69;', false);
        $this->assertStringNotContainsString('--wn-data-', CustomTheme::css($this->theme()));
        Setting::setValue('appearance.active_custom_theme', ['bad' => 'data'], 'json', 'appearance');
        $this->assertSame(['palette' => 'weathernode', 'mode' => 'dark'], PublicAppearance::settings());
        $this->get('/login')->assertOk()->assertDontSee('id="public-custom-theme"', false);
    }
}
