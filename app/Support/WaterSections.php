<?php

namespace App\Support;

use App\Models\Setting;
use App\Services\River\RiverProviderRegistry;

/**
 * The tabs of the Water section and the switches behind them. Tides and River
 * Levels are off until switched on; Waves (which also drives Sea Temperature)
 * is on until switched off.
 */
final class WaterSections
{
    /** Tab => route name, in menu order. */
    private const ROUTES = [
        'tides' => 'water',
        'waves' => 'water.waves',
        'temp' => 'water.temp',
        'rivers' => 'water.rivers',
    ];

    public static function isEnabled(string $tab): bool
    {
        return match ($tab) {
            'tides' => (bool) Setting::getValue('tide.enabled', false),
            'waves', 'temp' => (bool) Setting::getValue('waves.enabled', true),
            'rivers' => self::riversEnabled(),
            default => false,
        };
    }

    /** @return array<string, string> enabled tab => route name */
    public static function enabled(): array
    {
        return array_filter(self::ROUTES, fn ($route, $tab) => self::isEnabled($tab), ARRAY_FILTER_USE_BOTH);
    }

    /** The route the Water tab opens, or null when every section is off. */
    public static function firstRoute(): ?string
    {
        return array_values(self::enabled())[0] ?? null;
    }

    private static function riversEnabled(): bool
    {
        foreach (array_keys(RiverProviderRegistry::active()) as $providerId) {
            if ((bool) RiverProviderRegistry::getSetting($providerId, 'enabled', false)) {
                return true;
            }
        }

        return false;
    }
}
