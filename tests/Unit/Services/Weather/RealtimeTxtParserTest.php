<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Weather;

use App\Services\Weather\LocalFiles\RealtimeTxtParser;
use Tests\TestCase;

class RealtimeTxtParserTest extends TestCase
{
    public function test_maps_avg_wind_direction_and_daily_max_gust(): void
    {
        $parts = array_fill(0, 58, '0');
        $parts[0] = '30/07/26';
        $parts[1] = '12:00:00';
        $parts[2] = '20.0';
        $parts[3] = '50';
        $parts[4] = '10.0';
        $parts[5] = '5.0';
        $parts[6] = '6.0';
        $parts[7] = '180';
        $parts[8] = '0.0';
        $parts[9] = '1.2';
        $parts[10] = '1013.0';
        $parts[13] = 'km/h';
        $parts[14] = 'C';
        $parts[15] = 'hPa';
        $parts[16] = 'mm';
        $parts[32] = '44.0';
        $parts[40] = '12.5';
        $parts[46] = '260';
        $parts[47] = '0.3';

        $data = (new RealtimeTxtParser())->parseContent(implode(' ', $parts), 'cumulus');

        $this->assertNotNull($data);
        $this->assertSame(5.0, $data['wind_speed_avg_10m']);
        $this->assertSame(260, $data['wind_direction_avg_10m']);
        $this->assertSame(12.5, $data['wind_gust']);
        $this->assertSame(44.0, $data['wind_gust_max_daily']);
        $this->assertSame(0.3, $data['rain_hourly']);
    }

    public function test_converts_daily_max_gust_units(): void
    {
        $parts = array_fill(0, 58, '0');
        $parts[0] = '30/07/26';
        $parts[1] = '12:00:00';
        $parts[2] = '20.0';
        $parts[3] = '50';
        $parts[4] = '10.0';
        $parts[5] = '5.0';
        $parts[6] = '6.0';
        $parts[7] = '180';
        $parts[8] = '0.0';
        $parts[9] = '1.2';
        $parts[10] = '1013.0';
        $parts[13] = 'mph';
        $parts[14] = 'C';
        $parts[15] = 'hPa';
        $parts[16] = 'mm';
        $parts[32] = '10.0';
        $parts[46] = '90';

        $data = (new RealtimeTxtParser())->parseContent(implode(' ', $parts), 'cumulus');

        $this->assertNotNull($data);
        $this->assertSame(16.1, $data['wind_gust_max_daily']);
        $this->assertSame(90, $data['wind_direction_avg_10m']);
    }
}
