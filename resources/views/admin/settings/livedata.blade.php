@extends('layouts.admin')

@section('title', __('Live Data Source'))

@section('content')
@php
    $format = \App\Models\Setting::getValue('livedata.format', 'ecoLcl');
    $fetchMode = \App\Models\Setting::getValue('livedata.fetch_mode', 'file');
    $filePath = \App\Models\Setting::getValue('livedata.file_path', './ecowitt/ecco_lcl.arr');
    $apiUrl = \App\Models\Setting::getValue('livedata.api_url', '');

    // An example file for each local format. The Ecowitt file is the shipped
    // default and means nothing to these formats, so it is not shown for them.
    $ecowittDefaultPath = './ecowitt/ecco_lcl.arr';
    $realtimeExample = [
        'placeholder' => '/path/to/realtime.txt',
        'hint' => __('Path or web address of your realtime.txt file, for example /path/to/realtime.txt or http://192.168.1.10/realtime.txt'),
    ];
    $clientrawExample = [
        'placeholder' => '/path/to/clientraw.txt',
        'hint' => __('Path or web address of your clientraw.txt file, for example /path/to/clientraw.txt or http://192.168.1.10/clientraw.txt'),
    ];
    $localFileExamples = [
        'cumulus' => $realtimeExample,
        'weewx' => [
            'placeholder' => '/var/tmp/realtime.txt',
            'hint' => __('Path or web address of the realtime.txt that WeeWX writes, for example /var/tmp/realtime.txt or http://raspberrypi.local/weewx/realtime.txt'),
        ],
        'weathercat' => $realtimeExample,
        'meteohub' => $clientrawExample,
        'wswin' => $clientrawExample,
        'weatherlink' => $realtimeExample,
        'wifilogger' => $realtimeExample,
        'MB_rt' => $realtimeExample,
        'wd' => $clientrawExample,
    ];
    $localFileExample = $localFileExamples[$format] ?? $realtimeExample;
    $shownFilePath = isset($localFileExamples[$format]) && $filePath === $ecowittDefaultPath ? '' : $filePath;
    
    
    // WeatherLink demo mode requires API key (only API credential allowed on livedata page)
    $wlDemoApiKey = \App\Models\Setting::getValue('weatherlink.api_key', '');
@endphp

<div class="w-full">
    <nav class="mb-6 text-sm">
        <ol class="flex items-center space-x-2">
            <li><a href="{{ route('admin.settings.index') }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">{{ __('Settings') }}</a></li>
            <li><svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></li>
            <li class="text-gray-900 dark:text-white font-medium">{{ __('Live Data Source') }}</li>
        </ol>
    </nav>

    <div class="mb-8">
        <div class="flex items-center space-x-4">
            <div class="p-3 rounded-xl bg-emerald-100 dark:bg-emerald-900/30">
                <svg class="w-8 h-8 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Live Data Source Configuration') }}</h1>
                <p class="text-gray-500 dark:text-gray-400">{{ __('Select your primary live weather data source. Configure API credentials on the dedicated data source pages.') }}</p>
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

    <form action="{{ route('admin.settings.update', 'livedata') }}" method="POST" class="space-y-6">
        @csrf

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm divide-y divide-gray-100 dark:divide-gray-700">
            {{-- Source Selector (FIRST) --}}
            <div class="p-5">
                <div class="space-y-2">
                    <label for="livedata_format" class="block text-sm font-medium text-gray-900 dark:text-white">{{ __('Primary Live Data Source') }}</label>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('Select your data source first; configuration fields will appear below.') }}</p>
                    <select name="livedata_format"
                            id="livedata_format"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">
                        <option value="ecoLcl" {{ $format === 'ecoLcl' ? 'selected' : '' }}>{{ __('Ecowitt Local (push)') }}</option>
                        <option value="ecowittAPI" {{ $format === 'ecowittAPI' ? 'selected' : '' }}>{{ __('Ecowitt Cloud API') }}</option>
                        <option value="wu" {{ $format === 'wu' ? 'selected' : '' }}>{{ __('Weather Underground') }}</option>
                        <option value="DWL" {{ $format === 'DWL' ? 'selected' : '' }}>{{ __('WeatherLink Cloud v1') }}</option>
                        <option value="DWL_v2api" {{ $format === 'DWL_v2api' ? 'selected' : '' }}>{{ __('WeatherLink Cloud v2') }}</option>
                        <option value="DWL_v2api_demo" {{ $format === 'DWL_v2api_demo' ? 'selected' : '' }}>{{ __('WeatherLink Cloud v2 (Demo Mode)') }}</option>
                        <option value="wf" {{ $format === 'wf' ? 'selected' : '' }}>{{ __('WeatherFlow') }}</option>
                        <option value="AWapi" {{ $format === 'AWapi' ? 'selected' : '' }}>{{ __('Ambient Weather API') }}</option>
                        <option value="cumulus" {{ $format === 'cumulus' ? 'selected' : '' }}>{{ __('Cumulus') }}</option>
                        <option value="weewx" {{ $format === 'weewx' ? 'selected' : '' }}>{{ __('WeeWX') }}</option>
                        <option value="weathercat" {{ $format === 'weathercat' ? 'selected' : '' }}>{{ __('WeatherCat') }}</option>
                        <option value="meteohub" {{ $format === 'meteohub' ? 'selected' : '' }}>{{ __('Meteohub') }}</option>
                        <option value="wswin" {{ $format === 'wswin' ? 'selected' : '' }}>{{ __('WSWIN') }}</option>
                        <option value="weatherlink" {{ $format === 'weatherlink' ? 'selected' : '' }}>{{ __('WeatherLink Local') }}</option>
                        <option value="wifilogger" {{ $format === 'wifilogger' ? 'selected' : '' }}>{{ __('WiFiLogger') }}</option>
                        <option value="MB_rt" {{ $format === 'MB_rt' ? 'selected' : '' }}>{{ __('Meteobridge (realtime.txt)') }}</option>
                        <option value="wd" {{ $format === 'wd' ? 'selected' : '' }}>{{ __('Weather Display') }}</option>
                    </select>
                </div>
            </div>

            {{-- Ecowitt Local (push) Configuration --}}
            <div class="p-5" id="source-ecoLcl-config" style="display: {{ $format === 'ecoLcl' ? 'block' : 'none' }};">
                <div class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg text-sm text-blue-800 dark:text-blue-200">
                    <p class="font-semibold mb-1">{{ __('Configure Ecowitt push') }}</p>
                    <p class="mb-2">{{ __('The console set-up, passkey and push security are on the Ecowitt settings page.') }}</p>
                    <a href="{{ route('admin.settings.group', 'ecowitt') }}" class="inline-flex items-center text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200 font-medium">
                        {{ __('Go to Ecowitt Settings') }}
                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>
            </div>

            {{-- Ecowitt Cloud API Configuration --}}
            <div class="p-5" id="source-ecowittAPI-config" style="display: {{ $format === 'ecowittAPI' ? 'block' : 'none' }};">
                <div class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mr-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                        <div class="text-sm text-blue-800 dark:text-blue-200">
                            <p class="font-semibold mb-1">{{ __('Configure Ecowitt Cloud API') }}</p>
                            <p class="mb-2">{{ __('Configure your Ecowitt API credentials on the dedicated Ecowitt settings page.') }}</p>
                            <a href="{{ route('admin.settings.group', 'ecowitt') }}" class="inline-flex items-center text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200 font-medium">
                                {{ __('Go to Ecowitt Settings') }}
                                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Weather Underground Configuration --}}
            <div class="p-5" id="source-wu-config" style="display: {{ $format === 'wu' ? 'block' : 'none' }};">
                <div class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mr-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                        <div class="text-sm text-blue-800 dark:text-blue-200">
                            <p class="font-semibold mb-1">{{ __('Configure Weather Underground') }}</p>
                            <p class="mb-2">{{ __('Configure your Weather Underground station ID and API key on the dedicated Weather Underground settings page.') }}</p>
                            <a href="{{ route('admin.settings.group', 'wunderground') }}" class="inline-flex items-center text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200 font-medium">
                                {{ __('Go to Weather Underground Settings') }}
                                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- WeatherLink v1 Configuration --}}
            <div class="p-5" id="source-DWL-config" style="display: {{ $format === 'DWL' ? 'block' : 'none' }};">
                <div class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mr-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                        <div class="text-sm text-blue-800 dark:text-blue-200">
                            <p class="font-semibold mb-1">{{ __('Configure WeatherLink Cloud v1') }}</p>
                            <p class="mb-2">{{ __('Configure your WeatherLink v1 API credentials on the dedicated WeatherLink settings page.') }}</p>
                            <a href="{{ route('admin.settings.group', 'weatherlink') }}" class="inline-flex items-center text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200 font-medium">
                                {{ __('Go to WeatherLink Settings') }}
                                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- WeatherLink v2 Configuration --}}
            <div class="p-5" id="source-DWL_v2api-config" style="display: {{ $format === 'DWL_v2api' ? 'block' : 'none' }};">
                <div class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mr-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                        <div class="text-sm text-blue-800 dark:text-blue-200">
                            <p class="font-semibold mb-1">{{ __('Configure WeatherLink Cloud v2') }}</p>
                            <p class="mb-2">{{ __('Configure your WeatherLink v2 API credentials on the dedicated WeatherLink settings page.') }}</p>
                            <a href="{{ route('admin.settings.group', 'weatherlink') }}" class="inline-flex items-center text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200 font-medium">
                                {{ __('Go to WeatherLink Settings') }}
                                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- WeatherLink v2 Demo Mode --}}
            <div class="p-5" id="source-DWL_v2api_demo-config" style="display: {{ $format === 'DWL_v2api_demo' ? 'block' : 'none' }};">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ __('WeatherLink Cloud v2 (Demo Mode)') }}</h3>
                <div class="space-y-4">
                    <div class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mr-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                            </svg>
                            <div class="text-sm text-blue-800 dark:text-blue-200">
                                <p class="font-semibold mb-1">{{ __('Demo Mode') }}</p>
                                <p>{{ __('Demo mode uses the Davis Instruments demo station. Both API key and API secret are required. Configure your API secret on the') }} <a href="{{ route('admin.settings.group', 'weatherlink') }}" class="underline font-semibold">{{ __('WeatherLink settings page') }}</a>.</p>
                                <p class="mt-2 text-xs">{{ __('Demo Station ID: 9722cfc3-a4ef-47b9-befb-72f52592d6ed') }}</p>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label for="wl_demo_api_key" class="block text-sm font-medium text-gray-900 dark:text-white mb-2">{{ __('API Key') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('Your WeatherLink v2 API key from weatherlink.com/account (required for demo mode)') }}</p>
                        <input type="text"
                               name="wl_demo_api_key"
                               id="wl_demo_api_key"
                               autocomplete="off"
                               data-lpignore="true"
                               style="-webkit-text-security: disc; text-security: disc;"
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400 font-mono"
                               placeholder="{{ $wlDemoApiKey ? __('(configured - enter new value to change)') : __('Enter API key') }}" />
                        @if($wlDemoApiKey)
                            <p class="mt-1 text-xs text-green-600 dark:text-green-400">{{ __('Configured (leave empty to keep current value)') }}</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- WeatherFlow Configuration --}}
            <div class="p-5" id="source-wf-config" style="display: {{ $format === 'wf' ? 'block' : 'none' }};">
                <div class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mr-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                        <div class="text-sm text-blue-800 dark:text-blue-200">
                            <p class="font-semibold mb-1">{{ __('Configure WeatherFlow') }}</p>
                            <p class="mb-2">{{ __('Configure your WeatherFlow station ID on the dedicated WeatherFlow settings page.') }}</p>
                            <a href="{{ route('admin.settings.group', 'weatherflow') }}" class="inline-flex items-center text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200 font-medium">
                                {{ __('Go to WeatherFlow Settings') }}
                                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Ambient Weather Configuration --}}
            <div class="p-5" id="source-AWapi-config" style="display: {{ $format === 'AWapi' ? 'block' : 'none' }};">
                <div class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mr-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                        <div class="text-sm text-blue-800 dark:text-blue-200">
                            <p class="font-semibold mb-1">{{ __('Configure Ambient Weather') }}</p>
                            <p class="mb-2">{{ __('Configure your Ambient Weather API credentials on the dedicated Ambient Weather settings page.') }}</p>
                            <a href="{{ route('admin.settings.group', 'ambient') }}" class="inline-flex items-center text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200 font-medium">
                                {{ __('Go to Ambient Weather Settings') }}
                                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Local Files/API Configuration (for cumulus, weewx, etc.) --}}
            <div class="p-5" id="source-local-config" style="display: {{ in_array($format, ['cumulus', 'weewx', 'weathercat', 'meteohub', 'wswin', 'weatherlink', 'wifilogger', 'MB_rt', 'wd']) ? 'block' : 'none' }};">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ __('Local File/API Configuration') }}</h3>
                <div class="space-y-4">
                    <div>
                        <label for="livedata_fetch_mode" class="block text-sm font-medium text-gray-900 dark:text-white mb-2">{{ __('Fetch Mode') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('How to fetch the live data') }}</p>
                        <select name="livedata_fetch_mode"
                                id="livedata_fetch_mode"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">
                            <option value="file" {{ $fetchMode === 'file' ? 'selected' : '' }}>{{ __('Local file') }}</option>
                            <option value="local_api" {{ $fetchMode === 'local_api' ? 'selected' : '' }}>{{ __('Local API URL') }}</option>
                        </select>
                    </div>
                    <div id="local-file-config" style="display: {{ $fetchMode === 'file' ? 'block' : 'none' }};">
                        <label for="livedata_file_path" class="block text-sm font-medium text-gray-900 dark:text-white mb-2">{{ __('File Path') }}</label>
                        <p id="livedata_file_path_hint" class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ $localFileExample['hint'] }}</p>
                        <input type="text"
                               name="livedata_file_path"
                               id="livedata_file_path"
                               value="{{ $shownFilePath }}"
                               data-saved="{{ $filePath }}"
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400"
                               placeholder="{{ $localFileExample['placeholder'] }}" />
                        <p id="livedata_weewx_guide" class="mt-2 text-xs" style="display: {{ $format === 'weewx' ? 'block' : 'none' }};">
                            <a href="https://github.com/centauri/WeatherNode/blob/main/docs/WEEWX.md" target="_blank" rel="noopener" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200 font-medium">{{ __('How to set up WeeWX') }}</a>
                        </p>
                    </div>
                    <div id="local-api-config" style="display: {{ $fetchMode === 'local_api' ? 'block' : 'none' }};">
                        <label for="livedata_api_url" class="block text-sm font-medium text-gray-900 dark:text-white mb-2">{{ __('API URL') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('Local API URL for live data (e.g., http://localhost/api/current)') }}</p>
                        <input type="text"
                               name="livedata_api_url"
                               id="livedata_api_url"
                               value="{{ $apiUrl }}"
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400"
                               placeholder="http://localhost/api/current" />
                    </div>
                </div>
            </div>

            {{-- Test Connection Button --}}
            <div class="p-5">
                <button type="button" id="test-connection-btn" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 text-white font-medium rounded-lg transition shadow-sm">
                    {{ __('Test Connection') }}
                </button>
                <div id="test-result" class="mt-4 hidden"></div>
            </div>

            {{-- Yearly Rain Data Source --}}
            <div class="p-5 border-t border-gray-100 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ __('Yearly Rain Data Source') }}</h3>
                <div class="space-y-4">
                    <div class="p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                        <p class="text-sm text-blue-800 dark:text-blue-200">
                            {{ __('Choose whether to use yearly rain totals from your weather station or calculate them from historical database records.') }}
                        </p>
                    </div>
                    <div>
                        <label for="rain_yearly_source" class="block text-sm font-medium text-gray-900 dark:text-white mb-2">{{ __('Yearly Rain Source') }}</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __('Select how yearly rain totals should be determined') }}</p>
                        <select name="rain_yearly_source"
                                id="rain_yearly_source"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400">
                            <option value="station" {{ (\App\Models\Setting::getValue('livedata.rain_yearly_source', 'station') === 'station') ? 'selected' : '' }}>
                                {{ __('Use Station Data') }} - {{ __('Trust the yearly total from your weather station') }}
                            </option>
                            <option value="calculated" {{ (\App\Models\Setting::getValue('livedata.rain_yearly_source', 'station') === 'calculated') ? 'selected' : '' }}>
                                {{ __('Calculate from Database') }} - {{ __('Sum daily rain from database history') }}
                            </option>
                        </select>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            {{ __('Station data: Uses the yearly total provided by your weather station hardware. Database: Calculates yearly total by summing all daily rainfall records from January 1st to today.') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <a href="{{ route('admin.settings.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
                ← {{ __('Back to Settings') }}
            </a>
            <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 text-white font-medium rounded-lg transition shadow-sm">
                {{ __('Save Changes') }}
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
(function () {
    const formatSelect = document.getElementById('livedata_format');
    const fetchModeSelect = document.getElementById('livedata_fetch_mode');
    const testBtn = document.getElementById('test-connection-btn');
    const testResult = document.getElementById('test-result');
    
    // Source config containers (only for sources that need livedata-specific config)
    const configs = {
        'ecoLcl': document.getElementById('source-ecoLcl-config'),
        'ecowittAPI': document.getElementById('source-ecowittAPI-config'),
        'wu': document.getElementById('source-wu-config'),
        'DWL': document.getElementById('source-DWL-config'),
        'DWL_v2api': document.getElementById('source-DWL_v2api-config'),
        'DWL_v2api_demo': document.getElementById('source-DWL_v2api_demo-config'),
        'wf': document.getElementById('source-wf-config'),
        'AWapi': document.getElementById('source-AWapi-config'),
    };
    
    const localConfig = document.getElementById('source-local-config');
    const localFileConfig = document.getElementById('local-file-config');
    const localApiConfig = document.getElementById('local-api-config');
    
    const localSources = ['cumulus', 'weewx', 'weathercat', 'meteohub', 'wswin', 'weatherlink', 'wifilogger', 'MB_rt', 'wd'];
    const localFileExamples = @json($localFileExamples);
    const ecowittDefaultPath = @json($ecowittDefaultPath);
    const filePathInput = document.getElementById('livedata_file_path');
    const filePathHint = document.getElementById('livedata_file_path_hint');
    const weewxGuide = document.getElementById('livedata_weewx_guide');

    function updateLocalFileExample() {
        const format = formatSelect.value;
        const example = localFileExamples[format];
        if (!example || !filePathInput) {
            // Back on Ecowitt: put its saved path back so saving keeps it.
            if (filePathInput && filePathInput.value === '') filePathInput.value = filePathInput.dataset.saved || '';
            return;
        }
        filePathInput.placeholder = example.placeholder;
        if (filePathHint) filePathHint.textContent = example.hint;
        if (weewxGuide) weewxGuide.style.display = format === 'weewx' ? 'block' : 'none';
        if (filePathInput.value === ecowittDefaultPath) filePathInput.value = '';
    }
    
    // Sources that only show info/link (no form fields)
    const infoOnlySources = ['ecowittAPI', 'wu', 'DWL', 'DWL_v2api', 'wf', 'AWapi'];

    function updateVisibility() {
        const format = formatSelect.value;
        
        // Hide all configs
        Object.values(configs).forEach(config => {
            if (config) config.style.display = 'none';
        });
        if (localConfig) localConfig.style.display = 'none';
        
        // Show relevant config
        if (configs[format]) {
            configs[format].style.display = 'block';
        } else if (localSources.includes(format)) {
            localConfig.style.display = 'block';
        }
    }
    
    function updateLocalConfigVisibility() {
        const mode = fetchModeSelect?.value || 'file';
        if (localFileConfig) localFileConfig.style.display = mode === 'file' ? 'block' : 'none';
        if (localApiConfig) localApiConfig.style.display = mode === 'local_api' ? 'block' : 'none';
    }

    formatSelect?.addEventListener('change', updateVisibility);
    formatSelect?.addEventListener('change', updateLocalFileExample);
    fetchModeSelect?.addEventListener('change', updateLocalConfigVisibility);

    // Test connection
    testBtn?.addEventListener('click', async function() {
        const format = formatSelect.value;
        testResult.classList.add('hidden');
        testBtn.disabled = true;
        testBtn.textContent = '{{ __('Testing...') }}';

        try {
            const response = await fetch('{{ route('admin.settings.test-api') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    service: 'livedata',
                    format: format
                })
            });

            // Check if response is OK and is JSON
            if (!response.ok) {
                throw new Error('HTTP ' + response.status + ': ' + response.statusText);
            }

            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                throw new Error('Expected JSON but received: ' + contentType + '. Response: ' + text.substring(0, 200));
            }

            const data = await response.json();
            testResult.classList.remove('hidden');
            testResult.className = 'mt-4 p-4 rounded-lg ' + (data.success ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800' : 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800');
            testResult.innerHTML = '<p class="' + (data.success ? 'text-green-800 dark:text-green-200' : 'text-red-800 dark:text-red-200') + '">' + (data.message || (data.success ? 'Connection successful!' : 'Connection failed!')) + '</p>';
        } catch (error) {
            testResult.classList.remove('hidden');
            testResult.className = 'mt-4 p-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800';
            testResult.innerHTML = '<p class="text-red-800 dark:text-red-200">Error testing connection: ' + (error.message || 'Unknown error') + '</p>';
        } finally {
            testBtn.disabled = false;
            testBtn.textContent = '{{ __('Test Connection') }}';
        }
    });

    // Initialize visibility
    updateVisibility();
    updateLocalConfigVisibility();
})();
</script>
@endpush
@endsection
