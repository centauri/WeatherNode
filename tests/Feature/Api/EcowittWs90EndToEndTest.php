<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Setting;
use App\Models\User;
use App\Models\WeatherReading;
use App\Services\Weather\EcowittService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #131 and #132 through the real entry points: a push arriving at the
 * receive endpoint, a cloud API response being saved, and both dashboards
 * reading the result. The unit tests prove the rules; these prove every path
 * actually goes through them.
 */
class EcowittWs90EndToEndTest extends TestCase
{
    use RefreshDatabase;

    private function payload(string $name): array
    {
        return (require base_path('tests/Fixtures/Ecowitt/payloads.php'))[$name];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Setting::setValue('ecowitt.secure_mode', false, 'boolean', 'ecowitt');
    }

    private function latest(): WeatherReading
    {
        return WeatherReading::query()->latest('id')->firstOrFail();
    }

    // ── #132: rain ────────────────────────────────────────────────────────

    public function test_a_ws90_push_records_its_rain(): void
    {
        $raw = $this->payload('push_gw2000a_ws90');
        $raw['drain_piezo'] = '0.091';
        $raw['yrain_piezo'] = '24.12';
        unset($raw['PASSKEY']);

        $this->post('/api/ecowitt/receive', $raw)->assertOk();

        $this->assertSame(2.31, (float) $this->latest()->rain_daily);
        $this->assertSame(612.65, (float) $this->latest()->rain_yearly);
    }

    public function test_a_ws90_cloud_reading_records_its_rain(): void
    {
        app(EcowittService::class)->saveReading($this->payload('cloud_ws90_lightning_raining'));

        $this->assertSame(2.3, (float) $this->latest()->rain_daily);
        $this->assertSame(0.8, (float) $this->latest()->rain_rate);
    }

    /** A tipping-bucket station keeps exactly what it recorded before. */
    public function test_a_tipping_bucket_push_is_unchanged(): void
    {
        $this->post('/api/ecowitt/receive', $this->payload('push_wn1980b_mixed'))->assertOk();

        $this->assertSame(125.6, (float) $this->latest()->rain_yearly);
        $this->assertSame(26.29, (float) $this->latest()->rain_event);
    }

    // ── #131: batteries ───────────────────────────────────────────────────

    /** The cloud wraps each battery; it is stored as plain numbers now. */
    public function test_cloud_batteries_are_stored_as_numbers(): void
    {
        app(EcowittService::class)->saveReading($this->payload('cloud_ws90_lightning_raining'));

        $this->assertSame(
            ['haptic_array_battery' => 3.12, 'haptic_array_capacitor' => 5.3, 'lightning_sensor' => 5],
            $this->latest()->battery_status
        );
    }

    /** Every battery in a push is kept, not only the ones on an old fixed list. */
    public function test_a_push_keeps_every_battery_it_sends(): void
    {
        $this->post('/api/ecowitt/receive', $this->payload('push_wn1980b_mixed'))->assertOk();

        $battery = $this->latest()->battery_status;
        $this->assertSame(1.84, $battery['wh68batt']);
        $this->assertSame(1.2, $battery['wh40batt']);
        $this->assertSame(1.36, $battery['leaf_batt1']);
        $this->assertSame(2.51, $battery['console_batt']);
        $this->assertSame(6, $battery['co2_batt']);
    }

    /** dft601: "all 3 are reported as low in admin". */
    public function test_the_admin_reads_the_cloud_station_as_healthy(): void
    {
        app(EcowittService::class)->saveReading($this->payload('cloud_ws90_lightning_raining'));
        $admin = User::factory()->create(['is_admin' => true]);

        $batteries = $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->viewData('batteryStatus');

        $this->assertSame(['good', 'good', 'good'], array_column($batteries, 'state'));
    }

    /** And rows stored wrapped before this change read correctly too. */
    public function test_the_admin_reads_an_old_wrapped_row_as_healthy(): void
    {
        WeatherReading::query()->create([
            'recorded_at' => now(),
            'battery_status' => $this->payload('cloud_ws90_lightning_raining')['battery'],
        ]);
        $admin = User::factory()->create(['is_admin' => true]);

        $batteries = $this->actingAs($admin)->get(route('admin.dashboard'))->viewData('batteryStatus');

        $this->assertSame(['good', 'good', 'good'], array_column($batteries, 'state'));
    }

    /** dft601: "on the main page only lightning sensor is wrong". */
    public function test_the_public_dashboard_reads_the_cloud_station_as_healthy(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\ApiKeyMiddleware::class);
        app(EcowittService::class)->saveReading($this->payload('cloud_ws90_lightning_raining'));

        $battery = $this->getJson('/api/weather/dashboard')->assertOk()->json('battery_status');

        $this->assertSame('good', $battery['lightning_sensor']['state']);
        $this->assertSame('good', $battery['haptic_array_battery']['state']);
        $this->assertSame('Lightning Sensor', $battery['lightning_sensor']['label']);
    }
}
