<?php

declare(strict_types=1);

namespace Tests\Feature\Weather;

use App\Models\Setting;
use App\Models\WeatherReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * File Path can hold a web address, such as the realtime.txt that WeeWX
 * writes on a Raspberry Pi. weather:fetch read it, then checked the address
 * as if it were a file on disk, called it missing, and crashed before saving.
 * Test Connection still said it worked.
 */
class FetchLocalFileUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_realtime_txt_at_a_web_address_is_saved(): void
    {
        $fields = array_fill(0, 58, 'NULL');
        foreach ([
            0 => now()->format('d/m/y'), 1 => now()->format('H:i:s'), 2 => '12.3', 3 => '81', 4 => '9.1',
            6 => '14.0', 7 => '225', 8 => '0.0', 9 => '1.2', 10 => '1012.4',
            13 => 'km/h', 14 => 'C', 15 => 'hPa', 16 => 'mm',
        ] as $i => $v) {
            $fields[$i] = $v;
        }
        Http::fake(['raspberrypi.local/*' => Http::response(implode(' ', $fields))]);

        Setting::setValue('livedata.format', 'weewx', 'string', 'livedata');
        Setting::setValue('livedata.fetch_mode', 'file', 'select', 'livedata');
        Setting::setValue('livedata.file_path', 'http://raspberrypi.local/weewx/realtime.txt', 'string', 'livedata');

        $this->artisan('weather:fetch', ['--save' => true])->assertSuccessful();

        $reading = WeatherReading::mostRecent();
        $this->assertNotNull($reading);
        $this->assertSame(12.3, (float) $reading->temperature);
        $this->assertSame(1012.4, (float) $reading->pressure_rel);
    }
}
