<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\WeatherReading;
use App\Support\BatteryStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #131. The dashboard's battery card put name, status and voltage on one line
 * of a narrow card, so every row wrapped, and the battery names stayed in
 * English on a German site.
 */
class BatteryCardTest extends TestCase
{
    use RefreshDatabase;

    private function reading(array $battery): void
    {
        WeatherReading::query()->create(['recorded_at' => now(), 'temperature' => 20, 'battery_status' => $battery]);
        Setting::setValue('widgets.enabled', ['current', 'battery'], 'json', 'widgets');
    }

    public function test_every_battery_string_is_sent_to_the_page_for_translation(): void
    {
        $this->reading(['wh57batt' => 5]);
        Setting::setValue('display.language', 'de-de', 'select', 'display');

        $content = (string) $this->get('/')->assertOk()->getContent();

        foreach (BatteryStatus::translatable() as $string) {
            $this->assertStringContainsString(json_encode($string), $content, $string);
        }
        $this->assertStringContainsString(json_encode('All good'), $content);
    }

    public function test_the_card_draws_icons_and_keeps_one_line_per_sensor(): void
    {
        $this->reading(['haptic_array_battery' => 3.12, 'haptic_array_capacitor' => 5.3, 'wh57batt' => 5]);

        $content = (string) $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('data-battery-icon="capacitor"', $content);
        $this->assertStringContainsString('data-battery-icon="battery"', $content);
        $this->assertStringContainsString('batterySummary()', $content);
        $this->assertStringNotContainsString("getBatteryIcon(key, value)", $content);
    }

    public function test_the_server_rendered_card_is_short_too(): void
    {
        Setting::setValue('dashboard.hybrid_ssr_enabled', true, 'boolean', 'advanced');
        $this->reading(['haptic_array_battery' => 3.12, 'wh57batt' => 1]);

        $content = (string) $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('WS90 batteries: 3.12 V', $content);
        $this->assertStringContainsString('Lightning Sensor (WH57): Low', $content);
    }
}
