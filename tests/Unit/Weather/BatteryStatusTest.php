<?php

declare(strict_types=1);

namespace Tests\Unit\Weather;

use App\Support\BatteryStatus;
use Tests\TestCase;

/**
 * Ecowitt sensors report their batteries three different ways: a 0/1 flag, a
 * 0 to 5 level, or a voltage. Which one depends on the sensor, and the cloud
 * API uses other names than the push protocol for the same sensors, wraps
 * every value in time/unit/value, and reports the lightning sensor as a flag
 * where push sends a level.
 *
 * The admin and the public dashboard each had their own guess at this, and
 * both treated anything they did not recognise as a flag, so a healthy 3.1 V
 * pack read Low (#131). This is the one place that knows.
 *
 * Types: aioecowitt (Home Assistant) for push names, CumulusMX for the cloud.
 */
class BatteryStatusTest extends TestCase
{
    private function payload(string $name): array
    {
        return (require base_path('tests/Fixtures/Ecowitt/payloads.php'))[$name];
    }

    private function states(array $classified): array
    {
        return array_map(fn (array $e) => $e['state'], $classified);
    }

    // ── What dft601 reported on #131 ──────────────────────────────────────

    /** Cloud API: WS90 pack, WS90 capacitor and lightning sensor, all healthy. */
    public function test_the_cloud_station_from_131_reads_healthy(): void
    {
        $classified = BatteryStatus::classify($this->payload('cloud_ws90_lightning_raining')['battery']);

        $this->assertSame([
            'haptic_array_battery' => 'good',
            'haptic_array_capacitor' => 'good',
            'lightning_sensor' => 'good',
        ], $this->states($classified));
    }

    /** A real cloud response: every sensor healthy, lightning at level 4. */
    public function test_a_captured_cloud_station_reads_healthy(): void
    {
        $classified = BatteryStatus::classify($this->payload('cloud_wh65_captured')['battery']);

        $this->assertSame([
            'outdoor_t_rh_sensor' => 'good',
            'sensor_array' => 'good',
            'lightning_sensor' => 'good',
            'temp_humidity_sensor_ch1' => 'good',
            'temp_humidity_sensor_ch2' => 'good',
        ], $this->states($classified));
    }

    /** On the cloud the lightning sensor is a level, as in push: 1 is low. */
    public function test_a_cloud_lightning_sensor_at_level_one_reads_low(): void
    {
        $classified = BatteryStatus::classify([
            'lightning_sensor' => ['time' => '1', 'unit' => '', 'value' => '1'],
        ]);

        $this->assertSame('level', $classified['lightning_sensor']['type']);
        $this->assertSame('low', $classified['lightning_sensor']['state']);
    }

    /** It used to read Good whatever the voltage, because it compared an object. */
    public function test_a_flat_ws90_pack_on_the_cloud_reads_low(): void
    {
        $classified = BatteryStatus::classify([
            'haptic_array_battery' => ['time' => '1', 'unit' => 'V', 'value' => '2.2'],
        ]);

        $this->assertSame('low', $classified['haptic_array_battery']['state']);
    }

    // ── Push names, from a real payload ───────────────────────────────────

    public function test_every_kind_of_push_battery_is_judged_by_its_own_kind(): void
    {
        $stored = BatteryStatus::fromPush($this->payload('push_wn1980b_mixed'));
        $classified = BatteryStatus::classify($stored);

        $this->assertSame('flag', $classified['wh26batt']['type']);
        $this->assertSame('flag', $classified['batt1']['type']);
        $this->assertSame('level', $classified['co2_batt']['type']);
        $this->assertSame('volts', $classified['wh68batt']['type']);
        $this->assertSame('volts', $classified['wh40batt']['type']);
        $this->assertSame('volts', $classified['leaf_batt1']['type']);
        $this->assertSame('volts', $classified['console_batt']['type']);

        $this->assertSame(
            ['good'],
            array_values(array_unique($this->states($classified))),
            'every sensor in this healthy station should read healthy'
        );
    }

    /** Volts stay decimals; rounding 1.84 V down to 1 is how they used to read Low. */
    public function test_push_voltages_are_kept_as_decimals(): void
    {
        $stored = BatteryStatus::fromPush($this->payload('push_wn1980b_mixed'));

        $this->assertSame(1.84, $stored['wh68batt']);
        $this->assertSame(1.36, $stored['leaf_batt1']);
        $this->assertSame(0, $stored['wh26batt'], 'flags stay whole numbers');
        $this->assertSame(6, $stored['co2_batt'], 'levels stay whole numbers');
    }

    /** Level 6 is not a seventh step: the sensor is on mains power. */
    public function test_level_six_means_mains_power(): void
    {
        $classified = BatteryStatus::classify(['co2_batt' => 6]);

        $this->assertSame('good', $classified['co2_batt']['state']);
        $this->assertSame('Mains', $classified['co2_batt']['status']);
    }

    public function test_the_ws90_from_push_keeps_its_existing_names(): void
    {
        $stored = BatteryStatus::fromPush($this->payload('push_gw2000a_ws90'));

        $this->assertSame(2.74, $stored['haptic_array_battery']);
        $this->assertSame(5.4, $stored['haptic_array_capacitor']);
    }

    public function test_a_level_sensor_runs_from_full_to_low(): void
    {
        $classified = BatteryStatus::classify(['wh57batt' => 5, 'pm25batt1' => 2, 'leakbatt1' => 1]);

        $this->assertSame('good', $classified['wh57batt']['state']);
        $this->assertSame('medium', $classified['pm25batt1']['state']);
        $this->assertSame('low', $classified['leakbatt1']['state']);
    }

    // ── What it must not do ───────────────────────────────────────────────

    /**
     * An unknown sensor reporting something other than 0 or 1 is not known to
     * be low. Saying Low is what made healthy sensors look broken.
     */
    public function test_an_unknown_reading_is_not_called_low(): void
    {
        $classified = BatteryStatus::classify(['some_new_sensor' => 3.3]);

        $this->assertSame('unknown', $classified['some_new_sensor']['state']);
    }

    public function test_rows_stored_before_this_change_still_read_correctly(): void
    {
        // A cloud row stored wrapped, and a push row stored flat, as they are in
        // existing databases. No migration: the reader copes with both.
        $classified = BatteryStatus::classify(json_encode([
            'lightning_sensor' => ['time' => '1', 'unit' => '', 'value' => '5'],
            'wh65batt' => 0,
        ]));

        $this->assertSame('good', $classified['lightning_sensor']['state']);
        $this->assertSame('good', $classified['wh65batt']['state']);
    }

    public function test_nothing_in_gives_nothing_out(): void
    {
        $this->assertSame([], BatteryStatus::classify(null));
        $this->assertSame([], BatteryStatus::classify(''));
        $this->assertSame([], BatteryStatus::fromPush(['tempf' => '68.0']));
    }
}
