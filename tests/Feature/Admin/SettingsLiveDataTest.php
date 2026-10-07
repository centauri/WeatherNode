<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsLiveDataTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_livedata_settings_page_requires_admin(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $response = $this->actingAs($user)->get(route('admin.settings.group', 'livedata'));
        $response->assertRedirect(route('dashboard'));
    }

    public function test_livedata_settings_page_loads_for_admin(): void
    {
        $response = $this->actingAs($this->adminUser())->get(route('admin.settings.group', 'livedata'));
        $response->assertStatus(200);
        $response->assertSee('Live Data Source', false);
        $response->assertSee('livedata_format', false);
        // Push security moved to the Ecowitt page; this page links there.
        $response->assertSee(route('admin.settings.group', 'ecowitt'), false);
    }

    public function test_weatherlink_settings_page_loads_for_admin(): void
    {
        $response = $this->actingAs($this->adminUser())->get(route('admin.settings.group', 'weatherlink'));
        $response->assertStatus(200);
        $response->assertSee('WeatherLink', false);
    }

    public function test_test_api_requires_authentication(): void
    {
        $response = $this->postJson(route('admin.settings.test-api'), [
            'service' => 'livedata',
            'format' => 'ecoLcl',
        ]);
        $response->assertStatus(401);
    }

    public function test_test_api_rejects_get_requests(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->getJson(route('admin.settings.test-api'));

        $response->assertStatus(405);
        $response->assertJson([
            'success' => false,
            'message' => 'Method not allowed. Use POST.',
        ]);
    }

    public function test_test_api_returns_400_when_service_missing(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->postJson(route('admin.settings.test-api'), []);
        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => 'Service parameter is required',
        ]);
    }

    public function test_test_api_returns_json_with_service_parameter(): void
    {
        Setting::setValue('livedata.format', 'ecoLcl', 'select', 'livedata');
        $response = $this->actingAs($this->adminUser())
            ->postJson(route('admin.settings.test-api'), [
                'service' => 'livedata',
                'format' => 'ecoLcl',
            ]);
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/json');
        $data = $response->json();
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('message', $data);
    }

    public function test_test_api_does_not_mutate_weatherlink_demo_mode(): void
    {
        Setting::setValue('weatherlink.demo_mode', '0', 'boolean', 'weatherlink');

        $response = $this->actingAs($this->adminUser())
            ->postJson(route('admin.settings.test-api'), [
                'service' => 'livedata',
                'format' => 'DWL_v2api_demo',
            ]);

        $response->assertStatus(200);
        $this->assertFalse((bool) Setting::getValue('weatherlink.demo_mode', false));
    }

    public function test_update_livedata_settings_requires_admin(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $response = $this->actingAs($user)->post(route('admin.settings.update', 'livedata'), [
            '_token' => csrf_token(),
            'livedata_format' => 'ecoLcl',
        ]);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_update_livedata_settings_saves_format(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->post(route('admin.settings.update', 'livedata'), [
                '_token' => csrf_token(),
                'livedata_format' => 'DWL_v2api_demo',
            ]);
        $response->assertRedirect(route('admin.settings.group', 'livedata'));
        $response->assertSessionHas('success');
        $this->assertSame('DWL_v2api_demo', Setting::getValue('livedata.format'));
        $this->assertTrue((bool) Setting::getValue('weatherlink.demo_mode'));
    }

    public function test_update_livedata_settings_saves_ecowitt_secure_options(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->post(route('admin.settings.update', 'livedata'), [
                '_token' => csrf_token(),
                'livedata_format' => 'ecoLcl',
                'ecowitt_passkey' => 'my-passkey',
                'ecowitt_secure_mode' => '1',
                'ecowitt_secure_token' => 'my/token-123_ABC',
            ]);

        $response->assertRedirect(route('admin.settings.group', 'livedata'));
        $response->assertSessionHas('success');
        $this->assertSame('ecoLcl', Setting::getValue('livedata.format'));
        $this->assertSame('my-passkey', Setting::getValue('ecowitt.passkey'));
        $this->assertTrue((bool) Setting::getValue('ecowitt.secure_mode'));
        $this->assertSame('mytoken-123_ABC', Setting::getValue('ecowitt.secure_token'));
    }

    public function test_update_livedata_settings_saves_ecowitt_source_allowlists(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->post(route('admin.settings.update', 'livedata'), [
                '_token' => csrf_token(),
                'livedata_format' => 'ecoLcl',
                'ecowitt_ip_filter_enabled' => '1',
                'ecowitt_ip_allowlist' => "203.0.113.10,\n198.51.100.0/24\n203.0.113.10",
                'ecowitt_name_filter_enabled' => '1',
                'ecowitt_name_allowlist' => "GW2000A, Backyard Station\nGW2000A",
            ]);

        $response->assertRedirect(route('admin.settings.group', 'livedata'));
        $response->assertSessionHas('success');
        $this->assertTrue((bool) Setting::getValue('ecowitt.ip_filter_enabled'));
        $this->assertSame("203.0.113.10\n198.51.100.0/24", Setting::getValue('ecowitt.ip_allowlist'));
        $this->assertTrue((bool) Setting::getValue('ecowitt.name_filter_enabled'));
        $this->assertSame("GW2000A\nBackyard Station", Setting::getValue('ecowitt.name_allowlist'));
    }

    public function test_enabling_ambient_weather_requires_both_credentials(): void
    {
        Setting::setValue('ambient.enabled', false, 'boolean', 'ambient');
        Setting::setValue('ambient.api_key', '', 'encrypted', 'ambient');
        Setting::setValue('ambient.application_key', '', 'encrypted', 'ambient');

        $admin = $this->adminUser();
        $response = $this->actingAs($admin)
            ->from(route('admin.settings.group', 'ambient'))
            ->post(route('admin.settings.update', 'ambient'), [
                '_token' => csrf_token(),
                'ambient_enabled' => '1',
            ]);

        $response->assertRedirect(route('admin.settings.group', 'ambient'));
        $response->assertSessionHasErrors(['ambient_api_key', 'ambient_application_key']);
        $this->assertFalse((bool) Setting::getValue('ambient.enabled'));

        $this->actingAs($admin)
            ->get(route('admin.settings.group', 'ambient'))
            ->assertSee('The Ambient Weather API key is required when the integration is enabled.')
            ->assertSee('The Ambient Weather application key is required when the integration is enabled.');
    }

    public function test_saving_ambient_settings_preserves_existing_encrypted_credentials(): void
    {
        Setting::setValue('ambient.enabled', true, 'boolean', 'ambient');
        Setting::setValue('ambient.api_key', 'existing-api-key', 'encrypted', 'ambient');
        Setting::setValue('ambient.application_key', 'existing-application-key', 'encrypted', 'ambient');
        $apiKeyCiphertext = Setting::findOrFail('ambient.api_key')->value;
        $applicationKeyCiphertext = Setting::findOrFail('ambient.application_key')->value;

        $response = $this->actingAs($this->adminUser())
            ->post(route('admin.settings.update', 'ambient'), [
                '_token' => csrf_token(),
                'ambient_enabled' => '1',
                'ambient_api_key' => '',
                'ambient_application_key' => '',
                'ambient_mac_address' => 'AA:BB:CC:DD:EE:FF',
            ]);

        $response->assertRedirect(route('admin.settings.group', 'ambient'));
        $response->assertSessionHas('success');
        $this->assertSame($apiKeyCiphertext, Setting::findOrFail('ambient.api_key')->value);
        $this->assertSame($applicationKeyCiphertext, Setting::findOrFail('ambient.application_key')->value);
        $this->assertSame('AA:BB:CC:DD:EE:FF', Setting::getValue('ambient.mac_address'));
    }

    /** The shipped Ecowitt default meant nothing to WeeWX and looked like a real setting. */
    public function test_weewx_shows_its_own_example_instead_of_the_ecowitt_file(): void
    {
        Setting::setValue('livedata.format', 'weewx', 'select', 'livedata');
        Setting::setValue('livedata.file_path', './ecowitt/ecco_lcl.arr', 'string', 'livedata');

        $response = $this->actingAs($this->adminUser())->get(route('admin.settings.group', 'livedata'));

        $this->assertMatchesRegularExpression('/id="livedata_file_path"\s+value=""/', $response->getContent());
        $response->assertSee('placeholder="/var/tmp/realtime.txt"', false);
        $response->assertSee('http://raspberrypi.local/weewx/realtime.txt', false);
        $response->assertSee('docs/WEEWX.md', false);
    }

    public function test_weather_display_asks_for_clientraw_and_keeps_a_real_path(): void
    {
        Setting::setValue('livedata.format', 'wd', 'select', 'livedata');
        Setting::setValue('livedata.file_path', '/srv/wd/clientraw.txt', 'string', 'livedata');

        $response = $this->actingAs($this->adminUser())->get(route('admin.settings.group', 'livedata'));

        $this->assertMatchesRegularExpression('#id="livedata_file_path"\s+value="/srv/wd/clientraw.txt"#', $response->getContent());
        $response->assertSee('placeholder="/path/to/clientraw.txt"', false);
    }

    public function test_choosing_wunderground_upload_makes_a_station_key_and_shows_it(): void
    {
        $admin = $this->adminUser();
        $this->actingAs($admin)->post(route('admin.settings.update', 'livedata'), [
            '_token' => csrf_token(),
            'livedata_format' => 'wuPush',
        ])->assertRedirect(route('admin.settings.group', 'livedata'));

        $key = \App\Support\WuPush::stationKey();
        $this->assertSame(32, strlen($key));

        $page = $this->actingAs($admin)->get(route('admin.settings.group', 'livedata'));
        $page->assertSee($key, false);
        $page->assertSee('/api/wu/receive', false);
        $page->assertSee('server_url', false);

        // Saving again keeps the key, so the uploader keeps working.
        $this->actingAs($admin)->post(route('admin.settings.update', 'livedata'), [
            '_token' => csrf_token(),
            'livedata_format' => 'wuPush',
        ]);
        $this->assertSame($key, \App\Support\WuPush::stationKey());
    }

    public function test_a_new_station_key_replaces_the_old_one_on_request(): void
    {
        $admin = $this->adminUser();
        Setting::setValue('livedata.format', 'wuPush', 'select', 'livedata');
        Setting::setValue('wupush.station_key', 'old-key', 'string', 'livedata');

        $this->actingAs($admin)->post(route('admin.settings.update', 'livedata'), [
            '_token' => csrf_token(),
            'livedata_format' => 'wuPush',
            'wupush_new_key' => '1',
        ]);

        $this->assertNotSame('old-key', \App\Support\WuPush::stationKey());
    }
}
