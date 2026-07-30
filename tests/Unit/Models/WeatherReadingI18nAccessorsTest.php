<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\WeatherReading;
use App\Support\WindCompass;
use Tests\TestCase;

class WeatherReadingI18nAccessorsTest extends TestCase
{
    public function test_wind_compass_returns_english_points(): void
    {
        $reading = new WeatherReading(['wind_direction' => 168]);

        $this->assertSame('SSE', $reading->wind_direction_compass);
        $this->assertSame('SSE', $reading->wind_direction_compass_en);
        $this->assertSame('SSE', WindCompass::fromDegrees(168.0));
    }

    public function test_beaufort_uv_and_pm25_return_english_keys(): void
    {
        $reading = new WeatherReading([
            'wind_speed' => 25,
            'uv_index' => 2,
            'pm25_ch1' => 10,
        ]);

        $this->assertSame('Moderate breeze', $reading->beaufort_description);
        $this->assertSame('Low', $reading->uv_level);
        $this->assertSame('Good', $reading->pm25_level);
    }

    public function test_high_uv_and_hazardous_pm25_keys(): void
    {
        $reading = new WeatherReading([
            'uv_index' => 11,
            'pm25_ch1' => 300,
        ]);

        $this->assertSame('Extreme', $reading->uv_level);
        $this->assertSame('Hazardous', $reading->pm25_level);
    }
}
