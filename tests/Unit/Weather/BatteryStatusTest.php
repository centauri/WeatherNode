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

    /**
     * Ecowitt's API v3 doc lists these as 0 to 5 levels (6 is mains power),
     * not OK/low flags: https://doc.ecowitt.net/web/#/apiv3en?page_id=17
     */
    public function test_cloud_air_quality_pm25_and_leak_batteries_are_levels(): void
    {
        $wrap = fn (string $v) => ['time' => '1', 'unit' => '', 'value' => $v];
        $classified = BatteryStatus::classify([
            'aqi_combo_sensor' => $wrap('6'),
            'pm25_sensor_ch1' => $wrap('5'),
            'pm25_sensor_ch2' => $wrap('1'),
            'water_leak_sensor_ch1' => $wrap('4'),
            'water_leak_sensor_ch2' => $wrap('0'),
        ]);

        $this->assertSame([
            'aqi_combo_sensor' => 'good',
            'pm25_sensor_ch1' => 'good',
            'pm25_sensor_ch2' => 'low',
            'water_leak_sensor_ch1' => 'good',
            'water_leak_sensor_ch2' => 'low',
        ], $this->states($classified));
        $this->assertSame('Mains', $classified['aqi_combo_sensor']['status']);
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

    /** The dashboard draws a capacitor as a capacitor, not as a battery. */
    public function test_each_entry_says_which_icon_it_needs(): void
    {
        $classified = BatteryStatus::classify([
            'haptic_array_battery' => 3.12,
            'haptic_array_capacitor' => 5.3,
            'ws85cap_volt' => 4.1,
            'pm25_sensor_ch1' => ['time' => '1', 'unit' => '', 'value' => '6'],
            'wh65batt' => 0,
        ]);

        $this->assertSame('battery', $classified['haptic_array_battery']['icon']);
        $this->assertSame('capacitor', $classified['haptic_array_capacitor']['icon']);
        $this->assertSame('capacitor', $classified['ws85cap_volt']['icon']);
        $this->assertSame('mains', $classified['pm25_sensor_ch1']['icon']);
        $this->assertSame('battery', $classified['wh65batt']['icon']);
    }

    /** Short enough to fit one line of the dashboard card. */
    public function test_the_ws90_labels_are_short(): void
    {
        $classified = BatteryStatus::classify(['haptic_array_battery' => 3.12, 'haptic_array_capacitor' => 5.3]);

        $this->assertSame('WS90 batteries', $classified['haptic_array_battery']['label']);
        $this->assertSame('WS90 capacitor', $classified['haptic_array_capacitor']['label']);
    }

    /** Every label and status, so the dashboard can translate all of them. */
    public function test_it_lists_every_string_to_translate(): void
    {
        $strings = BatteryStatus::translatable();

        foreach (['WS90 batteries', 'Lightning Sensor (WH57)', 'Lightning Sensor', 'PM2.5 Sensor', 'Leak Sensor', 'Low', 'Good', 'Moderate', 'Mains', 'Unknown'] as $string) {
            $this->assertContains($string, $strings);
        }
    }

    /**
     * A healthy OK/low sensor says Good, like a level sensor. "OK" was looked
     * up as the generic OK, which Spanish translates as "De acuerdo." (Agreed).
     */
    public function test_a_healthy_flag_sensor_says_good(): void
    {
        $classified = BatteryStatus::classify(['wh65batt' => 0, 'wh26batt' => 1]);

        $this->assertSame('Good', $classified['wh65batt']['status']);
        $this->assertSame('Low', $classified['wh26batt']['status']);
        $this->assertNotContains('OK', BatteryStatus::translatable());
    }
}
