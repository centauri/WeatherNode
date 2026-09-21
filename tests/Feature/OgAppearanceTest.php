<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\OgImageService;
use App\Support\CustomTheme;
use App\Support\OgAppearance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OgAppearanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_only_the_published_theme_and_resolves_system_to_dark(): void
    {
        $published = CustomTheme::presets()['ocean'];
        $published['modes']['dark']['bg'] = '#112233';
        $draft = $published;
        $draft['modes']['dark']['bg'] = '#abcdef';

        Setting::setValue('appearance.palette', 'custom');
        Setting::setValue('appearance.color_mode', 'system');
        Setting::setValue('appearance.active_custom_theme', $published, 'json', 'appearance');
        Setting::setValue('appearance.custom_theme', $draft, 'json', 'appearance');

        $appearance = OgAppearance::current();
        $this->assertSame('custom', $appearance['palette']);
        $this->assertSame('dark', $appearance['mode']);
        $this->assertSame('#112233', $appearance['tokens']['bg']);
    }

    public function test_invalid_published_theme_falls_back_to_valid_shipped_tokens(): void
    {
        Setting::setValue('appearance.palette', 'custom');
        Setting::setValue('appearance.color_mode', 'light');
        Setting::setValue('appearance.active_custom_theme', ['invalid' => true], 'json', 'appearance');

        $appearance = OgAppearance::current();
        $this->assertFalse($appearance['classic']);
        $this->assertSame('light', $appearance['mode']);
        $this->assertSame('#f1f5f9', $appearance['tokens']['bg']);
        $this->assertSame('#0f172a', $appearance['tokens']['fg']);
    }

    public function test_fingerprint_and_image_url_change_with_published_appearance(): void
    {
        $classic = OgAppearance::fingerprint();
        $this->assertSame(
            "https://example.test/og/home.png?appearance={$classic}",
            OgAppearance::imageUrl('https://example.test/og/home.png')
        );
        $this->assertStringContainsString("existing=1&appearance={$classic}", OgAppearance::imageUrl('/card?existing=1'));

        Setting::setValue('appearance.palette', 'forest');
        Setting::setValue('appearance.color_mode', 'light');
        $this->assertNotSame($classic, OgAppearance::fingerprint());
    }

    public function test_share_urls_are_versioned_but_static_seo_image_is_unchanged(): void
    {
        Setting::setValue('og.enabled', true, 'boolean', 'og');
        $first = OgAppearance::fingerprint();
        $this->get(route('share'))->assertOk()
            ->assertSee('appearance='.$first, false);

        Setting::setValue('appearance.palette', 'ocean');
        $second = OgAppearance::fingerprint();
        $this->assertNotSame($first, $second);
        $this->get(route('share'))->assertOk()
            ->assertSee('appearance='.$second, false)
            ->assertDontSee('appearance='.$first, false);

        Setting::setValue('og.enabled', false, 'boolean', 'og');
        Setting::setValue('seo.og_image', 'https://cdn.example.test/station.png', 'string', 'seo');
        $this->get(route('share'))->assertOk()
            ->assertSee('https://cdn.example.test/station.png', false)
            ->assertDontSee('cdn.example.test/station.png?appearance=', false);
    }

    public function test_generated_png_cache_is_partitioned_by_published_appearance(): void
    {
        if (! in_array(true, OgImageService::availableDrivers(), true)) {
            $this->markTestSkipped('GD or Imagick is required to generate an OG PNG.');
        }

        Setting::setValue('og.enabled', true, 'boolean', 'og');
        $classicFingerprint = OgAppearance::fingerprint();
        $classic = $this->get(route('og.generic', ['page' => 'radar']))
            ->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $classic->getContent());
        $this->assertStringNotContainsString('immutable', (string) $classic->headers->get('Cache-Control'));
        $this->assertNotNull(Cache::get('og_generic_radar_appearance_'.$classicFingerprint));

        Setting::setValue('appearance.palette', 'solar-flare');
        Setting::setValue('appearance.color_mode', 'light');
        $lightFingerprint = OgAppearance::fingerprint();
        $light = $this->get(route('og.generic', ['page' => 'radar']))->assertOk();

        $this->assertNotSame($classicFingerprint, $lightFingerprint);
        $this->assertNotSame($classic->getContent(), $light->getContent());
        $this->assertNotNull(Cache::get('og_generic_radar_appearance_'.$lightFingerprint));
    }

    public function test_renderer_chooses_the_higher_contrast_direction_for_a_midtone_custom_background(): void
    {
        if (! in_array(true, OgImageService::availableDrivers(), true)) {
            $this->markTestSkipped('GD or Imagick is required to construct the OG renderer.');
        }

        $theme = CustomTheme::presets()['weathernode'];
        $theme['modes']['light']['bg'] = '#777777';
        Setting::setValue('appearance.palette', 'custom');
        Setting::setValue('appearance.color_mode', 'light');
        Setting::setValue('appearance.active_custom_theme', $theme, 'json', 'appearance');

        $service = OgImageService::make();
        $method = new \ReflectionMethod($service, 'readableTextColour');
        $adjusted = $method->invoke($service, '#22c55e');

        $this->assertSame('#000000', $adjusted);
        $this->assertGreaterThanOrEqual(4.5, $this->contrastRatio($adjusted, '#777777'));
        // A filled label can have a different surface from the configured canvas.
        $onBadge = $method->invoke($service, '#94a3b8', '#152536');
        $this->assertGreaterThanOrEqual(4.5, $this->contrastRatio($onBadge, '#152536'));
    }

    private function contrastRatio(string $a, string $b): float
    {
        $luminance = static function (string $hex): float {
            $channels = array_map(static function (string $channel): float {
                $value = hexdec($channel) / 255;

                return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
            }, str_split(substr($hex, 1), 2));

            return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
        };
        $lighter = max($luminance($a), $luminance($b));
        $darker = min($luminance($a), $luminance($b));

        return ($lighter + 0.05) / ($darker + 0.05);
    }
}
