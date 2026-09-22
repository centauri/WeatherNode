<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetarDefaultSceneSettingTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_aviation_settings_backfill_and_render_default_scene(): void
    {
        $response = $this->actingAs($this->adminUser())->get(route('admin.settings.group', 'aviation'));

        $response->assertOk();
        $response->assertSee('Default atmospheric scene');
        $response->assertSee('name="metar_default_scene"', false);
        $this->assertSame('schiphol', Setting::getValue('metar.default_scene'));
    }

    public function test_default_scene_accepts_only_known_scene_ids(): void
    {
        $this->actingAs($this->adminUser())
            ->post(route('admin.settings.update', 'aviation'), ['metar_default_scene' => 'arctic'])
            ->assertRedirect();
        $this->assertSame('arctic', Setting::getValue('metar.default_scene'));

        $this->actingAs($this->adminUser())
            ->from(route('admin.settings.group', 'aviation'))
            ->post(route('admin.settings.update', 'aviation'), ['metar_default_scene' => 'unknown'])
            ->assertSessionHasErrors('metar_default_scene');
        $this->assertSame('arctic', Setting::getValue('metar.default_scene'));
    }

    public function test_omitting_default_scene_preserves_existing_value(): void
    {
        Setting::setValue('metar.default_scene', 'volcanic', 'select', 'aviation');

        $this->actingAs($this->adminUser())
            ->post(route('admin.settings.update', 'aviation'), [])
            ->assertRedirect();

        $this->assertSame('volcanic', Setting::getValue('metar.default_scene'));
    }

    public function test_non_admin_cannot_change_the_default_scene(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->postJson(route('admin.settings.update', 'aviation'), ['metar_default_scene' => 'spaceport'])
            ->assertForbidden();
        $this->assertNull(Setting::getValue('metar.default_scene'));
    }

    public function test_public_page_uses_the_admin_default_and_safely_falls_back(): void
    {
        Setting::setValue('metar.default_scene', 'spaceport', 'select', 'aviation');
        $this->get('/aviation/EHAM')->assertOk()->assertViewHas('defaultScene', 'spaceport');
        Setting::setValue('metar.default_scene', 'obsolete-scene', 'select', 'aviation');
        $this->get('/aviation/EHAM')->assertOk()->assertViewHas('defaultScene', 'schiphol');
    }

}
