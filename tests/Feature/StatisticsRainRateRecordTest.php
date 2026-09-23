<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DailySummary;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatisticsRainRateRecordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DailySummary::create([
            'date' => '2024-06-11',
            'temp_high' => 18.2,
            'temp_low' => 9.7,
            'temp_avg' => 13.9,
            'humidity_high' => 97,
            'humidity_low' => 61,
            'humidity_avg' => 82,
            'pressure_high' => 1009.4,
            'pressure_low' => 1003.1,
            'pressure_avg' => 1006.2,
            'wind_max' => 20.9,
            'wind_avg' => 6.1,
            'rain_total' => 12.4,
            'rain_rate_max' => 36.6,
        ]);
    }

    public function test_highest_rain_rate_record_is_labelled_as_a_rate(): void
    {
        $response = $this->get(route('statistics', ['units' => 'metric']));

        $response->assertOk();
        $response->assertSee('36.6 mm/h');
        $response->assertSee('12.4 mm');
    }

    public function test_highest_rain_rate_record_converts_to_inches_per_hour(): void
    {
        $response = $this->get(route('statistics', ['units' => 'imperial']));

        $response->assertOk();
        $response->assertSee('1.4 in/h');
    }

    public function test_highest_rain_rate_record_follows_the_per_minute_setting(): void
    {
        Setting::setValue('display.rainrate_unit', '/min', 'string', 'display');

        $response = $this->get(route('statistics', ['units' => 'metric']));

        $response->assertOk();
        $response->assertSee('0.6 mm/min');
    }
}
