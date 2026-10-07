<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\DailySummary;
use App\Models\Setting;
use App\Models\WeatherReading;
use App\Support\WuPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Uploads in the Wunderground format, as WeeWX sends them with
 * [[Wunderground]] server_url pointed at WeatherNode. The query below is
 * built the way WeeWX 5 restx.py builds it, in US units.
 */
class WuPushReceiverTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'test-station-key-123';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Setting::setValue('livedata.format', WuPush::FORMAT, 'select', 'livedata');
        Setting::setValue(WuPush::KEY_SETTING, self::KEY, 'string', 'livedata');
    }

    private function weewxQuery(string $password = self::KEY, array $extra = []): string
    {
        $fields = [
            'action' => 'updateraw',
            'ID' => 'weathernode',
            'PASSWORD' => $password,
            'softwaretype' => 'weewx-5.5.2',
            'dateutc' => now('UTC')->format('Y-m-d H:i:s'),
            'baromin' => '29.921',
            'dailyrainin' => '0.12',
            'dewptf' => '48.4',
            'humidity' => '081',
            'rainin' => '0.04',
            'tempf' => '54.1',
            'UV' => '3.00',
            'winddir' => '225',
            'windgustmph' => '012.0',
            'windspeedmph' => '008.5',
            'solarradiation' => '412.00',
            'realtime' => '1',
            'rtfreq' => '2.5',
        ];

        return '/api/wu/receive?' . http_build_query($extra + $fields) . '&AqPM2.5=7.0';
    }

    public function test_a_weewx_upload_is_stored_in_metric(): void
    {
        $this->get($this->weewxQuery())->assertOk()->assertSee('success');

        $reading = WeatherReading::mostRecent();
        $this->assertNotNull($reading);
        $this->assertEqualsWithDelta(12.3, (float) $reading->temperature, 0.05);
        $this->assertEqualsWithDelta(1013.2, (float) $reading->pressure_rel, 0.1);
        $this->assertEqualsWithDelta(13.7, (float) $reading->wind_speed, 0.1);
        $this->assertEqualsWithDelta(19.3, (float) $reading->wind_gust, 0.1);
        $this->assertSame(225, (int) $reading->wind_direction);
        $this->assertSame(81, (int) $reading->humidity);
        $this->assertEqualsWithDelta(3.05, (float) $reading->rain_daily, 0.01);
        $this->assertEqualsWithDelta(1.02, (float) $reading->rain_hourly, 0.01);
        $this->assertNull($reading->rain_rate);
        $this->assertEqualsWithDelta(3.0, (float) $reading->uv_index, 0.01);
        $this->assertEqualsWithDelta(7.0, (float) $reading->pm25_ch1, 0.01);
        $this->assertSame('weewx-5.5.2', $reading->station_type);
        $this->assertNotNull(WuPush::lastReceived());
    }

    public function test_a_post_works_too(): void
    {
        $this->post('/api/wu/receive', ['PASSWORD' => self::KEY, 'tempf' => '50', 'dateutc' => 'now'])
            ->assertOk()->assertSee('success');

        $this->assertSame(1, WeatherReading::query()->count());
    }

    public function test_a_wrong_key_is_refused_with_an_error_weewx_will_not_retry(): void
    {
        $this->get($this->weewxQuery('wrong'))->assertStatus(401)->assertSee('ERROR');
        $this->get('/api/wu/receive?tempf=50')->assertStatus(401);

        $this->assertSame(0, WeatherReading::query()->count());
        $this->assertNull(WuPush::lastReceived());
    }

    public function test_nothing_is_stored_when_another_source_is_live(): void
    {
        Setting::setValue('livedata.format', 'ecoLcl', 'select', 'livedata');

        $this->get($this->weewxQuery())->assertStatus(403)->assertSee('ERROR');
        $this->assertSame(0, WeatherReading::query()->count());
    }

    public function test_without_a_key_nothing_is_accepted(): void
    {
        Setting::setValue(WuPush::KEY_SETTING, '', 'string', 'livedata');

        $this->get($this->weewxQuery(''))->assertStatus(503);
        $this->assertSame(0, WeatherReading::query()->count());
    }

    public function test_rapidfire_is_stored_once_a_minute(): void
    {
        $this->get($this->weewxQuery())->assertOk();
        $this->get($this->weewxQuery(extra: ['tempf' => '55.0']))->assertOk()->assertSee('success');
        $this->assertSame(1, WeatherReading::query()->count());

        $this->travel(WuPush::MIN_SECONDS_BETWEEN_SAVES + 1)->seconds();
        $this->get($this->weewxQuery(extra: ['tempf' => '56.0']))->assertOk();
        $this->assertSame(2, WeatherReading::query()->count());
    }

    public function test_missing_sensors_sent_as_minus_9999_are_left_out(): void
    {
        $this->get($this->weewxQuery(extra: ['UV' => '-9999', 'solarradiation' => '-9999']))->assertOk();

        $reading = WeatherReading::mostRecent();
        $this->assertNull($reading->uv_index);
        $this->assertNull($reading->solar_radiation);
    }

    public function test_an_upload_without_temperature_is_refused(): void
    {
        $this->get('/api/wu/receive?PASSWORD=' . self::KEY . '&humidity=80')->assertStatus(400);
        $this->assertSame(0, WeatherReading::query()->count());
    }

    public function test_the_scheduler_updates_todays_summary_from_the_upload(): void
    {
        $this->get($this->weewxQuery())->assertOk();

        $this->artisan('weather:fetch', ['--save' => true])->assertSuccessful();

        $today = DailySummary::whereDate('date', now()->toDateString())->first();
        $this->assertNotNull($today);
        $this->assertSame(1, WeatherReading::query()->count());
    }
}
