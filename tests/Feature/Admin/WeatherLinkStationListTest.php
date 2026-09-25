<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * "Fetch Available Stations" on the WeatherLink page. It had a button and no
 * backend, so clicking it did nothing.
 */
class WeatherLinkStationListTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function fakeStations(array $stations, int $status = 200): void
    {
        Http::fake([
            'api.weatherlink.com/v2/stations*' => Http::response(['stations' => $stations], $status),
        ]);
    }

    private function station(array $overrides = []): array
    {
        return array_merge([
            'station_id' => 123456,
            'station_id_uuid' => '9722cfc3-a4ef-47b9-befb-72f52592d6ed',
            'station_name' => 'Back Garden',
            'city' => 'Uitgeest',
            'region' => 'Noord-Holland',
            'country' => 'Netherlands',
            'active' => true,
            'relationship_type' => 'Owner',
            'user_email' => 'owner@example.com',
            'imei' => '123456789012345',
        ], $overrides);
    }

    public function test_it_lists_stations_using_the_typed_credentials(): void
    {
        $this->fakeStations([$this->station()]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.settings.weatherlink.stations'), ['api_key' => 'typed-key', 'api_secret' => 'typed-secret'])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'stations' => [[
                    'station_id' => '123456',
                    'station_id_uuid' => '9722cfc3-a4ef-47b9-befb-72f52592d6ed',
                    'name' => 'Back Garden',
                    'location' => 'Uitgeest, Noord-Holland, Netherlands',
                    'active' => true,
                ]],
            ]);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/v2/stations')
            && $r['api-key'] === 'typed-key'
            && $r->header('X-Api-Secret') === ['typed-secret']
            && ! str_contains($r->url(), 'typed-secret'));
    }

    public function test_it_falls_back_to_the_saved_credentials(): void
    {
        Setting::setValue('weatherlink.api_key', 'saved-key', 'encrypted', 'weatherlink');
        Setting::setValue('weatherlink.api_secret', 'saved-secret', 'encrypted', 'weatherlink');
        $this->fakeStations([$this->station()]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.settings.weatherlink.stations'), ['api_key' => '', 'api_secret' => ''])
            ->assertOk()
            ->assertJsonPath('success', true);

        Http::assertSent(fn (Request $r) => $r['api-key'] === 'saved-key' && $r->header('X-Api-Secret') === ['saved-secret']);
    }

    public function test_it_does_not_pass_on_account_details(): void
    {
        $this->fakeStations([$this->station()]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.settings.weatherlink.stations'), ['api_key' => 'k', 'api_secret' => 's'])
            ->assertOk()
            ->assertDontSee('owner@example.com')
            ->assertDontSee('123456789012345');
    }

    public function test_it_asks_for_credentials_when_there_are_none(): void
    {
        Http::fake();

        $this->actingAs($this->admin())
            ->postJson(route('admin.settings.weatherlink.stations'), [])
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Enter your API key and API secret first.');

        Http::assertNothingSent();
    }

    public function test_rejected_credentials_are_reported(): void
    {
        $this->fakeStations([], 401);

        $this->actingAs($this->admin())
            ->postJson(route('admin.settings.weatherlink.stations'), ['api_key' => 'bad', 'api_secret' => 'bad'])
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'WeatherLink rejected this API key and secret.');
    }

    public function test_an_account_without_stations_explains_why(): void
    {
        $this->fakeStations([]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.settings.weatherlink.stations'), ['api_key' => 'k', 'api_secret' => 's'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('stations', [])
            ->assertJsonFragment(['message' => 'This account has no stations. WeatherLink only lists stations you own or that were shared with you.']);
    }

    public function test_guests_and_non_admins_cannot_use_it(): void
    {
        Http::fake();

        $this->postJson(route('admin.settings.weatherlink.stations'), ['api_key' => 'k', 'api_secret' => 's'])
            ->assertUnauthorized();

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->postJson(route('admin.settings.weatherlink.stations'), ['api_key' => 'k', 'api_secret' => 's'])
            ->assertStatus(403);

        Http::assertNothingSent();
    }

    public function test_the_page_wires_the_button_to_the_endpoint(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.settings.group', 'weatherlink'))
            ->assertOk()
            ->assertSee('id="fetch-stations-btn"', false)
            ->assertSee('id="stations-result"', false)
            ->assertSee(route('admin.settings.weatherlink.stations'), false);
    }
}
