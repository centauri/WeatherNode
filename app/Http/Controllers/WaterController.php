<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\WaterSections;
use App\Services\TideService;
use App\Services\Tide\TideServiceFactory;
use App\Services\Wave\OpenMeteoWaveService;
use App\Services\River\RijkswaterstaatRiverService;
use App\Services\River\RiverProviderRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WaterController extends Controller
{
    // ── Shared helpers ──────────────────────────────────────────────────────

    /** Load wave + SST data from cache; live-fetch on miss. */
    private function loadWaveData(): ?array
    {
        $lat  = round((float) Setting::latitude(), 2);
        $lon  = round((float) Setting::longitude(), 2);
        $data = Cache::get("waves_{$lat}_{$lon}");

        if (!$data) {
            try {
                $data = \App\Services\Wave\WaveServiceFactory::make()->fetch();
                if ($data && !empty($data['wave_series'])) {
                    Cache::put("waves_{$lat}_{$lon}", $data, now()->addHours(2));
                } else {
                    $data = null;
                }
            } catch (\Exception $e) {
                Log::warning('WaterController wave fetch failed', ['error' => $e->getMessage()]);
                $data = null;
            }
        }

        return $data;
    }

    /** Load merged river data from all enabled providers; live-fetch on cache miss. */
    private function loadRiverData(): ?array
    {
        $merged = [];

        foreach (RiverProviderRegistry::active() as $providerId => $providerMeta) {
            $enabled = (bool) RiverProviderRegistry::getSetting($providerId, 'enabled', false);
            if (!$enabled) {
                continue;
            }

            $cacheKey    = RiverProviderRegistry::cacheKey($providerId);
            $providerData = Cache::get($cacheKey);

            if (!$providerData) {
                try {
                    $stationsRaw = RiverProviderRegistry::getSetting(
                        $providerId, 'stations', \App\Services\River\RiverProviderRegistry::defaultStations($providerId)
                    );
                    $selected = is_string($stationsRaw)
                        ? (json_decode($stationsRaw, true) ?? \App\Services\River\RiverProviderRegistry::defaultStations($providerId))
                        : ($stationsRaw ?? \App\Services\River\RiverProviderRegistry::defaultStations($providerId));
                    $selected = array_values(array_filter(
                        (array) $selected,
                        fn ($v) => is_string($v) && !is_numeric($v) && $v !== ''
                    ));
                    if (empty($selected)) {
                        $selected = \App\Services\River\RiverProviderRegistry::defaultStations($providerId);
                    }

                    $catalogMeta = [];
                    if (isset($providerMeta['catalog_service'])) {
                        $catalogMeta = app($providerMeta['catalog_service'])->getRiverStations();
                    }

                    $customRaw   = RiverProviderRegistry::getSetting($providerId, 'custom_stations', '[]');
                    $customList  = is_string($customRaw) ? (json_decode($customRaw, true) ?? []) : [];
                    $customMeta  = [];
                    $customCodes = [];
                    foreach ($customList as $entry) {
                        $code = $entry['code'] ?? null;
                        if (!$code) {
                            continue;
                        }
                        $customMeta[$code] = ['name' => $entry['name'] ?? $code, 'river' => $entry['river'] ?? '—'];
                        $customCodes[]     = $code;
                    }

                    $allCodes      = array_unique(array_merge($selected, $customCodes));
                    $extraMeta     = array_merge($catalogMeta, $customMeta);
                    $service       = app($providerMeta['service']);
                    $providerData  = $service->fetch($allCodes, $extraMeta);

                    if (!empty($providerData)) {
                        Cache::put($cacheKey, $providerData, now()->addMinutes(30));
                    } else {
                        $providerData = null;
                    }
                } catch (\Exception $e) {
                    Log::warning("WaterController river fetch failed [{$providerId}]", ['error' => $e->getMessage()]);
                    $providerData = null;
                }
            }

            if (!empty($providerData)) {
                $merged = array_merge($merged, $providerData);
            }
        }

        return empty($merged) ? null : $merged;
    }

    /**
     * A switched-off section sends visitors to the first one that is on, or
     * 404s when the whole Water section is off.
     */
    private function elsewhere(): \Illuminate\Http\RedirectResponse
    {
        $first = WaterSections::firstRoute();
        abort_if($first === null, 404);

        return redirect()->route($first);
    }

    // ── Tab controllers ─────────────────────────────────────────────────────

    /** GET /water — Tides (default tab) */
    public function tides()
    {
        if (!WaterSections::isEnabled('tides')) {
            return $this->elsewhere();
        }

        $enabled = true;
        $source  = Setting::getValue('tide.source', TideServiceFactory::DEFAULT_SOURCE);

        $stationCode = Setting::getValue("tide.{$source}_station_code",
                       Setting::getValue('tide.station_code', ''));
        $stationName = Setting::getValue('tide.station_name', '');

        $driver       = TideServiceFactory::make($source);
        $sourceLabel  = $driver->getName();
        $sourceDocUrl = $driver->getApiDocUrl();

        $tideData = null;
        if ($enabled) {
            $cacheKey = 'tide_' . $source . '_' . $stationCode;
            $tideData = Cache::get($cacheKey);
            if (!$tideData) {
                try {
                    $tideData = (new TideService())->fetchTideData($stationCode);
                    if ($tideData && !empty($tideData['series'])) {
                        Cache::put($cacheKey, $tideData, now()->addHours(2));
                    } else {
                        $tideData = null;
                    }
                } catch (\Exception $e) {
                    Log::warning('WaterController tide fetch failed', ['error' => $e->getMessage()]);
                }
            }
        }

        return view('weather.tide', [
            'activeTab'     => 'tides',
            'waterTabs'     => WaterSections::enabled(),
            // Tide
            'tideEnabled'   => $enabled,
            'tideData'      => $tideData,
            'stationCode'   => $stationCode,
            'stationName'   => $stationName,
            'stations'      => TideService::STATIONS,
            'source'        => $source,
            'sourceLabel'   => $sourceLabel,
            'sourceDocUrl'  => $sourceDocUrl,
            // Unused on this tab (nulls keep the view @php safe)
            'wavesEnabled'  => false,
            'waveData'      => null,
            'riverData'     => null,
        ]);
    }

    /** GET /water/waves — Waves */
    public function waves()
    {
        if (!WaterSections::isEnabled('waves')) {
            return $this->elsewhere();
        }

        return view('weather.tide', [
            'activeTab'     => 'waves',
            'waterTabs'     => WaterSections::enabled(),
            // Wave
            'wavesEnabled'  => true,
            'waveData'      => $this->loadWaveData(),
            // Unused on this tab
            'tideEnabled'   => false,
            'tideData'      => null,
            'stationCode'   => null,
            'stationName'   => null,
            'stations'      => [],
            'source'        => '',
            'sourceLabel'   => '',
            'sourceDocUrl'  => null,
            'riverData'     => null,
        ]);
    }

    /** GET /water/temp — Sea Temperature */
    public function temperature()
    {
        if (!WaterSections::isEnabled('temp')) {
            return $this->elsewhere();
        }

        return view('weather.tide', [
            'activeTab'     => 'temp',
            'waterTabs'     => WaterSections::enabled(),
            // SST data comes from wave service
            'wavesEnabled'  => true,
            'waveData'      => $this->loadWaveData(),
            // Unused on this tab
            'tideEnabled'   => false,
            'tideData'      => null,
            'stationCode'   => null,
            'stationName'   => null,
            'stations'      => [],
            'source'        => '',
            'sourceLabel'   => '',
            'sourceDocUrl'  => null,
            'riverData'     => null,
        ]);
    }

    /** GET /water/rivers — River Levels */
    public function rivers()
    {
        if (!WaterSections::isEnabled('rivers')) {
            return $this->elsewhere();
        }

        return view('weather.tide', [
            'activeTab'     => 'rivers',
            'waterTabs'     => WaterSections::enabled(),
            // River
            'riverData'     => $this->loadRiverData(),
            // Unused on this tab
            'tideEnabled'   => false,
            'tideData'      => null,
            'stationCode'   => null,
            'stationName'   => null,
            'stations'      => [],
            'source'        => '',
            'sourceLabel'   => '',
            'sourceDocUrl'  => null,
            'wavesEnabled'  => false,
            'waveData'      => null,
        ]);
    }
}
