<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_preferences_are_available_without_admin_edit_actions(): void
    {
        Setting::setValue('appearance.visitor_palettes', ['ocean'], 'json', 'appearance');
        foreach (['/', '/forecast'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('data-public-menu-button', false)
                ->assertSee('data-public-palette-select', false)
                ->assertSee('data-public-language', false)
                ->assertSee('data-public-units', false)
                ->assertSee('data-public-fx-toggle', false)
                ->assertDontSee('data-public-edit-button', false);
        }
        // The provider shares this once at request boot; this test makes several requests in one app.
        \Illuminate\Support\Facades\View::share('siteTheme', 'flat');
        $this->get('/')->assertOk()->assertDontSee('data-public-fx-toggle', false);
    }

    public function test_dashboard_edit_controls_are_only_rendered_for_admins(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));
        $this->get('/')->assertOk()->assertDontSee('data-public-edit-button', false);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->get('/')->assertOk()->assertSee('data-public-edit-button', false);
        $this->get('/forecast')->assertOk()->assertDontSee('data-public-edit-button', false);
    }
}
