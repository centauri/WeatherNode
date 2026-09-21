<?php

namespace App\Support;

use Throwable;

final class OgAppearance
{
    /** Original OG colours are the safe fallback when settings or theme data cannot be read. */
    private const FALLBACK = [
        'bg' => '#0c1424',
        'card' => '#1a2332',
        'fg' => '#ffffff',
        'secondary' => '#94a3b8',
        'muted' => '#475569',
        'border' => '#1e293b',
        'accent' => '#3b82f6',
    ];

    /**
     * Resolve the owner's published appearance for a deterministic crawler image.
     * Browsers using `system` can vary; an OG image cannot, so it uses dark mode.
     */
    public static function current(): array
    {
        try {
            $appearance = PublicAppearance::settings();
            $mode = $appearance['mode'] === 'light' ? 'light' : 'dark';
            if (! isset($appearance['custom']) && $appearance['palette'] === 'weathernode' && $mode === 'dark') {
                return ['palette' => 'weathernode', 'mode' => 'dark', 'tokens' => self::FALLBACK, 'classic' => true];
            }
            $theme = isset($appearance['custom'])
                ? CustomTheme::validate($appearance['custom'])
                : (CustomTheme::presets()[$appearance['palette']] ?? null);
            $tokens = $theme['modes'][$mode] ?? null;

            if (! is_array($tokens)) {
                throw new \UnexpectedValueException('Appearance has no usable colour tokens.');
            }

            return [
                'palette' => isset($appearance['custom']) ? 'custom' : $appearance['palette'],
                'mode' => $mode,
                'tokens' => $tokens,
                'classic' => false,
            ];
        } catch (Throwable) {
            return ['palette' => 'weathernode', 'mode' => 'dark', 'tokens' => self::FALLBACK, 'classic' => true];
        }
    }

    public static function fingerprint(): string
    {
        $appearance = self::current();

        return substr(hash('sha256', json_encode($appearance, JSON_THROW_ON_ERROR)), 0, 16);
    }

    public static function imageUrl(string $url): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?').'appearance='.self::fingerprint();
    }
}
