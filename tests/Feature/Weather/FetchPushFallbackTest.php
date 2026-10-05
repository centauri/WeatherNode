<?php

declare(strict_types=1);

namespace Tests\Feature\Weather;

use App\Models\DailySummary;
use App\Models\Setting;
use App\Models\WeatherReading;
use App\Support\EcowittSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * With a pushing Ecowitt station, weather:fetch has nothing to fetch and
 * updates today's summary from the newest stored reading. It took the
 * highest id, which after a gap fill is an older reading.
 */
class FetchPushFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_newest_reading_by_time_updates_the_summary(): void
    {
        EcowittSource::apply(EcowittSource::PUSH);
        Setting::setValue('livedata.fetch_mode', 'file', 'select', 'livedata');
        Setting::setValue('livedata.file_path', '', 'string', 'livedata');

        WeatherReading::query()->create(['recorded_at' => now()->subMinute(), 'temperature' => 21.0, 'rain_daily' => 1.0]);
        // Stored later, measured two days earlier: what a gap fill adds.
        WeatherReading::query()->create(['recorded_at' => now()->subDays(2), 'temperature' => 5.0, 'rain_daily' => 9.0]);

        $this->artisan('weather:fetch', ['--save' => true]);

        $today = DailySummary::whereDate('date', now()->toDateString())->first();
        $this->assertNotNull($today);
        $this->assertSame(21.0, (float) $today->temp_high);
        $this->assertNull(DailySummary::whereDate('date', now()->subDays(2)->toDateString())->first());
    }
}
