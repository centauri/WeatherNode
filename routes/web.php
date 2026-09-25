<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\FireWeatherController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\WaterController;
use App\Http\Controllers\AlertsController;
use App\Http\Controllers\LegalPageController;
use App\Http\Controllers\OgImageController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\ThemeCreatorController;
use App\Http\Controllers\Admin\SetupController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UpdateController;
use App\Http\Controllers\Admin\VisitorLogController;
use App\Http\Controllers\Admin\ApiKeyController;
use App\Support\MenuFeatureMap;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PWA Manifest (dynamic)
|--------------------------------------------------------------------------
*/
Route::get('/manifest.json', function () {
    $location = \App\Models\Setting::stationLocation() ?: 'Weather';

    return response()->json([
        'name' => "WeatherNode - {$location}",
        'short_name' => 'WeatherNode',
        'description' => \App\Models\Setting::getValue('seo.site_description', "Local weather station data for {$location}"),
        'start_url' => '/',
        'scope' => '/',
        'display' => 'standalone',
        'background_color' => '#0f1419',
        'theme_color' => '#3b82f6',
        'orientation' => 'portrait-primary',
        'icons' => [
            ['src' => '/images/android-chrome-192x192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ['src' => '/images/android-chrome-512x512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
        ],
    ])->header('Content-Type', 'application/manifest+json');
})->name('manifest');

/*
|--------------------------------------------------------------------------
| robots.txt
|--------------------------------------------------------------------------
| Served from here so the Sitemap line carries this install's own domain.
| A real file at public/robots.txt is served by the web server instead, so
| anyone who wants different rules can copy this output there and edit it.
*/
Route::get('/robots.txt', function () {
    $sitemap = rtrim(url('/'), '/').'/sitemap.xml';

    $body = <<<TXT
# robots.txt for WeatherNode
#
# Lines beginning with # are notes for you. Crawlers ignore them.
#
# To change any of this: save this page as public/robots.txt in your
# install and edit that copy. A file there replaces this default.

User-agent: *
Allow: /

# Admin, sign in and account pages. Nothing here belongs in a search engine.
Disallow: /admin
Disallow: /login
Disallow: /register
Disallow: /password
Disallow: /profile

# The JSON API. Crawling it burns your bandwidth and indexes nothing a
# person would search for. Page content is in the HTML already, so this
# does not hide anything from Google.
Disallow: /api/

# The unit switch links (?units=imperial and so on) show the same page
# in different units. Without this line every page gets fetched four
# extra times for no new content.
Disallow: /*?units=

# Bing honours Crawl-delay. This asks for one page every 10 seconds.
# Raise the number if bots are still using too much of your server.
User-agent: bingbot
Crawl-delay: 10

# EXAMPLE, off by default. Daily archive pages are the biggest part of a
# weather site and people do search for "weather on 12 February". Remove
# the # marks only if you would rather they were never in search results.
# User-agent: *
# Disallow: /history/

# EXAMPLE, off by default. Shut out one crawler completely. Replace the
# name with the one you see in Admin > Analytics.
# User-agent: BadBot
# Disallow: /

# EXAMPLE, off by default. Let one crawler in and keep the rest out.
# User-agent: Googlebot
# Allow: /
# User-agent: *
# Disallow: /

Sitemap: {$sitemap}

# --------------------------------------------------------------------
# Still too much bot traffic?
#
# Bing: bing.com/webmasters, then Settings > Crawl Control. You can set
#   the pages per hour, hour by hour. It takes effect faster than the
#   Crawl-delay above and is the better tool of the two.
#
# Google: Googlebot ignores Crawl-delay, and the old crawl rate setting
#   in Search Console was withdrawn. Google now decides the rate itself
#   and slows down when a site answers with 429 or 503. If Googlebot is
#   genuinely overloading you, report it at
#   search.google.com/search-console and pick the crawl rate form.
#
# Neither one is instant. A change here can take hours or days to show
# up, because crawlers re-read this file on their own schedule.
# --------------------------------------------------------------------
TXT;

    return response($body, 200, [
        'Content-Type' => 'text/plain; charset=UTF-8',
    ]);
})->name('robots');

/*
|--------------------------------------------------------------------------
| Sitemap XML
|--------------------------------------------------------------------------
*/
Route::get('/sitemap.xml', function () {
    $baseUrl = rtrim(url('/'), '/');

    // Use the deploy time (VERSION file mtime) rather than now() for every URL.
    // A lastmod that is always "now" trains crawlers to distrust the field entirely;
    // a stable per-deploy timestamp reflects when the indexable page content changed.
    $versionFile = base_path('VERSION');
    $lastMod = (is_file($versionFile) && filemtime($versionFile))
        ? \Carbon\Carbon::createFromTimestamp(filemtime($versionFile))->toW3cString()
        : now()->toW3cString();

    // Locale config
    $locales      = array_keys(config('localization.locales', []));
    $defaultLang  = \App\Models\Setting::getValue('display.language', config('app.locale', 'en-us'));
    $langDefaults = config('localization.language_defaults', []);
    $base         = explode('-', strtolower((string) $defaultLang))[0] ?? $defaultLang;
    $defaultLocale = $langDefaults[$base] ?? $defaultLang;
    if (! in_array($defaultLocale, $locales)) {
        $defaultLocale = $locales[0] ?? 'en-us';
    }

    // Helper: build a full URL for a given path + locale
    $localeUrl = static function (string $path, string $locale) use ($baseUrl, $defaultLocale): string {
        $clean = '/' . ltrim($path, '/');
        return $locale === $defaultLocale
            ? $baseUrl . $clean
            : $baseUrl . '/' . $locale . $clean;
    };

    // Define public pages with their change frequency and priority
    $pages = [
        ['loc' => '/',                   'changefreq' => 'always',  'priority' => '1.0'],
        ['loc' => '/privacy',            'changefreq' => 'monthly', 'priority' => '0.3'],
        ['loc' => '/terms',              'changefreq' => 'monthly', 'priority' => '0.3'],
        ['loc' => '/about',              'changefreq' => 'monthly', 'priority' => '0.3'],
        ['loc' => '/license',            'changefreq' => 'monthly', 'priority' => '0.2'],
        ['loc' => '/disclaimer',         'changefreq' => 'monthly', 'priority' => '0.2'],
        ['loc' => '/notices',            'changefreq' => 'monthly', 'priority' => '0.2'],
        ['loc' => '/forecast',           'changefreq' => 'hourly',  'priority' => '0.9', 'feature' => MenuFeatureMap::FEATURE_FORECAST],
        ['loc' => '/radar',              'changefreq' => 'always',  'priority' => '0.8', 'feature' => MenuFeatureMap::FEATURE_RADAR],
        ['loc' => '/satellite',          'changefreq' => 'hourly',  'priority' => '0.7', 'feature' => MenuFeatureMap::FEATURE_SATELLITE],
        ['loc' => '/history',            'changefreq' => 'daily',   'priority' => '0.7', 'feature' => MenuFeatureMap::FEATURE_HISTORY],
        ['loc' => '/statistics',         'changefreq' => 'daily',   'priority' => '0.6', 'feature' => MenuFeatureMap::FEATURE_STATISTICS],
        ['loc' => '/air-quality',        'changefreq' => 'hourly',  'priority' => '0.7', 'feature' => MenuFeatureMap::FEATURE_AIR_POLLEN],
        ['loc' => '/noise',              'changefreq' => 'always',  'priority' => '0.6', 'feature' => MenuFeatureMap::FEATURE_AIR_POLLEN],
        ['loc' => '/pollen',             'changefreq' => 'hourly',  'priority' => '0.7', 'feature' => MenuFeatureMap::FEATURE_AIR_POLLEN],
        ['loc' => '/astronomy',          'changefreq' => 'daily',   'priority' => '0.5', 'feature' => MenuFeatureMap::FEATURE_ASTRONOMY],
        ['loc' => '/lightning',          'changefreq' => 'always',  'priority' => '0.6'],
        ['loc' => '/earthquakes',        'changefreq' => 'hourly',  'priority' => '0.5', 'feature' => MenuFeatureMap::FEATURE_EARTHQUAKES],
        ['loc' => '/community-stations', 'changefreq' => 'daily',   'priority' => '0.5'],
        ['loc' => '/aviation',           'changefreq' => 'hourly',  'priority' => '0.7', 'feature' => MenuFeatureMap::FEATURE_SKY_WATER],
        ['loc' => '/alerts',             'changefreq' => 'hourly',  'priority' => '0.7', 'feature' => MenuFeatureMap::FEATURE_ALERTS],
        ['loc' => '/water',              'changefreq' => 'hourly',  'priority' => '0.7', 'feature' => MenuFeatureMap::FEATURE_SKY_WATER],
        ['loc' => '/water/waves',        'changefreq' => 'hourly',  'priority' => '0.6', 'feature' => MenuFeatureMap::FEATURE_SKY_WATER],
        ['loc' => '/water/temp',         'changefreq' => 'hourly',  'priority' => '0.6', 'feature' => MenuFeatureMap::FEATURE_SKY_WATER],
        ['loc' => '/water/rivers',       'changefreq' => 'hourly',  'priority' => '0.6', 'feature' => MenuFeatureMap::FEATURE_SKY_WATER],
    ];

    $featureFlags = MenuFeatureMap::all();
    $pages = array_values(array_filter($pages, static function (array $page) use ($featureFlags): bool {
        $feature = $page['feature'] ?? null;
        if (!$feature) {
            return true;
        }

        return (bool) ($featureFlags[$feature] ?? true);
    }));

    // Major world airports for aviation weather SEO
    $airports = [
        // Europe
        'EHAM', 'EGLL', 'EDDF', 'LFPG', 'LEMD', 'LIRF', 'LSZH', 'EBBR', 'EKCH', 'ENGM',
        'ESSA', 'EFHK', 'LPPT', 'LOWW', 'EPWA', 'LKPR', 'EIDW', 'LEBL', 'EDDM', 'EGPH',
        // North America
        'KJFK', 'KLAX', 'KORD', 'KATL', 'KDFW', 'KDEN', 'KSFO', 'KLAS', 'KMIA', 'KSEA',
        'KBOS', 'KEWR', 'KDTW', 'KPHL', 'KMSP', 'KIAH', 'CYYZ', 'CYVR', 'CYUL', 'CYMX',
        // Asia & Oceania
        'RJTT', 'VHHH', 'WSSS', 'RKSI', 'VTBS', 'RPLL', 'WIII', 'VIDP', 'VABB', 'OMDB',
        'OTHH', 'OEJN', 'YSSY', 'YMML', 'NZAA',
        // South America & Africa
        'SBGR', 'SCEL', 'SKBO', 'MMMX', 'FAOR', 'HECA', 'DNMM',
    ];

    // Add configured primary ICAO if not already listed
    $primaryIcao = \App\Models\Setting::getValue('metar.primary_icao', 'EHAM');
    if (!in_array($primaryIcao, $airports)) {
        array_unshift($airports, $primaryIcao);
    }

    // High-volume airport pages: keep every airport, but only list each one's <loc> in a
    // small set of languages (site default + English) to conserve crawl budget. Full
    // language coverage is still advertised via the hreflang alternates emitted below, so
    // a basic "EHAM weather" search still surfaces the page in the searcher's language.
    $airportLocales = array_values(array_unique(array_filter(
        [$defaultLocale, 'en-us'],
        static fn (string $l): bool => in_array($l, $locales, true)
    )));
    if (empty($airportLocales)) {
        $airportLocales = [$locales[0] ?? 'en-us'];
    }

    if (($featureFlags[MenuFeatureMap::FEATURE_SKY_WATER] ?? true) === true) {
        foreach ($airports as $icao) {
            $pages[] = ['loc' => '/aviation/' . $icao, 'changefreq' => 'hourly', 'priority' => '0.6', 'sitemap_locales' => $airportLocales];
        }
    }

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
    $xml .= '        xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

    // Emit one <url> entry per locale per page, each with full xhtml:link alternates
    foreach ($pages as $page) {
        $path = $page['loc'];

        // Alternates always advertise the full language set (so every translation stays
        // discoverable); $emitLocales controls which ones get their own <url> entry.
        $emitLocales = $page['sitemap_locales'] ?? $locales;

        // Build alternate links once (shared across all locale entries for this page)
        $alternates = '';
        foreach ($locales as $locale) {
            $href = htmlspecialchars($localeUrl($path, $locale), ENT_XML1, 'UTF-8');
            $alternates .= "    <xhtml:link rel=\"alternate\" hreflang=\"{$locale}\" href=\"{$href}\"/>\n";
        }
        $xDefault = htmlspecialchars($localeUrl($path, $defaultLocale), ENT_XML1, 'UTF-8');
        $alternates .= "    <xhtml:link rel=\"alternate\" hreflang=\"x-default\" href=\"{$xDefault}\"/>\n";

        foreach ($emitLocales as $locale) {
            $loc = htmlspecialchars($localeUrl($path, $locale), ENT_XML1, 'UTF-8');
            $xml .= "  <url>\n";
            $xml .= "    <loc>{$loc}</loc>\n";
            $xml .= $alternates;
            $xml .= "    <lastmod>{$lastMod}</lastmod>\n";
            $xml .= "    <changefreq>{$page['changefreq']}</changefreq>\n";
            $xml .= "    <priority>{$page['priority']}</priority>\n";
            $xml .= "  </url>\n";
        }
    }

    $xml .= '</urlset>';

    return response($xml, 200, [
        'Content-Type' => 'application/xml; charset=UTF-8',
    ]);
})->name('sitemap');

/*
|--------------------------------------------------------------------------
| Dynamic OG Images (social sharing cards)
|--------------------------------------------------------------------------
| All routes return image/png. Each endpoint is independently cached.
| Requests are silently 404'd when og.enabled = false in settings.
*/
Route::prefix('og')->name('og.')->group(function () {
    Route::get('/home.png',                [OgImageController::class, 'home'])        ->name('home');
    Route::get('/forecast.png',            [OgImageController::class, 'forecast'])    ->name('forecast');
    Route::get('/history/{date}.png',      [OgImageController::class, 'history'])     ->name('history')
         ->where('date', '\d{4}-\d{2}-\d{2}');
    Route::get('/statistics/{year?}.png',  [OgImageController::class, 'statistics'])  ->name('statistics')
         ->where('year', '\d{4}');
    Route::get('/fire-weather.png',        [OgImageController::class, 'fireWeather']) ->name('fire-weather');
    Route::get('/air-quality.png',         [OgImageController::class, 'airQuality'])  ->name('air-quality');
    Route::get('/astronomy.png',           [OgImageController::class, 'astronomy'])   ->name('astronomy');
    Route::get('/aviation/{icao}.png',     [OgImageController::class, 'aviation'])    ->name('aviation')
         ->where('icao', '[A-Za-z]{4}');
    Route::get('/generic/{page}.png',      [OgImageController::class, 'generic'])     ->name('generic')
         ->where('page', '[a-z\-]+');
});

/*
|--------------------------------------------------------------------------
| Public Weather Dashboard
|--------------------------------------------------------------------------
| Routes are registered twice:
|   (1) With required {locale} prefix  → /de-de/forecast, /nl-nl/water, …
|   (2) Without any prefix             → /forecast, /water, … (default locale)
|
| Laravel's optional-parameter prefix {locale?} doesn't compile the slash
| correctly, so two groups are used instead. The second (unprefixed)
| registration wins for route() URL generation.
*/
$publicRoutes = function () {
    Route::get('/', [DashboardController::class, 'index'])->name('home');

    // Legal pages
    Route::get('/privacy', [LegalPageController::class, 'show'])->defaults('page', 'privacy')->name('legal.privacy');
    Route::get('/terms', [LegalPageController::class, 'show'])->defaults('page', 'terms')->name('legal.terms');
    Route::get('/about', [LegalPageController::class, 'show'])->defaults('page', 'about')->name('legal.about');
    Route::get('/license', [LegalPageController::class, 'show'])->defaults('page', 'license')->name('legal.license');
    Route::get('/disclaimer', [LegalPageController::class, 'show'])->defaults('page', 'disclaimer')->name('legal.disclaimer');
    Route::get('/notices', [LegalPageController::class, 'show'])->defaults('page', 'notices')->name('legal.notices');

    // Share page
    Route::get('/share', fn () => view('weather.share'))->name('share');

    // History Pages
    Route::get('/history', [HistoryController::class, 'index'])->middleware('feature.menu:history')->name('history');
    Route::get('/history/year', [HistoryController::class, 'year'])->middleware('feature.menu:history')->name('history.year');
    Route::get('/history/{date}', [HistoryController::class, 'day'])->middleware('feature.menu:history')->name('history.day')
        ->where('date', '\d{4}-\d{2}-\d{2}');
    Route::get('/history/{date}/readings', [HistoryController::class, 'dayReadings'])->middleware('feature.menu:history')->name('history.day.readings')
        ->where('date', '\d{4}-\d{2}-\d{2}');

    // Statistics Page
    Route::get('/statistics', [StatisticsController::class, 'index'])->middleware('feature.menu:statistics')->name('statistics');
    Route::get('/statistics/compare', [StatisticsController::class, 'compare'])->middleware('feature.menu:statistics')->name('statistics.compare');

    // Fire Weather / Drought Index
    Route::get('/fire-weather', [FireWeatherController::class, 'index'])->middleware('feature.menu:fire_weather')->name('fire-weather');

    // Radar Page
    Route::get('/radar', function () {
        return view('weather.radar');
    })->middleware('feature.menu:radar')->name('radar');

    // Satellite Data Page
    Route::get('/satellite', function () {
        return view('weather.satellite');
    })->middleware('feature.menu:satellite')->name('satellite');

    // Forecast Page
    Route::get('/forecast', function () {
        return view('weather.forecast');
    })->middleware('feature.menu:forecast')->name('forecast');

    // Minimal AdSense test page (helps isolate ad network behavior from dashboard complexity)
    Route::get('/ads-test', function () {
        return view('weather.ads-test', [
            'adCode' => \App\Models\Setting::getValue('widgets.ad_code', ''),
            'adCompany' => \App\Models\Setting::getValue('widgets.ad_company', ''),
        ]);
    })->name('ads.test');

    // Air Quality, Noise & Pollen Pages
    Route::get('/air-quality', [\App\Http\Controllers\Api\WeatherController::class, 'airQualityPage'])->middleware('feature.menu:air_pollen')->name('airquality');
    Route::get('/noise', [\App\Http\Controllers\Api\WeatherController::class, 'airQualityPage'])->middleware('feature.menu:air_pollen')->name('noise');
    Route::get('/pollen', [\App\Http\Controllers\Api\WeatherController::class, 'airQualityPage'])->middleware('feature.menu:air_pollen')->name('pollen');

    // Astronomy Page
    Route::get('/astronomy', function () {
        return view('weather.astronomy');
    })->middleware('feature.menu:astronomy')->name('astronomy');

    // Aviation Weather Page
    Route::get('/aviation/{icao?}', [\App\Http\Controllers\AviationController::class, 'index'])->middleware('feature.menu:sky_water')->name('aviation')->where('icao', '[A-Za-z]{4}');

    // Alerts Page
    Route::get('/alerts',         [AlertsController::class, 'index'])->middleware('feature.menu:alerts')->name('alerts');
    Route::get('/alerts/partial', [AlertsController::class, 'partial'])->middleware('feature.menu:alerts')->name('alerts.partial');

    // Water Page — sub-routes per tab (each loads only its own data)
    Route::get('/water',        [WaterController::class, 'tides'])->middleware('feature.menu:sky_water')->name('water');
    Route::get('/water/waves',  [WaterController::class, 'waves'])->middleware('feature.menu:sky_water')->name('water.waves');
    Route::get('/water/temp',   [WaterController::class, 'temperature'])->middleware('feature.menu:sky_water')->name('water.temp');
    Route::get('/water/rivers', [WaterController::class, 'rivers'])->middleware('feature.menu:sky_water')->name('water.rivers');

    // Earthquakes Page
    Route::get('/earthquakes', [\App\Http\Controllers\Api\WeatherController::class, 'earthquakesPage'])->middleware('feature.menu:earthquakes')->name('earthquakes');

    // Lightning Map Page
    Route::get('/lightning', function () {
        $stationLat = (float) \App\Models\Setting::latitude();
        $stationLon = (float) \App\Models\Setting::longitude();
        $stationLocation = \App\Models\Setting::stationLocation() ?: \App\Models\Setting::stationName();
        $blitzUrl = sprintf('https://map.blitzortung.org/#6/%.5f/%.5f', $stationLat, $stationLon);

        return view('weather.lightning', [
            'blitzUrl' => $blitzUrl,
            'stationLocation' => $stationLocation,
        ]);
    })->name('lightning');

    // Pressure Map Popup (default map by station location; ?map=atlantic|us|pacific|europe overrides)
    Route::get('/pressure-map', function () {
        $lon = \App\Models\Setting::longitude();
        $lat = \App\Models\Setting::latitude();
        $allowed = \App\Support\PressureMapRegistry::names();

        // Checked after the US and Pacific bands so those are unchanged.
        // Longitude alone is not enough here: Cape Town shares Europe's
        // meridians, so the box needs latitude too.
        $inEurope = $lat >= 34 && $lat <= 72 && $lon >= -25 && $lon <= 45;
        $defaultByLocation = ($lon >= -130 && $lon <= -65) ? 'us'
            : (($lon >= 130 || $lon <= -120) ? 'pacific'
            : ($inEurope ? 'europe' : 'atlantic'));
        $queryMap = strtolower(request()->query('map', ''));
        $defaultMap = in_array($queryMap, $allowed) ? $queryMap : $defaultByLocation;
        $mapUrls = [];
        foreach (\App\Support\PressureMapRegistry::names() as $name) {
            $mapUrls[$name] = route('weather.pressure-map.image', ['map' => $name]);
        }

        return view('weather.pressure-map', [
            'defaultMap' => $defaultMap,
            'mapUrls' => $mapUrls,
            'mapOrder' => \App\Support\PressureMapRegistry::names(),
            'mapLabels' => \App\Support\PressureMapRegistry::labels(),
        ]);
    })->name('weather.pressure-map');

    // Charts are proxied and downscaled rather than hotlinked. Keyed by name,
    // so no caller can point this at an arbitrary host.
    Route::get('/pressure-map/image/{map}', [\App\Http\Controllers\Weather\PressureMapImageController::class, 'show'])
        ->name('weather.pressure-map.image');

    // Community Stations Map
    Route::get('/community-stations', [\App\Http\Controllers\CommunityStationsController::class, 'index'])->name('weather.community-stations');
};

// (1) Locale-prefixed routes: /de-de/forecast, /nl-nl/water, etc.
// Name prefix 'locale.' avoids collisions so route('home') always resolves to the clean URL.
Route::prefix('{locale}')
    ->where(['locale' => '[a-z]{2}-[a-z]{2}'])
    ->name('locale.')
    ->group($publicRoutes);

// (2) Unprefixed routes: /forecast, /water, etc. (default locale)
// Registered last so route('name') generates clean URLs without locale prefix.
$publicRoutes();

// Embeddable live weather widget (iframe) — excluded from locale prefix (embed use case)
Route::get('/widget', [\App\Http\Controllers\Api\WeatherController::class, 'widget'])->name('widget');

/*
|--------------------------------------------------------------------------
| Legacy Dashboard Route (redirect to appropriate location)
|--------------------------------------------------------------------------
*/
Route::get('/dashboard', function () {
    if (auth()->check() && auth()->user()->is_admin) {
        return redirect()->route('admin.dashboard');
    }
    return redirect()->route('home');
})->name('dashboard');

/*
|--------------------------------------------------------------------------
| Profile Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/*
|--------------------------------------------------------------------------
| Dashboard Widget Order (Admin only)
|--------------------------------------------------------------------------
| Saves the widget order when admin drags cards on dashboard
*/
Route::post('/widgets/order', [\App\Http\Controllers\Api\WeatherController::class, 'saveWidgetOrder'])
    ->middleware(['auth', 'admin'])
    ->name('widgets.order');

/*
|--------------------------------------------------------------------------
| Admin Panel Routes
|--------------------------------------------------------------------------
*/
Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'admin', 'setup.pending'])
    ->group(function () {
        // Admin Dashboard
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Visitor Analytics
        Route::get('/visitors', [VisitorLogController::class, 'index'])->name('visitors.index');
        
        // Help / Setup Guide
        Route::get('/help', function () {
            app()->setLocale('en-us');
            return view('admin.help');
        })->name('help');
        
        // Settings Management
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::get('/settings/widgets', [SettingsController::class, 'widgets'])->name('settings.widgets');
        Route::post('/settings/widgets', [SettingsController::class, 'updateWidgets'])->name('settings.widgets.update');
        Route::get('/settings/effects', [SettingsController::class, 'effects'])->name('settings.effects');
        Route::post('/settings/effects', [SettingsController::class, 'updateEffects'])->name('settings.effects.update');
        Route::get('/settings/integrations', [SettingsController::class, 'integrations'])->name('settings.integrations');
        Route::post('/settings/integrations', [SettingsController::class, 'updateIntegrations'])->name('settings.integrations.update');
        Route::get('/settings/theme-creator', [ThemeCreatorController::class, 'edit'])->name('settings.theme-creator');
        Route::get('/settings/theme-creator/preview', [ThemeCreatorController::class, 'preview'])->name('settings.theme-creator.preview');
        Route::post('/settings/theme-creator/validate', [ThemeCreatorController::class, 'validateImport'])->name('settings.theme-creator.validate');
        Route::post('/settings/theme-creator/export', [ThemeCreatorController::class, 'export'])->name('settings.theme-creator.export');
        Route::post('/settings/theme-creator', [ThemeCreatorController::class, 'save'])->name('settings.theme-creator.save');
        Route::post('/settings/theme-creator/reset', [ThemeCreatorController::class, 'reset'])->name('settings.theme-creator.reset');
        Route::get('/settings/appearance', [SettingsController::class, 'appearance'])->name('settings.appearance');
        Route::post('/settings/appearance', [SettingsController::class, 'updateAppearance'])->name('settings.appearance.update');
        Route::get('/settings/alerts', [SettingsController::class, 'alerts'])->name('settings.alerts');
        Route::post('/settings/alerts', [SettingsController::class, 'updateAlerts'])->name('settings.alerts.update');
        Route::get('/settings/notifications', [SettingsController::class, 'notifications'])->name('settings.notifications');
        Route::post('/settings/notifications', [SettingsController::class, 'updateNotifications'])->name('settings.notifications.update');
        Route::get('/settings/mail', [SettingsController::class, 'mail'])->name('settings.mail');
        Route::post('/settings/mail', [SettingsController::class, 'updateMail'])->name('settings.mail.update');
        Route::get('/settings/mail/oauth/{provider}', [SettingsController::class, 'initiateOAuth'])->name('settings.mail.oauth');
        Route::get('/settings/mail/oauth/callback/{provider}', [SettingsController::class, 'oauthCallback'])->name('settings.mail.oauth.callback');
        Route::post('/settings/mail/test', [SettingsController::class, 'testMail'])->name('settings.mail.test');
        Route::get('/settings/telemetry', [SettingsController::class, 'telemetry'])->name('settings.telemetry');
        Route::post('/settings/telemetry', [SettingsController::class, 'updateTelemetry'])->name('settings.telemetry.update');
        Route::post('/settings/telemetry/update-now', [SettingsController::class, 'updateTelemetryNow'])->name('settings.telemetry.update-now');
        Route::get('/settings/test-api', function () {
            return response()->json([
                'success' => false,
                'message' => 'Method not allowed. Use POST.',
            ], 405);
        });
        Route::post('/settings/test-api', [SettingsController::class, 'testApi'])->name('settings.test-api');
        Route::post('/settings/weatherlink/stations', [SettingsController::class, 'weatherLinkStations'])->name('settings.weatherlink.stations');
        Route::post('/settings/nlg/models', [SettingsController::class, 'fetchNlgModels'])->name('settings.nlg.models');
        Route::post('/settings/history/sync', [SettingsController::class, 'syncHistory'])->name('settings.history.sync');
        Route::post('/settings/history/wu-sync', [SettingsController::class, 'syncWundergroundHistory'])->name('settings.history.wu-sync');
        Route::post('/settings/advanced/diagnostics', [SettingsController::class, 'downloadAdvancedDiagnostics'])->name('settings.advanced.diagnostics');
        
        // First run setup. Above the /settings/{group} catch-all, which would
        // otherwise swallow nothing here but keeps the ordering obvious.
        Route::get('/setup/station', [SetupController::class, 'station'])->name('setup.station');
        Route::post('/setup/station', [SetupController::class, 'storeStation'])->name('setup.station.store');
        Route::get('/setup/source', [SetupController::class, 'source'])->name('setup.source');
        Route::post('/setup/source', [SetupController::class, 'storeSource'])->name('setup.source.store');
        Route::post('/setup/skip', [SetupController::class, 'skip'])->name('setup.skip');

        // OG cache flush (must be before catch-all /settings/{group} route)
        Route::post('/settings/og/clear-cache', [SettingsController::class, 'clearOgImageCache'])->name('settings.og.clear-cache');

        // Open Data Sources (must be before catch-all /settings/{group} route)
        Route::get('/settings/opendata', [SettingsController::class, 'opendata'])->name('settings.opendata');
        Route::post('/settings/opendata', [SettingsController::class, 'updateOpendata'])->name('settings.opendata.update');
        
        // Updates (must be before catch-all /settings/{group} route)
        Route::get('/settings/updates', [UpdateController::class, 'index'])->name('settings.updates');
        Route::get('/updates/check', [UpdateController::class, 'check'])->name('updates.check');
        Route::post('/updates/preview', [UpdateController::class, 'preview'])->name('updates.preview');
        Route::post('/updates/deploy', [UpdateController::class, 'deploy'])->name('updates.deploy');
        Route::post('/updates/git', [UpdateController::class, 'updateViaGit'])->name('updates.git');
        Route::post('/updates/rollback', [UpdateController::class, 'rollback'])->name('updates.rollback');
        Route::post('/updates/releases/delete', [UpdateController::class, 'deleteRelease'])->name('updates.releases.delete');
        Route::post('/updates/backups/delete', [UpdateController::class, 'deleteBackup'])->name('updates.backups.delete');
        Route::post('/updates/retention', [UpdateController::class, 'updateRetention'])->name('updates.retention.update');
        Route::post('/updates/notifications', [UpdateController::class, 'updateNotificationSettings'])->name('updates.notifications.update');

        // API Keys Management
        Route::get('/api-keys', [ApiKeyController::class, 'index'])->name('api-keys.index');
        Route::post('/api-keys', [ApiKeyController::class, 'store'])->name('api-keys.store');
        Route::post('/api-keys/{apiKey}/revoke', [ApiKeyController::class, 'revoke'])->name('api-keys.revoke');
        
        // History Charts (must be before catch-all /settings/{group} route)
        Route::get('/settings/charts', [SettingsController::class, 'charts'])->name('settings.charts');
        Route::post('/settings/charts', [SettingsController::class, 'updateCharts'])->name('settings.charts.update');

        // Ecowitt data import (must be before catch-all /settings/{group} route)
        Route::post('/settings/ecowitt/import', [SettingsController::class, 'importEcowittFile'])->name('settings.ecowitt.import');

        // Catch-all settings routes (must be last)
        Route::get('/settings/{group}', [SettingsController::class, 'group'])->name('settings.group');
        Route::post('/settings/{group}', [SettingsController::class, 'update'])->name('settings.update');
        
        // User Management
        Route::resource('users', UserController::class);
        Route::post('/users/toggle-registration', [UserController::class, 'toggleRegistration'])->name('users.toggle-registration');
    });

require __DIR__.'/auth.php';
