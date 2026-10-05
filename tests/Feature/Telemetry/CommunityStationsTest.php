<?php

declare(strict_types=1);

namespace Tests\Feature\Telemetry;

use App\Models\Setting;
use App\Models\User;
use App\Services\Telemetry\GitHubTelemetryService;
use App\Services\Telemetry\TelemetryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The community stations list and how a station's own entry is kept up to date.
 *
 * Reading: the page read stations.json through the GitHub API without a token,
 * which allows 60 calls an hour per IP. Every device behind one address shares
 * that, and a failed read was not cached, so each page view tried again and
 * the page showed 0 stations.
 *
 * Writing: the entry's id is a hash of the site address. A new address made a
 * new entry and left the old one listed; switching sharing off never removed
 * the entry; a LAN address was published as is; and coordinates got a
 * new random offset on every send, so the aggregator saw a change every day.
 */
class CommunityStationsTest extends TestCase
{
    use RefreshDatabase;

    private const RAW = 'raw.githubusercontent.com/centauri/community-stations/*';
    private const API = 'api.github.com/repos/centauri/community-stations/contents/*';
    private const AGGREGATOR = 'weathernode.dev/telemetry-aggregator/*';

    private function file(int $count = 2): array
    {
        $stations = [];
        for ($i = 1; $i <= $count; $i++) {
            $stations[] = ['id' => "s{$i}", 'name' => "Station {$i}", 'url' => "https://s{$i}.example", 'latitude' => 52.0, 'longitude' => 4.0];
        }

        return ['stations' => $stations, 'last_updated' => '2026-10-05T15:40:02+00:00'];
    }

    private function share(string $url = 'https://weather.example.org', string $name = 'Back Garden'): void
    {
        Setting::setValue('telemetry.enabled', true, 'boolean', 'telemetry');
        Setting::setValue('station.server_url', $url, 'string', 'station');
        Setting::setValue('station.name', $name, 'string', 'station');
        Setting::setValue('station.latitude', '52.5', 'float', 'station');
        Setting::setValue('station.longitude', '4.6', 'float', 'station');
        // Skip the country lookup.
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response(['address' => ['country_code' => 'nl']])]);
    }

    private function sent(): array
    {
        return collect(Http::recorded())
            ->map(fn ($pair) => $pair[0])
            ->filter(fn (Request $r) => str_contains($r->url(), 'telemetry-aggregator'))
            ->map(fn (Request $r) => $r->data())
            ->values()
            ->all();
    }

    // ── reading ─────────────────────────────────────────────────────────────

    public function test_the_list_is_read_from_raw_github_without_using_the_api(): void
    {
        Http::fake([self::RAW => Http::response($this->file(3)), self::API => Http::response([], 403)]);

        $this->assertCount(3, app(GitHubTelemetryService::class)->readStations()['stations']);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'api.github.com'));
    }

    public function test_the_api_is_the_fallback(): void
    {
        Http::fake([
            self::RAW => Http::response('', 503),
            self::API => Http::response(['content' => base64_encode(json_encode($this->file(2))), 'sha' => 'abc']),
        ]);

        $this->assertCount(2, app(GitHubTelemetryService::class)->readStations()['stations']);
    }

    public function test_when_github_fails_the_last_good_list_is_shown_and_it_is_not_asked_again_at_once(): void
    {
        Http::fake([self::RAW => Http::response($this->file(4))]);
        app(GitHubTelemetryService::class)->readStations();
        Cache::forget('github_stations_centauri/community-stations_stations.json');

        Http::fake([self::RAW => Http::response('', 503), self::API => Http::response([], 403)]);
        $first = app(GitHubTelemetryService::class)->readStations();
        $calls = count(Http::recorded());
        $second = app(GitHubTelemetryService::class)->readStations();

        $this->assertCount(4, $first['stations']);
        $this->assertCount(4, $second['stations']);
        $this->assertSame($calls, count(Http::recorded()), 'A failed read should be remembered for a while.');
    }

    public function test_the_page_shows_the_stations(): void
    {
        Http::fake([self::RAW => Http::response($this->file(21))]);

        $this->get('/community-stations')->assertOk()->assertSee('21');
    }

    // ── writing ─────────────────────────────────────────────────────────────

    public function test_publishing_sends_the_station_and_remembers_its_id(): void
    {
        $this->share();
        Http::fake([self::AGGREGATOR => Http::response(['success' => true])]);

        $result = app(TelemetryService::class)->publish();

        $this->assertTrue($result['success']);
        $this->assertSame('Back Garden', $this->sent()[0]['station']['name']);
        $this->assertSame($this->sent()[0]['station']['id'], Setting::getValue('telemetry.station_id'));
    }

    public function test_a_new_name_updates_the_same_entry(): void
    {
        $this->share();
        Http::fake([self::AGGREGATOR => Http::response(['success' => true])]);
        app(TelemetryService::class)->publish();

        Setting::setValue('station.name', 'Front Garden', 'string', 'station');
        app(TelemetryService::class)->publish();

        $sent = $this->sent();
        $this->assertCount(2, $sent);
        $this->assertSame($sent[0]['station']['id'], $sent[1]['station']['id']);
        $this->assertSame('Front Garden', $sent[1]['station']['name']);
    }

    public function test_a_new_address_removes_the_old_entry(): void
    {
        $this->share('https://old.example.org');
        Http::fake([self::AGGREGATOR => Http::response(['success' => true])]);
        app(TelemetryService::class)->publish();
        $oldId = $this->sent()[0]['station']['id'];

        Setting::setValue('station.server_url', 'https://new.example.org', 'string', 'station');
        app(TelemetryService::class)->publish();

        $sent = $this->sent();
        $this->assertSame('https://new.example.org', $sent[1]['station']['url']);
        $this->assertSame(['action' => 'remove', 'station_id' => $oldId], $sent[2]);
        $this->assertSame($sent[1]['station']['id'], Setting::getValue('telemetry.station_id'));
    }

    /** A local address is shared, but the admin is told visitors cannot open it. */
    public function test_a_lan_or_localhost_address_is_shared_with_a_warning(): void
    {
        foreach (['http://192.168.1.10:10130', 'http://localhost:8086', 'http://127.0.0.1:8000', 'http://10.0.0.5', 'http://weatherpi.local'] as $url) {
            $this->share($url);
            Http::fake([self::AGGREGATOR => Http::response(['success' => true])]);

            $result = app(TelemetryService::class)->publish();

            $this->assertTrue($result['success'], $url);
            $this->assertStringContainsString('local address', $result['warning'], $url);
            $this->assertSame($url, collect($this->sent())->whereNotNull('station')->last()['station']['url']);
        }
    }

    public function test_a_public_address_gets_no_warning(): void
    {
        $this->share('https://weather.example.org');
        Http::fake([self::AGGREGATOR => Http::response(['success' => true])]);

        $this->assertNull(app(TelemetryService::class)->publish()['warning']);
    }

    public function test_the_telemetry_page_points_out_a_local_address(): void
    {
        $this->share('http://192.168.1.15:10130');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.settings.telemetry'))
            ->assertOk()
            ->assertSee('local address')
            ->assertSee(route('admin.settings.group', 'station'), false);
    }

    /** The public list never shows a LAN address, only that the station is local. */
    public function test_the_community_page_shows_local_stations_as_local_only(): void
    {
        $file = $this->file(1);
        $file['stations'][] = ['id' => 'lan', 'name' => 'LAN Station', 'url' => 'http://192.168.1.10:10130', 'latitude' => 51.0, 'longitude' => 11.0];
        $file['stations'][] = ['id' => 'flagged', 'name' => 'Flagged Station', 'url' => null, 'local_only' => true, 'latitude' => 51.0, 'longitude' => 11.0];
        Http::fake([self::RAW => Http::response($file)]);

        $content = (string) $this->get('/community-stations')->assertOk()->getContent();

        $this->assertStringNotContainsString('192.168.1.10', $content);
        $this->assertStringContainsString('Local only', $content);
        $this->assertSame(2, substr_count($content, '"local_only":true'));
    }

    public function test_switching_sharing_off_removes_the_entry(): void
    {
        $this->share();
        Http::fake([self::AGGREGATOR => Http::response(['success' => true])]);
        app(TelemetryService::class)->publish();
        $id = $this->sent()[0]['station']['id'];

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post(route('admin.settings.telemetry.update'), ['enabled' => '0']);

        $this->assertContains(['action' => 'remove', 'station_id' => $id], $this->sent());
    }

    /** The same blurred spot every time, still within about 100 m. */
    public function test_the_shared_location_is_blurred_the_same_way_every_time(): void
    {
        $this->share();
        $first = app(TelemetryService::class)->previewStationData();
        $second = app(TelemetryService::class)->previewStationData();

        $this->assertSame([$first['latitude'], $first['longitude']], [$second['latitude'], $second['longitude']]);
        $this->assertNotSame([52.5, 4.6], [$first['latitude'], $first['longitude']]);
        $this->assertLessThan(0.0011, abs($first['latitude'] - 52.5));
    }

    public function test_saving_a_new_station_name_sends_it(): void
    {
        $this->share();
        Http::fake([self::AGGREGATOR => Http::response(['success' => true])]);
        app(TelemetryService::class)->publish();

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post(route('admin.settings.update', 'station'), [
            'station_name' => 'Renamed Station',
            'station_latitude' => '52.5',
            'station_longitude' => '4.6',
            'station_timezone' => 'Europe/Amsterdam',
        ]);

        $this->assertSame('Renamed Station', collect($this->sent())->last()['station']['name']);
    }
}
