<?php

declare(strict_types=1);

namespace Tests\Unit\Weather;

use App\Services\Weather\EcowittCloudParser;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * The cloud API names its groups differently from push and the local file, so
 * it gets its own parser that fills the same columns. Field names and units
 * are from Ecowitt's API v3 doc:
 * https://doc.ecowitt.net/web/#/apiv3en?page_id=17
 */
class EcowittCloudParserTest extends TestCase
{
    private function captured(): array
    {
        return json_decode(file_get_contents(base_path('tests/Fixtures/Ecowitt/cloud-wh65-2026-10-05.json')), true);
    }

    private function wrap(string $value, string $unit = ''): array
    {
        return ['time' => '1791209779', 'unit' => $unit, 'value' => $value];
    }

    public function test_a_captured_response_fills_the_core_columns(): void
    {
        $data = (new EcowittCloudParser())->parse($this->captured());

        $this->assertSame(19.2, $data['temperature']);
        $this->assertSame(16.2, $data['dew_point']);
        $this->assertSame(83, $data['humidity']);
        $this->assertSame(23.5, $data['temperature_indoor']);
        $this->assertSame(23.5, $data['indoor_temperature']);
        $this->assertSame(1018.9, $data['pressure_rel']);
        $this->assertSame(7.2, $data['wind_speed']);
        $this->assertSame(13.0, $data['wind_gust']);
        $this->assertSame(148, $data['wind_direction']);
        $this->assertSame(322.2, $data['solar_radiation']);
        $this->assertSame(3.0, $data['uv_index']);
        $this->assertSame(354.5, $data['rain_yearly']);
        $this->assertSame(4, $data['battery_status']['lightning_sensor']);
    }

    /** They used to be read from a group the cloud does not send, so were always empty. */
    public function test_extra_temperature_and_humidity_channels_are_stored(): void
    {
        $data = (new EcowittCloudParser())->parse($this->captured());

        $this->assertSame(19.4, $data['temp_1']);
        $this->assertSame(83, $data['humidity_1']);
        $this->assertSame(15.7, $data['temp_2']);
        $this->assertArrayNotHasKey('humidity_2', $data);
    }

    /** The time on the distance reading is when the last strike was detected. */
    public function test_the_last_lightning_strike_time_is_stored(): void
    {
        $data = (new EcowittCloudParser())->parse($this->captured());

        $this->assertSame(5, $data['lightning_distance']);
        $this->assertSame(0, $data['lightning_count_daily']);
        $this->assertTrue(Carbon::createFromTimestamp(1790640271)->equalTo($data['lightning_time']));
    }

    /** The doc's defaults are imperial; an account that gets them is converted. */
    public function test_imperial_units_are_converted(): void
    {
        $data = (new EcowittCloudParser())->parse([
            'outdoor' => ['temperature' => $this->wrap('68.0', 'ºF')],
            'pressure' => ['relative' => $this->wrap('29.92', 'inHg')],
            'wind' => ['wind_speed' => $this->wrap('10', 'mph'), 'wind_gust' => $this->wrap('5', 'm/s')],
            'lightning' => ['distance' => $this->wrap('10', 'mi')],
            'rainfall' => ['daily' => $this->wrap('0.10', 'in')],
            'temp_and_humidity_ch1' => ['temperature' => $this->wrap('50.0', '℉')],
        ]);

        $this->assertSame(20.0, $data['temperature']);
        $this->assertSame(1013.2, $data['pressure_rel']);
        $this->assertSame(16.1, $data['wind_speed']);
        $this->assertSame(18.0, $data['wind_gust']);
        $this->assertSame(16, $data['lightning_distance']);
        $this->assertSame(2.54, $data['rain_daily']);
        $this->assertSame(10.0, $data['temp_1']);
    }

    public function test_air_quality_soil_leaf_and_leak_sensors_are_stored(): void
    {
        $data = (new EcowittCloudParser())->parse([
            'pm25_ch1' => ['pm25' => $this->wrap('12.5', 'µg/m3')],
            'pm25_ch2' => ['pm25' => $this->wrap('8', 'µg/m3')],
            'co2_aqi_combo' => ['co2' => $this->wrap('612', 'ppm'), '24_hours_average' => $this->wrap('580', 'ppm')],
            'pm10_aqi_combo' => ['pm10' => $this->wrap('20.1', 'µg/m3')],
            't_rh_aqi_combo' => ['temperature' => $this->wrap('21.0', '℃'), 'humidity' => $this->wrap('48', '%')],
            'soil_ch1' => ['soilmoisture' => $this->wrap('34', '%')],
            'leaf_ch2' => ['leaf_wetness' => $this->wrap('7', '%')],
            'water_leak' => [
                'leak_ch1' => $this->wrap('0'),
                'leak_ch2' => $this->wrap('1'),
                'leak_ch3' => $this->wrap('2'),
            ],
        ]);

        $this->assertSame(12.5, $data['pm25_ch1']);
        $this->assertSame(8.0, $data['pm25_ch2']);
        $this->assertSame(612, $data['co2']);
        $this->assertSame(580, $data['co2_avg_24h']);
        $this->assertSame(20.1, $data['pm10']);
        $this->assertSame(21.0, $data['co2_temp']);
        $this->assertSame(48, $data['co2_humidity']);
        $this->assertSame(34, $data['soil_moisture_1']);
        $this->assertSame(7, $data['leaf_wetness_2']);
        $this->assertFalse($data['leak_ch1']);
        $this->assertTrue($data['leak_ch2']);
        // 2 means the sensor is offline, which is not a leak.
        $this->assertArrayNotHasKey('leak_ch3', $data);
    }

    public function test_the_combo_sensor_fills_pm25_only_when_there_is_no_pm25_channel(): void
    {
        $parser = new EcowittCloudParser();

        $this->assertSame(9.0, $parser->parse([
            'pm25_aqi_combo' => ['pm25' => $this->wrap('9', 'µg/m3')],
        ])['pm25_ch1']);

        $this->assertSame(12.5, $parser->parse([
            'pm25_ch1' => ['pm25' => $this->wrap('12.5', 'µg/m3')],
            'pm25_aqi_combo' => ['pm25' => $this->wrap('9', 'µg/m3')],
        ])['pm25_ch1']);
    }

    public function test_missing_groups_leave_their_columns_out(): void
    {
        $data = (new EcowittCloudParser())->parse(['outdoor' => ['temperature' => $this->wrap('19.2', '℃')]]);

        $this->assertSame(19.2, $data['temperature']);
        $this->assertArrayNotHasKey('temp_1', $data);
        $this->assertArrayNotHasKey('lightning_time', $data);
        $this->assertArrayNotHasKey('battery_status', $data);
        $this->assertArrayHasKey('recorded_at', $data);
    }
}
