<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Models\WeatherReading;
use App\Services\Weather\NetatmoClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Netatmo: the owner's app, the login, the token that changes on every
 * refresh, and the station readings.
 */
class NetatmoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function withApp(): void
    {
        Setting::setValue('netatmo.client_id', 'client-123', 'string', 'netatmo');
        Setting::setValue('netatmo.client_secret', 'secret-456', 'encrypted', 'netatmo');
    }

    private function connected(string $access = 'access-1', int $expiresIn = 3600): void
    {
        $this->withApp();
        Setting::setValue('netatmo.refresh_token', 'refresh-1', 'encrypted', 'netatmo');
        Setting::setValue('netatmo.access_token', $access, 'encrypted', 'netatmo');
        Setting::setValue('netatmo.access_expires_at', (string) (now()->timestamp + $expiresIn), 'string', 'netatmo');
    }

    private function stations(): array
    {
        return require base_path('tests/Fixtures/Netatmo/getstationsdata.php');
    }

    public function test_connect_sends_the_owner_to_netatmo_with_a_state(): void
    {
        $this->withApp();

        $response = $this->actingAs($this->admin())->get(route('admin.settings.netatmo.connect'));

        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('https://api.netatmo.com/oauth2/authorize?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('client-123', $query['client_id']);
        $this->assertSame('read_station', $query['scope']);
        $this->assertSame(route('admin.settings.netatmo.callback'), $query['redirect_uri']);
        $this->assertSame(session('netatmo_oauth_state'), $query['state']);
    }

    public function test_the_callback_swaps_the_code_for_tokens(): void
    {
        $this->withApp();
        Http::fake(['api.netatmo.com/oauth2/token' => Http::response(['access_token' => 'access-new', 'refresh_token' => 'refresh-new', 'expires_in' => 10800])]);

        $this->actingAs($this->admin())
            ->withSession(['netatmo_oauth_state' => 'state-abc'])
            ->get(route('admin.settings.netatmo.callback', ['state' => 'state-abc', 'code' => 'code-xyz']))
            ->assertRedirect(route('admin.settings.group', 'netatmo'))
            ->assertSessionHas('success');

        $this->assertSame('refresh-new', Setting::getValue('netatmo.refresh_token'));
        $this->assertTrue(app(NetatmoClient::class)->connected());
        Http::assertSent(fn (Request $request) => $request['grant_type'] === 'authorization_code'
            && $request['code'] === 'code-xyz'
            && $request['client_secret'] === 'secret-456'
            && $request['redirect_uri'] === route('admin.settings.netatmo.callback'));
    }

    public function test_a_callback_with_the_wrong_state_is_refused(): void
    {
        $this->withApp();
        Http::fake();

        $this->actingAs($this->admin())
            ->withSession(['netatmo_oauth_state' => 'state-abc'])
            ->get(route('admin.settings.netatmo.callback', ['state' => 'other', 'code' => 'code-xyz']))
            ->assertSessionHas('error');

        Http::assertNothingSent();
        $this->assertFalse(app(NetatmoClient::class)->connected());
    }

    public function test_an_expired_access_token_is_refreshed_and_the_new_refresh_token_kept(): void
    {
        $this->connected('access-old', -10);
        Http::fake([
            'api.netatmo.com/oauth2/token' => Http::response(['access_token' => 'access-2', 'refresh_token' => 'refresh-2', 'expires_in' => 10800]),
            'api.netatmo.com/api/getstationsdata*' => Http::response($this->stations()),
        ]);

        $station = app(NetatmoClient::class)->station();

        $this->assertSame('12:34:56:37:11:ca', $station['_id']);
        $this->assertSame('refresh-2', Setting::getValue('netatmo.refresh_token'));
        Http::assertSent(fn (Request $request) => ($request->data()['grant_type'] ?? null) === 'refresh_token'
            && ($request->data()['refresh_token'] ?? null) === 'refresh-1');
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'getstationsdata')
            && $request->hasHeader('Authorization', 'Bearer access-2')
            && str_contains($request->url(), 'get_favorites=true'));
    }

    public function test_a_token_netatmo_calls_expired_is_refreshed_once_and_retried(): void
    {
        $this->connected('access-1');
        Http::fake([
            'api.netatmo.com/oauth2/token' => Http::response(['access_token' => 'access-2', 'refresh_token' => 'refresh-2', 'expires_in' => 10800]),
            'api.netatmo.com/api/getstationsdata*' => Http::sequence()
                ->push(['error' => ['code' => 3, 'message' => 'Access token expired']], 403)
                ->push($this->stations()),
        ]);

        $this->assertNotNull(app(NetatmoClient::class)->station());
        $this->assertSame('refresh-2', Setting::getValue('netatmo.refresh_token'));
    }

    public function test_a_spent_refresh_token_disconnects_and_says_so(): void
    {
        $this->connected('access-old', -10);
        Http::fake(['api.netatmo.com/oauth2/token' => Http::response(['error' => 'invalid_grant'], 400)]);

        $this->assertNull(app(NetatmoClient::class)->station());
        $this->assertFalse(app(NetatmoClient::class)->connected());
        $this->assertStringContainsString('invalid_grant', (string) Setting::getValue('netatmo.last_error'));
    }

    public function test_the_first_owned_station_is_used_and_a_favorite_can_be_chosen(): void
    {
        $this->connected();
        Http::fake(['api.netatmo.com/api/getstationsdata*' => Http::response($this->stations())]);

        $this->assertSame('12:34:56:37:11:ca', app(NetatmoClient::class)->station()['_id']);

        Setting::setValue('netatmo.device_id', '12:34:56:00:00:01', 'string', 'netatmo');
        $this->assertSame('Neighbour (Garden)', app(NetatmoClient::class)->station()['station_name']);
    }

    public function test_a_pasted_refresh_token_connects(): void
    {
        $this->withApp();
        Http::fake(['api.netatmo.com/oauth2/token' => Http::response(['access_token' => 'access-new', 'refresh_token' => 'refresh-new', 'expires_in' => 10800])]);

        $this->actingAs($this->admin())->post(route('admin.settings.update', 'netatmo'), [
            '_token' => csrf_token(),
            'netatmo_client_id' => 'client-123',
            'netatmo_refresh_token' => 'pasted-token',
        ])->assertSessionHas('success');

        $this->assertSame('refresh-new', Setting::getValue('netatmo.refresh_token'));
        // An empty secret field keeps the saved secret.
        $this->assertSame('secret-456', Setting::getValue('netatmo.client_secret'));
    }

    public function test_the_page_lists_stations_and_never_shows_the_secret(): void
    {
        $this->connected();
        Http::fake(['api.netatmo.com/api/getstationsdata*' => Http::response($this->stations())]);

        $page = $this->actingAs($this->admin())->get(route('admin.settings.group', 'netatmo'))->assertOk();

        $page->assertSee('MyStation, Frankfurt');
        $page->assertSee('Neighbour (Garden), Uitgeest (favorite)');
        $page->assertSee(route('admin.settings.netatmo.callback'), false);
        $page->assertDontSee('secret-456');
        $page->assertDontSee('refresh-1');
        $page->assertDontSee('access-1');
    }

    public function test_the_scheduler_stores_a_reading_once_per_netatmo_update(): void
    {
        $this->connected();
        Setting::setValue('livedata.format', 'netatmo', 'select', 'livedata');
        Http::fake(['api.netatmo.com/api/getstationsdata*' => Http::response($this->stations())]);

        $this->artisan('weather:fetch', ['--save' => true])->assertSuccessful();
        $this->artisan('weather:fetch', ['--save' => true])->assertSuccessful();

        $this->assertSame(1, WeatherReading::query()->count());
        $this->assertEqualsWithDelta(28.6, (float) WeatherReading::mostRecent()->temperature, 0.01);
    }

    public function test_test_connection_reports_the_station(): void
    {
        $this->connected();
        Http::fake(['api.netatmo.com/api/getstationsdata*' => Http::response($this->stations())]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.settings.test-api'), ['service' => 'netatmo'])
            ->assertJson(['success' => true, 'message' => 'Netatmo returned data for MyStation.']);
    }
}
