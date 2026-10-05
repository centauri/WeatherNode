<?php

declare(strict_types=1);

namespace Tests\Unit\Weather;

use App\Services\Weather\EcowittCloudParser;
use App\Services\Weather\EcowittPushParser;
use App\Support\ExtraSensors;
use Tests\TestCase;

/**
 * Newer Ecowitt sensors with no columns of their own, kept together in
 * weather_readings.extra_sensors in metric: the WN38 black globe, WH52 soil
 * EC, LDS water level, wetness status and WQT01 water quality.
 *
 * Push names are from aioecowitt; cloud names and units from Ecowitt's API v3
 * doc. Push has no fields for wetness status or the WQT01 yet.
 */
class ExtraSensorsTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    private function wrap(string $value, string $unit = ''): array
    {
        return ['time' => '1791209779', 'unit' => $unit, 'value' => $value];
    }

    public function test_push_black_globe_soil_ec_and_water_level(): void
    {
        $sensors = ExtraSensors::fromPush([
            'bgt' => '71.96',
            'wbgt' => '68.00',
            'soil_ec_hum1' => '34',
            'soil_ec_temp1' => '50.0',
            'soil_ec1' => '512',
            'depth_ch2' => '1250',
            'air_ch2' => '750',
            'ldsheat_ch2' => '8806',
        ]);

        $this->assertSame(22.2, $sensors['bgt']);
        $this->assertSame(20.0, $sensors['wbgt']);
        $this->assertSame(['moisture' => 34, 'temperature' => 10.0, 'ec' => 512], $sensors['soil_ec'][1]);
        $this->assertSame(['depth' => 1250.0, 'air' => 750.0, 'heater' => 8806], $sensors['water_level'][2]);
    }

    public function test_push_celsius_black_globe_fields_are_used_as_sent(): void
    {
        $this->assertSame(22.4, ExtraSensors::fromPush(['bgtc' => '22.4'])['bgt']);
    }

    public function test_cloud_groups_are_read_and_converted(): void
    {
        $sensors = ExtraSensors::fromCloud([
            'black_globe_temperature' => ['bgt' => $this->wrap('71.96', 'ºF'), 'wbgt' => $this->wrap('20.0', '℃')],
            'soil_moisture_ec_ch3' => [
                'soilmoisture' => $this->wrap('2', '%'),
                'temperature' => $this->wrap('70.16', 'ºF'),
                'ec' => $this->wrap('480', 'μS/cm'),
            ],
            'ch_lds1' => ['air_ch1' => $this->wrap('7.26', 'ft'), 'depth_ch1' => $this->wrap('0.50', 'ft'), 'ldsheat_ch1' => $this->wrap('8806')],
            'wetness_status' => ['rain_level' => $this->wrap('1')],
            'wqt01' => [
                'ec' => $this->wrap('350', 'μS/cm'),
                'tds' => $this->wrap('175', 'mg/L'),
                'turb' => $this->wrap('1.2', 'NTU'),
                'leakalm' => $this->wrap('0'),
                'wateralm' => $this->wrap('1'),
                'dirtalm' => $this->wrap('0'),
            ],
        ]);

        $this->assertSame(22.2, $sensors['bgt']);
        $this->assertSame(20.0, $sensors['wbgt']);
        $this->assertSame(['moisture' => 2, 'temperature' => 21.2, 'ec' => 480], $sensors['soil_ec'][3]);
        $this->assertSame(['depth' => 152.4, 'air' => 2212.8, 'heater' => 8806], $sensors['water_level'][1]);
        $this->assertTrue($sensors['wetness']);
        $this->assertSame(350.0, $sensors['water_quality']['ec']);
        $this->assertSame(1.2, $sensors['water_quality']['turbidity']);
        $this->assertFalse($sensors['water_quality']['leak']);
        $this->assertTrue($sensors['water_quality']['shortage']);
    }

    public function test_a_station_without_them_gets_nothing(): void
    {
        $this->assertSame([], ExtraSensors::fromPush(['tempf' => '68.0']));
        $this->assertSame([], ExtraSensors::fromCloud(json_decode(
            file_get_contents(base_path('tests/Fixtures/Ecowitt/cloud-wh65-2026-10-05.json')),
            true
        )));
    }

    public function test_both_parsers_store_them(): void
    {
        $push = (new EcowittPushParser())->parse(['tempf' => '68.0', 'bgt' => '71.96']);
        $cloud = (new EcowittCloudParser())->parse(['wetness_status' => ['rain_level' => $this->wrap('0')]]);

        $this->assertSame(22.2, $push['extra_sensors']['bgt']);
        $this->assertFalse($cloud['extra_sensors']['wetness']);
        $this->assertArrayNotHasKey('extra_sensors', (new EcowittPushParser())->parse(['tempf' => '68.0']));
    }

    /** The dashboard card's lines: English labels, translated in the browser. */
    public function test_dashboard_lines(): void
    {
        $lines = ExtraSensors::lines([
            'bgt' => 22.2,
            'soil_ec' => [3 => ['moisture' => 2, 'temperature' => 21.2, 'ec' => 480]],
            'water_level' => [1 => ['depth' => 152.4, 'air' => 2212.8, 'heater' => 8806]],
            'wetness' => true,
            'water_quality' => ['tds' => 175.0, 'shortage' => true, 'leak' => false],
        ]);

        $this->assertContains(['label' => 'Black globe', 'channel' => null, 'value' => 22.2, 'unit' => '°C', 'kind' => 'temp'], $lines);
        $this->assertContains(['label' => 'Soil EC', 'channel' => 3, 'value' => 480, 'unit' => 'µS/cm', 'kind' => 'number'], $lines);
        $this->assertContains(['label' => 'Soil temperature', 'channel' => 3, 'value' => 21.2, 'unit' => '°C', 'kind' => 'temp'], $lines);
        $this->assertContains(['label' => 'Water depth', 'channel' => 1, 'value' => 152.4, 'unit' => 'mm', 'kind' => 'number'], $lines);
        $this->assertContains(['label' => 'Wetness', 'channel' => null, 'value' => 'Wet', 'unit' => '', 'kind' => 'state'], $lines);
        $this->assertContains(['label' => 'TDS', 'channel' => null, 'value' => 175.0, 'unit' => 'mg/L', 'kind' => 'number'], $lines);
        $this->assertContains(['label' => 'Water quality alarm', 'channel' => null, 'value' => 'Water shortage', 'unit' => '', 'kind' => 'state'], $lines);
        $this->assertNotContains(['label' => 'Water quality alarm', 'channel' => null, 'value' => 'Leak', 'unit' => '', 'kind' => 'state'], $lines);
    }

    public function test_the_public_dashboard_carries_them(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\ApiKeyMiddleware::class);
        \App\Models\WeatherReading::query()->create(['recorded_at' => now(), 'temperature' => 20, 'extra_sensors' => ['bgt' => 22.2]]);

        $more = $this->getJson('/api/weather/dashboard')->assertOk()->json('extra_sensors.more');

        $this->assertSame('Black globe', $more[0]['label']);
    }
}
