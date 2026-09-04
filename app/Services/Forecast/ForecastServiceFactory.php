<?php

namespace App\Services\Forecast;

use App\Contracts\Forecast\ForecastServiceInterface;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class ForecastServiceFactory
{
    /**
     * Get the configured forecast service
     */
    public static function make(): ForecastServiceInterface
    {
        $source = Setting::getValue('forecast.default_source', 'fct_yrno_block.php');
        
        // Map old PHP block file names to service classes
        $serviceMap = [
            'fct_yrno_block.php' => YrNoService::class,
            'fct_darksky_block.php' => OpenWeatherMapService::class,
            'fct_wu_block.php' => WeatherUndergroundService::class,
            'fct_wxsim_block.php' => WxsimService::class,
            'fct_ec_block.php' => EnvironmentCanadaService::class,
            'fct_tempest_block.php' => TempestService::class,
            'fct_aemet_block.php' => AemetService::class,
            'fct_dwd_block.php' => DwdService::class,
        ];

        $serviceClass = $serviceMap[$source] ?? YrNoService::class;

        try {
            return app($serviceClass);
        } catch (\Exception $e) {
            Log::error('Failed to instantiate forecast service', [
                'source' => $source,
                'service' => $serviceClass,
                'error' => $e->getMessage(),
            ]);
            
            // Fallback to Yr.no if service fails
            return app(YrNoService::class);
        }
    }
}
