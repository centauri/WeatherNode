<?php

declare(strict_types=1);

namespace Tests\Unit\Weather;

use App\Services\Weather\NetatmoParser;
use Tests\TestCase;

class NetatmoParserTest extends TestCase
{
    private function device(int $index): array
    {
        return (require base_path('tests/Fixtures/Netatmo/getstationsdata.php'))['body']['devices'][$index];
    }

    public function test_every_module_of_an_owned_station_lands_in_its_column(): void
    {
        $data = (new NetatmoParser())->parse($this->device(1));

        $this->assertSame(28.6, $data['temperature']);
        $this->assertSame(24, $data['humidity']);
        $this->assertSame(24.6, $data['temperature_indoor']);
        $this->assertSame(36, $data['humidity_indoor']);
        $this->assertSame(1017.3, $data['pressure_rel']);
        $this->assertSame(939.7, $data['pressure_abs']);
        $this->assertSame(749, $data['co2']);
        $this->assertSame(4.0, $data['wind_speed']);
        $this->assertSame(9.0, $data['wind_gust']);
        $this->assertSame(217, $data['wind_direction']);
        $this->assertSame(21.0, $data['wind_gust_max_daily']);
        $this->assertSame(0.5, $data['rain_hourly']);
        $this->assertSame(3.1, $data['rain_daily']);
        $this->assertArrayNotHasKey('rain_rate', $data);
        $this->assertSame(28.0, $data['temp_1']);
        $this->assertSame(26, $data['humidity_1']);
        $this->assertSame(26.4, $data['temp_2']);
        // The third indoor module lost contact and has no readings.
        $this->assertArrayNotHasKey('temp_3', $data);
        $this->assertSame('MyStation', $data['station_model']);
    }

    public function test_the_time_is_the_outdoor_modules_time(): void
    {
        $data = (new NetatmoParser())->parse($this->device(1));

        $this->assertSame(1559413157, $data['recorded_at']->timestamp);
    }

    public function test_a_favorite_public_station_gives_outdoor_readings(): void
    {
        $data = (new NetatmoParser())->parse($this->device(0));

        $this->assertSame(15.5, $data['temperature']);
        $this->assertSame(70, $data['humidity']);
        $this->assertSame(1012.0, $data['pressure_rel']);
        $this->assertArrayNotHasKey('temperature_indoor', $data);
    }
}
