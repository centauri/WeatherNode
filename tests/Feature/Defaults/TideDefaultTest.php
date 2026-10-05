<?php

declare(strict_types=1);

namespace Tests\Feature\Defaults;

use App\Models\Setting;
use App\Models\User;
use App\Services\Tide\TideServiceFactory;
use App\Services\TideService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Issue #105. The tide migration seeded IJMH and IJmuiden into every install,
 * and the code default for the source was Rijkswaterstaat, so an owner
 * anywhere in the world opened the water page and read the tide at a Dutch
 * sea lock.
 *
 * Tides are off until somebody turns them on, so the neutral default is no
 * station at all, with the global source selected rather than one country's
 * gauge network.
 */
class TideDefaultTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fresh_install_has_no_tide_station(): void
    {
        $this->assertSame('', (string) Setting::getValue('tide.station_code'));
        $this->assertSame('', (string) Setting::getValue('tide.station_name'));
        $this->assertFalse((bool) Setting::getValue('tide.enabled'));
    }

    public function test_the_default_source_covers_the_whole_world(): void
    {
        $this->assertSame('GLOBAL', TideServiceFactory::make()->getRegion());
    }

    /** A damaged or hand-edited value should not land on one country either. */
    public function test_an_unknown_source_does_not_fall_back_to_one_country(): void
    {
        $this->assertSame('GLOBAL', TideServiceFactory::make('not-a-source')->getRegion());
    }

    public function test_the_water_page_names_no_dutch_port_before_setup(): void
    {
        // Tides are off on a fresh install, so /water opens the next section.
        Http::fake();
        $response = $this->followingRedirects()->get('/water');

        $response->assertOk();
        $response->assertDontSee('IJmuiden');
        $response->assertDontSee('Rijkswaterstaat');
    }

    public function test_the_tide_settings_page_preselects_no_dutch_station(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get('/admin/settings/tide')
            ->assertOk()
            ->assertDontSee('IJmuiden');
    }

    /** Blank is not a name: the page used to caption the chart with nothing. */
    public function test_the_page_names_the_site_when_there_is_no_station(): void
    {
        Setting::setValue('station.name', 'Testfield Weather', 'string', 'station');
        Setting::setValue('tide.enabled', true, 'boolean', 'tide');

        $this->get('/water')->assertSee('water levels for Testfield Weather', false);
    }

    /**
     * A gauge network cannot be asked about "the usual station". Each driver
     * falls back to its own first entry, which for Rijkswaterstaat is
     * IJmuiden, so a blank station has to stop before the request goes out.
     */
    public function test_nothing_is_fetched_when_no_station_is_chosen(): void
    {
        Http::fake();
        Setting::setValue('tide.source', 'rws', 'string', 'tide');

        $threw = false;
        try {
            (new TideService())->fetchTideData('');
        } catch (\RuntimeException $e) {
            $threw = true;
        }

        $this->assertTrue($threw, 'a blank station was accepted');
        Http::assertNothingSent();
    }

    /** A station the owner picks must still be the one that is shown. */
    public function test_a_configured_station_is_still_used(): void
    {
        Setting::setValue('tide.enabled', true, 'boolean', 'tide');
        Setting::setValue('tide.source', 'rws', 'string', 'tide');
        Setting::setValue('tide.station_code', 'vlissingen', 'string', 'tide');
        Setting::setValue('tide.station_name', 'Vlissingen', 'string', 'tide');

        $this->get('/water')
            ->assertOk()
            ->assertSee('Vlissingen');
    }
}
