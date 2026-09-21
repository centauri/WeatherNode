<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

final class CustomTheme
{
    public const FORMAT = 'weathernode-theme';

    public const VERSION = 1;

    public const MAX_BYTES = 20000;

    public const TOKENS = [
        'bg' => 'Background', 'card' => 'Cards',
        'fg' => 'Primary text', 'secondary' => 'Secondary text',
        'muted' => 'Muted text', 'subtle' => 'Subtle text',
        'accent' => 'Accent colour', 'accent-strong' => 'Buttons',
        'link' => 'Links', 'border' => 'Borders',
        'gradient-mid' => 'Background gradient middle', 'gradient-end' => 'Background gradient end',
        'raised' => 'Raised surface', 'deep' => 'Recessed surface', 'soft' => 'Secondary surface',
        'overlay' => 'Overlay', 'line' => 'Dividers', 'grid' => 'Chart grid', 'accent-end' => 'Accent gradient end',
    ];

    /** Portable data only: no CSS, URLs, HTML, weather colours or executable content. */
    public static function validate(mixed $document): array
    {
        $keys = implode(',', array_keys(self::TOKENS));
        $rules = [
            'theme' => ['required', 'array:format,version,name,base,modes'],
            'theme.format' => ['required', 'in:'.self::FORMAT],
            'theme.version' => ['required', 'integer', 'in:'.self::VERSION],
            'theme.name' => ['required', 'string', 'max:80', 'regex:/^[^\p{C}]+$/u'],
            'theme.base' => ['required', 'in:'.implode(',', array_keys(PublicAppearance::PALETTES))],
            'theme.modes' => ['required', 'array:dark,light', 'required_array_keys:dark,light'],
        ];
        foreach (['dark', 'light'] as $mode) {
            $rules["theme.modes.$mode"] = ['required', 'array:'.$keys, 'required_array_keys:'.$keys];
            foreach (self::TOKENS as $key => $label) {
                $rules["theme.modes.$mode.$key"] = ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/D'];
            }
        }
        $validator = Validator::make(['theme' => $document], $rules);
        if ($validator->fails() || ($document['version'] ?? null) !== self::VERSION) {
            throw ValidationException::withMessages(['theme' => __('Invalid theme file. Use a WeatherNode theme file with both colour modes.')]);
        }
        $theme = $validator->validated()['theme'];
        $theme['name'] = trim($theme['name']);
        foreach (['dark', 'light'] as $mode) {
            foreach ($theme['modes'][$mode] as &$colour) {
                $colour = strtolower($colour);
            }
            unset($colour);
        }

        return $theme;
    }

    public static function decode(string $json): array
    {
        if (strlen($json) > self::MAX_BYTES) {
            throw ValidationException::withMessages(['theme' => __('Theme files must be smaller than 20 KB.')]);
        }
        try {
            $document = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages(['theme' => __('Invalid theme file. Use a WeatherNode theme file with both colour modes.')]);
        }

        return self::validate($document);
    }

    public static function stored(bool $active = false): ?array
    {
        try {
            return self::validate(Setting::getValue($active ? 'appearance.active_custom_theme' : 'appearance.custom_theme'));
        } catch (Throwable) {
            return null;
        }
    }

    /** Read the shipped tokens so a preset copy starts with its exact colours. */
    public static function presets(): array
    {
        $css = file_get_contents(public_path('css/public-theme.css'));
        preg_match_all('/html\[data-public-theme(?:="([^"]+)")?\](?:\[data-color-mode="([^"]+)"\])?\s*\{([^{}]+)\}/', $css, $rules, PREG_SET_ORDER);
        $presets = [];
        foreach (PublicAppearance::PALETTES as $palette => $label) {
            $modes = [];
            foreach (['dark', 'light'] as $mode) {
                $tokens = [];
                foreach ($rules as $rule) {
                    if (($rule[1] !== '' && $rule[1] !== $palette) || ($rule[2] !== '' && $rule[2] !== $mode)) {
                        continue;
                    }
                    preg_match_all('/--wn-([\w-]+):\s*(\d+) (\d+) (\d+);/', $rule[3], $values, PREG_SET_ORDER);
                    foreach ($values as $value) {
                        if (array_key_exists($value[1], self::TOKENS)) {
                            $tokens[$value[1]] = sprintf('#%02x%02x%02x', $value[2], $value[3], $value[4]);
                        }
                    }
                }
                $modes[$mode] = $tokens;
            }
            $presets[$palette] = self::validate(['format' => self::FORMAT, 'version' => self::VERSION, 'name' => __($label), 'base' => $palette, 'modes' => $modes]);
        }

        return $presets;
    }

    public static function css(array $document): string
    {
        $theme = self::validate($document);
        $css = '';
        foreach ($theme['modes'] as $mode => $tokens) {
            $css .= 'html[data-public-theme][data-color-mode="'.$mode.'"]{--wn-custom:1;';
            foreach ($tokens as $key => $hex) {
                $rgb = implode(' ', array_map('hexdec', str_split(substr($hex, 1), 2)));
                $css .= "--wn-$key:$rgb;";
            }
            foreach (['body' => 'fg', 'faint' => 'subtle', 'disabled' => 'soft', 'inactive' => 'soft', 'divider' => 'border', 'slate-deep' => 'deep', 'slate-soft' => 'soft', 'slate-border' => 'border', 'action' => 'accent', 'action-deep' => 'accent-strong'] as $alias => $target) {
                $css .= "--wn-$alias:var(--wn-$target);";
            }
            $css .= '--wn-browser-color:'.$tokens['card'].';}';
        }

        return $css;
    }
}
