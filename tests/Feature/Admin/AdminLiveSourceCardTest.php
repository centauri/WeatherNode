<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Models\WeatherReading;
use App\Support\LiveSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** The dashboard card describes the live data source that is set, not always Ecowitt. */
class AdminLiveSourceCardTest extends TestCase
{
    use RefreshDatabase;

    private function dashboard()
    {
        $admin = User::factory()->create(['is_admin' => true]);

        return $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_ecowitt_push_shows_the_receive_address(): void
    {
        Setting::setValue('livedata.format', 'ecoLcl', 'select', 'livedata');
        Setting::setValue('ecowitt.data_source', 'push', 'select', 'ecowitt');

        $this->dashboard()
            ->assertSee('data-live-source="ecoLcl"', false)
            ->assertSee('/api/ecowitt/receive')
            ->assertSee('Ecowitt (HTTP POST)');
    }

    public function test_wunderground_upload_shows_its_address_and_key(): void
    {
        Setting::setValue('livedata.format', 'wuPush', 'select', 'livedata');
        Setting::setValue('wupush.station_key', 'abc123key', 'string', 'livedata');

        $this->dashboard()
            ->assertSee('data-live-source="wuPush"', false)
            ->assertSee('/api/wu/receive')
            ->assertSee('abc123key')
            ->assertDontSee('/api/ecowitt/receive');
    }

    public function test_a_cloud_source_says_it_is_fetched_and_links_to_its_settings(): void
    {
        Setting::setValue('livedata.format', 'netatmo', 'select', 'livedata');

        $this->dashboard()
            ->assertSee('data-live-source="netatmo"', false)
            ->assertSee('WeatherNode fetches the live data from Netatmo every minute.')
            ->assertSee(route('admin.settings.group', 'netatmo'))
            ->assertDontSee('/api/ecowitt/receive');
    }

    public function test_a_local_file_source_shows_its_file(): void
    {
        Setting::setValue('livedata.format', 'weewx', 'select', 'livedata');
        Setting::setValue('livedata.fetch_mode', 'file', 'select', 'livedata');
        Setting::setValue('livedata.file_path', '/var/tmp/realtime.txt', 'string', 'livedata');

        $this->dashboard()
            ->assertSee('WeatherNode reads the live data from:')
            ->assertSee('/var/tmp/realtime.txt');
    }

    #[DataProvider('readingAges')]
    public function test_the_status_follows_the_age_of_the_newest_reading(?int $minutesAgo, string $state): void
    {
        if ($minutesAgo !== null) {
            WeatherReading::query()->create(['recorded_at' => now()->subMinutes($minutesAgo), 'temperature' => 20]);
        }

        $this->dashboard()->assertSee('data-live-health="' . $state . '"', false);
    }

    public static function readingAges(): array
    {
        return [
            'fresh' => [1, 'ok'],
            'late' => [8, 'late'],
            'old' => [40, 'down'],
            'none' => [null, 'down'],
        ];
    }

    public function test_the_last_fetch_error_is_shown_without_its_query_string(): void
    {
        Setting::setValue('livedata.format', 'netatmo', 'select', 'livedata');
        LiveSource::recordError('netatmo', 'Request to https://api.netatmo.com/getstationsdata?access_token=secret failed: 403');

        $this->dashboard()
            ->assertSee('data-live-error', false)
            ->assertSee('https://api.netatmo.com/getstationsdata?... failed: 403')
            ->assertDontSee('secret');
    }

    public function test_an_error_from_another_source_is_not_shown(): void
    {
        Setting::setValue('livedata.format', 'netatmo', 'select', 'livedata');
        LiveSource::recordError('wf', 'WeatherFlow said no');

        $this->dashboard()->assertDontSee('WeatherFlow said no');
    }

    public function test_a_failed_fetch_saves_the_error_and_a_good_one_clears_it(): void
    {
        Setting::setValue('livedata.format', 'wuPush', 'select', 'livedata');

        $this->artisan('weather:fetch --save');
        $this->assertSame('The source sent no data.', LiveSource::health('wuPush')['error']['message']);

        WeatherReading::query()->create(['recorded_at' => now(), 'temperature' => 20]);
        $this->artisan('weather:fetch --save');
        $this->assertNull(LiveSource::health('wuPush')['error']);
    }
}
