<?php

declare(strict_types=1);

namespace Tests\Unit\Nlg;

use App\Services\Nlg\ForecastNarrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #126. The forecast paragraph always said Celsius, whatever the site
 * or the reader had chosen, and there was nowhere to change it.
 *
 * The number is formatted here rather than patched into the finished sentence
 * later, because by then it is prose: an optional AI pass rewrites it, and
 * nothing can be relied on about how a temperature ends up written.
 */
class NarratorUnitsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function day(): array
    {
        return ['min_temp_c' => 12.0, 'max_temp_c' => 18.0, 'symbol' => 'cloudy'];
    }

    public function test_it_speaks_celsius_by_default(): void
    {
        $text = app(ForecastNarrator::class)->narrate($this->day(), ['locale' => 'en-us']);

        $this->assertStringContainsString('12°C', $text);
        $this->assertStringContainsString('18°C', $text);
    }

    public function test_it_speaks_fahrenheit_when_asked(): void
    {
        $text = app(ForecastNarrator::class)->narrate($this->day(), [
            'locale' => 'en-us',
            'units' => 'imperial',
        ]);

        // 12C is 53.6F and 18C is 64.4F.
        $this->assertStringContainsString('54°F', $text);
        $this->assertStringContainsString('64°F', $text);
        $this->assertStringNotContainsString('°C', $text);
    }

    /** Only imperial uses Fahrenheit. UK and Scandinavia are Celsius countries. */
    public function test_the_other_systems_stay_celsius(): void
    {
        foreach (['metric', 'uk', 'scandinavia'] as $units) {
            $text = app(ForecastNarrator::class)->narrate($this->day(), [
                'locale' => 'en-us',
                'units' => $units,
            ]);

            $this->assertStringContainsString('°C', $text, $units . ' should stay Celsius');
            $this->assertStringNotContainsString('°F', $text, $units . ' should stay Celsius');
        }
    }
}
