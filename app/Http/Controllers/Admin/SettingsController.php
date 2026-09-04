<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DailySummary;
use App\Models\Setting;
use App\Models\WeatherReading;
use App\Services\Weather\EcowittPushParser;
use App\Services\Weather\Normalization\WeatherReadingWriter;
use App\Services\Weather\SunshineHoursCalculator;
use App\Services\Ads\AdsConsentService;
use App\Services\Dashboard\DashboardPayloadService;
use App\Services\Mail\MailConfigService;
use App\Services\Nlg\NlgProviderModelDiscovery;
use App\Services\OpenData\OpenDataProviderRegistry;
use App\Services\Radar\RadarFutureFramesService;
use App\Support\MenuFeatureMap;
use App\Support\StatTileRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SettingsController extends Controller
{
    /**
     * Display settings that are still actively used at runtime.
     */
    protected array $activeDisplayKeys = [
        'display.language',
        'display.unit_system',
        'display.theme',
        'display.temperature_decimals',
        'display.wind_decimals',
        'display.rain_decimals',
        'display.pressure_decimals',
        'display.rainrate_unit',
    ];

    /**
     * All available setting groups with icons and descriptions
     */
    protected array $groups = [
        // === WEATHER STATION ===
        'station' => [
            'label' => 'Station Info',
            'description' => 'Basic weather station information and location',
            'icon' => 'location',
            'color' => 'blue',
            'category' => 'station',
        ],
        'livedata' => [
            'label' => 'Live Data Source',
            'description' => 'Configure primary live data source (Ecowitt, WU, Cumulus, etc.)',
            'icon' => 'activity',
            'color' => 'emerald',
            'category' => 'station',
        ],
        'history' => [
            'label' => 'Historical Data',
            'description' => 'Configure where historical/chart data comes from',
            'icon' => 'database',
            'color' => 'cyan',
            'category' => 'station',
        ],
        'sensors' => [
            'label' => 'Sensors',
            'description' => 'Configure available sensors (UV, solar, soil, etc.)',
            'icon' => 'cpu',
            'color' => 'violet',
            'category' => 'station',
        ],
        
        // === DATA SOURCES ===
        'wxsim' => [
            'label' => 'WXSIM',
            'description' => 'Local WXSIM plaintext forecast file settings',
            'icon' => 'code',
            'color' => 'slate',
            'category' => 'datasources',
        ],
        'environment_canada' => [
            'label' => 'Environment Canada',
            'description' => 'Environment Canada forecast RSS integration',
            'icon' => 'globe',
            'color' => 'red',
            'category' => 'datasources',
        ],
        'aemet' => [
            'label' => 'AEMET',
            'description' => 'AEMET OpenData forecast API configuration',
            'icon' => 'sun',
            'color' => 'amber',
            'category' => 'datasources',
        ],
        'dwd' => [
            'label' => 'DWD',
            'description' => 'Deutscher Wetterdienst MOSMIX forecast station',
            'icon' => 'sun',
            'color' => 'amber',
            'category' => 'datasources',
        ],
        'ecowitt' => [
            'label' => 'Ecowitt',
            'description' => 'Ecowitt local push or cloud API settings',
            'icon' => 'key',
            'color' => 'green',
            'category' => 'datasources',
        ],
        'wunderground' => [
            'label' => 'Weather Underground',
            'description' => 'Weather Underground station and API settings',
            'icon' => 'cloud',
            'color' => 'yellow',
            'category' => 'datasources',
        ],
        'openweathermap' => [
            'label' => 'OpenWeatherMap',
            'description' => 'OpenWeatherMap forecast API configuration',
            'icon' => 'sun',
            'color' => 'orange',
            'category' => 'datasources',
        ],
        'yrno' => [
            'label' => 'Yr.no',
            'description' => 'Norwegian Meteorological Institute forecasts',
            'icon' => 'globe',
            'color' => 'sky',
            'category' => 'datasources',
        ],
        'aeris' => [
            'label' => 'Aeris Weather',
            'description' => 'Aeris Weather API (radar, alerts)',
            'icon' => 'zap',
            'color' => 'purple',
            'category' => 'datasources',
        ],
        'weatherlink' => [
            'label' => 'Davis WeatherLink',
            'description' => 'Davis WeatherLink Cloud API settings',
            'icon' => 'link',
            'color' => 'amber',
            'category' => 'datasources',
        ],
        'ambient' => [
            'label' => 'Ambient Weather',
            'description' => 'Ambient Weather API settings',
            'icon' => 'wifi',
            'color' => 'teal',
            'category' => 'datasources',
        ],
        'weatherflow' => [
            'label' => 'WeatherFlow',
            'description' => 'WeatherFlow Tempest station settings',
            'icon' => 'wind',
            'color' => 'indigo',
            'category' => 'datasources',
        ],
        'airquality' => [
            'label' => 'Air Quality',
            'description' => 'WAQI, Luftdaten, PurpleAir air quality data',
            'icon' => 'air',
            'color' => 'teal',
            'category' => 'datasources',
        ],
        'pollen' => [
            'label' => 'Pollen Forecast',
            'description' => 'Configure pollen data sources (Open-Meteo, Google, Ambee)',
            'icon' => 'leaf',
            'color' => 'green',
            'category' => 'datasources',
        ],
        'aviation' => [
            'label' => 'Aviation / METAR',
            'description' => 'METAR data from nearby airports',
            'icon' => 'plane',
            'color' => 'indigo',
            'category' => 'datasources',
        ],
        'tide' => [
            'label' => 'Tides',
            'description' => 'Rijkswaterstaat tide & water level data',
            'icon' => 'water',
            'color' => 'cyan',
            'category' => 'datasources',
        ],
        'waves' => [
            'label' => 'Waves & Sea Temperature',
            'description' => 'Open-Meteo Marine wave height, swell and sea surface temperature',
            'icon' => 'wave',
            'color' => 'blue',
            'category' => 'datasources',
        ],
        'rivers' => [
            'label' => 'River Levels',
            'description' => 'Rijkswaterstaat inland river gauge stations',
            'icon' => 'trending-up',
            'color' => 'emerald',
            'category' => 'datasources',
        ],

        // === FEATURES ===
        'navigation' => [
            'label' => 'Navigation',
            'description' => 'Enable or disable public menu sections and route access',
            'icon' => 'widgets',
            'color' => 'indigo',
            'category' => 'features',
        ],
        'forecast' => [
            'label' => 'Forecast Settings',
            'description' => 'Default forecast and sky condition sources',
            'icon' => 'calendar',
            'color' => 'blue',
            'category' => 'features',
        ],
        'alerts' => [
            'label' => 'Weather Alerts',
            'description' => 'Severe weather warning configuration',
            'icon' => 'alert',
            'color' => 'red',
            'category' => 'features',
        ],
        'lightning' => [
            'label' => 'Lightning',
            'description' => 'Lightning detector settings (station or Boltek)',
            'icon' => 'zap',
            'color' => 'yellow',
            'category' => 'features',
        ],
        'earthquakes' => [
            'label' => 'Earthquakes',
            'description' => 'USGS earthquake monitoring settings',
            'icon' => 'seismic',
            'color' => 'amber',
            'category' => 'features',
        ],
        'iss' => [
            'label' => 'ISS / Space Stations',
            'description' => 'International Space Station and Tiangong tracking settings',
            'icon' => 'rocket',
            'color' => 'indigo',
            'category' => 'features',
        ],
        'snow' => [
            'label' => 'Snow',
            'description' => 'Snow depth display settings',
            'icon' => 'snowflake',
            'color' => 'sky',
            'category' => 'features',
        ],
        'webcam' => [
            'label' => 'Webcam',
            'description' => 'Weather webcam display settings',
            'icon' => 'camera',
            'color' => 'pink',
            'category' => 'features',
        ],
        'radar' => [
            'label' => 'Rain Radar',
            'description' => 'Precipitation radar animation settings',
            'icon' => 'radar',
            'color' => 'sky',
            'category' => 'features',
        ],
        'satellite' => [
            'label' => 'Satellite Imagery',
            'description' => 'Satellite image display settings',
            'icon' => 'satellite',
            'color' => 'cyan',
            'category' => 'features',
        ],
        'solar_forecast' => [
            'label' => 'Solar Radiation Forecast',
            'description' => 'Solar irradiance forecast provider and cache settings',
            'icon' => 'sun',
            'color' => 'amber',
            'category' => 'features',
        ],
        'thresholds' => [
            'label' => 'Warning Thresholds',
            'description' => 'Temperature, wind and UV warning levels',
            'icon' => 'gauge',
            'color' => 'rose',
            'category' => 'features',
        ],
        
        // === DISPLAY ===
        'display' => [
            'label' => 'Display Settings',
            'description' => 'Units, language, admin theme and formatting options',
            'icon' => 'display',
            'color' => 'purple',
            'category' => 'display',
        ],
        'widgets' => [
            'label' => 'Dashboard Widgets',
            'description' => 'Configure which widgets appear on the dashboard',
            'icon' => 'widgets',
            'color' => 'violet',
            'category' => 'display',
        ],
        'seo' => [
            'label' => 'SEO & Meta',
            'description' => 'Search engine optimization settings',
            'icon' => 'search',
            'color' => 'lime',
            'category' => 'display',
        ],
        'og' => [
            'label' => 'Social Sharing Cards',
            'description' => 'Dynamic Open Graph images for Twitter/X, WhatsApp and Facebook previews',
            'icon' => 'share',
            'color' => 'violet',
            'category' => 'display',
        ],
        'contact' => [
            'label' => 'Contact & Social',
            'description' => 'Contact information and social media links',
            'icon' => 'mail',
            'color' => 'fuchsia',
            'category' => 'display',
        ],
        'footer' => [
            'label' => 'Footer',
            'description' => 'Footer links and content configuration',
            'icon' => 'layout',
            'color' => 'slate',
            'category' => 'display',
        ],
        'appearance' => [
            'label' => 'Appearance',
            'description' => 'Site theme (FX vs Flat)',
            'icon' => 'paint',
            'color' => 'slate',
            'category' => 'display',
        ],
        
        // === VISUAL EFFECTS ===
        'effects' => [
            'label' => 'Weather Effects',
            'description' => 'Visual weather animations (rain, snow, lightning, fog)',
            'icon' => 'sparkles',
            'color' => 'cyan',
            'category' => 'display',
        ],
        'integrations' => [
            'label' => 'Head Code & Integrations',
            'description' => 'Custom code injected into <head> (ads, analytics, tracking, etc.)',
            'icon' => 'code',
            'color' => 'amber',
            'category' => 'display',
        ],
        
        // === SYSTEM ===
        'advanced' => [
            'label' => 'Advanced',
            'description' => 'Logging, diagnostics, and scheduler tooling',
            'icon' => 'cog',
            'color' => 'slate',
            'category' => 'system',
        ],
        'mail' => [
            'label' => 'Mail',
            'description' => 'Email provider configuration (OAuth2, SMTP)',
            'icon' => 'mail',
            'color' => 'blue',
            'category' => 'system',
        ],
        'telemetry' => [
            'label' => 'Community Telemetry',
            'description' => 'Share your station on the community map',
            'icon' => 'globe',
            'color' => 'indigo',
            'category' => 'system',
        ],
        'scheduler' => [
            'label' => 'Schedulers',
            'description' => 'Cron setup, background jobs, and sync timings',
            'icon' => 'clock',
            'color' => 'slate',
            'category' => 'system',
        ],
        'notifications' => [
            'label' => 'Notifications',
            'description' => 'Configure alert methods and notification preferences',
            'icon' => 'bell',
            'color' => 'red',
            'category' => 'system',
        ],
        'nlg' => [
            'label' => 'NLG / Text Generation',
            'description' => 'Natural Language Generation settings for forecast text',
            'icon' => 'type',
            'color' => 'indigo',
            'category' => 'system',
        ],
    ];

    /**
     * Show settings overview page.
     */
    public function index()
    {
        $settings = [];
        foreach (array_keys($this->groups) as $group) {
            $settings[$group] = $this->settingsQueryForGroup($group)->get();
        }

        $formatKey = Setting::getValue('livedata.format', '');
        $formatSetting = Setting::find('livedata.format');
        $formatOptions = $formatSetting?->getOptionsArray() ?? [];
        $formatLabel = $formatOptions[$formatKey] ?? $formatKey;

        $lastUpdate = Cache::get('weather:last_update');
        $lastUpdateAt = $lastUpdate ? \Carbon\Carbon::parse($lastUpdate) : null;
        $status = 'no_data';
        if ($lastUpdateAt) {
            $status = $lastUpdateAt->diffInMinutes(now()) <= 10 ? 'online' : 'stale';
        }

        $fetchMode = Setting::getValue('livedata.fetch_mode', 'file');
        $modeLabel = $fetchMode === 'local_api' ? 'Local API' : 'Local file';
        $modeDetail = $fetchMode === 'local_api'
            ? Setting::getValue('livedata.api_url', '')
            : Setting::getValue('livedata.file_path', '');

        $liveDataStatus = [
            'format_key' => $formatKey,
            'format_label' => $formatLabel,
            'fetch_mode' => $fetchMode,
            'mode_label' => $modeLabel,
            'mode_detail' => $modeDetail,
            'last_update' => $lastUpdateAt,
            'status' => $status,
        ];

        return view('admin.settings.index', [
            'groups' => $this->groups,
            'settings' => $settings,
            'liveDataStatus' => $liveDataStatus,
        ]);
    }

    /**
     * Show settings for a specific group.
     */
    public function group(string $group)
    {
        if (!isset($this->groups[$group])) {
            abort(404);
        }

        // Order settings - provider/type fields first, then others
        $settings = $this->settingsQueryForGroup($group)->get();
        if ($group === 'satellite') {
            // Reorder: enabled, provider first, then provider-specific fields
            $ordered = $settings->sortBy(function($setting) {
                $order = [
                    'satellite.enabled' => 1,
                    'satellite.provider' => 2,
                    'satellite.knmi_url' => 3,
                    'satellite.nasa_url' => 4,
                    'satellite.zoom' => 5,
                    'satellite.custom_url' => 6,
                ];
                return $order[$setting->key] ?? 99;
            });
            $settings = $ordered->values();
        } elseif ($group === 'radar') {
            // Reorder: enabled, provider first, then RainViewer-specific fields in logical order
            $ordered = $settings->sortBy(function($setting) {
                $order = [
                    'radar.enabled' => 1,
                    'radar.provider' => 2,         // Provider direct na enabled
                    'radar.card_sources' => 3,
                    'radar.url' => 4,
                    'radar.rainviewer_zoom' => 5,
                    'radar.rainviewer_mode' => 6,
                    'radar.frame_delay' => 7,      // Right after mode
                    'radar.use_proxy' => 8,        // Then proxy
                    'radar.widget_provider' => 9,
                    'radar.widget_rainviewer_mode' => 10,
                ];
                return $order[$setting->key] ?? 99;
            });
            $settings = $ordered->values();
        } else {
            $settings = $settings->sortBy('key');
        }
        $historySync = $group === 'history' ? $this->getHistorySyncStatus() : null;
        $wuSyncConfig = $group === 'history' ? $this->getWuSyncConfig() : null;
        $schedulerStatus = in_array($group, ['history', 'scheduler'], true) ? $this->getSchedulerStatus() : null;
        $schedulerTasks = $group === 'scheduler' ? $this->getSchedulerTasks() : null;

        // Check if a custom view exists for this group
        $customView = "admin.settings.{$group}";
        $view = view()->exists($customView) ? $customView : 'admin.settings.group';

        $timezones = $group === 'station' ? \DateTimeZone::listIdentifiers() : [];
        if ($group === 'station') {
            sort($timezones);
        }
        $radarFutureFrameProviders = $group === 'radar'
            ? app(RadarFutureFramesService::class)->getProviderOptions()
            : [];

        return view($view, [
            'settings' => $settings,
            'group' => $group,
            'groupInfo' => $this->groups[$group],
            'allGroups' => $this->groups,
            'historySync' => $historySync,
            'wuSyncConfig' => $wuSyncConfig,
            'schedulerStatus' => $schedulerStatus,
            'schedulerTasks' => $schedulerTasks,
            'timezones' => $timezones,
            'radarFutureFrameProviders' => $radarFutureFrameProviders,
        ]);
    }

    /**
     * Sync missing daily summaries using live readings.
     */
    public function syncHistory(Request $request)
    {
        $limit = (int) $request->input('limit', 30);
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 3650) {
            $limit = 3650;
        }

        $missingDates = $this->getHistoryMissingDates();
        if ($missingDates->isEmpty()) {
            return redirect()
                ->route('admin.settings.group', 'history')
                ->with('success', 'No missing days found to sync.');
        }

        $toSync = $missingDates->sort()->reverse()->take($limit);
        $synced = 0;
        $skipped = 0;

        foreach ($toSync as $date) {
            if ($this->generateSummaryForDate($date)) {
                $synced++;
            } else {
                $skipped++;
            }
        }

        return redirect()
            ->route('admin.settings.group', 'history')
            ->with('success', "Synced {$synced} day(s). Skipped {$skipped} day(s) without readings.");
    }

    /**
     * Sync recent WU history via command.
     */
    public function syncWundergroundHistory(Request $request)
    {
        $days = (int) $request->input('days', Setting::getValue('history.wu_sync_days', 7));
        if ($days < 1) {
            $days = 1;
        }
        if ($days > 365) {
            $days = 365;
        }

        $exitCode = Artisan::call('weather:sync-wu', [
            '--days' => $days,
            '--force' => true,
            '--skip-existing' => true,
        ]);

        $message = $exitCode === 0
            ? "WU sync complete for last {$days} day(s)."
            : 'WU sync failed. Check your API key and station ID.';

        return redirect()
            ->route('admin.settings.group', 'history')
            ->with('success', $message);
    }

    /**
     * Generate and download a diagnostics snapshot for troubleshooting.
     */
    public function downloadAdvancedDiagnostics()
    {
        $filename = 'system-diagnostics-' . now()->format('Ymd_His') . '.json';
        $relativeOutput = 'diagnostics/' . $filename;
        $absoluteOutput = storage_path('app/' . $relativeOutput);

        try {
            $exitCode = Artisan::call('system:diagnostics', [
                '--output' => $relativeOutput,
                '--pretty' => true,
            ]);

            if ($exitCode !== 0 || !file_exists($absoluteOutput)) {
                return redirect()
                    ->route('admin.settings.group', 'advanced')
                    ->with('error', __('Failed to generate diagnostics snapshot. Please check application logs.'));
            }

            return response()
                ->download($absoluteOutput, $filename, ['Content-Type' => 'application/json'])
                ->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            \Log::error('Failed to generate diagnostics snapshot', [
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('admin.settings.group', 'advanced')
                ->with('error', __('Failed to generate diagnostics snapshot. Please check application logs.'));
        }
    }

    /**
     * Update settings for a group.
     */
    public function update(Request $request, string $group)
    {
        // Special handling for footer group
        if ($group === 'footer') {
            $this->updateFooterSettings($request);
            $this->clearSettingsCache();
            return redirect()
                ->route('admin.settings.group', $group)
                ->with('success', 'Settings saved successfully!');
        }

        // Special handling for notifications group
        if ($group === 'notifications') {
            $this->updateNotifications($request);
            return redirect()
                ->route('admin.settings.group', $group)
                ->with('success', 'Notification settings saved!');
        }

        // Special handling for NLG group (stored in config, not database)
        if ($group === 'nlg') {
            $this->updateNlgSettings($request);
            return redirect()
                ->route('admin.settings.group', $group)
                ->with('success', 'NLG settings saved successfully!');
        }

        // Handle custom radar nowcast settings
        if ($group === 'radar') {
            $this->updateRadarNowcastSettings($request);
        }

        // Handle custom satellite settings
        if ($group === 'satellite') {
            $this->updateSatelliteSettings($request);
        }

        if ($group === 'solar_forecast') {
            $this->updateSolarForecastSettings($request);
        }

        if ($group === 'weatherlink') {
            $this->updateWeatherLinkSettings($request);
        }

        if ($group === 'livedata') {
            $this->updateLiveDataSettings($request);
        }

        if ($group === 'ambient' && $request->input('ambient_enabled') === '1') {
            $apiKey = trim((string) $request->input('ambient_api_key', ''))
                ?: trim((string) Setting::getValue('ambient.api_key', ''));
            $applicationKey = trim((string) $request->input('ambient_application_key', ''))
                ?: trim((string) Setting::getValue('ambient.application_key', ''));

            $errors = [];
            if (empty($apiKey)) {
                $errors['ambient_api_key'] = 'The Ambient Weather API key is required when the integration is enabled.';
            }
            if (empty($applicationKey)) {
                $errors['ambient_application_key'] = 'The Ambient Weather application key is required when the integration is enabled.';
            }
            if ($errors !== []) {
                throw \Illuminate\Validation\ValidationException::withMessages($errors);
            }
        }

        if ($group === 'radar') {
            // Checkboxes: an empty selection posts nothing at all, so this
            // cannot be folded into the generic loop, which only writes keys
            // that are present in the request.
            $sources = (array) $request->input('radar_card_sources', []);
            $sources = array_values(array_filter(
                $sources,
                fn ($id) => is_string($id) && \App\Support\RadarSourceRegistry::exists($id)
            ));

            Setting::setValue('radar.card_sources', implode(',', $sources), 'string', 'radar');
        }

        if ($group === 'pollen') {
            $this->updatePollenSettings($request);
            Cache::forget('pollen_forecast');
            $this->clearSettingsCache();
            return redirect()
                ->route('admin.settings.group', $group)
                ->with('success', 'Settings saved successfully!');
        }

        if ($group === 'tide') {
            $this->updateTideSettings($request);
            $this->clearSettingsCache();
            return redirect()
                ->route('admin.settings.group', $group)
                ->with('success', 'Settings saved successfully!');
        }

        if ($group === 'rivers') {
            if ($request->input('action') === 'refresh_catalog') {
                // Provider-aware refresh: 'provider' field selects which catalog to refresh.
                // Defaults to 'rws' for backward compat with the old single-form submission.
                $providerId = $request->input('provider', 'rws');
                $providers  = \App\Services\River\RiverProviderRegistry::active();
                if (isset($providers[$providerId]['catalog_service'])) {
                    app($providers[$providerId]['catalog_service'])->refresh();
                }
                return redirect()
                    ->route('admin.settings.group', $group)
                    ->with('success', 'Station list refreshed from RWS catalog.');
            }
            $this->updateRiversSettings($request);
            $this->clearSettingsCache();
            return redirect()
                ->route('admin.settings.group', $group)
                ->with('success', 'Settings saved successfully!');
        }

        if ($group === 'waves') {
            $this->updateWavesSettings($request);
            $this->clearSettingsCache();
            return redirect()
                ->route('admin.settings.group', $group)
                ->with('success', 'Settings saved successfully!');
        }

        $settings = $this->settingsQueryForGroup($group)->get();

        foreach ($settings as $setting) {
            if ($group === 'radar' && in_array($setting->key, [
                'radar.nowcast_enabled',
                'radar.nowcast_animation_speed',
                'radar.nowcast_autoplay',
                'radar.widget_future_frames_enabled',
                'radar.widget_future_frames_provider',
            ], true)) {
                // Managed by updateRadarNowcastSettings() to avoid duplicate processing.
                continue;
            }

            if ($group === 'radar' && $setting->key === 'radar.card_sources') {
                // Posted as a checkbox array and already stored above. The
                // generic branch would write the raw array over it.
                continue;
            }

            $formKey = str_replace('.', '_', $setting->key);
            
            // Handle boolean (toggle) fields - the hidden input sends '1' or '0'
            if ($setting->type === 'boolean') {
                $value = $request->input($formKey) === '1' ? '1' : '0';
                $setting->value = $value;
                $setting->save();
                Cache::forget("setting.{$setting->key}");
                continue;
            }
            
            if ($request->has($formKey)) {
                $value = $request->input($formKey);
                
                // Don't update encrypted fields if they're empty (keeps existing value)
                if ($setting->type === 'encrypted' && empty($value)) {
                    continue;
                }
                
                // Handle encrypted fields
                if ($setting->type === 'encrypted' && !empty($value)) {
                    $value = \Illuminate\Support\Facades\Crypt::encryptString($value);
                }
                
                $setting->value = $value;
                $setting->save();
                Cache::forget("setting.{$setting->key}");
            }
        }

        // Clear only settings-related caches (preserves external API data)
        $this->clearSettingsCache();

        // If OG settings changed, flush all cached OG images
        if ($group === 'og') {
            \DB::table('cache')->where('key', 'like', '%og\_%')->delete();
        }

        // If station settings changed and telemetry is enabled, trigger update
        if ($group === 'station') {
            $this->checkAndUpdateTelemetry();
        }

        return redirect()
            ->route('admin.settings.group', $group)
            ->with('success', 'Settings saved successfully!');
    }

    /**
     * Flush all cached OG images so they are regenerated on next request.
     */
    public function clearOgImageCache(): \Illuminate\Http\RedirectResponse
    {
        \DB::table('cache')->where('key', 'like', '%og\_%')->delete();

        return redirect()
            ->route('admin.settings.group', 'og')
            ->with('success', 'OG image cache cleared — cards will regenerate on next visit.');
    }

    /**
     * Check if telemetry should be updated and trigger update if needed
     */
    private function checkAndUpdateTelemetry(): void
    {
        $enabled = Setting::getValue('telemetry.enabled', false);
        
        if (!$enabled) {
            return;
        }

        try {
            $telemetryService = app(\App\Services\Telemetry\TelemetryService::class);
            $aggregatorService = app(\App\Services\Telemetry\TelemetryAggregatorService::class);
            
            if ($telemetryService->shouldUpdate()) {
                $stationData = $telemetryService->collectStationData();
                if ($stationData) {
                    $success = $aggregatorService->sendStationData($stationData);
                    if ($success) {
                        $telemetryService->markAsUpdated($stationData);
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error('Failed to auto-update telemetry', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the settings query for a group, with per-group filtering.
     */
    private function settingsQueryForGroup(string $group)
    {
        $query = Setting::where('group', $group);

        if ($group === 'display') {
            $query->whereIn('key', $this->activeDisplayKeys);
        }

        if ($group === 'sensors') {
            // Legacy placeholder setting; intentionally hidden from admin UI.
            $query->where('key', '!=', 'sensors.extra_data_source');
        }

        return $query;
    }

    /**
     * Widget configuration page - special UI
     */
    public function widgets()
    {
        $availableWidgets = [
            // Core weather widgets
            'current' => ['label' => 'Current Conditions', 'icon' => 'thermometer', 'description' => 'Main temperature and conditions display'],
            'forecast' => ['label' => 'Forecast', 'icon' => 'calendar', 'description' => '7-day weather forecast'],
            'hourly' => ['label' => 'Hourly', 'icon' => 'clock', 'description' => 'Hour-by-hour forecast'],
            'wind' => ['label' => 'Wind', 'icon' => 'wind', 'description' => 'Wind speed, direction and gusts'],
            'rain' => ['label' => 'Precipitation', 'icon' => 'droplet', 'description' => 'Rainfall data and totals'],
            'sun' => ['label' => 'Sun', 'icon' => 'sun', 'description' => 'Sunrise, sunset, daylight'],
            'moon' => ['label' => 'Moon', 'icon' => 'moon', 'description' => 'Moon phase and illumination'],
            'airquality' => ['label' => 'Air Quality', 'icon' => 'air', 'description' => 'AQI and pollutant levels'],
            'pollen' => ['label' => 'Pollen', 'icon' => 'leaf', 'description' => 'Daily pollen risk for grass, tree and weed'],
            'tide' => ['label' => 'Tides', 'icon' => 'water', 'description' => 'Next high/low tide times and water level (Rijkswaterstaat)'],
            'metar' => ['label' => 'METAR', 'icon' => 'plane', 'description' => 'Airport weather data'],
            'radar' => ['label' => 'Radar', 'icon' => 'radar', 'description' => 'Rain radar animation'],
            'webcam' => ['label' => 'Webcam', 'icon' => 'camera', 'description' => 'Live camera image'],
            'lightning' => ['label' => 'Lightning', 'icon' => 'zap', 'description' => 'Lightning strike data'],
            'indoor' => ['label' => 'Indoor', 'icon' => 'home', 'description' => 'Indoor temperature and humidity'],
            'uv' => ['label' => 'UV Index', 'icon' => 'sun', 'description' => 'UV radiation level'],
            'solar' => ['label' => 'Solar', 'icon' => 'bolt', 'description' => 'Solar radiation data'],
            'pressure' => ['label' => 'Pressure', 'icon' => 'gauge', 'description' => 'Barometric pressure'],
            'alerts' => ['label' => 'Alerts', 'icon' => 'alert', 'description' => 'Weather warnings'],
            'earthquakes' => ['label' => 'Earthquakes', 'icon' => 'seismic', 'description' => 'Recent seismic activity'],
            
            // Astronomy widgets
            'astro_events' => ['label' => 'Sky Events', 'icon' => 'stars', 'description' => 'Upcoming astronomical events (eclipses, meteor showers, conjunctions)'],
            'aurora' => ['label' => 'Aurora / Kp Index', 'icon' => 'sparkles', 'description' => 'Geomagnetic activity and aurora visibility'],
            'iss' => ['label' => 'ISS Passages', 'icon' => 'rocket', 'description' => 'International Space Station passes'],
            
            // Extra sensor widgets
            'extra_temps' => ['label' => 'Extra Temperatures', 'icon' => 'thermometer', 'description' => 'Additional temperature sensors (temp1-8)'],
            'soil' => ['label' => 'Soil Sensors', 'icon' => 'seedling', 'description' => 'Soil moisture and temperature sensors'],
            'pm25' => ['label' => 'PM2.5 Air Quality', 'icon' => 'cloud', 'description' => 'Particulate matter sensors'],
            'co2' => ['label' => 'CO2 Monitor', 'icon' => 'gauge', 'description' => 'Carbon dioxide levels'],
            'leak' => ['label' => 'Leak Detection', 'icon' => 'droplet', 'description' => 'Water leak sensor alerts'],
            'battery' => ['label' => 'Battery Status', 'icon' => 'battery', 'description' => 'Sensor battery levels'],
            
            // Advertising widget
            'ads' => ['label' => 'Advertisement', 'icon' => 'ad', 'description' => 'Display advertisements from various ad networks'],
        ];

        $widgetFeatureRequirements = [
            'forecast' => MenuFeatureMap::FEATURE_FORECAST,
            'sun' => MenuFeatureMap::FEATURE_ASTRONOMY,
            'moon' => MenuFeatureMap::FEATURE_ASTRONOMY,
            'astro_events' => MenuFeatureMap::FEATURE_ASTRONOMY,
            'pollen' => MenuFeatureMap::FEATURE_AIR_POLLEN,
            'tide' => MenuFeatureMap::FEATURE_SKY_WATER,
            'metar' => MenuFeatureMap::FEATURE_SKY_WATER,
            'alerts' => MenuFeatureMap::FEATURE_ALERTS,
            'earthquakes' => MenuFeatureMap::FEATURE_EARTHQUAKES,
        ];

        $widgetFeatureLabels = [
            MenuFeatureMap::FEATURE_FORECAST => 'Forecast',
            MenuFeatureMap::FEATURE_ASTRONOMY => 'Astronomy',
            MenuFeatureMap::FEATURE_AIR_POLLEN => 'Air & Pollen',
            MenuFeatureMap::FEATURE_SKY_WATER => 'Sky & Water',
            MenuFeatureMap::FEATURE_ALERTS => 'Alerts',
            MenuFeatureMap::FEATURE_EARTHQUAKES => 'Earthquakes',
        ];
        $menuFeatures = MenuFeatureMap::all();

        // Quick Stats bar: the compact tiles above the widget grid. Same
        // enable/reorder model as widgets, separate registry and storage.
        $availableStatTiles = StatTileRegistry::all();
        $enabledStatTiles = StatTileRegistry::enabledIds();

        $enabledWidgetsValue = Setting::getValue('widgets.enabled', []);
        $enabledWidgets = is_array($enabledWidgetsValue) ? $enabledWidgetsValue : (json_decode($enabledWidgetsValue, true) ?: []);
        
        $layoutValue = Setting::getValue('widgets.layout', []);
        $layout = is_array($layoutValue) ? $layoutValue : (json_decode($layoutValue, true) ?: []);
        
        // Get ad company configuration
        $adCompany = Setting::getValue('widgets.ad_company', '');
        $adCode = Setting::getValue('widgets.ad_code', '');
        $adsConsentMode = app(AdsConsentService::class)->normalizeConsentMode(
            (string) Setting::getValue('widgets.ads_consent_mode', AdsConsentService::MODE_AUTO)
        );
        $pageAdCodeLegacy = Setting::getValue('widgets.page_ad_code', $adCode);
        $pageAdCodeDisplay = Setting::getValue('widgets.page_ad_code_display', $pageAdCodeLegacy);
        $pageAdCodeInFeed = Setting::getValue('widgets.page_ad_code_in_feed', '');
        $pageAdCodeInArticle = Setting::getValue('widgets.page_ad_code_in_article', '');
        $pageAdEnabledRaw = Setting::getValue('widgets.page_ad_enabled', null);
        $legacyPageAdsEnabled = in_array('ads', $enabledWidgets, true) && trim((string) $pageAdCodeLegacy) !== '';
        $pageAdEnabled = is_null($pageAdEnabledRaw) ? $legacyPageAdsEnabled : (bool) $pageAdEnabledRaw;
        $pageAdCompany = Setting::getValue('widgets.page_ad_company', $adCompany);
        $pageAdUnitType = (string) Setting::getValue('widgets.page_ad_unit_type', 'display');
        if (!in_array($pageAdUnitType, ['display', 'in_feed', 'in_article'], true)) {
            $pageAdUnitType = 'display';
        }

        // Get rain visualization style
        $rainVisualization = Setting::getValue('widgets.rain_visualization', 'ripple');

        // Get pressure visualization style
        $pressureVisualization = Setting::getValue('widgets.pressure_visualization', 'sky');

        // Get wind visualization style
        $windVisualization = Setting::getValue('widgets.wind_visualization', 'streams');

        // Get temperature visualization style
        $tempVisualization = Setting::getValue('widgets.temp_visualization', 'gradient');

        // Temperature chart options
        $tempChartNowLine = Setting::getValue('widgets.temp_chart_now_line', true);
        $tempChartObserved = Setting::getValue('widgets.temp_chart_observed', false);

        return view('admin.settings.widgets', [
            'availableWidgets' => $availableWidgets,
            'enabledWidgets' => $enabledWidgets,
            'layout' => $layout,
            'allGroups' => $this->groups,
            'adCompany' => $adCompany,
            'adCode' => $adCode,
            'adsConsentMode' => $adsConsentMode,
            'pageAdEnabled' => $pageAdEnabled,
            'pageAdCompany' => $pageAdCompany,
            'pageAdCode' => $pageAdCodeLegacy,
            'pageAdCodeDisplay' => $pageAdCodeDisplay,
            'pageAdCodeInFeed' => $pageAdCodeInFeed,
            'pageAdCodeInArticle' => $pageAdCodeInArticle,
            'pageAdUnitType' => $pageAdUnitType,
            'rainVisualization' => $rainVisualization,
            'pressureVisualization' => $pressureVisualization,
            'windVisualization' => $windVisualization,
            'tempVisualization' => $tempVisualization,
            'tempChartNowLine' => $tempChartNowLine,
            'tempChartObserved' => $tempChartObserved,
            'menuFeatures' => $menuFeatures,
            'widgetFeatureRequirements' => $widgetFeatureRequirements,
            'widgetFeatureLabels' => $widgetFeatureLabels,
            'availableStatTiles' => $availableStatTiles,
            'enabledStatTiles' => $enabledStatTiles,
        ]);
    }

    /**
     * Update widget configuration
     */
    public function updateWidgets(Request $request)
    {
        $enabledWidgets = $request->input('enabled_widgets', []);
        $gridCols = (int) $request->input('grid_cols', 3);
        
        // Get existing layout to preserve widget_order
        $existingLayout = Setting::getValue('widgets.layout', '{}');
        $existingLayout = is_array($existingLayout) ? $existingLayout : (json_decode($existingLayout, true) ?: []);
        
        // Update only grid_cols, preserve widget_order
        $existingLayout['grid_cols'] = $gridCols;
        
        // If widget order is provided (from drag-drop in admin), update it
        if ($request->has('widget_order')) {
            $existingLayout['widget_order'] = $request->input('widget_order');
        }

        Setting::setValue('widgets.enabled', $enabledWidgets, 'json', 'widgets');
        Setting::setValue('widgets.layout', $existingLayout, 'json', 'widgets');

        // Guarded by a marker input: an empty enabled_stat_tiles means "all tiles
        // off", which is indistinguishable from a form that omits the section.
        if ($request->boolean('stat_tiles_submitted')) {
            $enabledStatTiles = StatTileRegistry::sanitizeEnabled((array) $request->input('enabled_stat_tiles', []));
            Setting::setValue(StatTileRegistry::SETTING_ENABLED, $enabledStatTiles, 'json', 'widgets');
        }


        // Save ad company and ad code if provided
        if ($request->has('ad_company')) {
            Setting::setValue('widgets.ad_company', $request->input('ad_company'), 'string', 'widgets');
        }
        if ($request->has('ad_code')) {
            Setting::setValue('widgets.ad_code', $request->input('ad_code'), 'text', 'widgets');
        }
        Setting::setValue(
            'widgets.ads_consent_mode',
            app(AdsConsentService::class)->normalizeConsentMode($request->input('ads_consent_mode', AdsConsentService::MODE_AUTO)),
            'string',
            'widgets'
        );
        Setting::setValue('widgets.page_ad_enabled', $request->boolean('page_ad_enabled'), 'boolean', 'widgets');
        if ($request->has('page_ad_company')) {
            Setting::setValue('widgets.page_ad_company', $request->input('page_ad_company'), 'string', 'widgets');
        }
        $pageAdUnitType = $request->input('page_ad_unit_type', 'display');
        if (!in_array($pageAdUnitType, ['display', 'in_feed', 'in_article'], true)) {
            $pageAdUnitType = 'display';
        }
        Setting::setValue('widgets.page_ad_unit_type', $pageAdUnitType, 'string', 'widgets');
        $hasAnyPageCodeInput = $request->has('page_ad_code_display')
            || $request->has('page_ad_code_in_feed')
            || $request->has('page_ad_code_in_article')
            || $request->has('page_ad_code');
        if ($hasAnyPageCodeInput) {
            $pageAdCodeDisplay = (string) $request->input('page_ad_code_display', $request->input('page_ad_code', ''));
            $pageAdCodeInFeed = (string) $request->input('page_ad_code_in_feed', '');
            $pageAdCodeInArticle = (string) $request->input('page_ad_code_in_article', '');

            Setting::setValue('widgets.page_ad_code_display', $pageAdCodeDisplay, 'text', 'widgets');
            Setting::setValue('widgets.page_ad_code_in_feed', $pageAdCodeInFeed, 'text', 'widgets');
            Setting::setValue('widgets.page_ad_code_in_article', $pageAdCodeInArticle, 'text', 'widgets');

            $selectedPageAdCode = match ($pageAdUnitType) {
                'in_feed' => trim($pageAdCodeInFeed),
                'in_article' => trim($pageAdCodeInArticle),
                default => trim($pageAdCodeDisplay),
            };
            if ($selectedPageAdCode === '') {
                foreach ([trim($pageAdCodeDisplay), trim($pageAdCodeInFeed), trim($pageAdCodeInArticle)] as $fallbackCode) {
                    if ($fallbackCode !== '') {
                        $selectedPageAdCode = $fallbackCode;
                        break;
                    }
                }
            }
            // Keep legacy key in sync so existing fallbacks keep working.
            Setting::setValue('widgets.page_ad_code', $selectedPageAdCode, 'text', 'widgets');
        }

        // Save rain visualization style
        if ($request->has('rain_visualization')) {
            Setting::setValue('widgets.rain_visualization', $request->input('rain_visualization'), 'string', 'widgets');
        }

        // Save pressure visualization style
        if ($request->has('pressure_visualization')) {
            Setting::setValue('widgets.pressure_visualization', $request->input('pressure_visualization'), 'string', 'widgets');
        }

        // Save wind visualization style
        if ($request->has('wind_visualization')) {
            Setting::setValue('widgets.wind_visualization', $request->input('wind_visualization'), 'string', 'widgets');
        }

        // Save temperature visualization style
        if ($request->has('temp_visualization')) {
            Setting::setValue('widgets.temp_visualization', $request->input('temp_visualization'), 'string', 'widgets');
        }

        // Save temperature chart options
        Setting::setValue('widgets.temp_chart_now_line', $request->boolean('temp_chart_now_line'), 'boolean', 'widgets');
        Setting::setValue('widgets.temp_chart_observed', $request->boolean('temp_chart_observed'), 'boolean', 'widgets');

        $this->clearSettingsCache();
        // The payload caches enabled widgets and stat tiles for 30s; without this
        // the dashboard keeps serving the old set right after saving.
        app(DashboardPayloadService::class)->forgetDashboardPayloadCaches();

        return redirect()
            ->route('admin.settings.widgets')
            ->with('success', 'Widget configuration saved!');
    }

    /**
     * Head code & integrations configuration page
     */
    public function integrations()
    {
        $headCode = Setting::getValue('integrations.head_code', '');

        return view('admin.settings.integrations', [
            'headCode' => $headCode,
            'allGroups' => $this->groups,
        ]);
    }

    /**
     * Update head code & integrations
     */
    public function updateIntegrations(Request $request)
    {
        Setting::setValue('integrations.head_code', $request->input('head_code', ''), 'text', 'integrations');

        $this->clearSettingsCache();

        return redirect()
            ->route('admin.settings.integrations')
            ->with('success', 'Integrations settings saved!');
    }

    /**
     * Weather effects configuration page
     */
    public function effects()
    {
        $effectSettings = [
            'rain' => [
                'label' => 'Regen Effect',
                'icon' => '🌧️',
                'svg_icon' => 'rain',
                'description' => 'Animated raindrops falling across the screen',
                'enabled' => Setting::getValue('effects.rain.enabled', true),
                'intensity' => Setting::getValue('effects.rain.intensity', 50),
                'splash_on_cards' => Setting::getValue('effects.rain.splash_on_cards', true),
                'show_forecast' => Setting::getValue('effects.rain.show_forecast', true),
                'forecast_threshold_type' => Setting::getValue('effects.rain.forecast_threshold_type', 'absolute'),
                'forecast_threshold_value' => Setting::getValue('effects.rain.forecast_threshold_value', 0.5),
            ],
            'snow' => [
                'label' => 'Sneeuw Effect',
                'icon' => '❄️',
                'svg_icon' => 'snow',
                'description' => 'Gentle snowflakes drifting down',
                'enabled' => Setting::getValue('effects.snow.enabled', true),
                'intensity' => Setting::getValue('effects.snow.intensity', 50),
            ],
            'wind' => [
                'label' => 'Wind Effect',
                'icon' => '💨',
                'svg_icon' => 'wind',
                'description' => 'Horizontal streaks showing wind movement',
                'enabled' => Setting::getValue('effects.wind.enabled', true),
                'intensity' => Setting::getValue('effects.wind.intensity', 50),
            ],
            'lightning' => [
                'label' => 'Bliksem Effect',
                'icon' => '⚡',
                'svg_icon' => 'lightning-bolt',
                'description' => 'Screen flashes during thunderstorms',
                'enabled' => Setting::getValue('effects.lightning.enabled', true),
            ],
            'sun' => [
                'label' => 'Zonnestralen',
                'icon' => '☀️',
                'svg_icon' => 'clear-day',
                'description' => 'Golden rays radiating on sunny days',
                'enabled' => Setting::getValue('effects.sun.enabled', true),
            ],
            'clouds' => [
                'label' => 'Wolken',
                'icon' => '☁️',
                'svg_icon' => 'cloudy',
                'description' => 'Subtle cloud layer drifting in background',
                'enabled' => Setting::getValue('effects.clouds.enabled', true),
            ],
            'fog' => [
                'label' => 'Mist Effect',
                'icon' => '🌫️',
                'svg_icon' => 'fog',
                'description' => 'Atmospheric fog overlay when humidity is high',
                'enabled' => Setting::getValue('effects.fog.enabled', true),
            ],
        ];

        return view('admin.settings.effects', [
            'effectSettings' => $effectSettings,
            'globalEnabled' => Setting::getValue('effects.enabled', true),
            'testMode' => Setting::getValue('effects.test_mode', false),
            'testEffect' => Setting::getValue('effects.test_effect', 'rain'),
            'allGroups' => $this->groups,
        ]);
    }

    /**
     * Update weather effects settings
     */
    public function updateEffects(Request $request)
    {
        // Global toggle
        Setting::setValue('effects.enabled', $request->boolean('effects_enabled') ? '1' : '0', 'boolean', 'effects');
        Setting::setValue('effects.test_mode', $request->boolean('test_mode') ? '1' : '0', 'boolean', 'effects');
        Setting::setValue('effects.test_effect', $request->input('test_effect', 'rain'), 'select', 'effects');

        // Individual effects
        $effects = ['rain', 'snow', 'wind', 'lightning', 'sun', 'clouds', 'fog'];
        
        foreach ($effects as $effect) {
            Setting::setValue(
                "effects.{$effect}.enabled", 
                $request->boolean("{$effect}_enabled") ? '1' : '0', 
                'boolean', 
                'effects'
            );
            
            // Save intensity for effects that have it
            if (in_array($effect, ['rain', 'snow', 'wind'])) {
                $intensity = max(10, min(100, (int) $request->input("{$effect}_intensity", 50)));
                Setting::setValue("effects.{$effect}.intensity", (string) $intensity, 'integer', 'effects');
            }
        }

        // Special rain settings
        Setting::setValue(
            'effects.rain.splash_on_cards',
            $request->boolean('rain_splash_on_cards') ? '1' : '0',
            'boolean',
            'effects'
        );
        
        Setting::setValue(
            'effects.rain.show_forecast',
            $request->boolean('rain_show_forecast') ? '1' : '0',
            'boolean',
            'effects'
        );
        
        Setting::setValue(
            'effects.rain.forecast_threshold_type',
            $request->input('rain_forecast_threshold_type', 'absolute'),
            'select',
            'effects'
        );
        
        $thresholdValue = (float) $request->input('rain_forecast_threshold_value', 0.5);
        if ($thresholdValue < 0) {
            $thresholdValue = 0;
        }
        Setting::setValue(
            'effects.rain.forecast_threshold_value',
            (string) $thresholdValue,
            'float',
            'effects'
        );

        $this->clearSettingsCache();

        return redirect()
            ->route('admin.settings.effects')
            ->with('success', 'Weather effects saved!');
    }

    /**
     * Appearance (site theme) configuration page
     */
    public function appearance()
    {
        return view('admin.settings.appearance', [
            'allGroups' => $this->groups,
        ]);
    }

    /**
     * Update appearance (site theme) settings
     */
    public function updateAppearance(Request $request)
    {
        $theme = in_array($request->input('appearance_theme'), ['fx', 'flat'], true)
            ? $request->input('appearance_theme')
            : 'fx';
        Setting::setValue('appearance.theme', $theme, 'select', 'appearance');
        Cache::forget('setting.appearance.theme');
        $this->clearSettingsCache();

        return redirect()
            ->route('admin.settings.appearance')
            ->with('success', __('Appearance settings saved.'));
    }

    /**
     * Weather alerts configuration page
     */
    public function alerts()
    {
        $sources = \App\Services\Alerts\AlertServiceFactory::getSources();
        $currentSource = Setting::getValue('alerts.source', 'europe');
        
        // Get current settings for all regions
        $settings = [
            'enabled' => Setting::getValue('alerts.enabled', true),
            'source' => $currentSource,
            
            // Europe (Meteoalarm)
            'region_code' => Setting::getValue('alerts.region_code', 'NL011'),
            'region_name' => Setting::getValue('alerts.region_name', ''),
            
            // USA (NWS)
            'us_state' => Setting::getValue('alerts.us_state', 'NY'),
            'us_zone' => Setting::getValue('alerts.us_zone', ''),
            
            // Canada
            'province' => Setting::getValue('alerts.province', 'ON'),
            'ca_region_code' => Setting::getValue('alerts.ca_region_code', 'on-143'),
            
            // UK
            'uk_region' => Setting::getValue('alerts.uk_region', 'se'),
            
            // Australia
            'au_state' => Setting::getValue('alerts.au_state', 'nsw'),
        ];

        // Test current service
        $testResult = null;
        $alertService = \App\Services\Alerts\AlertServiceFactory::make();
        try {
            $alerts = $alertService->fetchAlerts();
            $testResult = [
                'success' => $alerts !== null,
                'count' => is_array($alerts) ? count($alerts) : 0,
                'alerts' => is_array($alerts) ? array_slice($alerts, 0, 3) : [],
            ];
        } catch (\Exception $e) {
            $testResult = [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }

        return view('admin.settings.alerts', [
            'sources' => $sources,
            'settings' => $settings,
            'testResult' => $testResult,
            'allGroups' => $this->groups,
        ]);
    }

    /**
     * Update weather alerts settings
     */
    public function updateAlerts(Request $request)
    {
        // Region/state/zone codes are forwarded to external alert APIs; restrict
        // them to the expected alphanumeric form (defence in depth / data hygiene).
        $request->validate([
            'region_code'    => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9_-]*$/'],
            'us_state'       => ['nullable', 'string', 'max:8',  'regex:/^[A-Za-z]*$/'],
            'us_zone'        => ['nullable', 'string', 'max:16', 'regex:/^[A-Za-z0-9]*$/'],
            'province'       => ['nullable', 'string', 'max:8',  'regex:/^[A-Za-z]*$/'],
            'ca_region_code' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9_-]*$/'],
            'uk_region'      => ['nullable', 'string', 'max:16', 'regex:/^[A-Za-z]*$/'],
            'au_state'       => ['nullable', 'string', 'max:16', 'regex:/^[A-Za-z]*$/'],
        ]);

        Setting::setValue('alerts.enabled', $request->boolean('alerts_enabled') ? '1' : '0', 'boolean', 'alerts');
        Setting::setValue('alerts.source', $request->input('source', 'europe'), 'select', 'alerts');
        
        // Europe (Meteoalarm)
        Setting::setValue('alerts.region_code', $request->input('region_code', 'NL011'), 'string', 'alerts');
        Setting::setValue('alerts.region_name', $request->input('region_name', ''), 'string', 'alerts');
        
        // USA (NWS)
        Setting::setValue('alerts.us_state', strtoupper($request->input('us_state', 'NY')), 'string', 'alerts');
        Setting::setValue('alerts.us_zone', $request->input('us_zone', ''), 'string', 'alerts');
        
        // Canada
        Setting::setValue('alerts.province', strtoupper($request->input('province', 'ON')), 'string', 'alerts');
        Setting::setValue('alerts.ca_region_code', strtolower($request->input('ca_region_code', 'on-143')), 'string', 'alerts');
        
        // UK
        Setting::setValue('alerts.uk_region', strtolower($request->input('uk_region', 'se')), 'string', 'alerts');
        
        // Australia
        Setting::setValue('alerts.au_state', strtolower($request->input('au_state', 'nsw')), 'string', 'alerts');

        $this->clearSettingsCache();

        return redirect()
            ->route('admin.settings.alerts')
            ->with('success', 'Weather alert settings saved!');
    }

    /**
     * System notifications configuration page
     */
    public function notifications()
    {
        $settings = [
            'enabled' => Setting::getValue('notifications.enabled', false),
            'method' => Setting::getValue('notifications.method', 'email'), // email, webhook, both
            'email' => Setting::getValue('notifications.email', ''),
            'webhook_url' => Setting::getValue('notifications.webhook_url', ''),
            
            // Alert types - what to notify about
            'sensor_offline' => Setting::getValue('notifications.sensor_offline', true),
            'data_fetch_failed' => Setting::getValue('notifications.data_fetch_failed', true),
            'data_save_failed' => Setting::getValue('notifications.data_save_failed', true),
            'source_file_stale' => Setting::getValue('notifications.source_file_stale', true),
            'cache_missing' => Setting::getValue('notifications.cache_missing', false),
            'api_error' => Setting::getValue('notifications.api_error', false),

            // Per-sensor health tracking (drives the "sensor offline" alerts)
            'sensor_health_enabled' => Setting::getValue('sensor_health.enabled', true),
            'sensor_health_fail_minutes' => (int) Setting::getValue('sensor_health.fail_minutes', 30),
            'sensor_health_track_days' => (int) Setting::getValue('sensor_health.track_days', 7),
        ];

        return view('admin.settings.notifications', [
            'settings' => $settings,
            'allGroups' => $this->groups,
        ]);
    }

    /**
     * Update system notifications settings
     */
    public function updateNotifications(Request $request)
    {
        // Handle notifications_enabled - it comes as '1' or '0' string from the toggle switch
        $enabledValue = $request->input('notifications_enabled', '0');
        $enabled = ($enabledValue === '1' || $enabledValue === 'true' || $enabledValue === 'on' || $enabledValue === 'yes');
        
        Setting::setValue('notifications.enabled', $enabled, 'boolean', 'notifications');
        Setting::setValue('notifications.method', $request->input('method', 'email'), 'select', 'notifications');
        Setting::setValue('notifications.email', $request->input('email', ''), 'string', 'notifications');
        Setting::setValue('notifications.webhook_url', $request->input('webhook_url', ''), 'string', 'notifications');
        
        // Alert types
        $sensorOffline = ($request->input('sensor_offline') === '1');
        $dataFetchFailed = ($request->input('data_fetch_failed') === '1');
        $dataSaveFailed = ($request->input('data_save_failed') === '1');
        $sourceFileStale = ($request->input('source_file_stale') === '1');
        $cacheMissing = ($request->input('cache_missing') === '1');
        $apiError = ($request->input('api_error') === '1');
        
        Setting::setValue('notifications.sensor_offline', $sensorOffline, 'boolean', 'notifications');
        Setting::setValue('notifications.data_fetch_failed', $dataFetchFailed, 'boolean', 'notifications');
        Setting::setValue('notifications.data_save_failed', $dataSaveFailed, 'boolean', 'notifications');
        Setting::setValue('notifications.source_file_stale', $sourceFileStale, 'boolean', 'notifications');
        Setting::setValue('notifications.cache_missing', $cacheMissing, 'boolean', 'notifications');
        Setting::setValue('notifications.api_error', $apiError, 'boolean', 'notifications');

        // Per-sensor health tracking. Bounds match CheckSensorHealth so a value
        // saved here can never disable detection by being out of range.
        $sensorHealthEnabled = ($request->input('sensor_health_enabled') === '1');
        $failMinutes = max(15, min(10080, (int) $request->input('sensor_health_fail_minutes', 30)));
        $trackDays = max(1, min(30, (int) $request->input('sensor_health_track_days', 7)));

        Setting::setValue('sensor_health.enabled', $sensorHealthEnabled, 'boolean', 'sensors');
        Setting::setValue('sensor_health.fail_minutes', $failMinutes, 'integer', 'sensors');
        Setting::setValue('sensor_health.track_days', $trackDays, 'integer', 'sensors');

        $this->clearSettingsCache();

        return redirect()
            ->route('admin.settings.notifications')
            ->with('success', 'Notification settings saved!');
    }

    /**
     * Telemetry configuration page
     */
    public function telemetry()
    {
        $telemetryService = app(\App\Services\Telemetry\TelemetryService::class);
        $stationData = $telemetryService->collectStationData();
        
        $settings = [
            'enabled' => Setting::getValue('telemetry.enabled', false),
            'aggregator_url' => Setting::getValue('telemetry.aggregator_url', 'https://weathernode.dev/telemetry-aggregator/api/telemetry'),
            'github_repo' => Setting::getValue('telemetry.github_repo', 'centauri/community-stations'),
            'github_file' => Setting::getValue('telemetry.github_file', 'stations.json'),
            'last_updated' => Setting::getValue('telemetry.last_updated', ''),
        ];

        return view('admin.settings.telemetry', [
            'settings' => $settings,
            'stationData' => $stationData,
            'allGroups' => $this->groups,
        ]);
    }

    /**
     * Update telemetry settings
     */
    public function updateTelemetry(Request $request)
    {
        // The server POSTs station data to aggregator_url, so constrain it to a
        // well-formed http/https URL rather than accepting any string.
        $request->validate([
            'aggregator_url' => ['nullable', 'url:http,https', 'max:255'],
        ]);

        $enabled = $request->boolean('telemetry_enabled');

        Setting::setValue('telemetry.enabled', $enabled ? '1' : '0', 'boolean', 'telemetry');
        Setting::setValue('telemetry.aggregator_url', $request->input('aggregator_url', 'https://weathernode.dev/telemetry-aggregator/api/telemetry'), 'string', 'telemetry');
        Setting::setValue('telemetry.github_repo', $request->input('github_repo', 'centauri/community-stations'), 'string', 'telemetry');
        Setting::setValue('telemetry.github_file', $request->input('github_file', 'stations.json'), 'string', 'telemetry');
        
        // Handle API key (encrypted)
        if ($request->has('api_key') && !empty($request->input('api_key'))) {
            Setting::setValue('telemetry.api_key', $request->input('api_key'), 'encrypted', 'telemetry');
        }

        $this->clearSettingsCache();

        // If enabled, trigger update via aggregator
        if ($enabled) {
            try {
                $telemetryService = app(\App\Services\Telemetry\TelemetryService::class);
                $aggregatorService = app(\App\Services\Telemetry\TelemetryAggregatorService::class);
                
                if ($telemetryService->shouldUpdate()) {
                    $stationData = $telemetryService->collectStationData();
                    if ($stationData) {
                        $success = $aggregatorService->sendStationData($stationData);
                        if ($success) {
                            $telemetryService->markAsUpdated($stationData);
                        }
                    }
                }
            } catch (\Exception $e) {
                \Log::error('Failed to update telemetry after enabling', [
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            // If disabled, remove station from aggregator
            try {
                $telemetryService = app(\App\Services\Telemetry\TelemetryService::class);
                $aggregatorService = app(\App\Services\Telemetry\TelemetryAggregatorService::class);
                $stationId = $telemetryService->getStationId();
                if ($stationId) {
                    $aggregatorService->removeStation($stationId);
                }
            } catch (\Exception $e) {
                \Log::error('Failed to remove station from aggregator', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return redirect()
            ->route('admin.settings.telemetry')
            ->with('success', 'Telemetry settings saved!');
    }

    /**
     * Manually trigger telemetry update
     */
    public function updateTelemetryNow()
    {
        try {
            $telemetryService = app(\App\Services\Telemetry\TelemetryService::class);
            $aggregatorService = app(\App\Services\Telemetry\TelemetryAggregatorService::class);
            
            $enabled = Setting::getValue('telemetry.enabled', false);
            if (!$enabled) {
                return response()->json([
                    'success' => false,
                    'message' => 'Telemetry is disabled. Enable it first.',
                ]);
            }
            
            $stationData = $telemetryService->collectStationData();
            if (!$stationData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to collect station data.',
                ]);
            }
            
            $result = $aggregatorService->sendStationData($stationData);
            if ($result) {
                $telemetryService->markAsUpdated($stationData);
                return response()->json([
                    'success' => true,
                    'message' => 'Station data sent to aggregator successfully!',
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send data to aggregator. Check aggregator URL and API key.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Test API connection.
     */
    public function testApi(Request $request)
    {
        try {
            $service = $request->input('service');
            
            if (empty($service)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Service parameter is required'
                ], 400);
            }
            
            $result = ['success' => false, 'message' => 'Unknown service'];
        $cacheCheck = function (int $freshMinutes = 10): array {
            $lastUpdate = Cache::get('weather:last_update');
            if (!$lastUpdate) {
                return ['success' => false, 'message' => 'No live data received yet.'];
            }

            try {
                $lastUpdateAt = \Carbon\Carbon::parse($lastUpdate);
            } catch (\Exception $e) {
                return ['success' => false, 'message' => 'Invalid live data timestamp.'];
            }

            $minutesAgo = $lastUpdateAt->diffInMinutes(now());
            if ($minutesAgo <= $freshMinutes) {
                return ['success' => true, 'message' => "Last update {$minutesAgo} min ago."];
            }

            return ['success' => false, 'message' => "Last update {$minutesAgo} min ago (stale)."];
        };

        switch ($service) {
                case 'ecowitt':
                    $format = Setting::getValue('livedata.format', '');
                    $source = Setting::getValue('ecowitt.data_source', '');

                    if ($format === 'ecoLcl' || $source === 'local_api') {
                        $result = $cacheCheck();
                        break;
                    }

                    $svc = app(\App\Services\Weather\EcowittService::class);
                    $data = $svc->fetchRealTimeData();
                    $result = $data ?
                        ['success' => true, 'message' => 'Connection successful! Data received.'] :
                        ['success' => false, 'message' => 'No data returned. Check your API keys or file path.'];
                    break;

                case 'yrno':
                    $svc = app(\App\Services\Forecast\YrNoService::class);
                    $data = $svc->fetchForecast();
                    $result = $data ? 
                        ['success' => true, 'message' => 'Yr.no connection successful!'] :
                        ['success' => false, 'message' => 'No forecast data returned'];
                    break;

                case 'waqi':
                    $svc = app(\App\Services\AirQuality\WaqiService::class);
                    $data = $svc->fetchAirQuality();
                    $result = $data ?
                        ['success' => true, 'message' => 'WAQI connection successful!'] :
                        ['success' => false, 'message' => 'No air quality data returned'];
                    break;

                case 'purpleair':
                    $svc = app(\App\Services\AirQuality\PurpleAirService::class);
                    $data = $svc->fetchSensorData();
                    $result = $data ?
                        ['success' => true, 'message' => 'PurpleAir connection successful!'] :
                        ['success' => false, 'message' => 'No data returned. Check sensor ID and API key.'];
                    break;

                case 'luftdaten':
                    $svc = app(\App\Services\AirQuality\LuftdatenService::class);
                    $data = $svc->fetchSensorData();
                    $result = $data ?
                        ['success' => true, 'message' => 'Luftdaten/Sensor.Community connection successful!'] :
                        ['success' => false, 'message' => 'No data returned. Check sensor ID.'];
                    break;

                case 'luftdaten_noise':
                    $sensorId = trim(Setting::getValue('luftdaten_noise.sensor_id', ''));
                    if (empty($sensorId)) {
                        $result = ['success' => false, 'message' => 'No noise sensor ID configured.'];
                    } else {
                        $svc = app(\App\Services\AirQuality\LuftdatenService::class);
                        $data = $svc->fetchBySensorId($sensorId);
                        $result = $data && ($data['category'] ?? '') === 'noise'
                            ? ['success' => true, 'message' => 'Luftdaten noise sensor (DNMS) connection successful!']
                            : ['success' => false, 'message' => 'No data or not a noise sensor. Check sensor ID (must be a DNMS).'];
                    }
                    break;

                case 'davis_aq':
                    $svc = app(\App\Services\Weather\AirLinkLocalService::class);
                    $data = $svc->getCurrentConditions();
                    $result = $data ?
                        ['success' => true, 'message' => 'Davis AirLink connection successful!'] :
                        ['success' => false, 'message' => 'No data returned. Check IP address in WeatherLink settings.'];
                    break;

                case 'checkwx':
                    $svc = app(\App\Services\Aviation\MetarService::class);
                    $data = $svc->fetchMetar([Setting::getValue('metar.primary_icao', 'EHAM')]);
                    $result = $data ? 
                        ['success' => true, 'message' => 'CheckWX connection successful!'] :
                        ['success' => false, 'message' => 'No METAR data returned'];
                    break;

                case 'wunderground':
                    $svc = app(\App\Services\Weather\Sources\WundergroundAdapter::class);
                    $data = $svc->fetch();
                    $result = $data ?
                        ['success' => true, 'message' => 'Weather Underground connection successful!'] :
                        ['success' => false, 'message' => 'No data returned. Check station ID and API key.'];
                    break;

                case 'livedata':
                    // Allow testing with a specific format from request, otherwise use saved format
                    $format = $request->input('format', Setting::getValue('livedata.format', ''));

                    if ($format === 'ecoLcl') {
                        $result = $cacheCheck();
                        break;
                    }

                    if ($format === 'ecowittAPI') {
                        $svc = app(\App\Services\Weather\EcowittService::class);
                        $data = $svc->fetchRealTimeData();
                        $result = $data ?
                            ['success' => true, 'message' => 'Ecowitt cloud API returned data.'] :
                            ['success' => false, 'message' => 'No data returned. Check Ecowitt API credentials.'];
                        break;
                    }

                    if ($format === 'wu') {
                        $svc = app(\App\Services\Weather\Sources\WundergroundAdapter::class);
                        $data = $svc->fetch();
                        $result = $data ?
                            ['success' => true, 'message' => 'Weather Underground returned data.'] :
                            ['success' => false, 'message' => 'No data returned. Check station ID and API key.'];
                        break;
                    }

                    if ($format === 'wf') {
                        $svc = app(\App\Services\Weather\Sources\WeatherFlowAdapter::class);
                        $data = $svc->fetch();
                        $result = $data ?
                            ['success' => true, 'message' => 'WeatherFlow returned data.'] :
                            ['success' => false, 'message' => 'No data returned. Check station ID.'];
                        break;
                    }

                    if ($format === 'AWapi') {
                        $svc = app(\App\Services\Weather\Sources\AmbientWeatherAdapter::class);
                        $data = $svc->fetch();
                        $result = $data ?
                            ['success' => true, 'message' => 'Ambient Weather returned data.'] :
                            ['success' => false, 'message' => 'No data returned. Check API key and device ID.'];
                        break;
                    }

                    if ($format === 'DWL') {
                        $svc = app(\App\Services\Weather\Sources\WeatherLinkV1Adapter::class);
                        $data = $svc->fetch();
                        $result = $data ?
                            ['success' => true, 'message' => 'WeatherLink v1 returned data.'] :
                            ['success' => false, 'message' => 'No data returned. Check WeatherLink v1 credentials.'];
                        break;
                    }

                    if ($format === 'DWL_v2api' || $format === 'DWL_v2api_demo') {
                        // Test endpoint must be side-effect free: only validate and fetch.
                        if ($format === 'DWL_v2api_demo') {
                            // Check if API key and API secret are configured (both required for demo mode)
                            $apiKey = Setting::getValue('weatherlink.api_key', '');
                            $apiSecret = Setting::getValue('weatherlink.api_secret', '');
                            if (empty($apiKey)) {
                                $result = ['success' => false, 'message' => 'API key is required for demo mode. Please enter your API key and save the form first, then test again.'];
                                break;
                            }
                            if (empty($apiSecret)) {
                                $result = ['success' => false, 'message' => 'API secret is required for demo mode. Please configure it on the WeatherLink settings page and test again.'];
                                break;
                            }
                        }
                        
                        try {
                            $svc = app(\App\Services\Weather\Sources\WeatherLinkAdapter::class);
                            $data = $svc->fetch();
                            $result = $data ?
                                ['success' => true, 'message' => $format === 'DWL_v2api_demo' ? 'WeatherLink v2 Demo Mode returned data.' : 'WeatherLink v2 returned data.'] :
                                ['success' => false, 'message' => $format === 'DWL_v2api_demo' ? 'No data returned from demo station. Check your API key.' : 'No data returned. Check WeatherLink v2 credentials.'];
                        } catch (\Throwable $e) {
                            Log::error('WeatherLink adapter error during test', [
                                'format' => $format,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]);
                            $result = ['success' => false, 'message' => 'Error testing WeatherLink: ' . $e->getMessage()];
                        }
                        break;
                    }

                    $svc = app(\App\Services\Weather\LocalFiles\LocalFileSourceService::class);
                    $data = $svc->fetchCurrent();
                    $result = $data ?
                        ['success' => true, 'message' => 'Live data source returned data.'] :
                        ['success' => false, 'message' => 'No data returned. Check file path or API URL.'];
                    break;

                case 'weatherflow':
                    $svc = app(\App\Services\Weather\WeatherFlowService::class);
                    $data = $svc->getCurrentConditions();
                    $result = $data
                        ? ['success' => true, 'message' => 'WeatherFlow connection successful!']
                        : ['success' => false, 'message' => 'No data returned. For public stations leave API token blank. For your own or a private station, use a token from tempestwx.com → Settings → Data Authorizations. Check storage/logs/laravel.log for the API response if it keeps failing.'];
                    break;

                case 'ambient':
                    $svc = app(\App\Services\Weather\AmbientWeatherService::class);
                    $data = $svc->getCurrentConditions();
                    $result = $data ?
                        ['success' => true, 'message' => 'Ambient Weather connection successful!'] :
                        ['success' => false, 'message' => 'No data returned. Check API key and device ID.'];
                    break;

                case 'weatherlink':
                    $type = Setting::getValue('weatherlink.type', 'v2');
                    $testType = $request->input('type', $type);
                    
                    if ($testType === 'v1') {
                        $svc = app(\App\Services\Weather\Sources\WeatherLinkV1Adapter::class);
                        $data = $svc->fetch();
                        $result = $data ?
                            ['success' => true, 'message' => 'WeatherLink v1 connection successful!'] :
                            ['success' => false, 'message' => 'No data returned. Check Device ID, Password, and API Key.'];
                    } elseif ($testType === 'v2') {
                        $svc = app(\App\Services\Weather\WeatherLinkService::class);
                        $data = $svc->getCurrentConditions();
                        $demoMode = Setting::getValue('weatherlink.demo_mode', false);
                        $result = $data ?
                            ['success' => true, 'message' => $demoMode ? 'WeatherLink v2 Demo Mode connection successful!' : 'WeatherLink v2 connection successful!'] :
                            ['success' => false, 'message' => $demoMode ? 'No data returned from demo station.' : 'No data returned. Check API Key, Secret, and Station ID.'];
                    } elseif ($testType === 'airlink_local') {
                        $svc = app(\App\Services\Weather\AirLinkLocalService::class);
                        $data = $svc->getCurrentConditions();
                        $result = $data ?
                            ['success' => true, 'message' => 'AirLink Local API connection successful!'] :
                            ['success' => false, 'message' => 'No data returned. Check IP address and ensure device is on the local network.'];
                    } elseif ($testType === 'wll_local') {
                        $svc = app(\App\Services\Weather\WeatherLinkLiveLocalService::class);
                        $data = $svc->getCurrentConditions();
                        $result = $data ?
                            ['success' => true, 'message' => 'WeatherLink Live Local API connection successful!'] :
                            ['success' => false, 'message' => 'No data returned. Check IP address and ensure device is on the local network.'];
                    } else {
                        $result = ['success' => false, 'message' => 'Invalid WeatherLink type.'];
                    }
                    break;

                case 'openweathermap':
                    $apiKey = Setting::getValue('openweathermap.api_key', '');
                    if (empty($apiKey)) {
                        $result = ['success' => false, 'message' => 'API key not configured'];
                    } else {
                        $lat = Setting::latitude();
                        $lon = Setting::longitude();
                        $response = Http::timeout(10)->get('https://api.openweathermap.org/data/2.5/weather', [
                            'lat' => $lat,
                            'lon' => $lon,
                            'appid' => $apiKey,
                        ]);
                        if ($response->successful()) {
                            $result = ['success' => true, 'message' => 'OpenWeatherMap connection successful!'];
                        } elseif ($response->status() === 401) {
                            $body = $response->json();
                            $msg = $body['message'] ?? 'Invalid API key';
                            $result = ['success' => false, 'message' => $msg . ' New keys can take up to 2 hours to activate (see openweathermap.org/faq#error401).'];
                        } elseif ($response->clientError()) {
                            $body = $response->json();
                            $msg = $body['message'] ?? $response->body();
                            $result = ['success' => false, 'message' => 'OpenWeatherMap API error: ' . $msg];
                        } else {
                            $result = ['success' => false, 'message' => 'Failed to connect to OpenWeatherMap: ' . ($response->body() ?: 'check network or try again later')];
                        }
                    }
                    break;

                case 'nlg':
                    $provider = Setting::getValue('nlg.provider', 'openai');
                    $preset = config("nlg.providers.{$provider}", []);
                    $type = $preset['type'] ?? 'compatible';
                    $modelDiscovery = app(NlgProviderModelDiscovery::class);

                    if ($type === 'ollama') {
                        $hostUrl = Setting::getValue('nlg.base_url', '') ?: ($preset['base_url'] ?? 'http://localhost:11434');
                        $model = Setting::getValue('nlg.model', '') ?: ($preset['default_model'] ?? 'llama3');
                        $discovery = $modelDiscovery->discover($type, $hostUrl);
                        if ($discovery['supported']) {
                            $models = $discovery['models'];
                            $hasModel = $modelDiscovery->modelExists($models, $model, $type);
                            $result = [
                                'success' => true,
                                'message' => 'Ollama connected. ' . count($models) . ' model(s) available.'
                                    . ($hasModel ? " Model \"{$model}\" found." : " Warning: model \"{$model}\" not found locally."),
                            ];
                        } else {
                            $result = ['success' => false, 'message' => $discovery['message'] ?? "Cannot reach Ollama at {$hostUrl}. Is it running?"];
                        }
                    } else {
                        // OpenAI-compatible: send a minimal chat completion request
                        $baseUrl = Setting::getValue('nlg.base_url', '') ?: ($preset['base_url'] ?? '');
                        $apiKey = Setting::getValue('nlg.api_key', '');
                        $model = Setting::getValue('nlg.model', '') ?: ($preset['default_model'] ?? '');

                        if (empty($apiKey)) {
                            $result = ['success' => false, 'message' => 'No API key configured. Please save your API key first.'];
                            break;
                        }
                        if (empty($baseUrl)) {
                            $result = ['success' => false, 'message' => 'No base URL configured for this provider.'];
                            break;
                        }

                        $discovery = $modelDiscovery->discover($type, $baseUrl, $apiKey);
                        $modelDiscoveryMessage = '';
                        if ($discovery['supported']) {
                            $availableModels = $discovery['models'];
                            $hasModel = $modelDiscovery->modelExists($availableModels, $model, $type);
                            $modelDiscoveryMessage = ' ' . count($availableModels) . ' model(s) listed.'
                                . ($hasModel ? " Model \"{$model}\" found." : " Warning: model \"{$model}\" not found in the provider list.");
                        } elseif (is_string($discovery['message']) && $discovery['message'] !== '') {
                            $modelDiscoveryMessage = ' ' . $discovery['message'];
                        }

                        $res = Http::withToken($apiKey)
                            ->timeout(15)
                            ->post(rtrim($baseUrl, '/') . '/chat/completions', [
                                'model' => $model,
                                'messages' => [
                                    ['role' => 'user', 'content' => 'Say "ok" and nothing else.'],
                                ],
                                'max_tokens' => 5,
                                'temperature' => 0,
                            ]);

                        if ($res->ok()) {
                            $reply = data_get($res->json(), 'choices.0.message.content', '');
                            $usedModel = data_get($res->json(), 'model', $model);
                            $result = [
                                'success' => true,
                                'message' => "Connection successful! Model: {$usedModel}. Response: \"{$reply}\"{$modelDiscoveryMessage}",
                            ];
                        } else {
                            $body = $res->json();
                            $errMsg = data_get($body, 'error.message', data_get($body, 'error', $res->body()));
                            $result = [
                                'success' => false,
                                'message' => "API returned HTTP {$res->status()}: {$errMsg}{$modelDiscoveryMessage}",
                            ];
                        }
                    }
                    break;
            }
        } catch (\Throwable $e) {
            // Catch any errors (including fatal errors)
            Log::error('Test API error', [
                'service' => $service ?? 'unknown',
                'format' => $request->input('format', 'unknown'),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Provide user-friendly error message
            $errorMessage = 'Error testing connection: ' . $e->getMessage();
            if (str_contains($e->getMessage(), 'api') || str_contains($e->getMessage(), 'key')) {
                $errorMessage .= ' Please ensure your API credentials are saved before testing.';
            }
            
            $result = ['success' => false, 'message' => $errorMessage];
        }

        return response()->json($result);
    }

    public function fetchNlgModels(Request $request)
    {
        $provider = (string) $request->input('provider', Setting::getValue('nlg.provider', 'openai'));
        $preset = config("nlg.providers.{$provider}", []);
        $type = (string) ($preset['type'] ?? 'compatible');
        $baseUrl = trim((string) $request->input('base_url', ''));
        $apiKey = trim((string) $request->input('api_key', ''));

        if ($baseUrl === '') {
            $baseUrl = (string) (Setting::getValue('nlg.base_url', '') ?: ($preset['base_url'] ?? ''));
        }

        if ($apiKey === '') {
            $apiKey = (string) Setting::getValue('nlg.api_key', '');
        }

        if ($type !== 'ollama' && $apiKey === '') {
            return response()->json([
                'success' => false,
                'models' => [],
                'message' => 'No API key available for model discovery. You can still enter a model manually.',
            ], 422);
        }

        if ($baseUrl === '') {
            return response()->json([
                'success' => false,
                'models' => [],
                'message' => 'No base URL available for model discovery. You can still enter a model manually.',
            ], 422);
        }

        $modelDiscovery = app(NlgProviderModelDiscovery::class);
        $discovery = $modelDiscovery->discover($type, $baseUrl, $apiKey);
        if (!$discovery['supported']) {
            return response()->json([
                'success' => false,
                'models' => [],
                'message' => ($discovery['message'] ?? 'This provider did not return a usable model list.') . ' You can still enter a model manually.',
            ]);
        }

        $selectedModel = trim((string) $request->input('model', ''));
        if ($selectedModel === '') {
            $selectedModel = (string) (Setting::getValue('nlg.model', '') ?: ($preset['default_model'] ?? ''));
        }

        return response()->json([
            'success' => true,
            'models' => $discovery['models'],
            'selected_model' => $selectedModel,
            'default_model' => $preset['default_model'] ?? '',
            'message' => 'Found ' . count($discovery['models']) . ' model(s).',
        ]);
    }

    /**
     * Update NLG settings (stored in DB)
     */
    private function updateNlgSettings(Request $request): void
    {
        // LLM settings → DB
        Setting::setValue('nlg.llm_enabled', $request->boolean('nlg_llm_enabled'), 'boolean', 'nlg');
        Setting::setValue('nlg.provider', $request->input('nlg_provider', 'openai'), 'string', 'nlg');
        Setting::setValue('nlg.default_tone', $request->input('nlg_default_tone', 'brief'), 'string', 'nlg');

        $availableLocales = array_keys(config('localization.locales', []));
        $aiLocales = $request->input('nlg_ai_locales', []);
        if (!is_array($aiLocales)) {
            $aiLocales = [];
        }
        $aiLocales = array_values(array_filter(array_unique($aiLocales), static function ($locale) use ($availableLocales): bool {
            return is_string($locale) && in_array($locale, $availableLocales, true);
        }));
        Setting::setValue('nlg.ai_locales', $aiLocales, 'json', 'nlg');

        $aiDays = $request->input('nlg_ai_days', (string) \App\Services\Nlg\ForecastNlgCacheService::DEFAULT_AI_DAYS);
        $aiDays = is_string($aiDays) ? strtolower(trim($aiDays)) : (string) $aiDays;
        if ($aiDays !== 'all') {
            $aiDays = (string) max(1, min(14, (int) $aiDays));
        }
        Setting::setValue('nlg.ai_days', $aiDays, 'string', 'nlg');

        // API key → encrypted in DB (only update if user entered a new value)
        if ($request->filled('nlg_api_key')) {
            Setting::setValue('nlg.api_key', $request->input('nlg_api_key'), 'encrypted', 'nlg');
        }

        // Base URL — store for custom/ollama; clear for preset providers
        $provider = $request->input('nlg_provider', 'openai');
        if ($provider === 'compatible' || $provider === 'ollama') {
            Setting::setValue('nlg.base_url', $request->input('nlg_base_url', ''), 'string', 'nlg');
        } else {
            Setting::setValue('nlg.base_url', '', 'string', 'nlg');
        }

        // Model name — allow clearing so the provider default is used again
        Setting::setValue('nlg.model', trim((string) $request->input('nlg_model', '')), 'string', 'nlg');

        // Reasoning control for reasoning-capable models: 'disabled' turns thinking off
        // (Cerebras GLM / gpt-oss), low/medium/high set the OpenAI-compatible `reasoning_effort`.
        // Empty = send nothing (plain chat models reject these params).
        $reasoningEffort = strtolower(trim((string) $request->input('nlg_reasoning_effort', '')));
        if (! in_array($reasoningEffort, ['', 'disabled', 'low', 'medium', 'high'], true)) {
            $reasoningEffort = '';
        }
        Setting::setValue('nlg.reasoning_effort', $reasoningEffort, 'string', 'nlg');

        // Thresholds → DB
        Setting::setValue('nlg.min_amount', (string) (float) $request->input('nlg_min_amount', 0.1), 'string', 'nlg');
        Setting::setValue('nlg.min_prob', (string) (int) $request->input('nlg_min_prob', 60), 'string', 'nlg');

        // Per-provider request budget overrides (blank/0 = no limit for that tier).
        $request->validate([
            'nlg_limit_rpm' => ['nullable', 'integer', 'min:0'],
            'nlg_limit_rph' => ['nullable', 'integer', 'min:0'],
            'nlg_limit_rpd' => ['nullable', 'integer', 'min:0'],
        ]);
        foreach (['rpm', 'rph', 'rpd'] as $tier) {
            $value = $request->input("nlg_limit_{$tier}");
            $value = ($value === null || $value === '') ? '' : (string) max(0, (int) $value);
            Setting::setValue("nlg.limits.{$tier}", $value, 'string', 'nlg');
        }
    }

    /**
     * Update footer settings with special handling for custom links
     */
    private function updateFooterSettings(Request $request): void
    {
        // Handle boolean settings
        Setting::setValue('footer.enabled', $request->boolean('footer_enabled') ? '1' : '0', 'boolean', 'footer');
        Setting::setValue('footer.show_station_info', $request->boolean('footer_show_station_info') ? '1' : '0', 'boolean', 'footer');
        Setting::setValue('footer.show_coordinates', $request->boolean('footer_show_coordinates') ? '1' : '0', 'boolean', 'footer');
        Setting::setValue('footer.show_social', $request->boolean('footer_show_social') ? '1' : '0', 'boolean', 'footer');
        Setting::setValue('footer.show_quick_links', $request->boolean('footer_show_quick_links') ? '1' : '0', 'boolean', 'footer');
        Setting::setValue('footer.show_legal', $request->boolean('footer_show_legal') ? '1' : '0', 'boolean', 'footer');
        Setting::setValue('footer.show_seo_text', $request->boolean('footer_show_seo_text') ? '1' : '0', 'boolean', 'footer');

        // Handle custom links (JSON)
        $customLinksJson = $request->input('footer_custom_links', '[]');
        
        // Decode and validate JSON
        if (empty($customLinksJson) || $customLinksJson === '[]') {
            $customLinks = [];
        } else {
            $customLinks = json_decode($customLinksJson, true);
            
            // Validate and filter
            if (!is_array($customLinks)) {
                $customLinks = [];
            } else {
                // Filter out empty or invalid links
                $customLinks = array_filter($customLinks, function($link) {
                    return is_array($link) 
                        && isset($link['label']) 
                        && isset($link['url'])
                        && trim($link['label']) !== '' 
                        && trim($link['url']) !== '';
                });
                $customLinks = array_values($customLinks); // Re-index array
            }
        }
        
        Setting::setValue('footer.custom_links', $customLinks, 'json', 'footer');
    }

    private function getHistorySyncStatus(): array
    {
        $readingDates = $this->getReadingDates();
        $summaryDates = $this->getSummaryDates();
        $missing = $readingDates->diff($summaryDates)->values();
        $missingRecent = $missing->sort()->reverse()->take(10)->values();

        return [
            'reading_start' => $readingDates->first(),
            'reading_end' => $readingDates->last(),
            'reading_days' => $readingDates->count(),
            'summary_start' => $summaryDates->first(),
            'summary_end' => $summaryDates->last(),
            'summary_days' => $summaryDates->count(),
            'missing_days' => $missing->count(),
            'missing_recent' => $missingRecent,
        ];
    }

    private function getWuSyncConfig(): array
    {
        return [
            'enabled' => (bool) Setting::getValue('history.wu_sync_enabled', false),
            'days' => (int) Setting::getValue('history.wu_sync_days', 7),
            'time' => Setting::getValue('history.wu_sync_time', '02:10'),
            'skip_existing' => (bool) Setting::getValue('history.wu_sync_skip_existing', true),
        ];
    }

    private function getSchedulerStatus(): array
    {
        $lastRun = Cache::get('scheduler:last_run');
        $lastRunAt = null;
        if ($lastRun) {
            try {
                $lastRunAt = $lastRun instanceof \Carbon\Carbon
                    ? $lastRun
                    : \Carbon\Carbon::parse($lastRun);
            } catch (\Exception $e) {
                $lastRunAt = null;
            }
        }

        $status = 'never';
        $minutesAgo = null;
        if ($lastRunAt) {
            $minutesAgo = $lastRunAt->diffInMinutes(now());
            $status = $minutesAgo <= 5 ? 'running' : 'stale';
        }

        $cronLine = '* * * * * cd ' . base_path() . ' && php artisan schedule:run >> /dev/null 2>&1';

        return [
            'status' => $status,
            'last_run' => $lastRunAt,
            'minutes_ago' => $minutesAgo,
            'cron_line' => $cronLine,
        ];
    }

    private function getSchedulerTasks(): array
    {
        $wuSyncTime = Setting::getValue('history.wu_sync_time', '02:10');
        if (!is_string($wuSyncTime) || !preg_match('/^\d{2}:\d{2}$/', $wuSyncTime)) {
            $wuSyncTime = '02:10';
        }

        $availableLocales = config('localization.locales', []);
        $configuredAiLocales = Setting::getValue('nlg.ai_locales', null);
        if (!is_array($configuredAiLocales)) {
            $configuredAiLocales = [Setting::defaultLanguage()];
        }
        $configuredAiLocales = array_values(array_filter(array_unique($configuredAiLocales), static function ($locale) use ($availableLocales): bool {
            return is_string($locale) && isset($availableLocales[$locale]);
        }));
        $configuredAiLocaleLabels = array_map(static function (string $locale) use ($availableLocales): string {
            return $availableLocales[$locale]['label'] ?? $locale;
        }, $configuredAiLocales);
        $configuredAiLocaleSummary = $configuredAiLocaleLabels !== [] ? implode(', ', $configuredAiLocaleLabels) : 'none';
        $nlgCacheService = app(\App\Services\Nlg\ForecastNlgCacheService::class);
        $configuredAiDaysLimit = $nlgCacheService->resolveAiDaysLimit(
            Setting::getValue('nlg.ai_days', \App\Services\Nlg\ForecastNlgCacheService::DEFAULT_AI_DAYS)
        );
        $configuredAiDaysSummary = $configuredAiDaysLimit === null
            ? 'all forecast days'
            : ($configuredAiDaysLimit === 1 ? 'the first forecast day' : "the first {$configuredAiDaysLimit} forecast days");

        $forecastSource = Setting::getValue('forecast.default_source', 'fct_yrno_block.php');
        $forecastMeta = [
            'fct_aemet_block.php' => ['name' => 'Forecast (AEMET)', 'group' => 'aemet', 'enabled' => !empty(Setting::getValue('aemet.api_key', ''))],
            'fct_dwd_block.php' => ['name' => 'Forecast (DWD)', 'group' => 'dwd', 'enabled' => (bool) Setting::getValue('opendata.dwd.enabled', false)],
            'fct_yrno_block.php' => ['name' => 'Forecast (Yr.no)', 'group' => 'yrno', 'enabled' => true],
            'fct_darksky_block.php' => ['name' => 'Forecast (OpenWeatherMap)', 'group' => 'openweathermap', 'enabled' => !empty(Setting::getValue('openweathermap.api_key', ''))],
            'fct_wu_block.php' => ['name' => 'Forecast (Weather Underground)', 'group' => 'wunderground', 'enabled' => !empty(Setting::getValue('wunderground.api_key', ''))],
            'fct_ec_block.php' => ['name' => 'Forecast (Environment Canada)', 'group' => 'environment_canada', 'enabled' => (bool) Setting::getValue('environment_canada.enabled', false)],
            'fct_wxsim_block.php' => ['name' => 'Forecast (WXSIM)', 'group' => 'wxsim', 'enabled' => (bool) Setting::getValue('wxsim.enabled', false)],
            'fct_tempest_block.php' => ['name' => 'Forecast (WeatherFlow Tempest)', 'group' => 'weatherflow', 'enabled' => (bool) Setting::getValue('weatherflow.enabled', false)],
        ][$forecastSource] ?? ['name' => 'Forecast', 'group' => 'forecast', 'enabled' => true];

        return [
            [
                'name' => 'Live Weather Fetch',
                'command' => 'weather:fetch --save',
                'schedule' => 'Every minute',
                'description' => 'Store live readings and update daily summaries.',
                'enabled' => true,
                'log' => storage_path('logs/weather-fetch.log'),
                'settings_group' => 'livedata',
                'last_run' => $this->getLastRunTimestamp(null, storage_path('logs/weather-fetch.log')),
            ],
            [
                'name' => $forecastMeta['name'],
                'command' => 'weather:poll-external --source=forecast',
                'schedule' => 'Every 30 minutes',
                'description' => 'Cache forecast data for the dashboard.',
                'enabled' => $forecastMeta['enabled'],
                'log' => storage_path('logs/poll-forecast.log'),
                'settings_group' => $forecastMeta['group'],
                'last_run' => $this->getLastRunTimestamp('poll_timestamp_forecast', storage_path('logs/poll-forecast.log')),
            ],
            [
                'name' => 'Radar Frames (RainViewer)',
                'command' => 'weather:poll-external --source=rainviewer',
                'schedule' => 'Every 10 minutes',
                'description' => 'Cache RainViewer radar frame metadata for the radar page and widgets.',
                'enabled' => (bool) Setting::getValue('radar.enabled', true) && (
                    (Setting::getValue('radar.provider') === 'rainviewer' && Setting::getValue('radar.rainviewer_mode', 'api') === 'api') ||
                    (Setting::getValue('radar.widget_provider') === 'rainviewer' && Setting::getValue('radar.widget_rainviewer_mode', 'api') === 'api')
                ),
                'log' => storage_path('logs/poll-rainviewer.log'),
                'settings_group' => 'radar',
                'last_run' => $this->getLastRunTimestamp('poll_timestamp_rainviewer', storage_path('logs/poll-rainviewer.log')),
            ],
            [
                'name' => 'NLG Forecast Generation',
                'command' => 'weather:generate-nlg',
                'schedule' => 'Every 30 minutes (at :02 and :32)',
                'description' => 'Generate deterministic forecast text for all configured languages.',
                'enabled' => true,
                'log' => storage_path('logs/generate-nlg.log'),
                'settings_group' => 'nlg',
                'last_run' => $this->getLastRunTimestamp(null, storage_path('logs/generate-nlg.log')),
            ],
            [
                'name' => 'NLG LLM Rephrasing',
                'command' => 'weather:rephrase-nlg',
                'schedule' => 'Every 30 minutes (at :05 and :35)',
                'description' => $configuredAiLocales !== []
                    ? 'Polish cached forecast text with AI for selected languages: ' . $configuredAiLocaleSummary . '; window: ' . $configuredAiDaysSummary . '.'
                    : 'No AI enhancement languages selected; the scheduled rephrase pass will skip.',
                'enabled' => (bool) Setting::getValue('nlg.llm_enabled', false) && $configuredAiLocales !== [],
                'log' => storage_path('logs/rephrase-nlg.log'),
                'settings_group' => 'nlg',
                'last_run' => $this->getLastRunTimestamp(null, storage_path('logs/rephrase-nlg.log')),
            ],
            [
                'name' => 'Weather Alerts',
                'command' => 'weather:poll-external --source=alerts',
                'schedule' => 'Every 15 minutes',
                'description' => 'Poll alert sources for active warnings.',
                'enabled' => (bool) Setting::getValue('alerts.enabled', false),
                'log' => storage_path('logs/poll-alerts.log'),
                'settings_group' => 'alerts',
                'last_run' => $this->getLastRunTimestamp('poll_timestamp_alerts', storage_path('logs/poll-alerts.log')),
            ],
            [
                'name' => 'Air Quality',
                'command' => 'weather:poll-external --source=airquality',
                'schedule' => 'Every 30 minutes',
                'description' => 'Refresh WAQI/Luftdaten data.',
                'enabled' => (bool) Setting::getValue('waqi.enabled', false)
                    || (bool) Setting::getValue('luftdaten.enabled', false),
                'log' => storage_path('logs/poll-airquality.log'),
                'settings_group' => 'airquality',
                'last_run' => $this->getLastRunTimestamp('poll_timestamp_airquality', storage_path('logs/poll-airquality.log')),
            ],
            [
                'name' => 'Noise (Luftdaten)',
                'command' => 'weather:poll-external --source=airquality_noise',
                'schedule' => 'Every 5 min (interval in Air Quality settings)',
                'description' => 'Refresh noise sensor data (fresher than air quality).',
                'enabled' => (bool) Setting::getValue('luftdaten_noise.enabled', false),
                'log' => storage_path('logs/poll-airquality-noise.log'),
                'settings_group' => 'airquality',
                'last_run' => $this->getLastRunTimestamp('poll_timestamp_airquality_noise', storage_path('logs/poll-airquality-noise.log')),
            ],
            [
                'name' => 'Pollen Forecast',
                'command' => 'weather:poll-external --source=pollen',
                'schedule' => 'Hourly',
                'description' => 'Refresh pollen forecast and risk categories.',
                'enabled' => (bool) Setting::getValue('pollen.openmeteo_enabled', true)
                    || (bool) Setting::getValue('pollen.google_enabled', false)
                    || (bool) Setting::getValue('pollen.ambee_enabled', false),
                'log' => storage_path('logs/poll-pollen.log'),
                'settings_group' => 'pollen',
                'last_run' => $this->getLastRunTimestamp('poll_timestamp_pollen', storage_path('logs/poll-pollen.log')),
            ],
            [
                'name' => 'Tide Data',
                'command' => 'weather:poll-external --source=tide',
                'schedule' => 'Hourly',
                'description' => 'Fetch tidal measurements and 72-hour forecast for the Water page.',
                'enabled' => (bool) Setting::getValue('tide.enabled', false),
                'log' => storage_path('logs/poll-tide.log'),
                'settings_group' => 'tide',
                'last_run' => $this->getLastRunTimestamp('poll_timestamp_tide', storage_path('logs/poll-tide.log')),
            ],
            [
                'name' => 'Wave & Sea Temperature',
                'command' => 'weather:poll-external --source=waves',
                'schedule' => 'Hourly',
                'description' => 'Fetch wave height, direction, period and sea surface temperature from Open-Meteo Marine.',
                'enabled' => true,
                'log' => storage_path('logs/poll-waves.log'),
                'settings_group' => null,
                'last_run' => $this->getLastRunTimestamp('poll_timestamp_waves', storage_path('logs/poll-waves.log')),
            ],
            [
                'name' => 'River Levels',
                'command' => 'weather:poll-external --source=rivers',
                'schedule' => 'Every 15 minutes',
                'description' => 'Fetch real-time inland river gauge readings for configured Rijkswaterstaat stations.',
                'enabled' => (bool) Setting::getValue('rivers.enabled', false),
                'log' => storage_path('logs/poll-rivers.log'),
                'settings_group' => 'rivers',
                'last_run' => $this->getLastRunTimestamp('poll_timestamp_rivers', storage_path('logs/poll-rivers.log')),
            ],
            [
                'name' => 'Aviation METAR',
                'command' => 'weather:poll-external --source=metar',
                'schedule' => 'Every 30 minutes',
                'description' => 'Fetch METAR aviation data.',
                'enabled' => (bool) Setting::getValue('metar.enabled', false),
                'log' => storage_path('logs/poll-metar.log'),
                'settings_group' => 'aviation',
                'last_run' => $this->getLastRunTimestamp('poll_timestamp_metar', storage_path('logs/poll-metar.log')),
            ],
            [
                'name' => 'Earthquakes',
                'command' => 'weather:poll-external --source=earthquake',
                'schedule' => 'Every 15 minutes',
                'description' => 'Update earthquake feed.',
                'enabled' => (bool) Setting::getValue('earthquakes.enabled', false),
                'log' => storage_path('logs/poll-earthquake.log'),
                'settings_group' => 'earthquakes',
                'last_run' => $this->getLastRunTimestamp('poll_timestamp_earthquake', storage_path('logs/poll-earthquake.log')),
            ],
            [
                'name' => 'Aurora / Kp Index',
                'command' => 'weather:poll-external --source=aurora',
                'schedule' => 'Every 30 minutes',
                'description' => 'Refresh geomagnetic activity.',
                'enabled' => true,
                'log' => storage_path('logs/poll-aurora.log'),
                'settings_group' => 'astronomy',
                'last_run' => $this->getLastRunTimestamp('poll_timestamp_aurora', storage_path('logs/poll-aurora.log')),
            ],
            [
                'name' => 'ISS Passes',
                'command' => 'weather:poll-external --source=iss',
                'schedule' => 'Hourly',
                'description' => 'Update ISS pass predictions.',
                'enabled' => true,
                'log' => storage_path('logs/poll-iss.log'),
                'settings_group' => 'iss',
                'last_run' => $this->getLastRunTimestamp('poll_timestamp_iss', storage_path('logs/poll-iss.log')),
            ],
            [
                'name' => 'Astronomy (Sun/Moon)',
                'command' => 'weather:poll-external --source=astronomy',
                'schedule' => 'Hourly',
                'description' => 'Refresh sun/moon calculations.',
                'enabled' => true,
                'log' => storage_path('logs/poll-astronomy.log'),
                'settings_group' => 'astronomy',
                'last_run' => $this->getLastRunTimestamp('poll_timestamp_astronomy', storage_path('logs/poll-astronomy.log')),
            ],
            [
                'name' => 'KNMI Radar Nowcast',
                'command' => 'weather:poll-external --source=knmi_nowcast',
                'schedule' => 'Every 10 minutes',
                'description' => 'Pre-cache 2-hour precipitation forecast.',
                'enabled' => (bool) Setting::getValue('radar.nowcast_enabled', false),
                'log' => storage_path('logs/poll-knmi-nowcast.log'),
                'settings_group' => 'radar',
                'last_run' => $this->getLastRunTimestamp('poll_timestamp_knmi_nowcast', storage_path('logs/poll-knmi-nowcast.log')),
            ],
            [
                'name' => 'Solar Forecast',
                'command' => 'weather:poll-external --source=solar_forecast',
                'schedule' => 'Every 30 minutes',
                'description' => 'Pre-cache solar radiation forecast.',
                'enabled' => (bool) Setting::getValue('solar_forecast.enabled', false),
                'log' => storage_path('logs/poll-solar-forecast.log'),
                'settings_group' => 'solar_forecast',
                'last_run' => $this->getLastRunTimestamp('poll_timestamp_solar_forecast', storage_path('logs/poll-solar-forecast.log')),
            ],
            [
                'name' => 'KNMI WMS Layers',
                'command' => 'weather:poll-external --source=knmi_wms',
                'schedule' => 'Hourly',
                'description' => 'Pre-cache satellite layer metadata.',
                'enabled' => (bool) Setting::getValue('satellite.wms_enabled', false),
                'log' => storage_path('logs/poll-knmi-wms.log'),
                'settings_group' => 'satellite',
                'last_run' => $this->getLastRunTimestamp('poll_timestamp_knmi_wms', storage_path('logs/poll-knmi-wms.log')),
            ],
            [
                'name' => 'Daily Summary',
                'command' => 'weather:summarize',
                'schedule' => 'Daily at 00:05',
                'description' => 'Generate daily summary from readings.',
                'enabled' => (bool) Setting::getValue('history.auto_generate', true),
                'log' => storage_path('logs/weather-summary.log'),
                'settings_group' => 'history',
                'last_run' => $this->getLastRunTimestamp(null, storage_path('logs/weather-summary.log')),
            ],
            [
                'name' => 'WU History Sync',
                'command' => 'weather:sync-wu',
                'schedule' => "Daily at {$wuSyncTime}",
                'description' => 'Sync recent Weather Underground history.',
                'enabled' => (bool) Setting::getValue('history.wu_sync_enabled', false),
                'log' => storage_path('logs/wu-history-sync.log'),
                'settings_group' => 'history',
                'last_run' => $this->getLastRunTimestamp(null, storage_path('logs/wu-history-sync.log')),
            ],
            [
                'name' => 'Visitor Log Rollup',
                'command' => 'visitorlog:rollup',
                'schedule' => 'Daily at 00:15',
                'description' => 'Aggregate visitor logs and purge raw data.',
                'enabled' => (bool) config('visitorlog.enabled', true),
                'log' => storage_path('logs/visitor-rollup.log'),
                'settings_group' => 'visitors',
                'last_run' => $this->getLastRunTimestamp(null, storage_path('logs/visitor-rollup.log')),
            ],
            [
                'name' => 'GeoIP Update',
                'command' => 'geoip:update',
                'schedule' => 'Weekly (Mon 02:30)',
                'description' => 'Update GeoLite2 Country database.',
                'enabled' => true,
                'log' => storage_path('logs/geoip-update.log'),
                'settings_group' => 'advanced',
                'last_run' => $this->getLastRunTimestamp(null, storage_path('logs/geoip-update.log')),
            ],
            [
                'name' => 'Cache Cleanup',
                'command' => 'cache:clean-expired',
                'schedule' => 'Daily at 03:00',
                'description' => 'Cleanup expired cache entries (works for database, file, and other cache drivers).',
                'enabled' => true,
                'log' => storage_path('logs/cache-cleanup.log'),
                'settings_group' => 'advanced',
                'last_run' => $this->getLastRunTimestamp(null, storage_path('logs/cache-cleanup.log')),
            ],
            [
                'name' => 'Radar Tile Cleanup',
                'command' => 'radar:clean-tiles',
                'schedule' => 'Hourly',
                'description' => 'Cleanup cached radar tiles older than 2 hours to free up disk space.',
                'enabled' => true,
                'log' => storage_path('logs/radar-cleanup.log'),
                'settings_group' => 'advanced',
                'last_run' => $this->getLastRunTimestamp(null, storage_path('logs/radar-cleanup.log')),
            ],
            [
                'name' => 'Community Telemetry',
                'command' => 'telemetry:send',
                'schedule' => 'Daily at ' . $this->getTelemetryScheduleTime(),
                'description' => 'Send anonymized station data to the community map.',
                'enabled' => (bool) Setting::getValue('telemetry.enabled', false),
                'log' => storage_path('logs/telemetry-send.log'),
                'settings_group' => 'telemetry',
                'last_run' => $this->getLastRunTimestamp(null, storage_path('logs/telemetry-send.log')),
            ],
        ];
    }

    private function getLastRunTimestamp(?string $cacheKey, ?string $logPath): ?\Carbon\Carbon
    {
        if ($cacheKey) {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                try {
                    return $cached instanceof \Carbon\Carbon
                        ? $cached
                        : \Carbon\Carbon::parse($cached);
                } catch (\Exception $e) {
                    // Fall through to log file.
                }
            }
        }

        if ($logPath && file_exists($logPath)) {
            try {
                return \Carbon\Carbon::createFromTimestamp(filemtime($logPath));
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }

    private function getTelemetryScheduleTime(): string
    {
        $seed = crc32(config('app.url', 'default'));
        $hour = ($seed & 0x7FFFFFFF) % 24;
        $minute = (($seed >> 8) & 0x7FFFFFFF) % 60;

        return sprintf('%02d:%02d', $hour, $minute);
    }

    private function getHistoryMissingDates()
    {
        $readingDates = $this->getReadingDates();
        $summaryDates = $this->getSummaryDates();

        return $readingDates->diff($summaryDates)->values();
    }

    private function getReadingDates()
    {
        return WeatherReading::selectRaw('DATE(recorded_at) as date')
            ->distinct()
            ->orderBy('date')
            ->pluck('date')
            ->filter()
            ->map(static fn ($date) => is_string($date) ? $date : (string) $date)
            ->values();
    }

    private function getSummaryDates()
    {
        return DailySummary::orderBy('date')
            ->pluck('date')
            ->filter()
            ->map(static fn ($date) => $date instanceof \Carbon\Carbon ? $date->toDateString() : (string) $date)
            ->values();
    }

    private function generateSummaryForDate(string $date): bool
    {
        $readings = WeatherReading::whereDate('recorded_at', $date)->get();

        if ($readings->isEmpty()) {
            return false;
        }

        $tempHigh = $readings->max('temperature');
        $tempLow = $readings->min('temperature');
        $windMax = $readings->max('wind_gust');

        $tempHighTime = optional($readings->firstWhere('temperature', $tempHigh))->recorded_at?->format('H:i:s');
        $tempLowTime = optional($readings->firstWhere('temperature', $tempLow))->recorded_at?->format('H:i:s');
        $windMaxTime = optional($readings->firstWhere('wind_gust', $windMax))->recorded_at?->format('H:i:s');
        $windDirections = $readings
            ->pluck('wind_direction')
            ->filter(static fn ($direction) => $direction !== null);
        $windDominantDirection = null;
        if ($windDirections->isNotEmpty()) {
            $sinSum = 0.0;
            $cosSum = 0.0;
            foreach ($windDirections as $direction) {
                $radians = deg2rad((float) $direction);
                $sinSum += sin($radians);
                $cosSum += cos($radians);
            }
            if (abs($sinSum) > 0.000001 || abs($cosSum) > 0.000001) {
                $degrees = rad2deg(atan2($sinSum, $cosSum));
                if ($degrees < 0) {
                    $degrees += 360;
                }
                $windDominantDirection = ((int) round($degrees)) % 360;
            }
        }

        DailySummary::updateOrCreate(
            ['date' => $date],
            [
                'temp_high' => $tempHigh,
                'temp_high_time' => $tempHighTime,
                'temp_low' => $tempLow,
                'temp_low_time' => $tempLowTime,
                'temp_avg' => round((float) $readings->avg('temperature'), 1),
                'humidity_high' => (int) $readings->max('humidity'),
                'humidity_low' => (int) $readings->min('humidity'),
                'humidity_avg' => (int) $readings->avg('humidity'),
                'pressure_high' => $readings->max('pressure_rel'),
                'pressure_low' => $readings->min('pressure_rel'),
                'pressure_avg' => round((float) $readings->avg('pressure_rel'), 1),
                'wind_max' => $windMax,
                'wind_max_time' => $windMaxTime,
                'wind_avg' => round((float) $readings->avg('wind_speed'), 1),
                'wind_dominant_direction' => $windDominantDirection,
                'rain_total' => $readings->max('rain_daily'),
                'rain_rate_max' => $readings->max('rain_rate'),
                'uv_max' => $readings->max('uv_index'),
                'solar_max' => $readings->max('solar_radiation'),
                'solar_hours' => app(SunshineHoursCalculator::class)->resolveFromReadings($readings),
            ]
        );

        return true;
    }

    /**
     * Show Open Data Sources management page
     */
    public function opendata()
    {
        $providers = OpenDataProviderRegistry::getAll();
        $isInNetherlands = Setting::isStationInNetherlands();

        return view('admin.settings.opendata', [
            'providers' => $providers,
            'isInNetherlands' => $isInNetherlands,
        ]);
    }

    /**
     * Update Open Data provider settings
     */
    public function updateOpendata(Request $request)
    {
        $providers = OpenDataProviderRegistry::getAll();

        foreach ($providers as $provider) {
            $key = $provider->getSettingsKey();
            $enabled = $request->input("opendata_{$key}_enabled", false);
            
            Setting::setValue(
                "opendata.{$key}.enabled",
                $enabled ? '1' : '0',
                'boolean',
                'opendata'
            );
        }

        $this->clearSettingsCache();

        return redirect()->route('admin.settings.opendata')
            ->with('success', __('Open data settings updated successfully.'));
    }

    /**
     * Update radar nowcast settings
     */
    private function updateRadarNowcastSettings(Request $request): void
    {
        $futureFramesProvider = app(RadarFutureFramesService::class)
            ->normalizeProviderKey($request->input('radar_widget_future_frames_provider', RadarFutureFramesService::PROVIDER_AUTO));

        Setting::setValue('radar.nowcast_enabled', $request->boolean('radar_nowcast_enabled'), 'boolean', 'radar');
        Setting::setValue('radar.nowcast_animation_speed', $request->input('radar_nowcast_animation_speed', 0.5), 'float', 'radar');
        Setting::setValue('radar.nowcast_autoplay', $request->boolean('radar_nowcast_autoplay'), 'boolean', 'radar');
        Setting::setValue('radar.widget_future_frames_enabled', $request->boolean('radar_widget_future_frames_enabled'), 'boolean', 'radar');
        Setting::setValue('radar.widget_future_frames_provider', $futureFramesProvider, 'string', 'radar');
    }

    /**
     * Update satellite settings (including WMS layers and solar nowcast)
     */
    private function updateSatelliteSettings(Request $request): void
    {
        // Handle WMS layers settings
        Setting::setValue('satellite.wms_enabled', $request->has('satellite_wms_enabled'), 'boolean', 'satellite');
        Setting::setValue('satellite.wms_default_layer', $request->input('satellite_wms_default_layer', 'lwe_precipitation_rate'), 'string', 'satellite');
        Setting::setValue('satellite.wms_default_style', $request->input('satellite_wms_default_style', 'precip-rainbow/nearest'), 'string', 'satellite');
        Setting::setValue('satellite.wms_default_opacity', $request->input('satellite_wms_default_opacity', 70), 'integer', 'satellite');
        Setting::setValue('satellite.wms_animation_speed', $request->input('satellite_wms_animation_speed', 0.5), 'float', 'satellite');
        Setting::setValue('satellite.wms_auto_refresh', $request->input('satellite_wms_auto_refresh', 15), 'integer', 'satellite');
    }

    /**
     * Update solar forecast settings (provider, hours, Solcast API key)
     */
    private function updateSolarForecastSettings(Request $request): void
    {
        Setting::setValue('solar_forecast.enabled', $request->has('solar_forecast_enabled'), 'boolean', 'solar_forecast');
        Setting::setValue('solar_forecast.provider', $request->input('solar_forecast_provider', 'open_meteo'), 'string', 'solar_forecast');
        Setting::setValue('solar_forecast.forecast_hours', (int) $request->input('solar_forecast_forecast_hours', 48), 'integer', 'solar_forecast');
        Setting::setValue('solar_forecast.update_interval', (int) $request->input('solar_forecast_update_interval', 30), 'integer', 'solar_forecast');
        if ($request->filled('solar_forecast_solcast_api_key')) {
            Setting::setValue('solar_forecast.solcast_api_key', $request->input('solar_forecast_solcast_api_key'), 'string', 'solar_forecast');
        }
    }

    /**
     * Update tide settings
     */
    private function updateTideSettings(Request $request): void
    {
        Setting::setValue('tide.enabled', $request->input('tide_enabled') === '1', 'boolean', 'tide');
        Setting::setValue('tide.source',  trim($request->input('tide_source', 'rws')),           'string',  'tide');

        // Only update station fields when the station section was actually rendered in the form
        // (i.e. the source is station-based). Skipping this prevents non-station sources like
        // Marea or Open-Meteo from overwriting the saved NOAA / RWS station code with the default.
        if ($request->has('tide_station_code')) {
            $newCode   = trim($request->input('tide_station_code'));
            $newSource = trim($request->input('tide_source', 'rws'));

            // Save both the generic key (used by TideController) and a per-source key so that
            // switching away and back to a station-based source retains the correct station.
            Setting::setValue('tide.station_code',              $newCode, 'string', 'tide');
            Setting::setValue("tide.{$newSource}_station_code", $newCode, 'string', 'tide');

            // Auto-populate station name from the driver's built-in list when available
            $driver   = \App\Services\Tide\TideServiceFactory::make($newSource);
            $stations = $driver->getStations();
            $autoName = $stations[$newCode]['name'] ?? null;

            Setting::setValue(
                'tide.station_name',
                $autoName ?? trim($request->input('tide_station_name', $newCode)),
                'string',
                'tide'
            );
        }

        Setting::setValue('tide.marea_api_key',       trim($request->input('tide_marea_api_key', '')),       'string', 'tide');
        Setting::setValue('tide.copernicus_username',  trim($request->input('tide_copernicus_username', '')), 'string', 'tide');
        Setting::setValue('tide.copernicus_password',  trim($request->input('tide_copernicus_password', '')), 'string', 'tide');
    }

    /**
     * Update waves & sea temperature settings
     */
    private function updateWavesSettings(Request $request): void
    {
        Setting::setValue('waves.enabled', $request->input('waves_enabled') === '1', 'boolean', 'waves');

        // Blank means "use the station", so empty is stored rather than rejected.
        foreach (['latitude', 'longitude'] as $part) {
            $value = trim((string) $request->input("marine_{$part}", ''));
            Setting::setValue("marine.{$part}", $value, 'string', 'marine');
        }
    }

    /**
     * Update river levels settings.
     *
     * The form posts per-provider data under providers[{id}][...].
     * Each active provider gets its own namespaced settings keys:
     *   rivers.{provider}.enabled
     *   rivers.{provider}.stations
     *   rivers.{provider}.custom_stations
     */
    private function updateRiversSettings(Request $request): void
    {
        $allProviderData = $request->input('providers', []);

        foreach (\App\Services\River\RiverProviderRegistry::active() as $providerId => $providerMeta) {
            $data = $allProviderData[$providerId] ?? [];

            // Enabled flag
            $enabled = ($data['enabled'] ?? '0') === '1';
            Setting::setValue("rivers.{$providerId}.enabled", $enabled, 'boolean', 'rivers');

            // Selected stations — sent as a JSON string from the single hidden input
            $stationsJson = $data['stations_json'] ?? '';
            $stations     = $stationsJson !== '' ? (json_decode($stationsJson, true) ?? []) : [];
            // Guard against corrupted numeric keys
            $stations = array_values(array_filter(
                (array) $stations,
                fn ($v) => is_string($v) && !is_numeric($v) && $v !== ''
            ));
            Setting::setValue("rivers.{$providerId}.stations", json_encode($stations), 'string', 'rivers');

            // Custom stations — sent as a JSON string from the single hidden input
            $customJson = $data['custom_json'] ?? '[]';
            $rawCustom  = json_decode($customJson, true) ?? [];
            $custom     = [];
            foreach ($rawCustom as $entry) {
                $code = strtolower(trim($entry['code'] ?? ''));
                if (!$code) {
                    continue;
                }
                $custom[] = [
                    'code'  => $code,
                    'name'  => trim($entry['name'] ?? '') ?: $code,
                    'river' => trim($entry['river'] ?? '') ?: '—',
                ];
            }
            Setting::setValue("rivers.{$providerId}.custom_stations", json_encode($custom), 'string', 'rivers');
        }
    }

    /**
     * Update pollen settings (enables/toggles and API keys for all three sources)
     */
    private function updatePollenSettings(Request $request): void
    {
        Setting::setValue('pollen.openmeteo_enabled', $request->input('pollen_openmeteo_enabled') === '1', 'boolean', 'pollen');
        Setting::setValue('pollen.google_enabled', $request->input('pollen_google_enabled') === '1', 'boolean', 'pollen');
        Setting::setValue('pollen.ambee_enabled', $request->input('pollen_ambee_enabled') === '1', 'boolean', 'pollen');
        Setting::setValue('pollen.cache_minutes', (int) $request->input('pollen_cache_minutes', 60), 'integer', 'pollen');

        if ($request->filled('pollen_google_api_key')) {
            Setting::setValue('pollen.google_api_key', $request->input('pollen_google_api_key'), 'encrypted', 'pollen');
        }
        if ($request->filled('pollen_ambee_api_key')) {
            Setting::setValue('pollen.ambee_api_key', $request->input('pollen_ambee_api_key'), 'encrypted', 'pollen');
        }
    }

    /**
     * Update WeatherLink settings (type-specific configuration)
     */
    private function updateWeatherLinkSettings(Request $request): void
    {
        $type = $request->input('weatherlink_type', 'v2');
        
        // Save the type
        Setting::setValue('weatherlink.type', $type, 'select', 'weatherlink');
        
        // Handle enabled toggle
        Setting::setValue('weatherlink.enabled', $request->has('weatherlink_enabled'), 'boolean', 'weatherlink');
        
        // Type-specific settings
        if ($type === 'v1') {
            // v1 API settings
            if ($request->has('weatherlink_device_id')) {
                Setting::setValue('weatherlink.device_id', $request->input('weatherlink_device_id'), 'string', 'weatherlink');
            }
            // Using short field names to avoid browser autofill issues
            $v1Pass = $request->input('wl_v1_pass', '');
            if (!empty($v1Pass)) {
                Setting::setValue('weatherlink.password', $v1Pass, 'encrypted', 'weatherlink');
                \Log::info('WeatherLink v1 password saved', ['length' => strlen($v1Pass)]);
            }
            $v1Key = $request->input('wl_v1_key', '');
            if (!empty($v1Key)) {
                Setting::setValue('weatherlink.api_key', $v1Key, 'encrypted', 'weatherlink');
                \Log::info('WeatherLink v1 API key saved', ['length' => strlen($v1Key)]);
            }
        } elseif ($type === 'v2') {
            // v2 API settings - using short field names to avoid browser autofill issues
            $v2Key = $request->input('wl_v2_key', '');
            if (!empty($v2Key)) {
                Setting::setValue('weatherlink.api_key', $v2Key, 'encrypted', 'weatherlink');
                \Log::info('WeatherLink v2 API key saved', ['length' => strlen($v2Key)]);
            }
            // Always check for API secret - it's required for v2 API
            // Using field name 'wl_v2_secret' to avoid browser autofill issues
            $apiSecret = $request->input('wl_v2_secret', '');
            \Log::debug('WeatherLink API secret check', [
                'field_name' => 'wl_v2_secret',
                'has_field' => $request->has('wl_v2_secret'),
                'value_length' => strlen($apiSecret ?? ''),
                'is_empty' => empty($apiSecret),
            ]);
            
            if (!empty($apiSecret)) {
                Setting::setValue('weatherlink.api_secret', $apiSecret, 'encrypted', 'weatherlink');
                \Log::info('WeatherLink API secret saved successfully', ['length' => strlen($apiSecret)]);
            } else {
                // Check if there's an existing value - if not, log a warning
                $existingSecret = Setting::getValue('weatherlink.api_secret', '');
                if (empty($existingSecret)) {
                    \Log::warning('WeatherLink API secret field empty and no existing value. User needs to enter the secret.');
                }
            }
            if ($request->has('weatherlink_station_id')) {
                Setting::setValue('weatherlink.station_id', $request->input('weatherlink_station_id'), 'string', 'weatherlink');
            }
            Setting::setValue('weatherlink.demo_mode', $request->has('weatherlink_demo_mode'), 'boolean', 'weatherlink');
        } elseif ($type === 'airlink_local') {
            // AirLink Local settings
            if ($request->has('weatherlink_airlink_ip')) {
                Setting::setValue('weatherlink.airlink_ip', $request->input('weatherlink_airlink_ip'), 'string', 'weatherlink');
            }
            if ($request->has('weatherlink_airlink_port')) {
                Setting::setValue('weatherlink.airlink_port', $request->input('weatherlink_airlink_port'), 'integer', 'weatherlink');
            }
        } elseif ($type === 'wll_local') {
            // WeatherLink Live Local settings
            if ($request->has('weatherlink_wll_ip')) {
                Setting::setValue('weatherlink.wll_ip', $request->input('weatherlink_wll_ip'), 'string', 'weatherlink');
            }
            if ($request->has('weatherlink_wll_port')) {
                Setting::setValue('weatherlink.wll_port', $request->input('weatherlink_wll_port'), 'integer', 'weatherlink');
            }
            Setting::setValue('weatherlink.wll_udp_enabled', $request->has('weatherlink_wll_udp_enabled'), 'boolean', 'weatherlink');
            if ($request->has('weatherlink_wll_udp_port')) {
                Setting::setValue('weatherlink.wll_udp_port', $request->input('weatherlink_wll_udp_port'), 'integer', 'weatherlink');
            }
            if ($request->has('weatherlink_wll_udp_duration')) {
                Setting::setValue('weatherlink.wll_udp_duration', $request->input('weatherlink_wll_udp_duration'), 'integer', 'weatherlink');
            }
        }
    }

    /**
     * Update Live Data settings (source-specific configuration)
     */
    private function updateLiveDataSettings(Request $request): void
    {
        $format = $request->input('livedata_format', 'ecoLcl');
        
        // Save the format
        Setting::setValue('livedata.format', $format, 'select', 'livedata');
        
        // Handle fetch mode and file/api URL (for local file sources)
        if ($request->has('livedata_fetch_mode')) {
            Setting::setValue('livedata.fetch_mode', $request->input('livedata_fetch_mode'), 'select', 'livedata');
        }
        if ($request->has('livedata_file_path')) {
            // Reject path-traversal sequences; the reader joins relative paths under
            // base_path(), so a `..` segment would let it read files outside the app.
            $filePath = (string) $request->input('livedata_file_path');
            if (str_contains($filePath, '..')) {
                session()->flash('error', 'Live data file path may not contain ".."; the path was not saved.');
            } else {
                Setting::setValue('livedata.file_path', $filePath, 'string', 'livedata');
            }
        }
        if ($request->has('livedata_api_url')) {
            Setting::setValue('livedata.api_url', $request->input('livedata_api_url'), 'string', 'livedata');
        }

        // Yearly rain data source preference
        if ($request->has('rain_yearly_source')) {
            Setting::setValue('livedata.rain_yearly_source', $request->input('rain_yearly_source'), 'select', 'livedata');
        }

        // Source-specific settings (only livedata-specific, not API credentials)
        if ($format === 'ecoLcl') {
            // Ecowitt Local (push) - only passkey (livedata-specific)
            if ($request->has('ecowitt_passkey')) {
                Setting::setValue('ecowitt.passkey', $request->input('ecowitt_passkey'), 'string', 'ecowitt');
            }

            // Optional hardening for WS View push receiver.
            $secureMode = $request->boolean('ecowitt_secure_mode');
            Setting::setValue('ecowitt.secure_mode', $secureMode, 'boolean', 'ecowitt');

            $secureToken = trim((string) $request->input('ecowitt_secure_token', ''));
            if ($secureToken !== '') {
                $secureToken = preg_replace('/[^A-Za-z0-9_-]/', '', $secureToken) ?? '';
            }
            Setting::setValue('ecowitt.secure_token', $secureToken, 'string', 'ecowitt');

            // Optional source filters for shared hosting environments (app-level allowlists).
            $ipFilterEnabled = $request->boolean('ecowitt_ip_filter_enabled');
            Setting::setValue('ecowitt.ip_filter_enabled', $ipFilterEnabled, 'boolean', 'ecowitt');
            Setting::setValue(
                'ecowitt.ip_allowlist',
                $this->normalizeEcowittAllowlist((string) $request->input('ecowitt_ip_allowlist', '')),
                'text',
                'ecowitt'
            );

            $nameFilterEnabled = $request->boolean('ecowitt_name_filter_enabled');
            Setting::setValue('ecowitt.name_filter_enabled', $nameFilterEnabled, 'boolean', 'ecowitt');
            Setting::setValue(
                'ecowitt.name_allowlist',
                $this->normalizeEcowittAllowlist((string) $request->input('ecowitt_name_allowlist', '')),
                'text',
                'ecowitt'
            );
        } elseif ($format === 'DWL_v2api_demo') {
            // WeatherLink v2 Demo Mode - enable demo mode and save API key (only API credential allowed on livedata page)
            Setting::setValue('weatherlink.demo_mode', '1', 'boolean', 'weatherlink');
            if ($request->has('wl_demo_api_key') && !empty($request->input('wl_demo_api_key'))) {
                Setting::setValue('weatherlink.api_key', $request->input('wl_demo_api_key'), 'encrypted', 'weatherlink');
            }
        } elseif ($format === 'DWL_v2api') {
            // Disable demo mode when using production v2 (livedata-specific)
            Setting::setValue('weatherlink.demo_mode', '0', 'boolean', 'weatherlink');
        }
        // Note: API credentials (ecowittAPI, wu, DWL, wf, AWapi) are configured on their dedicated settings pages
    }

    private function normalizeEcowittAllowlist(string $raw): string
    {
        $parts = preg_split('/[\r\n,;]+/', $raw) ?: [];
        $values = [];

        foreach ($parts as $part) {
            $candidate = trim($part);
            if ($candidate === '' || in_array($candidate, $values, true)) {
                continue;
            }
            $values[] = $candidate;
        }

        return implode("\n", $values);
    }

    /**
     * Mail configuration page
     */
    public function mail()
    {
        $mailService = app(MailConfigService::class);
        $providers = $mailService->getAvailableProviders();
        
        $currentProvider = Setting::getValue('mail.provider', 'smtp_custom');
        $oauth2Credentials = [];
        $smtpSettings = [];
        $predefinedConfig = null;
        
        if (in_array($currentProvider, ['gmail_oauth2', 'microsoft_oauth2'])) {
            $oauth2Credentials = $mailService->getOAuth2Credentials($currentProvider);
        } else {
            $smtpSettings = [
                'host' => Setting::getValue('mail.smtp.host'),
                'port' => Setting::getValue('mail.smtp.port'),
                'encryption' => Setting::getValue('mail.smtp.encryption', 'tls'),
                'username' => Setting::getValue('mail.smtp.username'),
            ];
            
            if (in_array($currentProvider, ['brevo', 'mailjet', 'postmark', 'mailgun', 'smtp2go'])) {
                $predefinedConfig = $mailService->getPredefinedProviderConfig($currentProvider);
            }
        }

        return view('admin.settings.mail', [
            'providers' => $providers,
            'currentProvider' => $currentProvider,
            'oauth2Credentials' => $oauth2Credentials,
            'smtpSettings' => $smtpSettings,
            'predefinedConfig' => $predefinedConfig,
        ]);
    }

    /**
     * Update mail configuration
     */
    public function updateMail(Request $request)
    {
        $provider = $request->input('provider', 'smtp_custom');
        
        // Save provider
        Setting::setValue('mail.provider', $provider, 'string', 'mail');
        
        if (in_array($provider, ['gmail_oauth2', 'microsoft_oauth2'])) {
            // OAuth2 provider: Only save OAuth2 credentials, ignore SMTP credentials
            // (SMTP fields are disabled in UI but may still be submitted)
            
            if ($request->has('oauth2_client_id') && !empty($request->input('oauth2_client_id'))) {
                Setting::setValue('mail.oauth2.client_id', $request->input('oauth2_client_id'), 'string', 'mail');
            }
            
            if ($request->has('oauth2_client_secret') && !empty($request->input('oauth2_client_secret'))) {
                Setting::setValue('mail.oauth2.client_secret', $request->input('oauth2_client_secret'), 'encrypted', 'mail');
            }
            
        } else {
            // SMTP provider: Save SMTP settings
            $predefinedProviders = ['brevo', 'mailjet', 'postmark', 'mailgun', 'smtp2go'];
            
            if (in_array($provider, $predefinedProviders)) {
                // Predefined SMTP provider: Use config values for host/port/encryption
                $predefinedConfig = config("mail_providers.{$provider}");
                if ($predefinedConfig) {
                    Setting::setValue('mail.smtp.host', $predefinedConfig['host'], 'string', 'mail');
                    Setting::setValue('mail.smtp.port', (string) $predefinedConfig['port'], 'string', 'mail');
                    Setting::setValue('mail.smtp.encryption', $predefinedConfig['encryption'], 'string', 'mail');
                }
            } else {
                // Custom SMTP: Save host/port/encryption from form
                if ($request->has('smtp_host')) {
                    Setting::setValue('mail.smtp.host', $request->input('smtp_host'), 'string', 'mail');
                }
                if ($request->has('smtp_port')) {
                    Setting::setValue('mail.smtp.port', $request->input('smtp_port'), 'string', 'mail');
                }
                if ($request->has('smtp_encryption')) {
                    Setting::setValue('mail.smtp.encryption', $request->input('smtp_encryption'), 'string', 'mail');
                }
            }
            
            // Username/API key: Always save from form (even if empty, to clear old value)
            $username = $request->input('smtp_username', '');
            Setting::setValue('mail.smtp.username', $username, 'string', 'mail');
            
            // Password: Check if required and validate
            $password = $request->input('smtp_password');
            $existingPassword = Setting::getValue('mail.smtp.password');
            
            // Check if password is required for this provider
            $predefinedProviders = ['brevo', 'mailjet', 'postmark', 'mailgun', 'smtp2go'];
            $passwordRequired = false;
            if (in_array($provider, $predefinedProviders)) {
                $providerConfig = config("mail_providers.{$provider}");
                $passwordRequired = $providerConfig['password_required'] ?? false;
            }
            
            // If password is required and not provided, and no existing password exists, show error
            if ($passwordRequired && empty($password) && empty($existingPassword)) {
                return redirect()
                    ->route('admin.settings.mail')
                    ->with('error', __('SMTP password is required for this provider. Please enter your :label', [
                        'label' => $providerConfig['password_label'] ?? __('SMTP Password')
                    ]));
            }
            
            // Save password if provided (leave blank to keep existing)
            if ($request->has('smtp_password') && !empty($password)) {
                Setting::setValue('mail.smtp.password', $password, 'encrypted', 'mail');
            }
        }
        
        // Clear settings cache first to ensure fresh values
        $this->clearSettingsCache();
        
        // Update mail configuration
        $mailService = app(MailConfigService::class);
        $mailService->applyConfiguration();
        
        return redirect()
            ->route('admin.settings.mail')
            ->with('success', __('Mail settings saved!'));
    }

    /**
     * Initiate OAuth flow for Gmail or Microsoft
     */
    public function initiateOAuth(Request $request, string $provider)
    {
        if (!in_array($provider, ['gmail', 'microsoft'])) {
            abort(404);
        }
        
        $clientId = Setting::getValue("mail.oauth2.client_id");
        $clientSecret = Setting::getValue("mail.oauth2.client_secret");
        
        if (empty($clientId) || empty($clientSecret)) {
            return redirect()
                ->route('admin.settings.mail')
                ->with('error', __('Please configure Client ID and Client Secret first.'));
        }
        
        // Decrypt client secret
        try {
            $decryptedSecret = Crypt::decryptString($clientSecret);
        } catch (\Exception $e) {
            $decryptedSecret = $clientSecret;
        }
        
        $redirectUri = route('admin.settings.mail.oauth.callback', ['provider' => $provider]);
        
        if ($provider === 'gmail') {
            $providerKey = 'gmail_oauth2';
            $oauthProvider = new \League\OAuth2\Client\Provider\Google([
                'clientId' => $clientId,
                'clientSecret' => $decryptedSecret,
                'redirectUri' => $redirectUri,
                'scopes' => ['https://www.googleapis.com/auth/gmail.send'],
            ]);
        } else {
            $providerKey = 'microsoft_oauth2';
            $oauthProvider = new \TheNetworg\OAuth2\Client\Provider\Azure([
                'clientId' => $clientId,
                'clientSecret' => $decryptedSecret,
                'redirectUri' => $redirectUri,
                'scopes' => ['https://outlook.office.com/SMTP.Send', 'offline_access'],
            ]);
        }
        
        // Store provider in session for callback
        session(['oauth_mail_provider' => $providerKey]);
        
        $authUrl = $oauthProvider->getAuthorizationUrl([
            'access_type' => 'offline',
            'prompt' => 'consent',
        ]);
        
        session(['oauth2state' => $oauthProvider->getState()]);
        
        return redirect($authUrl);
    }

    /**
     * Handle OAuth callback
     */
    public function oauthCallback(Request $request, string $provider)
    {
        if (!in_array($provider, ['gmail', 'microsoft'])) {
            abort(404);
        }
        
        $state = $request->query('state');
        $code = $request->query('code');
        
        if (empty($code) || $state !== session('oauth2state')) {
            return redirect()
                ->route('admin.settings.mail')
                ->with('error', __('OAuth authorization failed. Please try again.'));
        }
        
        $providerKey = session('oauth_mail_provider');
        $clientId = Setting::getValue("mail.oauth2.client_id");
        $clientSecret = Setting::getValue("mail.oauth2.client_secret");
        
        try {
            $decryptedSecret = Crypt::decryptString($clientSecret);
        } catch (\Exception $e) {
            $decryptedSecret = $clientSecret;
        }
        
        $redirectUri = route('admin.settings.mail.oauth.callback', ['provider' => $provider]);
        
        try {
            if ($provider === 'gmail') {
                $oauthProvider = new \League\OAuth2\Client\Provider\Google([
                    'clientId' => $clientId,
                    'clientSecret' => $decryptedSecret,
                    'redirectUri' => $redirectUri,
                ]);
            } else {
                $oauthProvider = new \TheNetworg\OAuth2\Client\Provider\Azure([
                    'clientId' => $clientId,
                    'clientSecret' => $decryptedSecret,
                    'redirectUri' => $redirectUri,
                ]);
            }
            
            $token = $oauthProvider->getAccessToken('authorization_code', [
                'code' => $code,
            ]);
            
            $refreshToken = $token->getRefreshToken();
            
            if ($refreshToken) {
                Setting::setValue('mail.oauth2.refresh_token', $refreshToken, 'encrypted', 'mail');
                Setting::setValue('mail.provider', $providerKey, 'string', 'mail');
                
                // Get and store the email address from the token
                try {
                    $owner = $token->getResourceOwner($oauthProvider);
                    $email = null;
                    
                    if ($provider === 'gmail') {
                        // For Google, get email from resource owner
                        $email = $owner->getEmail() ?? $owner->getId();
                    } else {
                        // For Microsoft, get email from resource owner
                        $email = $owner->getClaim('email') ?? $owner->getClaim('upn') ?? $owner->getId();
                    }
                    
                    if ($email) {
                        Setting::setValue('mail.oauth2.email_address', $email, 'string', 'mail');
                    }
                } catch (\Exception $e) {
                    \Log::warning('Could not retrieve OAuth2 email address', [
                        'provider' => $provider,
                        'error' => $e->getMessage(),
                    ]);
                }
                
                // Apply configuration
                $mailService = app(MailConfigService::class);
                $mailService->applyConfiguration();
                
                $this->clearSettingsCache();
                
                return redirect()
                    ->route('admin.settings.mail')
                    ->with('success', __('OAuth authorization successful! Mail provider configured.'));
            } else {
                return redirect()
                    ->route('admin.settings.mail')
                    ->with('error', __('OAuth authorization completed but no refresh token received.'));
            }
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.settings.mail')
                ->with('error', __('OAuth error: ') . $e->getMessage());
        }
    }

    /**
     * Test email sending
     */
    public function testMail(Request $request)
    {
        $to = $request->input('test_email', Setting::getValue('notifications.email', auth()->user()->email));
        
        if (empty($to)) {
            return redirect()
                ->route('admin.settings.mail')
                ->with('error', __('Please provide an email address to test.'));
        }
        
        try {
            // Clear cache and apply current mail configuration
            $this->clearSettingsCache();
            $mailService = app(MailConfigService::class);
            $mailService->applyConfiguration();
            
            // Use SMTP username as FROM address if available (for Brevo, this should be verified)
            $fromAddress = config('mail.from.address', 'hello@example.com');
            $fromName = config('mail.from.name', 'WeatherNode');
            
            // For Brevo and other SMTP providers, try to use the SMTP username as FROM if it's an email
            $smtpUsername = config('mail.mailers.smtp.username');
            if ($smtpUsername && filter_var($smtpUsername, FILTER_VALIDATE_EMAIL)) {
                $fromAddress = $smtpUsername;
            }
            
            Mail::raw(__('This is a test email from your WeatherNode installation. Mail configuration is working correctly!'), function ($message) use ($to, $fromAddress, $fromName) {
                $message->from($fromAddress, $fromName)
                    ->to($to)
                    ->subject(__('Test Email from WeatherNode'));
            });
            
            return redirect()
                ->route('admin.settings.mail')
                ->with('success', __('Test email sent successfully to :email', ['email' => $to]));
        } catch (\Swift_TransportException $e) {
            // SMTP connection/authentication errors
            \Log::error('Test email failed - SMTP Transport Exception', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'to' => $to,
                'previous' => $e->getPrevious() ? $e->getPrevious()->getMessage() : null,
            ]);
            
            return redirect()
                ->route('admin.settings.mail')
                ->with('error', __('Failed to send test email: :error', ['error' => $e->getMessage()]));
        } catch (\Exception $e) {
            \Log::error('Test email failed', [
                'error' => $e->getMessage(),
                'class' => get_class($e),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'to' => $to,
            ]);
            
            return redirect()
                ->route('admin.settings.mail')
                ->with('error', __('Failed to send test email: :error', ['error' => $e->getMessage()]));
        }
    }

    /**
     * Clear only settings-related caches, preserving external API data caches.
     * This replaces Cache::flush() which was too aggressive and wiped all cached data.
     */
    private function clearSettingsCache(): void
    {
        // Clear all setting.* cache keys from the database
        \DB::table('cache')
            ->where('key', 'like', '%setting.%')
            ->delete();

        // Also clear these specific caches that depend on settings
        Cache::forget('today_summary');
        Cache::forget('current_conditions');
        Cache::forget('ecowitt_realtime');
    }

    public function charts()
    {
        $availableCharts = [
            // Core charts
            'temperature' => ['label' => 'Temperature', 'description' => 'High, low, average temperature and feels like', 'icon' => 'thermometer', 'category' => 'core'],
            'wind' => ['label' => 'Wind', 'description' => 'Wind speed, gusts and direction', 'icon' => 'wind', 'category' => 'core'],
            'solar_uv' => ['label' => 'UV & Solar Radiation', 'description' => 'Solar radiation and UV index', 'icon' => 'sun', 'category' => 'core'],
            'precipitation' => ['label' => 'Precipitation & Pressure', 'description' => 'Rainfall, rain rate and air pressure', 'icon' => 'droplet', 'category' => 'core'],
            'humidity' => ['label' => 'Humidity & Dew Point', 'description' => 'Relative humidity and dew point temperature', 'icon' => 'droplet', 'category' => 'core'],
            // Sensor charts
            'soil' => ['label' => 'Soil', 'description' => 'Soil moisture and temperature sensors', 'icon' => 'plant', 'category' => 'sensor'],
            'leaf_wetness' => ['label' => 'Leaf Wetness', 'description' => 'Leaf wetness sensor readings', 'icon' => 'leaf', 'category' => 'sensor'],
            'air_quality' => ['label' => 'Air Quality', 'description' => 'PM2.5 and PM10 particulate matter', 'icon' => 'cloud', 'category' => 'sensor'],
            'co2' => ['label' => 'CO₂', 'description' => 'Carbon dioxide concentration', 'icon' => 'gauge', 'category' => 'sensor'],
            'lightning' => ['label' => 'Lightning', 'description' => 'Lightning strike count and distance', 'icon' => 'zap', 'category' => 'sensor'],
            'water_temp' => ['label' => 'Water Temperature', 'description' => 'Water temperature sensor', 'icon' => 'waves', 'category' => 'sensor'],
            'extra_sensors' => ['label' => 'Extra Sensors', 'description' => 'Additional temperature and humidity sensors', 'icon' => 'thermometer', 'category' => 'sensor'],
        ];

        $defaultCharts = array_keys($availableCharts);
        $enabledCharts = Setting::getValue('charts.day_visible', $defaultCharts);
        if (!is_array($enabledCharts)) {
            $enabledCharts = $defaultCharts;
        }

        return view('admin.settings.charts', compact('availableCharts', 'enabledCharts', 'defaultCharts'));
    }

    public function updateCharts(Request $request)
    {
        $enabled = $request->input('enabled_charts', []);
        if (!is_array($enabled)) {
            $enabled = [];
        }

        Setting::setValue('charts.day_visible', $enabled, 'json', 'display');

        return redirect()->route('admin.settings.charts')->with('success', __('Chart settings saved.'));
    }

    /**
     * Import a legacy Ecowitt .arr file (PWS Dashboard migration).
     */
    public function importEcowittFile(Request $request)
    {
        $request->validate([
            'arr_file' => ['required', 'file', 'max:2048'],
        ]);

        $file = $request->file('arr_file');

        if (strtolower($file->getClientOriginalExtension()) !== 'arr') {
            return redirect()
                ->route('admin.settings.group', 'ecowitt')
                ->with('error', __('Only .arr files are accepted.'));
        }

        $content = $file->get();
        if ($content === false || $content === '') {
            return redirect()
                ->route('admin.settings.group', 'ecowitt')
                ->with('error', __('Could not read the uploaded file.'));
        }

        $raw = @unserialize($content, ['allowed_classes' => false]);
        if (!is_array($raw)) {
            return redirect()
                ->route('admin.settings.group', 'ecowitt')
                ->with('error', __('Could not parse the .arr file. The file may be corrupt or in an unexpected format.'));
        }

        $parser = app(EcowittPushParser::class);
        $data = $parser->parse($raw);

        if (empty($data) || !isset($data['temperature'])) {
            return redirect()
                ->route('admin.settings.group', 'ecowitt')
                ->with('error', __('No valid weather data found in the file.'));
        }

        $writer = app(WeatherReadingWriter::class);
        $reading = $writer->store($data);

        return redirect()
            ->route('admin.settings.group', 'ecowitt')
            ->with('success', __('Ecowitt data imported successfully. Reading #:id stored with :fields fields.', [
                'id' => $reading->id,
                'fields' => count(array_keys($raw)),
            ]));
    }
}
