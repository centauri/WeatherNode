@extends('layouts.admin')

@section('title', __('Admin Dashboard'))

@php
    $activeUnits = $activeUnits ?? 'metric';
@endphp

@section('content')
<!-- Telemetry Status Card (Prominent) -->
<div class="mb-6">
    <div class="bg-gradient-to-r from-slate-800 to-sky-700 rounded-xl shadow-lg p-4 md:p-6 text-white">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="flex items-center space-x-4">
                <div class="p-3 rounded-full bg-white/20 flex-shrink-0">
                    <svg class="w-6 h-6 md:w-8 md:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-base md:text-lg font-semibold">{{ __('Community Telemetry') }}</h3>
                    <p class="text-xs md:text-sm text-sky-100">
                        @if($telemetryEnabled)
                            {{ __('Your station is shared on the community map') }}
                        @else
                            {{ __('Share your station with the community') }}
                        @endif
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2 md:gap-4">
                <div x-data="{ telemetryEnabled: {{ $telemetryEnabled ? 'true' : 'false' }}, labelEnable: @json(__('Enable')), labelDisable: @json(__('Disable')) }" class="flex items-center">
                    <form action="{{ route('admin.settings.telemetry.update') }}" method="POST" class="inline">
                        @csrf
                        <input type="hidden" name="telemetry_enabled" :value="telemetryEnabled ? '0' : '1'">
                        <input type="hidden" name="github_repo" value="{{ \App\Models\Setting::getValue('telemetry.github_repo', 'centauri/community-stations') }}">
                        <input type="hidden" name="github_file" value="{{ \App\Models\Setting::getValue('telemetry.github_file', 'stations.json') }}">
                        <button type="submit"
                                @click="telemetryEnabled = !telemetryEnabled"
                                :class="telemetryEnabled ? 'bg-white text-sky-700' : 'bg-white/20 text-white border-2 border-white'"
                                class="px-3 py-1.5 md:px-4 md:py-2 rounded-lg font-medium transition-colors text-sm md:text-base">
                            <span x-text="telemetryEnabled ? labelDisable : labelEnable"></span>
                        </button>
                    </form>
                </div>
                <a href="{{ route('admin.settings.telemetry') }}" 
                   class="px-3 py-1.5 md:px-4 md:py-2 bg-white/20 hover:bg-white/30 text-white rounded-lg font-medium transition-colors text-sm md:text-base">
                    {{ __('Configure') }}
                </a>
                <a href="{{ route('weather.community-stations') }}" 
                   target="_blank"
                   class="px-3 py-1.5 md:px-4 md:py-2 bg-white/20 hover:bg-white/30 text-white rounded-lg font-medium transition-colors text-sm md:text-base">
                    {{ __('View Map') }}
                </a>
            </div>
        </div>
        @if($telemetryEnabled && $telemetryLastUpdated)
        <div class="mt-4 pt-4 border-t border-white/20">
            <p class="text-xs md:text-sm text-sky-100">
                {{ __('Last updated') }}: {{ \Carbon\Carbon::parse($telemetryLastUpdated)->format('Y-m-d H:i:s') }}
            </p>
        </div>
        @endif
    </div>
</div>

<div class="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-4 gap-3 md:gap-6 mb-6 md:mb-8">
    <!-- Stats Cards -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-3 md:p-6">
        <div class="flex items-center">
            <div class="p-2 md:p-3 rounded-full bg-blue-100 dark:bg-blue-900 flex-shrink-0">
                <svg class="w-5 h-5 md:w-6 md:h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
            </div>
            <div class="ml-2 md:ml-4 min-w-0">
                <p class="text-xs md:text-sm font-medium text-gray-500 dark:text-gray-400 truncate">{{ __('Total Readings') }}</p>
                <p class="text-lg md:text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($stats['total_readings']) }}</p>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-3 md:p-6">
        <div class="flex items-center">
            <div class="p-2 md:p-3 rounded-full bg-green-100 dark:bg-green-900 flex-shrink-0">
                <svg class="w-5 h-5 md:w-6 md:h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
            <div class="ml-2 md:ml-4 min-w-0">
                <p class="text-xs md:text-sm font-medium text-gray-500 dark:text-gray-400 truncate">{{ __('Daily Summaries') }}</p>
                <p class="text-lg md:text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($stats['daily_summaries']) }}</p>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-3 md:p-6">
        <div class="flex items-center">
            <div class="p-2 md:p-3 rounded-full bg-purple-100 dark:bg-purple-900 flex-shrink-0">
                <svg class="w-5 h-5 md:w-6 md:h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
            </div>
            <div class="ml-2 md:ml-4 min-w-0">
                <p class="text-xs md:text-sm font-medium text-gray-500 dark:text-gray-400 truncate">{{ __('Users') }}</p>
                <p class="text-lg md:text-2xl font-semibold text-gray-900 dark:text-white">{{ $stats['users'] }}</p>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-3 md:p-6">
        <div class="flex items-center">
            <div class="p-2 md:p-3 rounded-full bg-orange-100 dark:bg-orange-900 flex-shrink-0">
                <svg class="w-5 h-5 md:w-6 md:h-6 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div class="ml-2 md:ml-4 min-w-0">
                <p class="text-xs md:text-sm font-medium text-gray-500 dark:text-gray-400 truncate">{{ __('Last Reading') }}</p>
                <p class="text-sm md:text-base font-semibold text-gray-900 dark:text-white truncate">
                    {{ $stats['last_reading'] ? $stats['last_reading']->diffForHumans() : __('No data') }}
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Station Info & Battery Status Row -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 md:gap-6 mb-6 md:mb-8">
    <!-- Station Info -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Weather Station Info') }}</h2>
            <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 rounded-full">{{ __('Online') }}</span>
        </div>
        @if($stationInfo['type'])
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">{{ __('Type') }}</span>
                    <span class="font-medium text-gray-900 dark:text-white">{{ $stationInfo['type'] }}</span>
                </div>
                @if($stationInfo['model'])
                <div class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">{{ __('Model') }}</span>
                    <span class="font-medium text-gray-900 dark:text-white">{{ $stationInfo['model'] }}</span>
                </div>
                @endif
                @if($stationInfo['runtime_hours'])
                <div class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">{{ __('Uptime') }}</span>
                    <span class="font-medium text-gray-900 dark:text-white">
                        @if($stationInfo['runtime_hours'] > 24)
                            {{ round($stationInfo['runtime_hours'] / 24, 1) }} {{ __('days') }}
                        @else
                            {{ $stationInfo['runtime_hours'] }} {{ __('hours') }}
                        @endif
                    </span>
                </div>
                @endif
                @if($stationInfo['freq'])
                <div class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">{{ __('Frequency') }}</span>
                    <span class="font-medium text-gray-900 dark:text-white">{{ $stationInfo['freq'] }}</span>
                </div>
                @endif
            </div>
        @else
            <p class="text-gray-500 dark:text-gray-400 text-sm">{{ __('No station data available.') }}</p>
        @endif
    </div>

    <!-- Battery Status -->
    <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">🔋 {{ __('Battery Status') }}</h2>
            @php
                $lowBatteries = collect($batteryStatus)->where('state', 'low')->count();
            @endphp
            @if($lowBatteries > 0)
                <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 rounded-full">
                    {{ $lowBatteries }} {{ __('Low!') }}
                </span>
            @else
                <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 rounded-full">
                    {{ __('All OK') }}
                </span>
            @endif
        </div>
        
        @if(count($batteryStatus) > 0)
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($batteryStatus as $battery)
                    @php
                        $tone = match ($battery['state']) {
                            'good' => ['bg-green-50 dark:bg-green-900/20', 'text-green-500', 'text-green-600 dark:text-green-400'],
                            'medium' => ['bg-yellow-50 dark:bg-yellow-900/20', 'text-yellow-500', 'text-yellow-600 dark:text-yellow-400'],
                            'unknown' => ['bg-gray-50 dark:bg-gray-700/30', 'text-gray-400', 'text-gray-500 dark:text-gray-400'],
                            default => ['bg-red-50 dark:bg-red-900/20', 'text-red-500', 'text-red-600 dark:text-red-400'],
                        };
                    @endphp
                    {{-- Name above, status below: the tiles are narrow, and long names
                         (German, Greek, Polish) then wrap between words only. --}}
                    <div class="flex items-center gap-3 p-3 rounded-lg {{ $tone[0] }}">
                        <span class="shrink-0 {{ $tone[1] }}">
                            <x-battery-icon :icon="$battery['icon']" :percentage="$battery['percentage']" class="w-6 h-6" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 break-words">{{ $battery['name'] }}</p>
                            <p class="text-sm font-semibold {{ $tone[2] }}">{{ $battery['display'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-gray-500 dark:text-gray-400 text-sm">{{ __('No battery information available.') }}</p>
        @endif
    </div>
</div>

<!-- Active Sensors & Quick Actions -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6 mb-6 md:mb-8">
    <!-- Active Sensors -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 md:p-6">
        @php
            $failedSensors = collect($sensorStates)->where('state', 'failed');
            $staleSensors = collect($sensorStates)->where('state', 'stale');
        @endphp

        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base md:text-lg font-semibold text-gray-900 dark:text-white">📡 {{ __('Sensors') }}</h2>
            @if($failedSensors->isNotEmpty())
                <span class="px-2 py-0.5 md:py-1 text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 rounded-full">
                    {{ $failedSensors->count() }} {{ __('not reporting') }}
                </span>
            @else
                <span class="px-2 py-0.5 md:py-1 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 rounded-full">
                    {{ count($sensorStates) }} {{ __('reporting') }}
                </span>
            @endif
        </div>

        @if(count($sensorStates) > 0)
            @if($failedSensors->isNotEmpty())
                <p class="mb-3 text-xs text-red-700 dark:text-red-400">
                    {{ __('These sensors were reporting recently but have gone quiet. Check power, batteries or radio contact.') }}
                </p>
            @endif
            <div class="flex flex-wrap gap-1.5 md:gap-2">
                @foreach($sensorStates as $sensor)
                    @php
                        $chip = match($sensor['state']) {
                            'failed' => ['bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300', 'bg-red-500'],
                            'stale' => ['bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300', 'bg-amber-500'],
                            default => ['bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300', 'bg-green-500'],
                        };
                    @endphp
                    <span class="inline-flex items-center px-2 py-1 md:px-3 md:py-1.5 rounded-full text-xs md:text-sm font-medium {{ $chip[0] }}"
                          @if($sensor['last_seen']) title="{{ __('Last seen') }}: {{ $sensor['last_seen']->format('Y-m-d H:i') }}" @endif>
                        <span class="w-1.5 h-1.5 md:w-2 md:h-2 rounded-full {{ $chip[1] }} mr-1.5 md:mr-2"></span>
                        <span>{{ $sensor['label'] }}</span>
                        @if($sensor['state'] !== 'ok' && $sensor['minutes_ago'] !== null)
                            <span class="mx-1.5 opacity-50" aria-hidden="true">&middot;</span>
                            <span class="opacity-75 whitespace-nowrap">{{ $sensor['last_seen']->diffForHumans() }}</span>
                        @endif
                    </span>
                @endforeach
            </div>
        @else
            <p class="text-gray-500 dark:text-gray-400 text-sm">{{ __('No sensor data available.') }}</p>
        @endif
        
        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <a href="{{ route('admin.settings.group', 'sensors') }}" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-medium">
                {{ __('Sensor settings') }} →
            </a>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 md:p-6">
        <h2 class="text-base md:text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ __('Quick Actions') }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 md:gap-4">
            <a href="{{ route('admin.settings.group', 'ecowitt') }}" 
               class="flex items-center p-3 md:p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                <svg class="w-6 h-6 md:w-8 md:h-8 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                </svg>
                <span class="ml-3 text-sm md:text-base font-medium text-gray-700 dark:text-gray-200">{{ __('Configure APIs') }}</span>
            </a>

            <a href="{{ route('admin.settings.group', 'station') }}" 
               class="flex items-center p-3 md:p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                <svg class="w-6 h-6 md:w-8 md:h-8 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                </svg>
                <span class="ml-3 text-sm md:text-base font-medium text-gray-700 dark:text-gray-200">{{ __('Station Setup') }}</span>
            </a>

            <a href="{{ route('admin.users.index') }}" 
               class="flex items-center p-3 md:p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                <svg class="w-6 h-6 md:w-8 md:h-8 text-purple-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                </svg>
                <span class="ml-3 text-sm md:text-base font-medium text-gray-700 dark:text-gray-200">{{ __('Manage Users') }}</span>
            </a>

            <a href="{{ route('home') }}" target="_blank"
               class="flex items-center p-3 md:p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                <svg class="w-6 h-6 md:w-8 md:h-8 text-orange-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                <span class="ml-3 text-sm md:text-base font-medium text-gray-700 dark:text-gray-200">{{ __('View Site') }}</span>
            </a>
        </div>
    </div>
</div>

<!-- System Status -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6 mb-6 md:mb-8">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ __('System Status') }}</h2>
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-gray-600 dark:text-gray-400">{{ __('Database') }}</span>
                <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 rounded-full">{{ __('Connected') }}</span>
            </div>
            @if($stats['database_size'])
            <div class="flex items-center justify-between">
                <span class="text-gray-600 dark:text-gray-400">{{ __('Database Size') }}</span>
                <span class="text-gray-900 dark:text-white font-medium">{{ $stats['database_size'] }} MB</span>
            </div>
            @endif
            <div class="flex items-center justify-between">
                <span class="text-gray-600 dark:text-gray-400">{{ __('PHP Version') }}</span>
                <span class="text-gray-900 dark:text-white font-medium">{{ phpversion() }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-gray-600 dark:text-gray-400">{{ __('Laravel Version') }}</span>
                <span class="text-gray-900 dark:text-white font-medium">{{ app()->version() }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-gray-600 dark:text-gray-400">{{ __('App Version') }}</span>
                <span class="text-gray-900 dark:text-white font-medium">{{ \App\Services\VersionService::getAppVersion() }}</span>
            </div>
        </div>
    </div>

    <!-- Ecowitt Endpoint Info -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        @php
            $ecowittSecureMode = (bool) \App\Models\Setting::getValue('ecowitt.secure_mode', false);
            $ecowittSecureToken = trim((string) \App\Models\Setting::getValue('ecowitt.secure_token', ''));
            $ecowittPath = '/api/ecowitt/receive' . (($ecowittSecureMode && $ecowittSecureToken !== '') ? '/' . $ecowittSecureToken : '');
        @endphp
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">📥 {{ __('Ecowitt Data Endpoint') }}</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">
            {{ __('Configure your Ecowitt device to push data to:') }}
        </p>
        <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-3 font-mono text-sm break-all">
            {{ url($ecowittPath) }}
        </div>
        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            <strong>{{ __('Protocol') }}:</strong> Ecowitt (HTTP POST)<br>
            <strong>{{ __('Path') }}:</strong> {{ $ecowittPath }}<br>
            <strong>{{ __('Secure Mode') }}:</strong> {{ $ecowittSecureMode ? __('Enabled') : __('Disabled') }}<br>
            <strong>{{ __('Interval') }}:</strong> {{ __('60 seconds recommended') }}
        </p>
    </div>
</div>

<!-- Recent Readings -->
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 md:p-6">
    <h2 class="text-base md:text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ __('Recent Weather Readings') }}</h2>
    
    @if($recentReadings->isEmpty())
        <p class="text-gray-500 dark:text-gray-400">{{ __('No weather readings yet. Configure your Ecowitt API to start collecting data.') }}</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead>
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Time') }}</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Temp') }}</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Indoor') }}</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Humidity') }}</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Wind') }}</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Rain') }}</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Pressure') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($recentReadings as $reading)
                    <tr>
                        <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">{{ $reading->recorded_at->format('d-m H:i') }}</td>
                        <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">
                            {{ $reading->temperature !== null ? $unit->temperature($reading->temperature, $activeUnits) : '--' }}
                        </td>
                        <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">
                            {{ $reading->temperature_indoor !== null ? $unit->temperature($reading->temperature_indoor, $activeUnits) : '--' }}
                        </td>
                        <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">{{ $reading->humidity }}%</td>
                        <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">
                            {{ $reading->wind_speed !== null ? $unit->wind($reading->wind_speed, $activeUnits) : '--' }}
                        </td>
                        <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">
                            {{ $reading->rain_daily !== null ? $unit->rain($reading->rain_daily, $activeUnits) : $unit->rain(0, $activeUnits) }}
                        </td>
                        <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">
                            {{ $reading->pressure_rel !== null ? $unit->pressure($reading->pressure_rel, $activeUnits) : '--' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
