<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Setting;
use App\Models\WeatherReading;
use App\Services\Weather\EcowittPushParser;
use App\Services\Weather\EcowittService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #129: a WS90 reports its battery pack and solar capacitor as voltages. The
 * push and local-file paths dropped both, so only cloud API users saw them.
 * They arrive as wh90batt and ws90cap_volt and are stored under the cloud
 * API names the dashboard understands, as decimals: cast to int, a healthy
 * 3.14 V pack read as 3 and would sit close to the 2.7 V low mark.
 */
class EcowittWs90BatteryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_push_stores_ws90_voltages_as_decimals(): void
    {
        Setting::setValue('ecowitt.secure_mode', false, 'boolean', 'ecowitt');

        $this->post('/api/ecowitt/receive', [
            'PASSKEY' => 'push-pass',
            'tempf' => '68.0',
            'humidity' => '55',
            'wh90batt' => '3.14',
            'ws90cap_volt' => '5.3',
            'wh65batt' => '0',
        ])->assertOk();

        $battery = WeatherReading::query()->latest('id')->firstOrFail()->battery_status;

        $this->assertSame(3.14, $battery['haptic_array_battery']);
        $this->assertSame(5.3, $battery['haptic_array_capacitor']);
        $this->assertSame(0, $battery['wh65batt'], 'flag sensors stay whole numbers');
    }

    public function test_stations_without_a_ws90_get_no_ws90_entries(): void
    {
        $data = (new EcowittPushParser())->parse(['tempf' => '68.0', 'wh65batt' => '0']);

        $this->assertArrayNotHasKey('haptic_array_battery', $data['battery_status']);
        $this->assertArrayNotHasKey('haptic_array_capacitor', $data['battery_status']);
    }

    public function test_a_value_that_is_not_a_number_is_ignored(): void
    {
        $data = (new EcowittPushParser())->parse(['wh90batt' => 'n/a', 'wh65batt' => '0']);

        $this->assertArrayNotHasKey('haptic_array_battery', $data['battery_status']);
    }

    public function test_the_local_file_path_keeps_ws90_voltages(): void
    {
        $reading = app(EcowittService::class)->saveReading([
            'tempf' => '68.0',
            'wh90batt' => '3.14',
            'ws90cap_volt' => '5.3',
        ]);

        $this->assertSame(3.14, $reading->battery_status['haptic_array_battery']);
        $this->assertSame(5.3, $reading->battery_status['haptic_array_capacitor']);
    }

    public function test_the_local_file_path_adds_nothing_without_a_ws90(): void
    {
        $reading = app(EcowittService::class)->saveReading(['tempf' => '68.0', 'wh65batt' => '0']);

        $this->assertArrayNotHasKey('haptic_array_battery', $reading->battery_status);
    }

    /**
     * The local file holds the same fields as a push, so it is stored the same
     * way. Converting it to a cloud-like shape first dropped everything but two
     * extra temperatures and one humidity.
     */
    public function test_the_local_file_stores_everything_a_push_does(): void
    {
        $raw = [
            'dateutc' => '2026-10-05 12:00:00',
            'tempf' => '68.0',
            'temp3f' => '50.0',
            'humidity2' => '71',
            'soilmoisture1' => '34',
            'pm25_ch1' => '12.5',
            'co2' => '612',
            'leak_ch1' => '1',
            'windspdmph_avg10m' => '10',
        ];

        $local = app(EcowittService::class)->saveReading($raw)->fresh();

        $this->assertSame(10.0, $local->temp_3);
        $this->assertSame(71, $local->humidity_2);
        $this->assertSame(34, $local->soil_moisture_1);
        $this->assertSame(12.5, $local->pm25_ch1);
        $this->assertSame(612, $local->co2);
        $this->assertTrue((bool) $local->leak_ch1);
        $this->assertSame(16.1, $local->wind_speed_avg_10m);
    }
}
