<?php

declare(strict_types=1);

namespace Tests\Feature\Weather;

use App\Models\DailySummary;
use App\Models\Setting;
use App\Models\WeatherReading;
use App\Services\Weather\EcowittBackfill;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Gaps in the readings, from an outage of the server, the network or the
 * push, are filled from the Ecowitt cloud's 5-minute history. Any source can
 * use it once cloud keys and a MAC address are set.
 *
 * The history fixture is a real response for 22:00 to 23:00 UTC on
 * 4 October 2026: 13 points, 5 minutes apart.
 */
class EcowittBackfillTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $name): array
    {
        return json_decode(file_get_contents(base_path("tests/Fixtures/Ecowitt/{$name}")), true);
    }

    private function utc(string $time): Carbon
    {
        return Carbon::parse($time, 'UTC');
    }

    private function reading(string $utc, float $temperature = 10.0): WeatherReading
    {
        $at = $this->utc($utc)->setTimezone(config('app.timezone'));

        return WeatherReading::query()->create(['recorded_at' => $at, 'temperature' => $temperature]);
    }

    private function configureCloud(): void
    {
        Setting::setValue('ecowitt.application_key', 'app', 'encrypted', 'ecowitt');
        Setting::setValue('ecowitt.api_key', 'api', 'encrypted', 'ecowitt');
        Setting::setValue('ecowitt.mac_address', 'AA:BB:CC:DD:EE:FF', 'string', 'ecowitt');
    }

    private function fakeCloud(): void
    {
        Http::fake([
            'api.ecowitt.net/api/v3/device/info*' => Http::response($this->fixture('device-info.json')),
            'api.ecowitt.net/api/v3/device/history*' => Http::response($this->fixture('history-5min.json')),
        ]);
    }

    private function run_(): array
    {
        return app(EcowittBackfill::class)->run(7, $this->utc('2026-10-04 23:30:00'));
    }

    public function test_a_gap_is_filled_from_the_cloud_history(): void
    {
        $this->configureCloud();
        $this->fakeCloud();
        $this->reading('2026-10-04 21:55:00');
        $this->reading('2026-10-04 23:05:00');
        $this->reading('2026-10-04 23:15:00');
        $this->reading('2026-10-04 23:25:00');

        $report = $this->run_();

        $this->assertSame(13, $report['inserted']);
        $this->assertSame(1, $report['gaps']);
        $this->assertSame(17, WeatherReading::count());

        $first = WeatherReading::query()->orderBy('recorded_at')->skip(1)->first();
        $this->assertTrue($this->utc('2026-10-04 22:00:00')->equalTo($first->recorded_at));
        $this->assertSame(12.3, $first->temperature);
        $this->assertNotNull($first->temp_1);
        $this->assertNotNull($first->pressure_rel);
    }

    /** History dates are asked for in the station's own time zone. */
    public function test_the_request_covers_the_gap_in_the_station_time_zone(): void
    {
        $this->configureCloud();
        $this->fakeCloud();
        $this->reading('2026-10-04 21:55:00');
        $this->reading('2026-10-04 23:05:00');
        $this->reading('2026-10-04 23:15:00');
        $this->reading('2026-10-04 23:25:00');

        $this->run_();

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'device/history')
            && $r['start_date'] === '2026-10-04 23:55:00'
            && $r['end_date'] === '2026-10-05 01:05:00'
            // The real API answers "all is invalid" to call_back=all here.
            && $r['call_back'] !== 'all'
            && str_contains($r['call_back'], 'temp_and_humidity_ch1'));
    }

    /** Old rows added later must never look like the newest reading. */
    public function test_filled_readings_are_dated_when_they_were_measured(): void
    {
        $this->configureCloud();
        $this->fakeCloud();
        $this->reading('2026-10-04 21:55:00');
        $this->reading('2026-10-04 23:05:00');
        $this->reading('2026-10-04 23:15:00');
        $newest = $this->reading('2026-10-04 23:25:00', 19.0);

        $this->run_();

        $this->assertSame($newest->id, WeatherReading::mostRecent()->id);
        // Filled rows carry the time they were measured as created_at, so a
        // lookup by created_at still lands on a row stored live.
        $filled = WeatherReading::query()->where('id', '>', $newest->id)->get();
        $this->assertCount(13, $filled);
        foreach ($filled as $row) {
            $this->assertTrue($row->created_at->equalTo($row->recorded_at));
        }
        $this->assertTrue(WeatherReading::latest('created_at')->first()->created_at->equalTo($newest->created_at));
    }

    public function test_running_again_finds_nothing_left_to_fill(): void
    {
        $this->configureCloud();
        $this->fakeCloud();
        $this->reading('2026-10-04 21:55:00');
        $this->reading('2026-10-04 23:05:00');
        $this->reading('2026-10-04 23:15:00');
        $this->reading('2026-10-04 23:25:00');

        $this->run_();
        $again = $this->run_();

        $this->assertSame(0, $again['gaps']);
        $this->assertSame(0, $again['inserted']);
        $this->assertSame(17, WeatherReading::count());
    }

    /**
     * recorded_at is stored in the app's time zone. Comparing it with a UTC
     * time put the start of the window hours off, and a gap started from the
     * window edge instead of from the last reading.
     */
    public function test_a_gap_starts_at_the_last_reading_in_any_app_time_zone(): void
    {
        $this->configureCloud();
        $this->fakeCloud();
        $this->reading('2026-10-04 01:00:00');
        $this->reading('2026-10-04 23:25:00');

        app(EcowittBackfill::class)->run(1, $this->utc('2026-10-04 23:30:00'));

        // 01:00 UTC is 03:00 in the station's Europe/Berlin.
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'device/history')
            && $r['start_date'] === '2026-10-04 03:00:00');
    }

    public function test_short_pauses_are_not_gaps(): void
    {
        $this->configureCloud();
        $this->fakeCloud();
        $this->reading('2026-10-04 23:05:00');
        $this->reading('2026-10-04 23:15:00');
        $this->reading('2026-10-04 23:25:00');

        $this->assertSame(0, $this->run_()['gaps']);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'device/history'));
    }

    public function test_nothing_happens_without_cloud_keys(): void
    {
        Http::fake();
        $this->reading('2026-10-04 21:55:00');
        $this->reading('2026-10-04 23:25:00');

        $report = $this->run_();

        $this->assertSame(0, $report['inserted']);
        $this->assertNotNull($report['skipped']);
        Http::assertNothingSent();
    }

    public function test_it_can_be_switched_off(): void
    {
        $this->configureCloud();
        Setting::setValue('ecowitt.backfill_enabled', '0', 'boolean', 'ecowitt');
        Http::fake();
        $this->reading('2026-10-04 21:55:00');
        $this->reading('2026-10-04 23:25:00');

        $this->artisan('ecowitt:backfill')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_the_days_it_touched_get_a_fresh_summary(): void
    {
        $this->configureCloud();
        $this->fakeCloud();
        $this->reading('2026-10-04 21:55:00', 30.0);
        $this->reading('2026-10-04 23:05:00', 30.0);
        $this->reading('2026-10-04 23:15:00', 30.0);
        $this->reading('2026-10-04 23:25:00', 30.0);

        $this->run_();

        $date = $this->utc('2026-10-04 22:00:00')->setTimezone(config('app.timezone'))->toDateString();
        $summary = DailySummary::whereDate('date', $date)->first();
        $this->assertNotNull($summary);
        $this->assertSame(11.9, (float) $summary->temp_low);
    }

    public function test_an_api_error_is_reported_not_thrown(): void
    {
        $this->configureCloud();
        Http::fake(['*' => Http::response(['code' => 40010, 'msg' => 'Illegal Application_Key Parameter', 'data' => null])]);
        $this->reading('2026-10-04 21:55:00');
        $this->reading('2026-10-04 23:25:00');

        $report = $this->run_();

        $this->assertSame(0, $report['inserted']);
        $this->assertSame('Illegal Application_Key Parameter', $report['error']);
    }

    public function test_the_last_run_is_remembered_for_the_settings_page(): void
    {
        $this->configureCloud();
        $this->fakeCloud();
        $this->reading('2026-10-04 21:55:00');
        $this->reading('2026-10-04 23:05:00');
        $this->reading('2026-10-04 23:15:00');
        $this->reading('2026-10-04 23:25:00');

        $this->run_();

        $last = Setting::getValue('ecowitt.backfill_last_run');
        $this->assertSame(13, $last['inserted']);
    }
}
