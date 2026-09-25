<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #97: the AEMET page prefilled the key field with ********, and saving the
 * page again stored that mask as the key.
 */
class AemetApiKeyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    /** Post the page back with whatever the key field holds when it loads. */
    private function saveAsRendered(array $changes = []): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('admin.settings.group', 'aemet'))
            ->assertOk()
            ->getContent();

        preg_match('/<input[^>]*name="aemet_api_key"[^>]*value="([^"]*)"/s', $html, $m);
        $this->assertNotEmpty($m, 'API key field not found on the AEMET page');

        $this->actingAs($this->admin)
            ->post(route('admin.settings.update', 'aemet'), array_merge([
                'aemet_api_key' => html_entity_decode($m[1]),
                'aemet_municipio' => '28079',
            ], $changes))
            ->assertRedirect();
    }

    public function test_saving_the_page_twice_keeps_the_key(): void
    {
        Setting::setValue('aemet.api_key', 'real-aemet-key', 'encrypted', 'aemet');

        $this->saveAsRendered();
        $this->saveAsRendered(['aemet_municipio' => '08019']);

        $this->assertSame('real-aemet-key', Setting::getValue('aemet.api_key'));
        $this->assertSame('08019', Setting::getValue('aemet.municipio'));
    }

    public function test_the_page_does_not_prefill_the_key_field(): void
    {
        Setting::setValue('aemet.api_key', 'real-aemet-key', 'encrypted', 'aemet');

        $this->actingAs($this->admin)
            ->get(route('admin.settings.group', 'aemet'))
            ->assertOk()
            ->assertDontSee('value="********"', false)
            ->assertDontSee('real-aemet-key')
            ->assertSee('Configured (leave empty to keep current value)');
    }

    public function test_a_posted_mask_never_replaces_the_key(): void
    {
        Setting::setValue('aemet.api_key', 'real-aemet-key', 'encrypted', 'aemet');

        // A browser restoring an old copy of the page could still send it.
        $this->actingAs($this->admin)
            ->post(route('admin.settings.update', 'aemet'), ['aemet_api_key' => '********', 'aemet_municipio' => '28079'])
            ->assertRedirect();

        $this->assertSame('real-aemet-key', Setting::getValue('aemet.api_key'));
    }

    public function test_a_new_key_is_saved(): void
    {
        Setting::setValue('aemet.api_key', 'old-key', 'encrypted', 'aemet');

        $this->saveAsRendered(['aemet_api_key' => 'new-key']);

        $this->assertSame('new-key', Setting::getValue('aemet.api_key'));
    }

    public function test_the_migration_clears_a_key_that_was_saved_as_the_mask(): void
    {
        Setting::setValue('aemet.api_key', '********', 'encrypted', 'aemet');

        (require database_path('migrations/2026_09_26_120000_clear_masked_aemet_api_key.php'))->up();

        $this->assertSame('', Setting::where('key', 'aemet.api_key')->value('value'));
    }

    public function test_the_migration_leaves_a_real_key_alone(): void
    {
        Setting::setValue('aemet.api_key', 'real-aemet-key', 'encrypted', 'aemet');

        (require database_path('migrations/2026_09_26_120000_clear_masked_aemet_api_key.php'))->up();

        $this->assertSame('real-aemet-key', Setting::getValue('aemet.api_key'));
    }
}
