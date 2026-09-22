<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\Aviation\MetarService;
use App\Support\AviationScene;
use Illuminate\Support\Facades\Cache;

class AviationController extends Controller
{
    public function index(MetarService $metar, ?string $icao = null)
    {
        $primaryIcao = Setting::getValue('metar.primary_icao', '');
        $metarEnabled = (bool) Setting::getValue('metar.enabled', false);
        $defaultScene = AviationScene::normalize(Setting::getValue('metar.default_scene', AviationScene::DEFAULT));

        // Validate and normalize ICAO from URL
        if ($icao) {
            $icao = strtoupper($icao);
            if (!preg_match('/^[A-Z]{4}$/', $icao)) {
                abort(404);
            }
        }

        $activeIcao = $icao ?? $primaryIcao;

        // Fetch METAR data server-side for SEO (crawlers can't run JS)
        $ssrMetar = null;
        if ($metarEnabled) {
            $cacheKey = "metar_{$activeIcao}";
            $data = Cache::get($cacheKey);
            if (!$data) {
                $data = $metar->fetchMetar([$activeIcao]);
            }
            $ssrMetar = $data[0] ?? null;
        }

        return view('weather.aviation', compact(
            'primaryIcao', 'metarEnabled', 'activeIcao', 'ssrMetar', 'defaultScene'
        ));
    }
}
