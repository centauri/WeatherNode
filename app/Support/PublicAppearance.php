<?php

namespace App\Support;

use App\Models\Setting;
use Throwable;

final class PublicAppearance
{
    public const PALETTES = ['weathernode' => 'WeatherNode', 'ocean' => 'Ocean', 'forest' => 'Forest', 'solar-flare' => 'Solar Flare'];

    public const MODES = ['dark', 'light', 'system'];

    public static function settings(): array
    {
        try {
            $palette = Setting::getValue('appearance.palette', 'weathernode');
            $mode = Setting::getValue('appearance.color_mode', 'dark');
        } catch (Throwable) {
            // Error and first-run pages must work before settings are available.
            $palette = 'weathernode';
            $mode = 'dark';
        }

        if ($palette === 'custom' && ($custom = CustomTheme::stored(true))) {
            return ['palette' => $custom['base'], 'mode' => in_array($mode, self::MODES, true) ? $mode : 'dark', 'custom' => $custom];
        }

        return [
            'palette' => is_string($palette) && array_key_exists($palette, self::PALETTES) ? $palette : 'weathernode',
            'mode' => in_array($mode, self::MODES, true) ? $mode : 'dark',
        ];
    }
}
