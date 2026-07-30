@extends('layouts.admin')

@section('title', $group === 'environment_canada' ? 'Environment Canada' : __($groupInfo['label']))

@section('content')
<div class="w-full">
    <!-- Breadcrumb -->
    <nav class="mb-6 text-sm">
        <ol class="flex items-center space-x-2">
            <li><a href="{{ route('admin.settings.index') }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">{{ __('Settings') }}</a></li>
            <li><svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></li>
            <li class="text-gray-900 dark:text-white font-medium">
                {{ $group === 'environment_canada' ? 'Environment Canada' : __($groupInfo['label']) }}
            </li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center space-x-4">
            <div class="p-3 rounded-xl bg-{{ $groupInfo['color'] }}-100 dark:bg-{{ $groupInfo['color'] }}-900/30">
                <svg class="w-8 h-8 text-{{ $groupInfo['color'] }}-600 dark:text-{{ $groupInfo['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ $group === 'environment_canada' ? 'Environment Canada' : __($groupInfo['label']) }}
                </h1>
                <p class="text-gray-500 dark:text-gray-400">{{ __($groupInfo['description']) }}</p>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-green-600 dark:text-green-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <p class="text-green-800 dark:text-green-200">{{ session('success') }}</p>
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-red-600 dark:text-red-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10A8 8 0 112 10a8 8 0 0116 0zm-8-4a1 1 0 00-.894.553l-3 6A1 1 0 007 14h6a1 1 0 00.894-1.447l-3-6A1 1 0 0010 6zm1 6a1 1 0 10-2 0 1 1 0 002 0zm-1 4a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                </svg>
                <p class="text-red-800 dark:text-red-200">{{ session('error') }}</p>
            </div>
        </div>
    @endif

    @if($group === 'advanced')
        <div class="mb-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('System Diagnostics Snapshot') }}</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        {{ __('Generate a JSON snapshot with scheduler health, cache freshness, and logging state for troubleshooting.') }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ __('This snapshot excludes API keys and encrypted secrets.') }}
                    </p>
                </div>
                <form action="{{ route('admin.settings.advanced.diagnostics') }}" method="POST">
                    @csrf
                    <button type="submit" class="inline-flex items-center justify-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium transition">
                        {{ __('Generate Diagnostics Snapshot') }}
                    </button>
                </form>
            </div>
        </div>
    @endif

    @if($group === 'webcam')
        <div class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10A8 8 0 112 10a8 8 0 0116 0zm-8-4a1 1 0 00-1 1v3a1 1 0 102 0V7a1 1 0 00-1-1zm0 8a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd"/>
                </svg>
                <div class="text-sm text-blue-900 dark:text-blue-100">
                    <div class="font-semibold mb-1">{{ __('Webcam settings help') }}</div>
                    <ul class="list-disc pl-5 space-y-1">
                        <li>{{ __('Display Mode: Choose "Image" for static images, "Stream" for YouTube/Restreamer livestreams, or "Both" to show image with click-to-view stream option.') }}</li>
                        <li>{{ __('Refresh Interval: Only applies when Display Mode is set to "Image". Static images refresh automatically at the configured interval (default: 60 seconds).') }}</li>
                        <li>{{ __('Livestream Mode: When using YouTube or Restreamer streams, the widget does NOT refresh automatically to avoid interrupting playback.') }}</li>
                        <li>{{ __('Stream URL: For YouTube, use the live stream URL (e.g., https://www.youtube.com/live/VIDEO_ID). For Restreamer, use the stream URL or browser embed URL (/b/ path).') }}</li>
                    </ul>
                </div>
            </div>
        </div>
    @endif

    @if($group === 'radar')
        <div class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10A8 8 0 112 10a8 8 0 0116 0zm-8-4a1 1 0 00-1 1v3a1 1 0 102 0V7a1 1 0 00-1-1zm0 8a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd"/>
                </svg>
                <div class="text-sm text-blue-900 dark:text-blue-100">
                    <div class="font-semibold mb-1">{{ __('Radar settings help') }}</div>
                    <ul class="list-disc pl-5 space-y-1">
                        <li>{{ __('Changing the radar provider will automatically update and save the radar URL.') }}</li>
                        <li>{{ __('RainViewer has two modes: API (animated Leaflet map) or iframe (simple embed).') }}</li>
                        <li>{{ __('RainViewer zoom is limited to 1–7 (as of 2026).') }}</li>
                        <li>{{ __('You can configure a separate provider/mode for the dashboard radar widget (so it can differ from the /radar page).') }}</li>
                    </ul>
                </div>
            </div>
        </div>
    @endif

    @if($group === 'history' && (($schedulerStatus['status'] ?? '') !== 'running'))
        <div class="mb-6 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-xl">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <div>
                    <p class="text-sm font-semibold text-yellow-800 dark:text-yellow-200">
                        {{ __('Scheduler not running') }}
                    </p>
                    <p class="text-sm text-yellow-700 dark:text-yellow-300 mt-1">
                        {{ __('Last run') }}:
                        {{ ($schedulerStatus['last_run'] ?? null) ? $schedulerStatus['last_run']->diffForHumans() : __('Never') }}
                    </p>
                    <p class="text-xs text-yellow-700 dark:text-yellow-300 mt-2">
                        {{ __('Add this cron job:') }}
                    </p>
                    <pre class="mt-1 text-xs bg-yellow-100/60 dark:bg-yellow-900/40 text-yellow-900 dark:text-yellow-100 rounded-md p-2 overflow-x-auto">{{ $schedulerStatus['cron_line'] ?? '' }}</pre>
                </div>
            </div>
        </div>
    @endif

    <!-- Settings Form -->
    <form action="{{ route('admin.settings.update', $group) }}" method="POST" class="space-y-6">
        @csrf

        @if($group === 'radar')
            @php
                $nowcastEnabled = \App\Models\Setting::getValue('radar.nowcast_enabled', false);
                $nowcastAnimationSpeed = \App\Models\Setting::getValue('radar.nowcast_animation_speed', 0.5);
                $nowcastAutoPlay = \App\Models\Setting::getValue('radar.nowcast_autoplay', false);
                $widgetFutureFramesEnabled = \App\Models\Setting::getValue('radar.widget_future_frames_enabled', false);
                $widgetFutureFramesProvider = (string) \App\Models\Setting::getValue('radar.widget_future_frames_provider', 'auto');
                $futureFrameProviders = is_array($radarFutureFrameProviders ?? null) ? $radarFutureFrameProviders : [];
                $isInNetherlands = \App\Models\Setting::isStationInNetherlands();
            @endphp
            <div class="mb-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 divide-y divide-gray-100 dark:divide-gray-700">
                <div class="p-5">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">{{ __('KNMI Radar Nowcast') }}</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        {{ __('2-hour precipitation forecast from KNMI (Netherlands). Shows animated forecast up to 2 hours ahead with 5-minute intervals.') }}
                    </p>

                    @if(!$isInNetherlands)
                        <div class="mb-4 p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                            <div class="flex items-start">
                                <svg class="w-4 h-4 text-yellow-600 dark:text-yellow-400 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                <p class="text-xs text-yellow-800 dark:text-yellow-200">
                                    {{ __('Note: KNMI data covers the Netherlands region. Your station appears to be outside this area, so the data may not be relevant for your location.') }}
                                </p>
                            </div>
                        </div>
                    @endif

                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <label class="block text-sm font-medium text-gray-900 dark:text-white">
                                    {{ __('Enable Radar Nowcast') }}
                                </label>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    {{ __('Show 2-hour precipitation forecast on radar page') }}
                                </p>
                            </div>
                            <x-toggle-switch
                                :enabled="$nowcastEnabled"
                                name="radar_nowcast_enabled"
                                :labelEnabled="__('Enabled')"
                                :labelDisabled="__('Disabled')"
                            />
                        </div>

                        <div class="flex items-center justify-between">
                            <div>
                                <label class="block text-sm font-medium text-gray-900 dark:text-white">
                                    {{ __('Dashboard future frames') }}
                                </label>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    {{ __('Append regional future frames after RainViewer past frames in the dashboard radar widget (RainViewer API mode only).') }}
                                </p>
                            </div>
                            <x-toggle-switch
                                :enabled="$widgetFutureFramesEnabled"
                                name="radar_widget_future_frames_enabled"
                                :labelEnabled="__('Enabled')"
                                :labelDisabled="__('Disabled')"
                            />
                        </div>

                        <div>
                            <label for="radar_widget_future_frames_provider" class="block text-sm font-medium text-gray-900 dark:text-white mb-2">
                                {{ __('Future frames provider') }}
                            </label>
                            <select
                                name="radar_widget_future_frames_provider"
                                id="radar_widget_future_frames_provider"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">
                                @foreach($futureFrameProviders as $providerOption)
                                    @php
                                        $providerKey = (string) ($providerOption['key'] ?? '');
                                        $providerLabel = (string) ($providerOption['label'] ?? $providerKey);
                                        $providerImplemented = (bool) ($providerOption['implemented'] ?? false);
                                        $providerSuffix = $providerImplemented ? '' : ' • ' . __('planned');
                                    @endphp
                                    <option value="{{ $providerKey }}" {{ $widgetFutureFramesProvider === $providerKey ? 'selected' : '' }}>
                                        {{ $providerLabel }}{{ $providerSuffix }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                {{ __('Select where dashboard future radar frames should come from. Auto picks the best match for your station.') }}
                            </p>
                        </div>

                        @if($nowcastEnabled)
                            <div class="pt-4 border-t border-gray-200 dark:border-gray-700 space-y-4">
                                <div>
                                    <label for="radar_nowcast_animation_speed" class="block text-sm font-medium text-gray-900 dark:text-white mb-2">
                                        {{ __('Animation Speed') }} ({{ __('seconds per frame') }})
                                    </label>
                                    <input type="number"
                                           name="radar_nowcast_animation_speed"
                                           id="radar_nowcast_animation_speed"
                                           value="{{ $nowcastAnimationSpeed }}"
                                           min="0.1"
                                           max="2"
                                           step="0.1"
                                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        {{ __('Lower values = faster animation (default: 0.5 seconds)') }}
                                    </p>
                                </div>

                                <div class="flex items-center justify-between">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-900 dark:text-white">
                                            {{ __('Auto-play Animation') }}
                                        </label>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            {{ __('Automatically start animation when page loads') }}
                                        </p>
                                    </div>
                                    <x-toggle-switch
                                        :enabled="$nowcastAutoPlay"
                                        name="radar_nowcast_autoplay"
                                        :labelEnabled="__('Enabled')"
                                        :labelDisabled="__('Disabled')"
                                    />
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif
        
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm divide-y divide-gray-100 dark:divide-gray-700">
            @foreach($settings as $setting)
                @php
                    $formKey = str_replace('.', '_', $setting->key);
                    $isApi = $setting->type === 'encrypted';
                    $displayLabel = __(ucwords(str_replace(['_', '.'], ' ', basename($setting->key))));
                    $displayDescription = $setting->description ? __($setting->description) : '';

                    // Keep storage keys unchanged (contact.twitter) while showing modern naming in UI.
                    if (str_contains(strtolower($setting->key), 'twitter')) {
                        $displayLabel = str_ireplace('Twitter', 'X', $displayLabel);
                        $displayDescription = str_ireplace(
                            ['Twitter/X', 'Twitter / X', 'Twitter'],
                            ['X', 'X', 'X'],
                            $displayDescription
                        );
                    }
                @endphp
                
                @php
                    $isRainviewerZoom = $group === 'radar' && $setting->key === 'radar.rainviewer_zoom';
                    $isRainviewerMode = $group === 'radar' && $setting->key === 'radar.rainviewer_mode';
                    $isFrameDelay = $group === 'radar' && $setting->key === 'radar.frame_delay';
                    $isUseProxy = $group === 'radar' && $setting->key === 'radar.use_proxy';
                    $isWidgetRainviewerMode = $group === 'radar' && $setting->key === 'radar.widget_rainviewer_mode';
                    $isRadarNowcastSetting = $group === 'radar' && in_array($setting->key, [
                        'radar.nowcast_enabled',
                        'radar.nowcast_animation_speed',
                        'radar.nowcast_autoplay',
                        'radar.widget_future_frames_enabled',
                        'radar.widget_future_frames_provider',
                    ], true);
                    $isSatelliteKnmiUrl = $group === 'satellite' && $setting->key === 'satellite.knmi_url';
                    $isSatelliteNasaUrl = $group === 'satellite' && $setting->key === 'satellite.nasa_url';
                    $isSatelliteCustomUrl = $group === 'satellite' && $setting->key === 'satellite.custom_url';
                    $isSatelliteZoom = $group === 'satellite' && $setting->key === 'satellite.zoom';
                    $shouldHide = $isRainviewerZoom || $isRainviewerMode || $isFrameDelay || $isUseProxy || $isWidgetRainviewerMode || $isRadarNowcastSetting || $isSatelliteKnmiUrl || $isSatelliteNasaUrl || $isSatelliteCustomUrl || $isSatelliteZoom;
                    $containerId = '';
                    if ($isRainviewerZoom) $containerId = 'radar-zoom-container';
                    elseif ($isRainviewerMode) $containerId = 'radar-mode-container';
                    elseif ($isFrameDelay) $containerId = 'radar-delay-container';
                    elseif ($isUseProxy) $containerId = 'radar-proxy-container';
                    elseif ($isWidgetRainviewerMode) $containerId = 'widget-rainviewer-mode-container';
                    elseif ($isSatelliteKnmiUrl) $containerId = 'satellite-knmi-url-container';
                    elseif ($isSatelliteNasaUrl) $containerId = 'satellite-nasa-url-container';
                    elseif ($isSatelliteCustomUrl) $containerId = 'satellite-custom-url-container';
                    elseif ($isSatelliteZoom) $containerId = 'satellite-zoom-container';
                @endphp
                @if($isRadarNowcastSetting)
                    @continue
                @endif
                @php
                    // Show fields based on current provider
                    $shouldShowByDefault = false;
                    if ($group === 'radar' && $shouldHide) {
                        $currentRadarProvider = \App\Models\Setting::getValue('radar.provider', 'rainviewer');
                        $currentRainviewerMode = \App\Models\Setting::getValue('radar.rainviewer_mode', 'api');
                        if (($isRainviewerZoom || $isRainviewerMode || $isUseProxy) && $currentRadarProvider === 'rainviewer') {
                            $shouldShowByDefault = true;
                        }
                        // Frame delay only shows when rainviewer + api mode
                        if ($isFrameDelay && $currentRadarProvider === 'rainviewer' && $currentRainviewerMode === 'api') {
                            $shouldShowByDefault = true;
                        }
                    }
                    if ($group === 'satellite' && $shouldHide) {
                        $currentProvider = \App\Models\Setting::getValue('satellite.provider', 'knmi');
                        if ($isSatelliteKnmiUrl && $currentProvider === 'knmi') $shouldShowByDefault = true;
                        if ($isSatelliteNasaUrl && $currentProvider === 'nasa') $shouldShowByDefault = true;
                        if ($isSatelliteCustomUrl && $currentProvider === 'custom') $shouldShowByDefault = true;
                        if ($isSatelliteZoom && $currentProvider === 'nasa') $shouldShowByDefault = true;
                    }
                @endphp
                <div class="p-5" 
                     @if($shouldHide) 
                         id="{{ $containerId }}" 
                         style="display: {{ $shouldShowByDefault ? 'block' : 'none' }};" 
                     @endif>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <label for="{{ $formKey }}" class="block text-sm font-medium text-gray-900 dark:text-white">
                                {{ $displayLabel }}
                                @if($isApi)
                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                        </svg>
                                        {{ __('Encrypted') }}
                                    </span>
                                @endif
                            </label>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ $displayDescription }}</p>
                        
                        <div class="w-full">
                            @if($group === 'station' && $setting->key === 'station.timezone' && !empty($timezones))
                                <select name="{{ $formKey }}" 
                                        id="{{ $formKey }}"
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">
                                    @foreach($timezones as $tz)
                                        <option value="{{ $tz }}" {{ $setting->value === $tz ? 'selected' : '' }}>{{ $tz }}</option>
                                    @endforeach
                                </select>
                            @else
                            @switch($setting->type)
                                @case('boolean')
                                    <x-toggle-switch
                                        :enabled="$setting->getCastedValue()"
                                        :name="$formKey"
                                        :labelEnabled="__('Enabled')"
                                        :labelDisabled="__('Disabled')"
                                    />
                                    @break
                                    
                                @case('select')
                                    <select name="{{ $formKey }}" 
                                            id="{{ $formKey }}"
                                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400"
                                            @if($group === 'radar' && $setting->key === 'radar.provider') onchange="updateRadarProviderSettings()" @endif
                                            @if($group === 'radar' && $setting->key === 'radar.rainviewer_mode') onchange="updateRainviewerModeSettings()" @endif
                                            @if($group === 'radar' && $setting->key === 'radar.widget_provider') onchange="updateWidgetProviderSettings()" @endif
                                            @if($group === 'satellite' && $setting->key === 'satellite.provider') onchange="updateSatelliteProviderSettings()" @endif>
                                        @foreach($setting->getOptionsArray() as $optValue => $optLabel)
                                            <option value="{{ $optValue }}" {{ (string) $setting->value === (string) $optValue ? 'selected' : '' }}>
                                                {{ __($optLabel) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @break
                                    
                                @case('textarea')
                                    <textarea name="{{ $formKey }}" 
                                              id="{{ $formKey }}"
                                              rows="3"
                                              class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">{{ $setting->value }}</textarea>
                                    @break
                                    
                                @case('encrypted')
                                    <div class="relative">
                                        <input type="text" 
                                               name="{{ $formKey }}" 
                                               id="{{ $formKey }}"
                                               placeholder="{{ $setting->value ? __('(configured - enter new value to change)') : __('Enter API key') }}"
                                               autocomplete="off"
                                               data-lpignore="true"
                                               style="-webkit-text-security: disc; text-security: disc;"
                                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400 pr-10 font-mono">
                                        <button type="button" onclick="togglePassword('{{ $formKey }}')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                            <svg id="{{ $formKey }}_eye" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            <svg id="{{ $formKey }}_eye_off" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                            </svg>
                                        </button>
                                    </div>
                                    @if($setting->value)
                                        <p class="mt-1 text-xs text-green-600 dark:text-green-400">
                                            <svg class="w-3 h-3 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                            </svg>
                                            {{ __('Configured (leave empty to keep current value)') }}
                                        </p>
                                    @endif
                                    @break
                                    
                                @case('integer')
                                    <input type="number" 
                                           name="{{ $formKey }}" 
                                           id="{{ $formKey }}"
                                           value="{{ $setting->value }}"
                                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">
                                    @break
                                    
                                @case('float')
                                    <input type="number" 
                                           name="{{ $formKey }}" 
                                           id="{{ $formKey }}"
                                           value="{{ $setting->value }}"
                                           step="0.000001"
                                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">
                                    @break
                                    
                                @case('date')
                                    <input type="date" 
                                           name="{{ $formKey }}" 
                                           id="{{ $formKey }}"
                                           value="{{ $setting->value }}"
                                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">
                                    @break
                                    
                                @default
                                    <input type="text" 
                                           name="{{ $formKey }}" 
                                           id="{{ $formKey }}"
                                           value="{{ $setting->value }}"
                                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">
                            @endswitch
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-between">
            <a href="{{ route('admin.settings.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
                ← {{ __('Back to Settings') }}
            </a>
            <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 text-white font-medium rounded-lg transition shadow-sm">
                {{ __('Save Changes') }}
            </button>
        </div>
    </form>

    @if($group === 'history')
        <div class="mt-8 p-6 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
                <div>
                    <h3 class="font-medium text-gray-900 dark:text-white">{{ __('Historical Data Sync') }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Backfill daily summaries using live readings when data is missing.') }}
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="p-4 bg-white dark:bg-gray-700 rounded-lg">
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Readings range') }}</p>
                    <p class="mt-1 font-semibold text-gray-900 dark:text-white">
                        {{ $historySync['reading_start'] ?? __('N/A') }} → {{ $historySync['reading_end'] ?? __('N/A') }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ $historySync['reading_days'] ?? 0 }} {{ __('days with readings') }}
                    </p>
                </div>
                <div class="p-4 bg-white dark:bg-gray-700 rounded-lg">
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Summary range') }}</p>
                    <p class="mt-1 font-semibold text-gray-900 dark:text-white">
                        {{ $historySync['summary_start'] ?? __('N/A') }} → {{ $historySync['summary_end'] ?? __('N/A') }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ $historySync['summary_days'] ?? 0 }} {{ __('summary days') }}
                    </p>
                </div>
                <div class="p-4 bg-white dark:bg-gray-700 rounded-lg">
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Missing summaries') }}</p>
                    <p class="mt-1 font-semibold text-gray-900 dark:text-white">
                        {{ $historySync['missing_days'] ?? 0 }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ __('Most recent missing days listed below') }}
                    </p>
                </div>
            </div>

            @if(!empty($historySync['missing_recent']) && count($historySync['missing_recent']) > 0)
                <div class="mt-4">
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">{{ __('Recent missing days') }}</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($historySync['missing_recent'] as $missingDate)
                            <span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300">
                                {{ $missingDate }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.settings.history.sync') }}" class="mt-6 flex flex-wrap items-end gap-4">
                @csrf
                <div>
                    <label for="history_sync_limit" class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('Max days to sync') }}</label>
                    <input type="number"
                           id="history_sync_limit"
                           name="limit"
                           value="30"
                           min="1"
                           max="3650"
                           class="w-28 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">
                </div>
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 text-white font-medium rounded-lg transition shadow-sm">
                    {{ __('Sync missing days') }}
                </button>
            </form>
        </div>

        <div class="mt-6 p-6 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
                <div>
                    <h3 class="font-medium text-gray-900 dark:text-white">{{ __('Weather Underground Sync') }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Sync recent daily summaries from Weather Underground.') }}
                    </p>
                </div>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ ($wuSyncConfig['enabled'] ?? false) ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">
                    {{ ($wuSyncConfig['enabled'] ?? false) ? __('Scheduled') : __('Disabled') }}
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="p-4 bg-white dark:bg-gray-700 rounded-lg">
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Daily sync time') }}</p>
                    <p class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $wuSyncConfig['time'] ?? '02:10' }}</p>
                </div>
                <div class="p-4 bg-white dark:bg-gray-700 rounded-lg">
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Days back') }}</p>
                    <p class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $wuSyncConfig['days'] ?? 7 }}</p>
                </div>
                <div class="p-4 bg-white dark:bg-gray-700 rounded-lg">
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Skip existing') }}</p>
                    <p class="mt-1 font-semibold text-gray-900 dark:text-white">
                        {{ ($wuSyncConfig['skip_existing'] ?? true) ? __('Yes') : __('No') }}
                    </p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.settings.history.wu-sync') }}" class="mt-6 flex flex-wrap items-end gap-4">
                @csrf
                <div>
                    <label for="wu_sync_days" class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('Days back to sync') }}</label>
                    <input type="number"
                           id="wu_sync_days"
                           name="days"
                           value="{{ $wuSyncConfig['days'] ?? 7 }}"
                           min="1"
                           max="365"
                           class="w-28 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">
                </div>
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 text-white font-medium rounded-lg transition shadow-sm">
                    {{ __('Sync WU history now') }}
                </button>
            </form>
        </div>
    @endif

    @if($group === 'airquality')
        <div class="mt-8 p-6 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
            <h3 class="font-medium text-gray-900 dark:text-white mb-4">{{ __('Test Connections') }}</h3>
            <div class="flex flex-wrap gap-3">
                <button type="button"
                        onclick="testConnection('waqi')"
                        class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    {{ __('Test WAQI') }}
                </button>
                <button type="button"
                        onclick="testConnection('purpleair')"
                        class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    {{ __('Test PurpleAir') }}
                </button>
                <button type="button"
                        onclick="testConnection('luftdaten')"
                        class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    {{ __('Test Luftdaten') }}
                </button>
                <button type="button"
                        onclick="testConnection('luftdaten_noise')"
                        class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/>
                    </svg>
                    {{ __('Test Luftdaten Noise') }}
                </button>
                <button type="button"
                        onclick="testConnection('davis_aq')"
                        class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    {{ __('Test Davis AirLink') }}
                </button>
            </div>
            <div id="testResult" class="mt-4 hidden"></div>
        </div>
    @elseif(in_array($group, ['livedata', 'ecowitt', 'wunderground', 'weatherflow', 'weatherlink', 'ambient', 'openweathermap', 'aviation']))
        <div class="mt-8 p-6 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
            <h3 class="font-medium text-gray-900 dark:text-white mb-4">{{ __('Test Connection') }}</h3>
            <button type="button"
                    onclick="testConnection('{{ $group === 'aviation' ? 'checkwx' : ($group === 'livedata' ? 'livedata' : $group) }}')"
                    class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                {{ __('Test API Connection') }}
            </button>
            <div id="testResult" class="mt-4 hidden"></div>
        </div>
    @endif
</div>

@if($group === 'radar')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Radar provider URLs mapping
    // Note: RainViewer uses iframe embed, so URL is just a placeholder
    const radarUrls = {
        'knmi': 'https://cdn.knmi.nl/knmi/map/page/weer/actueel-weer/neerslagradar/WWWRADARTMP_loop.gif',
        'buienradar': 'https://api.buienradar.nl/image/1.0/radarmapnl?w=500&h=512',
        'rainviewer': 'https://www.rainviewer.com/map.html' // Iframe embed - URL will be generated dynamically with lat/lon
    };
    
    // Find the provider select and URL input by their form keys
    const providerSelect = document.querySelector('select[name="radar_provider"]');
    const urlInput = document.querySelector('input[name="radar_url"]');
    
    // Find RainViewer mode select
    const rainviewerModeSelect = document.querySelector('select[name="radar_rainviewer_mode"]');
    const widgetProviderSelect = document.querySelector('select[name="radar_widget_provider"]');
    const widgetRainviewerModeSelect = document.querySelector('select[name="radar_widget_rainviewer_mode"]');
    
    // Show/hide zoom level input based on provider
    function updateRadarProviderSettings() {
        const selectedProvider = providerSelect.value;
        const zoomContainer = document.getElementById('radar-zoom-container');
        const rainviewerModeContainer = document.getElementById('radar-mode-container');
        const delayContainer = document.getElementById('radar-delay-container');
        const proxyContainer = document.getElementById('radar-proxy-container');
        
        if (selectedProvider === 'rainviewer') {
            // Show zoom level, mode and proxy inputs for RainViewer
            if (zoomContainer) {
                zoomContainer.style.display = 'block';
            }
            if (rainviewerModeContainer) {
                rainviewerModeContainer.style.display = 'block';
            }
            if (proxyContainer) {
                proxyContainer.style.display = 'block';
            }
            // Update delay visibility based on mode
            updateRainviewerModeSettings();
            
            if (urlInput) {
                urlInput.value = radarUrls[selectedProvider];
                urlInput.placeholder = 'Iframe embed - URL generated automatically with station coordinates and zoom level';
                urlInput.readOnly = true;
                urlInput.classList.add('bg-gray-100', 'dark:bg-gray-800');
            }
        } else {
            // Hide zoom level, mode, delay and proxy inputs for other providers
            if (zoomContainer) {
                zoomContainer.style.display = 'none';
            }
            if (rainviewerModeContainer) {
                rainviewerModeContainer.style.display = 'none';
            }
            if (delayContainer) {
                delayContainer.style.display = 'none';
            }
            if (proxyContainer) {
                proxyContainer.style.display = 'none';
            }
            if (urlInput) {
                urlInput.readOnly = false;
                urlInput.classList.remove('bg-gray-100', 'dark:bg-gray-800');
                urlInput.placeholder = '';
                if (radarUrls[selectedProvider]) {
                    urlInput.value = radarUrls[selectedProvider];
                }
            }
        }
        
        // Visual feedback
        if (urlInput) {
            urlInput.classList.add('ring-2', 'ring-blue-500');
            setTimeout(() => {
                urlInput.classList.remove('ring-2', 'ring-blue-500');
            }, 1000);
        }
    }
    
    // Show/hide frame delay based on rainviewer mode (only for API mode)
    function updateRainviewerModeSettings() {
        const delayContainer = document.getElementById('radar-delay-container');
        const proxyContainer = document.getElementById('radar-proxy-container');
        const selectedMode = rainviewerModeSelect ? rainviewerModeSelect.value : 'api';
        const selectedProvider = providerSelect ? providerSelect.value : '';
        
        if (selectedProvider === 'rainviewer' && selectedMode === 'api') {
            // API mode: show delay and proxy options
            if (delayContainer) {
                delayContainer.style.display = 'block';
            }
            if (proxyContainer) {
                proxyContainer.style.display = 'block';
            }
        } else {
            // Iframe mode or other provider: hide delay and proxy
            if (delayContainer) {
                delayContainer.style.display = 'none';
            }
            if (selectedProvider !== 'rainviewer' && proxyContainer) {
                proxyContainer.style.display = 'none';
            }
        }
    }
    
    // Update widget provider settings visibility
    function updateWidgetProviderSettings() {
        const selectedWidgetProvider = widgetProviderSelect ? widgetProviderSelect.value : '';
        const widgetRainviewerModeContainer = document.getElementById('widget-rainviewer-mode-container');
        
        if (selectedWidgetProvider === 'rainviewer') {
            if (widgetRainviewerModeContainer) {
                widgetRainviewerModeContainer.style.display = 'block';
            }
        } else {
            if (widgetRainviewerModeContainer) {
                widgetRainviewerModeContainer.style.display = 'none';
            }
        }
    }
    
    // Make functions globally available for onchange
    window.updateRadarProviderSettings = updateRadarProviderSettings;
    window.updateRainviewerModeSettings = updateRainviewerModeSettings;
    
    if (providerSelect && urlInput) {
        // Initial setup
        updateRadarProviderSettings();
        if (rainviewerModeSelect) {
            rainviewerModeSelect.addEventListener('change', updateRainviewerModeSettings);
        }
        if (widgetProviderSelect) {
            updateWidgetProviderSettings();
            widgetProviderSelect.addEventListener('change', updateWidgetProviderSettings);
        }
        
        // Update URL when provider changes
        providerSelect.addEventListener('change', updateRadarProviderSettings);
        
        // Set initial URL if it's empty or matches a provider default
        const currentProvider = providerSelect.value;
        if (radarUrls[currentProvider]) {
            // Only auto-set if URL is empty or matches another provider's default
            const currentUrl = urlInput.value.trim();
            const isDefaultUrl = Object.values(radarUrls).some(url => {
                if (!url) return false;
                const urlPart = url.split('/').pop()?.split('?')[0] || '';
                return currentUrl.includes(urlPart);
            });
            
            if (!currentUrl || isDefaultUrl) {
                urlInput.value = radarUrls[currentProvider];
            }
        }
    }
});

// Satellite provider settings
@if($group === 'satellite')
(function() {
    function updateSatelliteProviderSettings() {
        const satelliteProviderSelect = document.querySelector('select[name="satellite_provider"]');
        if (!satelliteProviderSelect) {
            console.warn('Satellite provider select not found');
            return;
        }
        
        const selectedProvider = satelliteProviderSelect.value;
        const knmiUrlContainer = document.getElementById('satellite-knmi-url-container');
        const nasaUrlContainer = document.getElementById('satellite-nasa-url-container');
        const customUrlContainer = document.getElementById('satellite-custom-url-container');
        const zoomContainer = document.getElementById('satellite-zoom-container');
        
        // Show/hide URL containers based on provider
        if (knmiUrlContainer) {
            knmiUrlContainer.style.display = selectedProvider === 'knmi' ? 'block' : 'none';
        }
        if (nasaUrlContainer) {
            nasaUrlContainer.style.display = selectedProvider === 'nasa' ? 'block' : 'none';
        }
        if (customUrlContainer) {
            customUrlContainer.style.display = selectedProvider === 'custom' ? 'block' : 'none';
        }
        if (zoomContainer) {
            zoomContainer.style.display = selectedProvider === 'nasa' ? 'block' : 'none';
        }
    }
    
    // Make function globally available
    window.updateSatelliteProviderSettings = updateSatelliteProviderSettings;
    
    // Initialize when DOM is ready
    function initSatelliteSettings() {
        updateSatelliteProviderSettings();
        const select = document.querySelector('select[name="satellite_provider"]');
        if (select) {
            select.addEventListener('change', updateSatelliteProviderSettings);
        }
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSatelliteSettings);
    } else {
        // DOM already loaded
        setTimeout(initSatelliteSettings, 100);
    }
})();
@endif
</script>
@endif

<script>
function togglePassword(id) {
    const input = document.getElementById(id);
    const eyeOn = document.getElementById(id + '_eye');
    const eyeOff = document.getElementById(id + '_eye_off');
    
    if (input.type === 'password') {
        input.type = 'text';
        eyeOn.classList.add('hidden');
        eyeOff.classList.remove('hidden');
    } else {
        input.type = 'password';
        eyeOn.classList.remove('hidden');
        eyeOff.classList.add('hidden');
    }
}

const testApiUrl = @json(route('admin.settings.test-api', [], false));

async function testConnection(service) {
    const resultDiv = document.getElementById('testResult');
    resultDiv.classList.remove('hidden');
    resultDiv.innerHTML = `<div class="flex items-center text-gray-600 dark:text-gray-400"><svg class="animate-spin h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" @@cx="12" @@cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>${@json(__('Testing connection...'))}</div>`;
    
    try {
        const url = `${testApiUrl}?service=${encodeURIComponent(service)}`;
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ service })
        });
        const contentType = response.headers.get('content-type') || '';
        const data = contentType.includes('application/json')
            ? await response.json()
            : { success: false, message: `HTTP ${response.status}` };
        if (!response.ok && !data.message) {
            data.message = `HTTP ${response.status}`;
        }
        
        if (data.success) {
            resultDiv.innerHTML = `<div class="flex items-center text-green-600 dark:text-green-400"><svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>${data.message}</div>`;
        } else {
            resultDiv.innerHTML = `<div class="flex items-center text-red-600 dark:text-red-400"><svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>${data.message}</div>`;
        }
    } catch (e) {
        resultDiv.innerHTML = `<div class="flex items-center text-red-600 dark:text-red-400"><svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>${@json(__('Connection error:'))} ${e.message}</div>`;
    }
}
</script>
@endsection
