@extends('layouts.admin')

@section('title', __('Waves & Sea Temperature Settings'))

@section('content')
@php
    use App\Models\Setting;

    $s = $settings->keyBy('key');

    $enabled = (bool) ($s->get('waves.enabled')?->getCastedValue() ?? true);

    $lat = round((float) Setting::latitude(), 4);
    $lon = round((float) Setting::longitude(), 4);
    $marineLat = trim((string) (Setting::getValue('marine.latitude', '') ?? ''));
    $marineLon = trim((string) (Setting::getValue('marine.longitude', '') ?? ''));
@endphp

<div class="space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <svg class="w-8 h-8 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
                </svg>
                {{ __('Waves & Sea Temperature') }}
            </h1>
            <p class="text-gray-400 mt-1">{{ __('Configure Open-Meteo Marine wave height and sea surface temperature data') }}</p>
        </div>
        <a href="{{ route('admin.settings.index') }}" class="text-gray-400 hover:text-white transition-colors">
            ← {{ __('Back to Settings') }}
        </a>
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="rounded-lg border border-emerald-700/50 bg-emerald-900/30 px-4 py-3 text-emerald-200">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-700/50 bg-red-900/30 px-4 py-3 text-red-200">
            {{ session('error') }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.settings.update', 'waves') }}">
        @csrf

        {{-- Enable toggle --}}
        <div class="bg-gray-800/50 rounded-2xl p-6 border border-white/10 space-y-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-white">{{ __('Enable Waves & Sea Temperature') }}</h2>
                    <p class="text-sm text-gray-400 mt-1">
                        {{ __('Show wave height, direction, period and sea surface temperature on the Water page.') }}
                        {{ __('Data from Open-Meteo Marine — free, model-based, no API key required.') }}
                    </p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer ml-4 flex-shrink-0">
                    <input type="checkbox" name="waves_enabled" value="1"
                           class="sr-only peer" {{ $enabled ? 'checked' : '' }}>
                    <div class="w-11 h-6 bg-gray-600 peer-focus:outline-none peer-focus:ring-2
                                peer-focus:ring-blue-500 rounded-full peer
                                peer-checked:after:translate-x-full peer-checked:after:border-white
                                after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                                after:bg-white after:border-gray-300 after:border after:rounded-full
                                after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                </label>
            </div>
        </div>

        {{-- Source --}}
        @php
            $waveSource = \App\Services\Wave\WaveServiceFactory::source();
            $kaurStationCode = (string) Setting::getValue('waves.kaur_station_code', '');
            $kaurStations = $waveSource === 'kaur' ? app(\App\Services\Wave\KaurMarineService::class)->seaTemperatureStations() : [];
        @endphp
        <div class="bg-gray-800/50 rounded-2xl p-6 border border-white/10 mb-6">
            <h2 class="font-semibold text-white mb-4">{{ __('Data source') }}</h2>
            <div class="space-y-3">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="radio" name="waves_source" value="open_meteo" class="mt-1 accent-blue-500" {{ $waveSource === 'open_meteo' ? 'checked' : '' }}>
                    <span>
                        <span class="block text-sm font-medium text-white">Open-Meteo Marine</span>
                        <span class="block text-xs text-gray-400">{{ __('Global model. Waves with swell, and a sea surface temperature forecast.') }}</span>
                    </span>
                </label>
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="radio" name="waves_source" value="kaur" class="mt-1 accent-blue-500" {{ $waveSource === 'kaur' ? 'checked' : '' }}>
                    <span>
                        <span class="block text-sm font-medium text-white">Keskkonnaagentuur</span>
                        <span class="block text-xs text-gray-400">{{ __('Estonian waters. Waves from the SWAN model without a swell split, and sea temperature measured at a coastal gauge. The wave forecast downloads with the hourly poll.') }}</span>
                    </span>
                </label>
            </div>
            @if($waveSource === 'kaur')
                <div class="mt-4">
                    <label for="waves_kaur_station_code" class="block text-xs text-gray-500 uppercase tracking-wider mb-2">{{ __('Sea temperature gauge') }}</label>
                    <select name="waves_kaur_station_code" id="waves_kaur_station_code"
                            class="w-full px-4 py-2 rounded-xl bg-gray-900/40 border border-white/10 text-white">
                        <option value="">{{ __('Nearest to the data location') }}</option>
                        @foreach($kaurStations as $code => $station)
                            <option value="{{ $code }}" {{ $kaurStationCode === $code ? 'selected' : '' }}>{{ $station['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>

        {{-- Location --}}
        <div class="bg-gray-800/50 rounded-2xl p-6 border border-white/10 mb-6">
            <h2 class="font-semibold text-white mb-4">{{ __('Data location') }}</h2>
            <p class="text-sm text-gray-400 mb-4">
                {{ __('Marine data is fetched for your station coordinates. If your station is inland, set the nearest stretch of coast here instead. Leave both empty to use the station location.') }}
            </p>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="marine_latitude" class="block text-xs text-gray-500 uppercase tracking-wider mb-2">{{ __('Latitude') }}</label>
                    <input type="number" step="0.0001" min="-90" max="90"
                           name="marine_latitude" id="marine_latitude"
                           value="{{ $marineLat }}" placeholder="{{ $lat }}"
                           class="w-full px-4 py-2 rounded-xl bg-gray-900/40 border border-white/10 text-white font-mono">
                </div>
                <div>
                    <label for="marine_longitude" class="block text-xs text-gray-500 uppercase tracking-wider mb-2">{{ __('Longitude') }}</label>
                    <input type="number" step="0.0001" min="-180" max="180"
                           name="marine_longitude" id="marine_longitude"
                           value="{{ $marineLon }}" placeholder="{{ $lon }}"
                           class="w-full px-4 py-2 rounded-xl bg-gray-900/40 border border-white/10 text-white font-mono">
                </div>
            </div>
            <p class="mt-3 text-xs text-gray-500">
                {{ __('Applies to waves, sea temperature and the tide sources that look up a grid point rather than a named station.') }}
            </p>
            <p class="mt-2 text-xs text-gray-500">
                {{ __('Note: Open-Meteo Marine covers oceans and large bodies of water. Data may not be available for inland locations far from coast.') }}
            </p>
        </div>

        {{-- What's included --}}
        <div class="bg-gray-800/50 rounded-2xl p-6 border border-white/10 mb-6">
            <h2 class="font-semibold text-white mb-4">{{ __('What\'s included') }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="flex items-start gap-3 p-3 rounded-xl bg-blue-900/20 border border-blue-800/30">
                    <svg class="w-5 h-5 text-blue-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
                    </svg>
                    <div>
                        <div class="text-sm font-medium text-blue-200">{{ __('Wave Height & Period') }}</div>
                        <div class="text-xs text-blue-400 mt-0.5">{{ __('Significant wave height, mean wave period, direction') }}</div>
                    </div>
                </div>
                <div class="flex items-start gap-3 p-3 rounded-xl bg-indigo-900/20 border border-indigo-800/30">
                    <svg class="w-5 h-5 text-indigo-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    <div>
                        <div class="text-sm font-medium text-indigo-200">{{ __('Wind Waves vs Swell') }}</div>
                        <div class="text-xs text-indigo-400 mt-0.5">{{ __('Separate breakdown of locally generated wind waves and oceanic swell') }}</div>
                    </div>
                </div>
                <div class="flex items-start gap-3 p-3 rounded-xl bg-orange-900/20 border border-orange-800/30">
                    <svg class="w-5 h-5 text-orange-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    <div>
                        <div class="text-sm font-medium text-orange-200">{{ __('Sea Surface Temperature') }}</div>
                        <div class="text-xs text-orange-400 mt-0.5">{{ __('5-day SST trend with comfort rating (Cold → Hot)') }}</div>
                    </div>
                </div>
                <div class="flex items-start gap-3 p-3 rounded-xl bg-cyan-900/20 border border-cyan-800/30">
                    <svg class="w-5 h-5 text-cyan-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12h4l3 7 4-14 3 7h4"/>
                    </svg>
                    <div>
                        <div class="text-sm font-medium text-cyan-200">{{ __('Beaufort Sea State') }}</div>
                        <div class="text-xs text-cyan-400 mt-0.5">{{ __('Sea state classification from Calm (glassy) to High sea (≥ 9 m)') }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Info box --}}
        <div class="bg-blue-900/20 border border-blue-800/30 rounded-2xl p-5 mb-6 text-sm text-blue-200">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-blue-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div>
                    <p class="font-medium text-blue-100 mb-1">{{ __('About the data') }}</p>
                    <p class="text-blue-300">
                        {{ __('Wave data is model-based forecast data (not measured), covering past 12 hours + 4 days ahead. Sea Surface Temperature is a model analysis product. Both are free with no API key required — data is fetched using your station\'s coordinates.') }}
                    </p>
                    <p class="mt-2 text-blue-400">
                        {{ __('Source') }}: <a href="https://open-meteo.com/en/docs/marine-weather-api" target="_blank" rel="noopener"
                           class="underline hover:text-blue-200">Open-Meteo Marine Weather API</a>
                    </p>
                </div>
            </div>
        </div>

        {{-- Save button --}}
        <div class="flex items-center gap-4">
            <button type="submit"
                    class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition-colors">
                {{ __('Save Wave Settings') }}
            </button>
            @if($enabled)
                <a href="{{ route('water') }}" target="_blank"
                   class="text-sm text-gray-400 hover:text-white transition-colors">
                    🌊 {{ __('View Waves') }} ↗
                </a>
            @endif
        </div>

    </form>

    {{-- Polling schedule info --}}
    <div class="bg-gray-800/30 rounded-2xl p-5 border border-white/5 text-sm text-gray-400">
        <h3 class="font-semibold text-gray-300 mb-3">⏱ {{ __('Polling schedule') }}</h3>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div>
                <div class="text-xs text-gray-500 uppercase tracking-wider">{{ __('Interval') }}</div>
                <div class="text-white font-medium mt-1">60 {{ __('minutes') }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-500 uppercase tracking-wider">{{ __('Cache TTL') }}</div>
                <div class="text-white font-medium mt-1">3 {{ __('hours') }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-500 uppercase tracking-wider">{{ __('Command') }}</div>
                <div class="text-white font-mono text-xs mt-1">weather:poll-external --source=waves</div>
            </div>
            <div>
                <div class="text-xs text-gray-500 uppercase tracking-wider">{{ __('Coverage') }}</div>
                <div class="text-white font-medium mt-1">{{ __('Global (ocean)') }}</div>
            </div>
        </div>
    </div>

</div>
@endsection
