@extends('layouts.admin')

@section('title', __('Settings'))

@section('content')
<div class="mb-6 md:mb-8">
    <h1 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">{{ __('Settings') }}</h1>
    <p class="mt-1 text-sm md:text-base text-gray-500 dark:text-gray-400">{{ __('Configure your weather station, data sources, and display options.') }}</p>
</div>

@php
    $liveStatusColor = 'gray';
    $liveStatusLabel = __('No data');
    if ($liveDataStatus['status'] === 'online') {
        $liveStatusColor = 'green';
        $liveStatusLabel = __('Online');
    } elseif ($liveDataStatus['status'] === 'stale') {
        $liveStatusColor = 'yellow';
        $liveStatusLabel = __('Stale');
    }
@endphp

<div class="mb-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700/50 p-3 md:p-4">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 md:gap-4">
        <div>
            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Live Data Status') }}</p>
            <p class="mt-1 text-base md:text-lg font-semibold text-gray-900 dark:text-white">
                {{ $liveDataStatus['format_label'] ?: __('Not set') }}
            </p>
            <p class="mt-1 text-xs md:text-sm text-gray-500 dark:text-gray-400">
                {{ $liveDataStatus['mode_label'] }}: {{ $liveDataStatus['mode_detail'] ?: __('Not set') }}
            </p>
        </div>
        <div class="sm:text-right">
            <span class="inline-flex items-center px-2 py-0.5 md:px-2.5 md:py-1 rounded-full text-xs font-medium bg-{{ $liveStatusColor }}-100 text-{{ $liveStatusColor }}-800 dark:bg-{{ $liveStatusColor }}-900/30 dark:text-{{ $liveStatusColor }}-300">
                {{ $liveStatusLabel }}
            </span>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ __('Last update') }}: {{ $liveDataStatus['last_update'] ? $liveDataStatus['last_update']->diffForHumans() : __('Never') }}
            </p>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="mb-8 flex flex-wrap gap-2 md:gap-4">
    <a href="{{ route('admin.settings.updates') }}" class="inline-flex items-center px-3 py-1.5 md:px-4 md:py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition text-sm md:text-base">
        <svg class="w-4 h-4 md:w-5 md:h-5 mr-1.5 md:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
        {{ __('Updates') }}
    </a>
    <a href="{{ route('admin.settings.group', 'notifications') }}" class="inline-flex items-center px-3 py-1.5 md:px-4 md:py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition text-sm md:text-base">
        <svg class="w-4 h-4 md:w-5 md:h-5 mr-1.5 md:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        <span class="hidden sm:inline">{{ __('Notifications') }}</span>
        <span class="sm:hidden">{{ __('Notify') }}</span>
    </a>
    <button onclick="testAllConnections()" class="inline-flex items-center px-3 py-1.5 md:px-4 md:py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition text-sm md:text-base">
        <svg class="w-4 h-4 md:w-5 md:h-5 mr-1.5 md:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span class="hidden sm:inline">{{ __('Test All Connections') }}</span>
        <span class="sm:hidden">{{ __('Test') }}</span>
    </button>
</div>

<!-- System Section - Notifications Highlight -->
@php
    $systemGroups = array_filter($groups, fn($g) => ($g['category'] ?? '') === 'system');
    $hasNotifications = isset($groups['notifications']);
@endphp

@if($hasNotifications)
<div class="mb-6 bg-gradient-to-r from-red-50 to-orange-50 dark:from-red-900/20 dark:to-orange-900/20 rounded-xl border-2 border-red-200 dark:border-red-800 p-4 md:p-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="flex items-center space-x-4">
            <div class="p-2 md:p-3 rounded-xl bg-red-100 dark:bg-red-900/30 flex-shrink-0">
                <svg class="w-6 h-6 md:w-8 md:h-8 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
            </div>
            <div>
                <h2 class="text-base md:text-lg font-bold text-gray-900 dark:text-white">{{ __('System Notifications') }}</h2>
                <p class="text-xs md:text-sm text-gray-600 dark:text-gray-400">{{ __('Configure email and webhook alerts for system events') }}</p>
            </div>
        </div>
        <a href="{{ route('admin.settings.group', 'notifications') }}" class="inline-flex items-center justify-center px-4 py-2 md:px-6 md:py-3 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition shadow-lg text-sm md:text-base w-full md:w-auto">
            <svg class="w-4 h-4 md:w-5 md:h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            {{ __('Configure Notifications') }}
        </a>
    </div>
</div>
@endif

<!-- Settings Groups Grid -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 md:gap-4">
    <!-- Open Data Sources (Special Card) -->
    <a href="{{ route('admin.settings.opendata') }}" 
       class="group relative bg-white dark:bg-gray-800 rounded-xl shadow-sm hover:shadow-lg transition-all duration-200 overflow-hidden">
        <div class="absolute top-0 left-0 right-0 h-1 bg-blue-500"></div>
        <div class="p-5">
            <div class="flex items-start justify-between">
                <div class="p-2.5 rounded-xl bg-blue-100 dark:bg-blue-900/30">
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <h3 class="mt-4 font-semibold text-gray-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition">
                {{ __('Open Data Sources') }}
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 line-clamp-2">
                {{ __('Manage open data sources from meteorological agencies worldwide') }}
            </p>
            <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">
                {{ __('KNMI, Met Office, NOAA, and more') }}
            </p>
        </div>
        <div class="absolute right-4 bottom-4 opacity-0 group-hover:opacity-100 transition transform translate-x-2 group-hover:translate-x-0">
            <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
        </div>
    </a>

    @foreach($groups as $groupKey => $group)
        @php
            $count = isset($settings[$groupKey]) ? $settings[$groupKey]->count() : 0;
            $hasApiKeys = in_array($groupKey, ['ecowitt', 'wunderground', 'weatherflow', 'weatherlink', 'ambient', 'netatmo', 'openweathermap', 'airquality', 'aviation']);
            $configured = true;
            if ($hasApiKeys && isset($settings[$groupKey])) {
                foreach ($settings[$groupKey] as $s) {
                    if ($s->type === 'encrypted' && empty($s->value)) {
                        $configured = false;
                        break;
                    }
                }
            }
        @endphp
        
        <a href="{{ $groupKey === 'widgets' ? route('admin.settings.widgets') : ($groupKey === 'appearance' ? route('admin.settings.appearance') : ($groupKey === 'integrations' ? route('admin.settings.integrations') : route('admin.settings.group', $groupKey))) }}" 
           class="group relative bg-white dark:bg-gray-800 rounded-xl shadow-sm hover:shadow-lg transition-all duration-200 overflow-hidden">
            
            <!-- Color accent bar -->
            <div class="absolute top-0 left-0 right-0 h-1 bg-{{ $group['color'] }}-500"></div>
            
            <div class="p-5">
                <div class="flex items-start justify-between">
                    <div class="p-2.5 rounded-xl bg-{{ $group['color'] }}-100 dark:bg-{{ $group['color'] }}-900/30">
                        @switch($group['icon'])
                            @case('location')
                                <svg class="w-6 h-6 text-{{ $group['color'] }}-600 dark:text-{{ $group['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                @break
                            @case('display')
                                <svg class="w-6 h-6 text-{{ $group['color'] }}-600 dark:text-{{ $group['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                @break
                            @case('activity')
                                <svg class="w-6 h-6 text-{{ $group['color'] }}-600 dark:text-{{ $group['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12h4l3 7 4-14 3 7h4"/>
                                </svg>
                                @break
                            @case('database')
                                <svg class="w-6 h-6 text-{{ $group['color'] }}-600 dark:text-{{ $group['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4"/>
                                </svg>
                                @break
                            @case('cpu')
                                <svg class="w-6 h-6 text-{{ $group['color'] }}-600 dark:text-{{ $group['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 7h10a2 2 0 012 2v10a2 2 0 01-2 2H7a2 2 0 01-2-2V9a2 2 0 012-2zM9 9h6v6H9V9z"/>
                                </svg>
                                @break
                            @case('key')
                                <svg class="w-6 h-6 text-{{ $group['color'] }}-600 dark:text-{{ $group['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                                </svg>
                                @break
                            @case('cloud')
                                <svg class="w-6 h-6 text-{{ $group['color'] }}-600 dark:text-{{ $group['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
                                </svg>
                                @break
                            @case('sun')
                                <svg class="w-6 h-6 text-{{ $group['color'] }}-600 dark:text-{{ $group['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                                </svg>
                                @break
                            @case('globe')
                                <svg class="w-6 h-6 text-{{ $group['color'] }}-600 dark:text-{{ $group['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                @break
                            @case('wind')
                                <svg class="w-6 h-6 text-{{ $group['color'] }}-600 dark:text-{{ $group['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12h9a3 3 0 100-6 3 3 0 00-3 3"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6h10a2 2 0 110 4h-1"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 18h7a2 2 0 110 4"/>
                                </svg>
                                @break
                            @case('wifi')
                                <svg class="w-6 h-6 text-{{ $group['color'] }}-600 dark:text-{{ $group['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.07 12.93a10 10 0 0113.86 0"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.11 15.97a6 6 0 017.78 0"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.15 19.01a2 2 0 012.7 0"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21h.01"/>
                                </svg>
                                @break
                            @case('air')
                                <svg class="w-6 h-6 text-{{ $group['color'] }}-600 dark:text-{{ $group['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"/>
                                </svg>
                                @break
                            @case('plane')
                                <svg class="w-6 h-6 text-{{ $group['color'] }}-600 dark:text-{{ $group['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                </svg>
                                @break
                            @case('alert')
                                <svg class="w-6 h-6 text-{{ $group['color'] }}-600 dark:text-{{ $group['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                                @break
                            @case('zap')
                                <svg class="w-6 h-6 text-{{ $group['color'] }}-600 dark:text-{{ $group['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                @break
                            @case('seismic')
                                <svg class="w-6 h-6 text-{{ $group['color'] }}-600 dark:text-{{ $group['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                @break
                            @case('camera')
                                <svg class="w-6 h-6 text-{{ $group['color'] }}-600 dark:text-{{ $group['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                @break
                            @case('radar')
                                <svg class="w-6 h-6 text-{{ $group['color'] }}-600 dark:text-{{ $group['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.636 18.364a9 9 0 010-12.728m12.728 0a9 9 0 010 12.728m-9.9-2.829a5 5 0 010-7.07m7.072 0a5 5 0 010 7.07M13 12a1 1 0 11-2 0 1 1 0 012 0z"/>
                                </svg>
                                @break
                            @case('link')
                                <svg class="w-6 h-6 text-{{ $group['color'] }}-600 dark:text-{{ $group['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                </svg>
                                @break
                            @case('gauge')
                                <svg class="w-6 h-6 text-{{ $group['color'] }}-600 dark:text-{{ $group['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                @break
                            @case('widgets')
                                <svg class="w-6 h-6 text-{{ $group['color'] }}-600 dark:text-{{ $group['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
                                </svg>
                                @break
                            @case('search')
                                <svg class="w-6 h-6 text-{{ $group['color'] }}-600 dark:text-{{ $group['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                @break
                            @case('mail')
                                <svg class="w-6 h-6 text-{{ $group['color'] }}-600 dark:text-{{ $group['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                @break
                            @case('clock')
                                <svg class="w-6 h-6 text-{{ $group['color'] }}-600 dark:text-{{ $group['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2"/>
                                </svg>
                                @break
                            @case('cog')
                                <svg class="w-6 h-6 text-{{ $group['color'] }}-600 dark:text-{{ $group['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                @break
                            @case('bell')
                                <svg class="w-6 h-6 text-{{ $group['color'] }}-600 dark:text-{{ $group['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                </svg>
                                @break
                            @case('paint')
                                <svg class="w-6 h-6 text-{{ $group['color'] }}-600 dark:text-{{ $group['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/>
                                </svg>
                                @break
                            @case('code')
                                <svg class="w-6 h-6 text-{{ $group['color'] }}-600 dark:text-{{ $group['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                                </svg>
                                @break
                            @case('water')
                                <svg class="w-6 h-6 text-{{ $group['color'] }}-600 dark:text-{{ $group['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
                                </svg>
                                @break
                            @case('wave')
                                <svg class="w-6 h-6 text-{{ $group['color'] }}-600 dark:text-{{ $group['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12h4l3 7 4-14 3 7h4"/>
                                </svg>
                                @break
                            @case('trending-up')
                                <svg class="w-6 h-6 text-{{ $group['color'] }}-600 dark:text-{{ $group['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/>
                                </svg>
                                @break
                        @endswitch
                    </div>
                    
                    @if($hasApiKeys)
                        @if($configured)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                {{ __('OK') }}
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400">
                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                {{ __('Setup') }}
                            </span>
                        @endif
                    @endif
                </div>
                
                <h3 class="mt-4 font-semibold text-gray-900 dark:text-white group-hover:text-{{ $group['color'] }}-600 dark:group-hover:text-{{ $group['color'] }}-400 transition">
                    {{ __($group['label']) }}
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 line-clamp-2">
                    {{ __($group['description']) }}
                </p>
                <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">
                    {{ $count }} {{ $count === 1 ? __('setting') : __('settings') }}
                </p>
            </div>
            
            <!-- Hover arrow -->
            <div class="absolute right-4 bottom-4 opacity-0 group-hover:opacity-100 transition transform translate-x-2 group-hover:translate-x-0">
                <svg class="w-5 h-5 text-{{ $group['color'] }}-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </div>
        </a>
    @endforeach
</div>

<!-- Status Modal -->
<div id="statusModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-md w-full mx-4 p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4" id="modalTitle">{{ __('Testing Connections') }}</h3>
        <div id="modalContent" class="space-y-3">
            <!-- Content populated by JS -->
        </div>
        <button onclick="closeModal()" class="mt-6 w-full px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-900 dark:text-white rounded-lg transition">
            {{ __('Close') }}
        </button>
    </div>
</div>

<script>
const settingsI18n = {
    testingConnections: @json(__('Testing Connections')),
    testing: @json(__('Testing...')),
    ok: @json(__('OK')),
    failed: @json(__('Failed')),
    error: @json(__('Error')),
    refreshPage: @json(__('Refresh the page to see updated values.'))
};

function showModal(title, content) {
    document.getElementById('modalTitle').textContent = title;
    document.getElementById('modalContent').innerHTML = content;
    document.getElementById('statusModal').classList.remove('hidden');
    document.getElementById('statusModal').classList.add('flex');
}

function closeModal() {
    document.getElementById('statusModal').classList.add('hidden');
    document.getElementById('statusModal').classList.remove('flex');
}

@php
    $serviceLabels = [
        'livedata' => __('Live Data'),
        'ecowitt' => __('Ecowitt'),
        'wunderground' => __('Weather Underground'),
        'weatherflow' => __('WeatherFlow'),
        'weatherlink' => __('WeatherLink'),
        'ambient' => __('Ambient Weather'),
        'netatmo' => __('Netatmo'),
        'yrno' => __('Yr.no'),
        'waqi' => __('Air Quality'),
        'checkwx' => __('Aviation / METAR'),
        'openweathermap' => __('OpenWeatherMap'),
    ];
@endphp

const testApiUrl = @json(route('admin.settings.test-api', [], false));

async function testAllConnections() {
    const serviceLabels = @json($serviceLabels);
    const services = Object.keys(serviceLabels);
    let html = '';
    
    for (const service of services) {
        html += `<div id="test-${service}" class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
            <span class="font-medium">${serviceLabels[service] ?? service}</span>
            <span class="text-gray-400">${settingsI18n.testing}</span>
        </div>`;
    }
    
    showModal(settingsI18n.testingConnections, html);
    
    for (const service of services) {
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
            const el = document.getElementById(`test-${service}`);
            el.innerHTML = `
                <div>
                    <span class="font-medium">${serviceLabels[service] ?? service}</span>
                    ${data.message ? `<div class="text-xs text-gray-500 dark:text-gray-400 mt-1">${data.message}</div>` : ''}
                </div>
                <span class="${data.success ? 'text-green-600' : 'text-red-600'} flex items-center">
                    ${data.success ? '<svg class="w-5 h-5 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>' : '<svg class="w-5 h-5 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>'}
                    ${data.success ? settingsI18n.ok : settingsI18n.failed}
                </span>
            `;
        } catch (e) {
            const el = document.getElementById(`test-${service}`);
            el.innerHTML = `
                <div>
                    <span class="font-medium">${serviceLabels[service] ?? service}</span>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">${e.message}</div>
                </div>
                <span class="text-red-600">${settingsI18n.error}</span>
            `;
        }
    }
}

</script>
@endsection
