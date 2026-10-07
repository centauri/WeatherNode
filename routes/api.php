<?php

use App\Http\Controllers\Api\WeatherController;
use App\Http\Controllers\Api\EcowittController;
use App\Http\Controllers\Api\WuPushController;
use App\Http\Controllers\Api\DataController;
use App\Http\Controllers\Api\TelemetryController;
use App\Http\Controllers\Api\TileProxyController;
use App\Http\Controllers\ForecastController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Weather data API endpoints for the frontend dashboard and future mobile app
|
*/

/*
|--------------------------------------------------------------------------
| Ecowitt Local Data Receiver
|--------------------------------------------------------------------------
| Endpoint for Ecowitt devices to push weather data directly to the server.
| Configure in Ecowitt/WS View app: Device → Weather Services → Customized
| URL: https://yourdomain.com/api/ecowitt/receive
| Optional hardened mode path: /api/ecowitt/receive/{token}
*/
Route::post('/ecowitt/receive/{token?}', [EcowittController::class, 'receive'])
    ->where('token', '[A-Za-z0-9_-]+')
    ->withoutMiddleware('api.key');
Route::get('/ecowitt/status', [EcowittController::class, 'status']);

/*
|--------------------------------------------------------------------------
| Weather Underground format receiver
|--------------------------------------------------------------------------
| WeeWX, Meteobridge and many consoles can upload to a Wunderground-style
| address of your choosing. The station key goes in the PASSWORD field.
| URL: https://yourdomain.com/api/wu/receive
*/
Route::match(['get', 'post'], '/wu/receive', [WuPushController::class, 'receive'])
    ->withoutMiddleware('api.key');

// Weather API: same api.key middleware as other API routes; frontend sends key via window.Meteo.apiHeaders()
Route::prefix('weather')->group(function () {
    // Main dashboard data (combines multiple endpoints)
    Route::get('/dashboard', [WeatherController::class, 'dashboard']);
    
    // Current conditions
    Route::get('/current', [WeatherController::class, 'current']);
    
    // Today's summary
    Route::get('/today', [WeatherController::class, 'today']);
    
    // Forecast
    Route::get('/forecast', [WeatherController::class, 'forecast']);
    
    // Air quality
    Route::get('/air-quality', [WeatherController::class, 'airQuality']);

    // Noise (live or cached; for dashboard widget polling – fetches if cache older than 1 min)
    Route::get('/noise', [WeatherController::class, 'noise']);
    
    // Sun & Moon
    Route::get('/astronomy', [WeatherController::class, 'astronomy']);
    
    // METAR aviation weather
    Route::get('/metar', [WeatherController::class, 'metar']);
    
    // Historical data for charts
    Route::get('/history', [WeatherController::class, 'history']);
    
    // Active sensors configuration
    Route::get('/sensors', [WeatherController::class, 'sensors']);
    
    // Radar data (RainViewer API frames)
    Route::get('/radar', [WeatherController::class, 'radar']);
    
    // KNMI Radar Nowcast (2-hour precipitation forecast)
    Route::get('/radar-nowcast', [WeatherController::class, 'radarNowcast']);

    // Provider-agnostic future frames for dashboard radar widget
    Route::get('/radar-future-frames', [WeatherController::class, 'radarFutureFrames']);
    
    // KNMI Solar Radiation Nowcast
    Route::get('/solar-nowcast', [WeatherController::class, 'solarNowcast']);
    
    // KNMI WMS Layers
    Route::get('/wms-layers', [WeatherController::class, 'wmsLayers']);
    Route::get('/wms-map', [WeatherController::class, 'wmsMap']);
});

/*
|--------------------------------------------------------------------------
| Extended Data Endpoints
|--------------------------------------------------------------------------
| Additional data sources for weather alerts, earthquakes, etc.
*/
Route::prefix('data')->group(function () {
    // Luftdaten/Sensor.Community data (used by public air quality page)
    Route::get('/luftdaten', [DataController::class, 'luftdaten']);

    // Remaining data endpoints are intended for trusted integrations/admin tooling.
    Route::middleware('api.private')->group(function () {
        // Weather alerts (Meteoalarm for Europe)
        Route::get('/alerts', [DataController::class, 'alerts']);
    
        // Earthquake data
        Route::get('/earthquakes', [DataController::class, 'earthquakes']);
    
        // PurpleAir data
        Route::get('/purpleair', [DataController::class, 'purpleair']);
    
        // Combined air quality from all sources
        Route::get('/air-quality', [DataController::class, 'airQuality']);
    
        // Lightning data
        Route::get('/lightning', [DataController::class, 'lightning']);
    
        // All external data combined
        Route::get('/external', [DataController::class, 'external']);
    });
});

/*
|--------------------------------------------------------------------------
| Alternative Weather Sources
|--------------------------------------------------------------------------
| Endpoints for alternative weather station services
*/
Route::prefix('sources')->middleware('api.private')->group(function () {
    Route::get('/aeris', [DataController::class, 'aeris']);
    Route::get('/weatherlink', [DataController::class, 'weatherlink']);
    Route::get('/ambient', [DataController::class, 'ambient']);
    Route::get('/weatherflow', [DataController::class, 'weatherflow']);
});

/*
|--------------------------------------------------------------------------
| Telemetry & Community Stations
|--------------------------------------------------------------------------
| Endpoints for community station telemetry
*/
Route::prefix('telemetry')->middleware('api.private')->group(function () {
    Route::get('/stations', [TelemetryController::class, 'stations']);
});

/*
|--------------------------------------------------------------------------
| Forecast NLG Endpoint
|--------------------------------------------------------------------------
| Generate human-readable forecast text from structured forecast data
*/
Route::post('/forecast/narrate', [ForecastController::class, 'narrate'])->middleware('api.private');

/*
|--------------------------------------------------------------------------
| Radar Tile Proxy
|--------------------------------------------------------------------------
| Proxies and caches RainViewer radar tiles to prevent rate limiting
| and CORS issues. Tiles are cached for 15 minutes.
*/
Route::prefix('radar')->group(function () {
    // Get available radar frames (cached API response)
    Route::get('/frames', [TileProxyController::class, 'frames']);
    
    // Proxy individual tiles with caching
    // Format: /api/radar/tile/v2/radar/{timestamp}/{size}/{z}/{x}/{y}/{color}/{options}.png
    Route::get('/tile/{path}', [TileProxyController::class, 'tile'])
        ->where('path', '.*');

    // Proxy future radar image frames (NOAA/other providers) to bypass browser CORS.
    Route::get('/future-image', [TileProxyController::class, 'futureImage']);
});

// Widget order endpoint moved to web.php for proper session handling
