<?php

namespace App\Services\River;

/**
 * Central registry of all river data providers.
 *
 * ──────────────────────────────────────────────────────────────────────────────
 * HOW TO ADD A NEW PROVIDER
 * ──────────────────────────────────────────────────────────────────────────────
 *
 * Step 1 — Implement the service class
 * -------------------------------------
 * Create app/Services/River/YourProviderRiverService.php with a fetch() method:
 *
 *   public function fetch(array $stationCodes, array $extraMeta = []): array
 *
 * Return one entry per station code:
 *   [
 *     'station-code' => [
 *       'name'         => string,
 *       'river'        => string,
 *       'station_code' => string,
 *       'level_cm'     => float|null,   // cm above NAP (or equivalent datum)
 *       'trend'        => 'rising'|'falling'|'steady',
 *       'series'       => [['timestamp' => ..., 'value' => float], ...],
 *       'updated_at'   => string,       // ISO-8601
 *     ],
 *   ]
 *
 * See RijkswaterstaatRiverService as the reference implementation.
 *
 * Step 2 — Optionally implement a catalog service
 * ------------------------------------------------
 * If the provider has a searchable station list, create a catalog service:
 *
 *   public function getRiverStations(): array   // ['code' => ['name', 'river']]
 *   public function refresh(): array            // clears cache, re-fetches
 *   public function cachedAt(): ?\Carbon\Carbon
 *
 * Skip this if the provider has a small fixed list — use custom stations instead.
 *
 * Step 3 — Register the provider here
 * -------------------------------------
 * Add an entry to PROVIDERS (below). Set status = 'active'.
 * The admin settings page, scheduler, and TideController all auto-discover it.
 *
 * ──────────────────────────────────────────────────────────────────────────────
 * SETTINGS KEYS  (namespaced per provider — see getSetting() for migration info)
 * ──────────────────────────────────────────────────────────────────────────────
 *   rivers.{id}.enabled          boolean  Whether this provider is active
 *   rivers.{id}.stations         JSON     Station codes chosen by the admin
 *   rivers.{id}.custom_stations  JSON     [{code, name, river}] added by admin
 *
 * CACHE KEY  (see cacheKey())
 *   rivers_{id}    e.g. rivers_rws     TTL: 45 min (PollExternalData::$cacheTTLs)
 */
class RiverProviderRegistry
{
    public const PROVIDERS = [
        'rws' => [
            'id'               => 'rws',
            'name'             => 'Rijkswaterstaat',
            'short'            => 'RWS',
            'flag'             => '🇳🇱',
            'country'          => 'Netherlands',
            'description'      => 'Real-time river gauge measurements (cm NAP) from the Dutch national waterway authority.',
            'api_key_required' => false,
            'service'          => RijkswaterstaatRiverService::class,
            'catalog_service'  => RwsStationCatalogService::class,
            'station_search'   => true,   // whether the admin UI shows the station combobox
            'status'           => 'active',
            'default_stations' => RijkswaterstaatRiverService::DEFAULT_STATIONS,
            'source_url'       => 'https://waterinfo.rws.nl',
        ],
        'kaur' => [
            'id'               => 'kaur',
            'name'             => 'Keskkonnaagentuur',
            'short'            => 'KAUR',
            'flag'             => '🇪🇪',
            'country'          => 'Estonia',
            'description'      => 'Hourly river and lake gauge levels (cm above gauge zero) and water temperature from the Estonian Environment Agency.',
            'api_key_required' => false,
            'service'          => KaurRiverService::class,
            'catalog_service'  => KaurStationCatalogService::class,
            'station_search'   => true,
            'custom_stations'  => false,
            'status'           => 'active',
            'source_url'       => 'https://keskkonnaportaal.ee/et/avaandmed/hudroloogilise-seire-andmestik',
        ],
    ];

    /** Stations used until the admin picks some. */
    public static function defaultStations(string $providerId): array
    {
        $provider = self::PROVIDERS[$providerId] ?? [];
        if (isset($provider['service']) && method_exists($provider['service'], 'defaultStations')) {
            return app($provider['service'])->defaultStations();
        }

        return $provider['default_stations'] ?? [];
    }

    /** Return only fully implemented (deployable) providers. */
    public static function active(): array
    {
        return array_filter(self::PROVIDERS, fn ($p) => $p['status'] === 'active');
    }

    /**
     * Resolve the settings key for a given provider and field.
     * Falls back gracefully to the pre-registry flat keys for the 'rws' provider
     * so existing installs keep working without a migration script.
     */
    public static function settingKey(string $providerId, string $field): string
    {
        return "rivers.{$providerId}.{$field}";
    }

    /**
     * Read a provider setting, with a migration fallback for the legacy 'rws' flat keys.
     * Returns the raw stored value (not cast).
     */
    public static function getSetting(string $providerId, string $field, mixed $default = null): mixed
    {
        $newKey = self::settingKey($providerId, $field);

        // Try the namespaced key first
        $value = \App\Models\Setting::getValue($newKey, null);
        if ($value !== null) {
            return $value;
        }

        // Migration fallback: old flat keys only existed for 'rws'
        if ($providerId === 'rws') {
            $legacyKey = "rivers.{$field}";
            $legacy    = \App\Models\Setting::getValue($legacyKey, null);
            if ($legacy !== null) {
                return $legacy;
            }
        }

        return $default;
    }

    /** Cache key used to store fetched data for a provider. */
    public static function cacheKey(string $providerId): string
    {
        return "rivers_{$providerId}";
    }
}
