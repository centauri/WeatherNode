<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Models\WeatherReading;
use App\Support\EcowittSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The Ecowitt settings page: one place to choose how the station sends data
 * and to set up that source, with the cloud station finder and status.
 */
class EcowittSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function fixture(string $name): array
    {
        return json_decode(file_get_contents(base_path("tests/Fixtures/Ecowitt/{$name}")), true);
    }

    private function save(array $input)
    {
        return $this->actingAs($this->admin())->post(route('admin.settings.update', 'ecowitt'), $input);
    }

    public function test_the_page_shows_the_three_sources_and_its_sections(): void
    {
        $this->actingAs($this->admin())->get(route('admin.settings.group', 'ecowitt'))
            ->assertOk()
            ->assertSee('name="ecowitt_source"', false)
            ->assertSee('value="push"', false)
            ->assertSee('value="cloud"', false)
            ->assertSee('value="file"', false)
            ->assertSee('name="ecowitt_secure_mode"', false)
            ->assertSee('name="ecowitt_mac_address"', false)
            ->assertSee('name="ecowitt_rain_gauge"', false)
            ->assertSee('name="ecowitt_temp8_label"', false)
            ->assertSee('name="ecowitt_soil8_label"', false);
    }

    /** Fine Offset sells the same stations under many names; owners of those should find this page. */
    public function test_the_page_says_which_brands_it_covers(): void
    {
        $this->actingAs($this->admin())->get(route('admin.settings.group', 'ecowitt'))
            ->assertSee('Fine Offset')
            ->assertSee('Froggit')
            ->assertSee('Sainlogic');

        $this->actingAs($this->admin())->get(route('admin.settings.index'))
            ->assertSee('Froggit');
    }

    /** These were never read by anything. */
    public function test_settings_that_did_nothing_are_gone(): void
    {
        foreach ([
            'ecowitt.enabled', 'ecowitt.lightning_sensor', 'ecowitt.uv_sensor', 'ecowitt.solar_sensor',
            'ecowitt.air_quality_sensor', 'ecowitt.soil_sensors', 'ecowitt.extra_temp_sensors',
            'ecowitt.pm25_sensors', 'ecowitt.co2_sensor', 'ecowitt.leak_sensors',
        ] as $key) {
            $this->assertNull(Setting::where('key', $key)->first(), $key);
        }

        $this->actingAs($this->admin())->get(route('admin.settings.group', 'ecowitt'))
            ->assertDontSee('name="ecowitt_extra_temp_sensors"', false)
            ->assertDontSee('name="ecowitt_enabled"', false);
    }

    public function test_choosing_the_cloud_sets_the_live_data_source_too(): void
    {
        Setting::setValue('livedata.format', 'ecoLcl', 'select', 'livedata');

        $this->save([
            'ecowitt_source' => 'cloud',
            'ecowitt_application_key' => 'app-key',
            'ecowitt_api_key' => 'api-key',
            'ecowitt_mac_address' => 'aa:bb:cc:dd:ee:ff',
        ])->assertSessionHasNoErrors();

        $this->assertSame('ecowittAPI', Setting::getValue('livedata.format'));
        $this->assertSame(EcowittSource::CLOUD, EcowittSource::current());
        $this->assertSame('app-key', Setting::getValue('ecowitt.application_key'));
        $this->assertSame('AA:BB:CC:DD:EE:FF', Setting::getValue('ecowitt.mac_address'));
    }

    public function test_the_cloud_needs_keys_and_a_mac_address(): void
    {
        $this->save(['ecowitt_source' => 'cloud', 'ecowitt_mac_address' => ''])
            ->assertSessionHasErrors(['ecowitt_application_key', 'ecowitt_api_key', 'ecowitt_mac_address']);

        $this->assertNotSame(EcowittSource::CLOUD, EcowittSource::current());
    }

    public function test_a_mac_address_must_look_like_one(): void
    {
        $this->save(['ecowitt_mac_address' => 'not-a-mac'])->assertSessionHasErrors('ecowitt_mac_address');
    }

    public function test_saved_keys_are_kept_when_the_fields_are_left_empty(): void
    {
        Setting::setValue('ecowitt.application_key', 'stored-app', 'encrypted', 'ecowitt');
        Setting::setValue('ecowitt.api_key', 'stored-api', 'encrypted', 'ecowitt');

        $this->save([
            'ecowitt_source' => 'cloud',
            'ecowitt_application_key' => '',
            'ecowitt_api_key' => '********',
            'ecowitt_mac_address' => 'AA:BB:CC:DD:EE:FF',
        ])->assertSessionHasNoErrors();

        $this->assertSame('stored-app', Setting::getValue('ecowitt.application_key'));
        $this->assertSame('stored-api', Setting::getValue('ecowitt.api_key'));
    }

    public function test_push_security_is_saved_here(): void
    {
        $this->save([
            'ecowitt_source' => 'push',
            'ecowitt_passkey' => 'my-passkey',
            'ecowitt_secure_mode' => '1',
            'ecowitt_secure_token' => 'my/token',
            'ecowitt_ip_filter_enabled' => '1',
            'ecowitt_ip_allowlist' => "203.0.113.10, 203.0.113.10",
        ])->assertSessionHasNoErrors();

        $this->assertSame(EcowittSource::PUSH, EcowittSource::current());
        $this->assertSame('my-passkey', Setting::getValue('ecowitt.passkey'));
        $this->assertTrue((bool) Setting::getValue('ecowitt.secure_mode'));
        $this->assertSame('mytoken', Setting::getValue('ecowitt.secure_token'));
        $this->assertSame('203.0.113.10', Setting::getValue('ecowitt.ip_allowlist'));
    }

    public function test_the_local_file_path_is_used_by_both_readers(): void
    {
        $this->save(['ecowitt_source' => 'file', 'ecowitt_local_file' => './ecowitt/station.arr'])
            ->assertSessionHasNoErrors();

        $this->assertSame('./ecowitt/station.arr', Setting::getValue('ecowitt.local_file'));
        $this->assertSame('./ecowitt/station.arr', Setting::getValue('livedata.file_path'));
    }

    public function test_a_file_path_may_not_leave_the_app(): void
    {
        $this->save(['ecowitt_source' => 'file', 'ecowitt_local_file' => '../../etc/passwd'])
            ->assertSessionHasErrors('ecowitt_local_file');
    }

    /** Leaving the source on "not used" must not take over the live data source. */
    public function test_saving_without_choosing_a_source_keeps_the_live_data_source(): void
    {
        Setting::setValue('livedata.format', 'DWL_v2api', 'select', 'livedata');

        $this->save(['ecowitt_source' => 'keep', 'ecowitt_temp1_label' => 'Greenhouse'])->assertSessionHasNoErrors();

        $this->assertSame('DWL_v2api', Setting::getValue('livedata.format'));
        $this->assertSame('Greenhouse', Setting::getValue('ecowitt.temp1_label'));
    }

    public function test_every_channel_can_be_named(): void
    {
        $this->save(['ecowitt_temp8_label' => 'Attic', 'ecowitt_soil5_label' => 'Lawn'])->assertSessionHasNoErrors();

        $this->assertSame('Attic', Setting::getValue('ecowitt.temp8_label'));
        $this->assertSame('Lawn', Setting::getValue('ecowitt.soil5_label'));
    }

    public function test_the_page_shows_the_latest_value_next_to_each_named_channel(): void
    {
        WeatherReading::query()->create(['recorded_at' => now(), 'temperature' => 19.2, 'temp_2' => 15.7]);

        $this->actingAs($this->admin())->get(route('admin.settings.group', 'ecowitt'))
            ->assertSee('15.7');
    }

    public function test_choosing_a_station_can_set_the_station_location_and_time_zone(): void
    {
        $this->save([
            'ecowitt_apply_location' => '1',
            'ecowitt_station_latitude' => '48.8566',
            'ecowitt_station_longitude' => '2.3522',
            'ecowitt_station_timezone' => 'Europe/Paris',
        ])->assertSessionHasNoErrors();

        $this->assertSame(48.8566, Setting::latitude());
        $this->assertSame(2.3522, Setting::longitude());
        $this->assertSame('Europe/Paris', Setting::getValue('station.timezone'));
    }

    public function test_a_bad_location_from_the_picker_is_rejected(): void
    {
        $this->save([
            'ecowitt_apply_location' => '1',
            'ecowitt_station_latitude' => '999',
            'ecowitt_station_longitude' => '0',
            'ecowitt_station_timezone' => 'Mars/Olympus',
        ])->assertSessionHasErrors(['ecowitt_station_latitude', 'ecowitt_station_timezone']);
    }

    // ── station finder ──────────────────────────────────────────────────────

    public function test_find_stations_lists_them_with_the_typed_keys(): void
    {
        Http::fake(['api.ecowitt.net/api/v3/device/list*' => Http::response($this->fixture('device-list.json'))]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.settings.ecowitt.stations'), ['application_key' => 'typed-app', 'api_key' => 'typed-api'])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'stations' => [['name' => 'Test station', 'mac' => 'AA:BB:CC:DD:EE:FF', 'timezone' => 'Europe/Berlin']],
            ]);

        Http::assertSent(fn (Request $r) => $r['application_key'] === 'typed-app' && $r['api_key'] === 'typed-api');
    }

    public function test_find_stations_falls_back_to_the_saved_keys(): void
    {
        Setting::setValue('ecowitt.application_key', 'stored-app', 'encrypted', 'ecowitt');
        Setting::setValue('ecowitt.api_key', 'stored-api', 'encrypted', 'ecowitt');
        Http::fake(['*' => Http::response($this->fixture('device-list.json'))]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.settings.ecowitt.stations'), ['application_key' => '', 'api_key' => '********'])
            ->assertOk()
            ->assertJsonPath('success', true);

        Http::assertSent(fn (Request $r) => $r['application_key'] === 'stored-app' && $r['api_key'] === 'stored-api');
    }

    public function test_find_stations_explains_a_refusal(): void
    {
        Http::fake(['*' => Http::response(['code' => 40010, 'msg' => 'Illegal Application_Key Parameter', 'data' => null])]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.settings.ecowitt.stations'), ['application_key' => 'bad', 'api_key' => 'bad'])
            ->assertOk()
            ->assertJson(['success' => false, 'message' => 'Illegal Application_Key Parameter']);
    }

    public function test_find_stations_is_for_admins_only(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->postJson(route('admin.settings.ecowitt.stations'))
            ->assertStatus(403);
    }

    // ── status and test ─────────────────────────────────────────────────────

    public function test_the_status_says_whether_ecowitt_hears_from_the_station(): void
    {
        Setting::setValue('ecowitt.application_key', 'app', 'encrypted', 'ecowitt');
        Setting::setValue('ecowitt.api_key', 'api', 'encrypted', 'ecowitt');
        Setting::setValue('ecowitt.mac_address', 'AA:BB:CC:DD:EE:FF', 'string', 'ecowitt');
        Http::fake(['api.ecowitt.net/api/v3/device/info*' => Http::response($this->fixture('device-info.json'))]);

        $this->actingAs($this->admin())
            ->getJson(route('admin.settings.ecowitt.status'))
            ->assertOk()
            ->assertJson(['success' => true, 'online' => true, 'name' => 'Test station', 'model' => 'GW1000A_V1.7.8']);
    }

    public function test_the_test_button_reports_an_offline_cloud_station(): void
    {
        EcowittSource::apply(EcowittSource::CLOUD);
        Setting::setValue('ecowitt.application_key', 'app', 'encrypted', 'ecowitt');
        Setting::setValue('ecowitt.api_key', 'api', 'encrypted', 'ecowitt');
        Setting::setValue('ecowitt.mac_address', 'AA:BB:CC:DD:EE:FF', 'string', 'ecowitt');
        $info = $this->fixture('device-info.json');
        $info['data']['device_status'] = 'Offline';
        Http::fake([
            'api.ecowitt.net/api/v3/device/real_time*' => Http::response(['code' => 0, 'msg' => 'success', 'data' => ['outdoor' => []]]),
            'api.ecowitt.net/api/v3/device/info*' => Http::response($info),
        ]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.settings.test-api'), ['service' => 'ecowitt'])
            ->assertJson(['success' => false])
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'offline'));
    }

    public function test_the_test_button_passes_on_the_cloud_error(): void
    {
        EcowittSource::apply(EcowittSource::CLOUD);
        Setting::setValue('ecowitt.application_key', 'app', 'encrypted', 'ecowitt');
        Setting::setValue('ecowitt.api_key', 'api', 'encrypted', 'ecowitt');
        Setting::setValue('ecowitt.mac_address', 'AA:BB:CC:DD:EE:FF', 'string', 'ecowitt');
        Http::fake(['*' => Http::response(['code' => 40005, 'msg' => 'One of MAC and IMEI must exist', 'data' => null])]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.settings.test-api'), ['service' => 'ecowitt'])
            ->assertJson(['success' => false])
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'One of MAC and IMEI must exist'));
    }

    // ── gap filling ─────────────────────────────────────────────────────────

    public function test_gap_filling_is_on_by_default_and_can_be_set(): void
    {
        $this->assertTrue((bool) Setting::getValue('ecowitt.backfill_enabled'));
        $this->assertSame('7', (string) Setting::getValue('ecowitt.backfill_days'));

        $this->actingAs($this->admin())->get(route('admin.settings.group', 'ecowitt'))
            ->assertSee('name="ecowitt_backfill_enabled"', false)
            ->assertSee('name="ecowitt_backfill_days"', false);

        $this->save(['ecowitt_backfill_enabled' => '0', 'ecowitt_backfill_days' => '30'])->assertSessionHasNoErrors();

        $this->assertFalse((bool) Setting::getValue('ecowitt.backfill_enabled'));
        $this->assertSame('30', (string) Setting::getValue('ecowitt.backfill_days'));
    }

    public function test_gap_filling_looks_back_at_most_90_days(): void
    {
        $this->save(['ecowitt_backfill_days' => '365'])->assertSessionHasErrors('ecowitt_backfill_days');
    }

    public function test_fill_gaps_now_runs_it_and_says_what_happened(): void
    {
        Setting::setValue('ecowitt.application_key', 'app', 'encrypted', 'ecowitt');
        Setting::setValue('ecowitt.api_key', 'api', 'encrypted', 'ecowitt');
        Setting::setValue('ecowitt.mac_address', 'AA:BB:CC:DD:EE:FF', 'string', 'ecowitt');
        Http::fake();
        WeatherReading::query()->create(['recorded_at' => now()->subMinutes(2), 'temperature' => 10]);

        $this->actingAs($this->admin())
            ->post(route('admin.settings.ecowitt.backfill'))
            ->assertRedirect(route('admin.settings.group', 'ecowitt'))
            ->assertSessionHas('success');
    }

    public function test_fill_gaps_now_explains_missing_keys(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.settings.ecowitt.backfill'))
            ->assertSessionHas('error');
    }

    // ── the Live Data page stays in step ────────────────────────────────────

    public function test_choosing_ecowitt_cloud_on_the_live_data_page_switches_the_service_too(): void
    {
        Setting::setValue('ecowitt.data_source', 'local_file', 'select', 'ecowitt');

        $this->actingAs($this->admin())->post(route('admin.settings.update', 'livedata'), ['livedata_format' => 'ecowittAPI']);

        $this->assertSame('cloud_api', Setting::getValue('ecowitt.data_source'));
    }

    public function test_choosing_ecowitt_local_on_the_live_data_page_leaves_the_cloud(): void
    {
        Setting::setValue('ecowitt.data_source', 'cloud_api', 'select', 'ecowitt');

        $this->actingAs($this->admin())->post(route('admin.settings.update', 'livedata'), ['livedata_format' => 'ecoLcl']);

        $this->assertSame(EcowittSource::PUSH, EcowittSource::current());
    }

    /** The Live Data form no longer carries these, so saving it must leave them alone. */
    public function test_saving_the_live_data_page_keeps_push_security(): void
    {
        Setting::setValue('ecowitt.secure_mode', '1', 'boolean', 'ecowitt');
        Setting::setValue('ecowitt.secure_token', 'tok', 'string', 'ecowitt');

        $this->actingAs($this->admin())->post(route('admin.settings.update', 'livedata'), ['livedata_format' => 'ecoLcl']);

        $this->assertTrue((bool) Setting::getValue('ecowitt.secure_mode'));
        $this->assertSame('tok', Setting::getValue('ecowitt.secure_token'));
    }
}
