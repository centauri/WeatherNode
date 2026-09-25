<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The WeatherLink page used to end its form with a hidden copy of every saved
 * setting. Those came after the visible inputs, and PHP keeps the last of two
 * fields with the same name, so a new station ID was always replaced by the
 * saved one ("0" on a fresh install). The same copies put the decrypted API
 * key and secret in the page source.
 *
 * These tests submit the page the way a browser does: every field the page
 * renders, with only the visible input changed.
 */
class WeatherLinkSettingsFormTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    /**
     * The fields a browser would send, in page order. A later field with the
     * same name replaces an earlier one, which is what PHP does with the body.
     */
    private function formFields(): array
    {
        $html = $this->actingAs($this->admin)
            ->get(route('admin.settings.group', 'weatherlink'))
            ->assertOk()
            ->getContent();

        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $form = (new DOMXPath($dom))->query('//form[contains(@action, "/settings/weatherlink")]')->item(0);
        $this->assertNotNull($form, 'WeatherLink settings form not found');

        $fields = [];
        foreach ((new DOMXPath($dom))->query('.//input[@name]', $form) as $input) {
            $fields[$input->getAttribute('name')] = $input->getAttribute('value');
        }

        return $fields;
    }

    /** Set a visible input the way typing into it would. */
    private function submit(array $typed): void
    {
        $fields = $this->formFields();

        // Typing changes the first field with that name, the one on screen.
        // Any later duplicate would still be sent and would win.
        $html = $this->actingAs($this->admin)->get(route('admin.settings.group', 'weatherlink'))->getContent();
        foreach ($typed as $name => $value) {
            $this->assertSame(
                1,
                substr_count($html, 'name="' . $name . '"'),
                "More than one field is named {$name}, so the one you type in can be overridden"
            );
            $fields[$name] = $value;
        }

        $this->actingAs($this->admin)
            ->post(route('admin.settings.update', 'weatherlink'), $fields)
            ->assertRedirect();
    }

    public function test_a_new_station_id_is_saved(): void
    {
        $this->submit([
            'weatherlink_type' => 'v2',
            'weatherlink_station_id' => '9722cfc3-a4ef-47b9-befb-72f52592d6ed',
        ]);

        $this->assertSame('9722cfc3-a4ef-47b9-befb-72f52592d6ed', Setting::getValue('weatherlink.station_id'));
    }

    public function test_a_new_v1_device_id_is_saved(): void
    {
        $this->submit([
            'weatherlink_type' => 'v1',
            'weatherlink_device_id' => '001D0A00C0FF',
        ]);

        $this->assertSame('001D0A00C0FF', Setting::getValue('weatherlink.device_id'));
    }

    public function test_toggles_can_be_switched_off(): void
    {
        Setting::setValue('weatherlink.enabled', true, 'boolean', 'weatherlink');
        Setting::setValue('weatherlink.demo_mode', true, 'boolean', 'weatherlink');

        // The toggle component always sends its field, as "0" when off.
        $this->submit([
            'weatherlink_type' => 'v2',
            'weatherlink_enabled' => '0',
            'weatherlink_demo_mode' => '0',
        ]);

        $this->assertFalse((bool) Setting::getValue('weatherlink.enabled'));
        $this->assertFalse((bool) Setting::getValue('weatherlink.demo_mode'));
    }

    public function test_toggles_can_be_switched_on(): void
    {
        Setting::setValue('weatherlink.demo_mode', false, 'boolean', 'weatherlink');

        $this->submit(['weatherlink_type' => 'v2', 'weatherlink_demo_mode' => '1']);

        $this->assertTrue((bool) Setting::getValue('weatherlink.demo_mode'));
    }

    public function test_saved_credentials_are_not_in_the_page_source(): void
    {
        Setting::setValue('weatherlink.api_key', 'key-that-must-stay-secret', 'encrypted', 'weatherlink');
        Setting::setValue('weatherlink.api_secret', 'secret-that-must-stay-secret', 'encrypted', 'weatherlink');

        $this->actingAs($this->admin)
            ->get(route('admin.settings.group', 'weatherlink'))
            ->assertOk()
            ->assertDontSee('key-that-must-stay-secret')
            ->assertDontSee('secret-that-must-stay-secret');
    }

    public function test_saving_without_new_credentials_keeps_the_old_ones(): void
    {
        Setting::setValue('weatherlink.api_key', 'existing-key', 'encrypted', 'weatherlink');
        Setting::setValue('weatherlink.api_secret', 'existing-secret', 'encrypted', 'weatherlink');

        $this->submit(['weatherlink_type' => 'v2', 'weatherlink_station_id' => '123']);

        $this->assertSame('existing-key', Setting::getValue('weatherlink.api_key'));
        $this->assertSame('existing-secret', Setting::getValue('weatherlink.api_secret'));
    }
}
