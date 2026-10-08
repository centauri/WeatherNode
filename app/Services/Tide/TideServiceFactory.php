<?php

namespace App\Services\Tide;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class TideServiceFactory
{
    /**
     * All registered tide source drivers, in display order.
     * Keys are the setting values stored in tide.source.
     */
    public const SOURCES = [
        // ── Implemented ──────────────────────────────────────────────────────
        'rws'        => RijkswaterstaatSource::class,
        'open_meteo' => OpenMeteoMarineSource::class,
        'marea'      => MareaSource::class,
        'kaur'       => KaurSeaLevelSource::class,

        // ── Placeholders (coming soon) ────────────────────────────────────────
        'ea'         => EnvironmentAgencySource::class,
        'noaa'       => NoaaSource::class,
        'bsh'        => BshSource::class,
        'shom'       => ShomSource::class,
        'copernicus' => CopernicusMarineSource::class,
        'niwa'       => NiwaSource::class,
        'msq'        => MsqSource::class,
        'ntu_tpxo'   => NtuTpxoSource::class,
    ];

    /**
     * The source used until the owner picks one.
     *
     * Open-Meteo Marine, because it is the only global driver: it works from
     * the station coordinates, needs no key and needs no gauge to be chosen.
     * This used to be Rijkswaterstaat, which reads the Dutch gauge network and
     * has nothing to say anywhere else.
     */
    public const DEFAULT_SOURCE = 'open_meteo';

    /**
     * Instantiate the configured (or specified) tide source driver.
     * Falls back to the global source if the configured one is unknown or fails.
     */
    public static function make(?string $source = null): TideSourceInterface
    {
        $source = $source ?? Setting::getValue('tide.source', self::DEFAULT_SOURCE);
        $class  = self::SOURCES[$source] ?? self::SOURCES[self::DEFAULT_SOURCE];

        try {
            return app($class);
        } catch (\Exception $e) {
            Log::error('Failed to instantiate tide source', [
                'source' => $source,
                'class'  => $class,
                'error'  => $e->getMessage(),
            ]);
            return app(self::SOURCES[self::DEFAULT_SOURCE]);
        }
    }

    /**
     * Return metadata for all registered sources (for admin UI display).
     */
    public static function all(): array
    {
        $sources = [];
        foreach (self::SOURCES as $key => $class) {
            /** @var TideSourceInterface $instance */
            $instance  = app($class);
            $sources[$key] = [
                'key'           => $key,
                'name'          => $instance->getName(),
                'region'        => $instance->getRegion(),
                'coverage_area' => $instance->getCoverageArea(),
                'implemented'   => $instance->isImplemented(),
                'requires_key'  => $instance->requiresApiKey(),
                'station_based' => $instance->isStationBased(),
                'api_doc_url'   => $instance->getApiDocUrl(),
            ];
        }
        return $sources;
    }
}
