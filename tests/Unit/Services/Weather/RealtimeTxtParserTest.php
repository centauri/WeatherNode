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

    // WeeWX writes a Cumulus realtime.txt through the crt extension
    // (github.com/matthewwall/weewx-crt, 0.23). It fills any value it does not
    // have with NULL, which we used to read as 0: a station with no indoor or
    // solar sensor showed 0 °C inside and 0 W/m² of sun. crt also writes UV as
    // a plain whole number, so a summer UV of 11 must not become 1.1.

    /** One crt 0.23 line, 58 fields, keyed by 0-based position. */
    private function crtLine(array $values): string
    {
        $fields = array_fill(0, 58, 'NULL');
        $base = [
            0 => '07/10/26', 1 => '14:05:00', 2 => '12.3', 3 => '81', 4 => '9.1',
            6 => '14.0', 7 => '225', 8 => '0.0', 9 => '1.2', 10 => '1012.4',
            13 => 'km/h', 14 => 'C', 15 => 'hPa', 16 => 'mm',
            19 => '40.1', 20 => '600.2', 38 => '5.5.2', 39 => '0',
        ];
        foreach ($values + $base as $i => $v) {
            $fields[$i] = $v;
        }

        return implode(' ', $fields);
    }

    public function test_null_fields_are_left_out_instead_of_read_as_zero(): void
    {
        $data = (new RealtimeTxtParser())->parseContent($this->crtLine([]), 'weewx');

        $this->assertSame(12.3, $data['temperature']);
        $this->assertSame(14.0, $data['wind_speed']);
        $this->assertSame(1.2, $data['rain_daily']);

        foreach ([
            'wind_speed_avg_10m', 'wind_gust', 'wind_gust_max_daily', 'wind_direction_avg_10m',
            'temperature_indoor', 'humidity_indoor', 'wind_chill', 'heat_index',
            'uv_index', 'solar_radiation', 'solar_hours', 'lux', 'rain_hourly',
        ] as $key) {
            $this->assertArrayNotHasKey($key, $data, "{$key} should be missing, not 0");
        }
    }

    public function test_weewx_uv_of_eleven_stays_eleven(): void
    {
        $data = (new RealtimeTxtParser())->parseContent($this->crtLine([43 => '11']), 'weewx');

        $this->assertSame(11.0, $data['uv_index']);
    }

    public function test_weewx_imperial_units_are_converted(): void
    {
        $line = $this->crtLine([
            2 => '50.0', 6 => '10.0', 9 => '0.50', 10 => '29.92',
            13 => 'mph', 14 => 'F', 15 => 'in', 16 => 'in',
        ]);
        $data = (new RealtimeTxtParser())->parseContent($line, 'weewx');

        $this->assertSame(10.0, $data['temperature']);
        $this->assertEqualsWithDelta(16.1, $data['wind_speed'], 0.1);
        $this->assertEqualsWithDelta(12.7, $data['rain_daily'], 0.01);
        $this->assertEqualsWithDelta(1013.2, $data['pressure_rel'], 0.1);
    }

    public function test_date_is_read_as_day_month_year(): void
    {
        $data = (new RealtimeTxtParser())->parseContent($this->crtLine([0 => '03/10/26']), 'weewx');

        $this->assertSame('2026-10-03 14:05:00', $data['recorded_at']->format('Y-m-d H:i:s'));
    }
}
