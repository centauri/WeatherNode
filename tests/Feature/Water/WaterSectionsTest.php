<?php

declare(strict_types=1);

namespace Tests\Feature\Water;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The Water section has its own switches: Tides, Waves (also Sea
 * Temperature) and River Levels. The sub-tabs ignored them, so a switched-off
 * Tides tab stayed in the menu and showed visitors "Tides not enabled" with a
 * button into the admin.
 */
class WaterSectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['*' => Http::response([], 200)]);
    }

    private function sections(bool $tides, bool $waves): void
    {
        Setting::setValue('tide.enabled', $tides, 'boolean', 'tide');
        Setting::setValue('waves.enabled', $waves, 'boolean', 'waves');
    }

    public function test_a_switched_off_section_has_no_tab(): void
    {
        $this->sections(tides: false, waves: true);

        $this->get(route('water.waves'))
            ->assertOk()
            ->assertSee(route('water.temp'), false)
            ->assertDontSee('href="' . route('water') . '"', false)
            ->assertDontSee('/admin/settings', false);
    }

    public function test_a_switched_off_section_sends_visitors_to_one_that_is_on(): void
    {
        $this->sections(tides: false, waves: true);
        $this->get(route('water'))->assertRedirect(route('water.waves'));

        $this->sections(tides: true, waves: false);
        $this->get(route('water.waves'))->assertRedirect(route('water'));
        $this->get(route('water.temp'))->assertRedirect(route('water'));
    }

    public function test_with_everything_off_there_is_no_water_section(): void
    {
        $this->sections(tides: false, waves: false);

        $this->get(route('water'))->assertNotFound();
        $this->get(route('water.waves'))->assertNotFound();
        $this->get(route('aviation'))->assertOk()->assertDontSee('href="' . route('water'), false);
    }

    public function test_the_water_tab_opens_the_first_section_that_is_on(): void
    {
        $this->sections(tides: false, waves: true);

        $this->get(route('aviation'))->assertOk()->assertSee('href="' . route('water.waves') . '"', false);
    }

    public function test_waves_are_on_until_switched_off(): void
    {
        Setting::where('key', 'waves.enabled')->delete();
        Setting::setValue('tide.enabled', false, 'boolean', 'tide');

        $this->get(route('water.waves'))->assertOk();
    }

    public function test_visitors_never_get_an_admin_link(): void
    {
        $this->sections(tides: true, waves: true);

        foreach (['water', 'water.waves', 'water.temp'] as $route) {
            $this->get(route($route))->assertOk()->assertDontSee('/admin/settings', false);
        }
    }

    public function test_the_sitemap_leaves_out_switched_off_sections(): void
    {
        $this->sections(tides: false, waves: true);

        $sitemap = (string) $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('/water/waves</loc>', $sitemap);
        $this->assertStringNotContainsString('/water</loc>', $sitemap);
        $this->assertStringNotContainsString('/water/rivers</loc>', $sitemap);
    }
}
