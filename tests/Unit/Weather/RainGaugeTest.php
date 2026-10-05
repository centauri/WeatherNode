<?php

declare(strict_types=1);

namespace Tests\Unit\Weather;

use App\Models\Setting;
use App\Support\RainGauge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #132. A WS90 measures rain with a piezo sensor and reports it under its own
 * names: rainfall_piezo on the cloud API, rrain_piezo, drain_piezo and so on
 * in push. Nothing read them, so a WS90 station recorded no rain at all.
 *
 * On the cloud a WS90 station can also get a tipping-bucket group that reads
 * zero right through a storm, so "use the bucket when it is there" would have
 * kept the bug. Auto uses the bucket only once it has recorded rain this year.
 */
class RainGaugeTest extends TestCase
{
    use RefreshDatabase;

    private function payload(string $name): array
    {
        return (require base_path('tests/Fixtures/Ecowitt/payloads.php'))[$name];
    }

    // ── #132 ───────────────────────────────────────────────────────────────

    public function test_the_cloud_station_from_132_gets_its_rain(): void
    {
        $rain = RainGauge::choose(RainGauge::fromCloud($this->payload('cloud_ws90_lightning_raining')));

        $this->assertSame(2.3, $rain['rain_daily']);
        $this->assertSame(0.8, $rain['rain_rate']);
        $this->assertSame(612.7, $rain['rain_yearly']);
    }

    public function test_a_ws90_push_station_gets_its_rain(): void
    {
        $raw = $this->payload('push_gw2000a_ws90');
        $raw['drain_piezo'] = '0.091';
        $raw['rrain_piezo'] = '0.031';

        $rain = RainGauge::choose(RainGauge::fromPush($raw));

        $this->assertSame(2.31, $rain['rain_daily']);
        $this->assertSame(0.79, $rain['rain_rate']);
    }

    /** Some firmware also sends the piezo readings in mm. Use them as they are. */
    public function test_millimetre_piezo_fields_are_used_as_sent(): void
    {
        $rain = RainGauge::choose(RainGauge::fromPush(['drain_piezomm' => '2.4', 'drain_piezo' => '0.091']));

        $this->assertSame(2.4, $rain['rain_daily']);
    }

    // ── Stations that must keep what they have ────────────────────────────

    /** A tipping bucket and nothing else: unchanged. */
    public function test_a_tipping_bucket_station_is_unchanged(): void
    {
        $rain = RainGauge::choose(RainGauge::fromPush($this->payload('push_wn1980b_mixed')));

        $this->assertSame(125.6, $rain['rain_yearly']);
        $this->assertSame(26.29, $rain['rain_event']);
    }

    /**
     * A WS90 owner who also has a WH40 bucket that has recorded rain keeps the
     * bucket, which is what they have been seeing until now.
     */
    public function test_a_bucket_that_has_recorded_rain_is_kept_alongside_a_piezo(): void
    {
        $data = $this->payload('cloud_ws90_lightning_raining');
        $data['rainfall']['yearly']['value'] = '480.2';
        $data['rainfall']['daily']['value'] = '1.9';

        $rain = RainGauge::choose(RainGauge::fromCloud($data));

        $this->assertSame(1.9, $rain['rain_daily']);
    }

    // ── The owner's choice ─────────────────────────────────────────────────

    public function test_the_owner_can_pin_the_piezo(): void
    {
        Setting::setValue(RainGauge::SETTING, 'piezo', 'select', 'ecowitt');
        $data = $this->payload('cloud_ws90_lightning_raining');
        $data['rainfall']['yearly']['value'] = '480.2';

        $this->assertSame(2.3, RainGauge::choose(RainGauge::fromCloud($data))['rain_daily']);
    }

    public function test_the_owner_can_pin_the_bucket(): void
    {
        Setting::setValue(RainGauge::SETTING, 'tipping', 'select', 'ecowitt');

        $this->assertSame(0.0, RainGauge::choose(RainGauge::fromCloud($this->payload('cloud_ws90_lightning_raining')))['rain_daily']);
    }

    /** Pinning a gauge the station does not have should not blank the rain. */
    public function test_a_pinned_gauge_that_is_missing_falls_back_to_the_other(): void
    {
        Setting::setValue(RainGauge::SETTING, 'tipping', 'select', 'ecowitt');

        $rain = RainGauge::choose(RainGauge::fromPush(['drain_piezo' => '0.091']));

        $this->assertSame(2.31, $rain['rain_daily']);
    }

    // ── Units ──────────────────────────────────────────────────────────────

    /** The cloud answers in the account's units unless asked; cope with inches. */
    public function test_cloud_rain_in_inches_is_converted(): void
    {
        $rain = RainGauge::choose(RainGauge::fromCloud([
            'rainfall_piezo' => ['daily' => ['unit' => 'in', 'value' => '0.10']],
        ]));

        $this->assertSame(2.54, $rain['rain_daily']);
    }

    /** The API doc calls it hourly, but a captured response sends 1_hour. */
    public function test_cloud_hourly_rain_is_read_under_either_name(): void
    {
        $captured = RainGauge::choose(RainGauge::fromCloud($this->payload('cloud_wh65_captured')));
        $documented = RainGauge::choose(RainGauge::fromCloud([
            'rainfall' => ['hourly' => ['unit' => 'mm', 'value' => '0.6']],
        ]));

        $this->assertSame(0.0, $captured['rain_hourly'] ?? null);
        $this->assertSame(354.5, $captured['rain_yearly']);
        $this->assertSame(0.6, $documented['rain_hourly']);
    }

    public function test_no_rain_data_gives_nothing(): void
    {
        $this->assertSame([], RainGauge::choose(RainGauge::fromPush(['tempf' => '68.0'])));
        $this->assertSame([], RainGauge::choose(RainGauge::fromCloud([])));
    }
}
