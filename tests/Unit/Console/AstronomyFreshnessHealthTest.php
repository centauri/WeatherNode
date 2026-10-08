<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use App\Support\CacheFreshness;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AstronomyFreshnessHealthTest extends TestCase
{
    use RefreshDatabase;

    private function writtenMinutesAgo(int $minutes): void
    {
        Carbon::setTestNow(now()->subMinutes($minutes));
        CacheFreshness::put('astronomy_sun', ['sunrise' => '07:42', 'sunset' => '18:34'], now()->addMinutes(240));
        Carbon::setTestNow();
    }

    private function health(): array
    {
        $this->artisan('weather:check-sensor-health')->assertExitCode(0);

        return Cache::get('data_source_health', [])['astronomy'] ?? [];
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_data_from_the_previous_hourly_run_is_not_stale(): void
    {
        $this->writtenMinutesAgo(61);

        $this->assertFalse($this->health()['is_stale']);
    }

    public function test_one_missed_hourly_run_is_not_stale(): void
    {
        $this->writtenMinutesAgo(125);

        $this->assertFalse($this->health()['is_stale']);
    }

    public function test_a_poller_that_has_stopped_is_stale(): void
    {
        $this->writtenMinutesAgo(155);

        $this->assertTrue($this->health()['is_stale']);
    }
}
