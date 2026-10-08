<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\TideService;
use App\Services\Tide\TideServiceFactory;
use App\Services\Wave\OpenMeteoWaveService;
use App\Services\River\RijkswaterstaatRiverService;
use App\Services\River\RiverProviderRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TideController extends Controller
{
    public function index()
    {
        // ── Tide data ─────────────────────────────────────────────────────────
        $enabled = (bool) Setting::getValue('tide.enabled', false);
        $source  = Setting::getValue('tide.source', TideServiceFactory::DEFAULT_SOURCE);

        // Prefer per-source station key so switching sources retains each source's own station
        $stationCode = Setting::getValue("tide.{$source}_station_code",
                       Setting::getValue('tide.station_code', ''));
        $stationName = Setting::getValue('tide.station_name', '');

        $driver       = TideServiceFactory::make($source);
        $sourceLabel  = $driver->getName();
        $sourceDocUrl = $driver->getApiDocUrl();

        $tideData = null;
        if ($enabled) {
            $cacheKey = 'tide_' . $source . '_' . $stationCode;
            $cached   = Cache::get($cacheKey);
            if ($cached) {
                $tideData = $cached;
            } else {
                try {
                    $tideData = (new TideService())->fetchTideData($stationCode);
                    if ($tideData && !empty($tideData['series'])) {
                        Cache::put($cacheKey, $tideData, now()->addHours(2));
                    } else {
                        $tideData = null;
                    }
                } catch (\Exception $e) {
                    Log::warning('TideController live fetch failed', ['error' => $e->getMessage()]);
                    $tideData = null;
                }
            }
        }

        // ── Wave + SST data (Open-Meteo Marine — always free) ─────────────────
        $wavesEnabled = (bool) Setting::getValue('waves.enabled', true);
        $lat          = round((float) Setting::latitude(), 2);
        $lon          = round((float) Setting::longitude(), 2);
        $waveData     = null;

        if ($wavesEnabled) {
            $waveData = Cache::get("waves_{$lat}_{$lon}");
            if (!$waveData) {
                try {
                    $waveData = \App\Services\Wave\WaveServiceFactory::make()->fetch();
                    if ($waveData && !empty($waveData['wave_series'])) {
                        Cache::put("waves_{$lat}_{$lon}", $waveData, now()->addHours(2));
                    } else {
                        $waveData = null;
                    }
                } catch (\Exception $e) {
                    Log::warning('TideController wave fetch failed', ['error' => $e->getMessage()]);
                    $waveData = null;
                }
            }
        }

        // ── River levels — collect data from all enabled providers ───────────
        $riversEnabled = false;
        $riverData     = [];

        foreach (RiverProviderRegistry::active() as $providerId => $providerMeta) {
            $providerEnabled = (bool) RiverProviderRegistry::getSetting($providerId, 'enabled', false);
            if (!$providerEnabled) {
                continue;
            }
            $riversEnabled = true;

            // Selected stations
            $stationsRaw = RiverProviderRegistry::getSetting(
                $providerId, 'stations', \App\Services\River\RiverProviderRegistry::defaultStations($providerId)
            );
            $selected = is_string($stationsRaw)
                ? (json_decode($stationsRaw, true) ?? \App\Services\River\RiverProviderRegistry::defaultStations($providerId))
                : ($stationsRaw ?? \App\Services\River\RiverProviderRegistry::defaultStations($providerId));
            $selected = array_values(array_filter((array) $selected, fn ($v) => is_string($v) && !is_numeric($v) && $v !== ''));
            if (empty($selected)) {
                $selected = \App\Services\River\RiverProviderRegistry::defaultStations($providerId);
            }

            // Catalog metadata (if provider has a catalog service)
            $catalogMeta = [];
            if (isset($providerMeta['catalog_service'])) {
                $catalogMeta = app($providerMeta['catalog_service'])->getRiverStations();
            }

            // Custom stations (override catalog)
            $customRaw  = RiverProviderRegistry::getSetting($providerId, 'custom_stations', '[]');
            $customList = is_string($customRaw) ? (json_decode($customRaw, true) ?? []) : [];
            $customMeta = [];
            $customCodes = [];
            foreach ($customList as $entry) {
                $code = $entry['code'] ?? null;
                if (!$code) {
                    continue;
                }
                $customMeta[$code]  = ['name' => $entry['name'] ?? $code, 'river' => $entry['river'] ?? '—'];
                $customCodes[]      = $code;
            }

            $allCodes  = array_unique(array_merge($selected, $customCodes));
            $extraMeta = array_merge($catalogMeta, $customMeta);

            $cacheKey      = RiverProviderRegistry::cacheKey($providerId);
            $providerData  = Cache::get($cacheKey);
            if (!$providerData) {
                try {
                    $service      = app($providerMeta['service']);
                    $providerData = $service->fetch($allCodes, $extraMeta);
                    if (!empty($providerData)) {
                        Cache::put($cacheKey, $providerData, now()->addMinutes(30));
                    } else {
                        $providerData = null;
                    }
                } catch (\Exception $e) {
                    Log::warning("TideController river fetch failed [{$providerId}]", ['error' => $e->getMessage()]);
                    $providerData = null;
                }
            }

            if (!empty($providerData)) {
                $riverData = array_merge($riverData, $providerData);
            }
        }

        if (empty($riverData)) {
            $riverData = null;
        }

        return view('weather.tide', [
            'tideData'      => $tideData,
            'tideEnabled'   => $enabled,
            'stationCode'   => $stationCode,
            'stationName'   => $stationName,
            'stations'      => TideService::STATIONS,
            'source'        => $source,
            'sourceLabel'   => $sourceLabel,
            'sourceDocUrl'  => $sourceDocUrl,
            'wavesEnabled'  => $wavesEnabled,
            'waveData'      => $waveData,
            'riversEnabled' => $riversEnabled,
            'riverData'     => $riverData,
        ]);
    }
}
