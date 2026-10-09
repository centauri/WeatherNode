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
            <p class="text-gray-400 mt-1">{{ __('Configure wave height and sea surface temperature data') }}</p>
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
        </div>

        {{-- Data source: one card per provider --}}
        @php
            $waveSource = \App\Services\Wave\WaveServiceFactory::source();
            $kaurStationCode = (string) Setting::getValue('waves.kaur_station_code', '');
            $kaurStations = $waveSource === 'kaur' ? app(\App\Services\Wave\KaurMarineService::class)->seaTemperatureStations() : [];
            $waveProviders = [
                'open_meteo' => [
                    'name' => 'Open-Meteo Marine',
                    'flag' => '🌍',
                    'coverage' => __('Global'),
                    'description' => __('Global model. Waves with swell, and a sea surface temperature forecast.'),
                    'included' => [
                        [__('Wave Height & Period'), __('Significant wave height, mean wave period, direction')],
                        [__('Wind Waves vs Swell'), __('Separate breakdown of locally generated wind waves and oceanic swell')],
                        [__('Sea Surface Temperature'), __('5-day SST trend with comfort rating (Cold → Hot)')],
                        [__('Beaufort Sea State'), __('Sea state classification from Calm (glassy) to High sea (≥ 9 m)')],
                    ],
                    'about' => [
                        __('Wave data is model-based forecast data (not measured), covering past 12 hours + 4 days ahead. Sea Surface Temperature is a model analysis product. Both are free with no API key required — data is fetched using your station\'s coordinates.'),
                        __('Note: Open-Meteo Marine covers oceans and large bodies of water. Data may not be available for inland locations far from coast.'),
                    ],
                    'links' => [
                        'Open-Meteo Marine Weather API' => 'https://open-meteo.com/en/docs/marine-weather-api',
                    ],
                ],
                'kaur' => [
                    'name' => 'Keskkonnaagentuur',
                    'flag' => '🇪🇪',
                    'coverage' => __('Estonia'),
                    'description' => __('Estonian Environment Agency. Waves from the SWAN-EST model and sea temperature measured at a coastal gauge, for Estonian waters.'),
                    'included' => [
                        [__('Wave Height & Period'), __('Significant wave height, peak period and direction from the SWAN-EST model, on a grid of about 1 km')],
                        [__('Sea Surface Temperature'), __('Measured hourly at the coastal gauge chosen below')],
                        [__('Beaufort Sea State'), __('Sea state classification from Calm (glassy) to High sea (≥ 9 m)')],
                        [__('No swell split'), __('SWAN gives one wave height, so the wind wave and swell blocks are hidden')],
                    ],
                    'about' => [
                        __('Waves are a model forecast about 90 hours ahead, from runs published twice a day. Each run is a file of about 130 MB, which the hourly poll downloads when a new one appears. Pages read the stored file, so the first waves show after the first poll.'),
                        __('Sea temperature is measured, not modelled. KAUR publishes the current hour only, so the chart fills in as the polls collect readings. Free, no API key, licensed CC BY 4.0.'),
                    ],
                    'links' => [
                        __('Wave model') => 'https://keskkonnaportaal.ee/et/avaandmed/ilma-mudelprognoosid',
                        __('Coastal observations') => 'https://keskkonnaportaal.ee/et/avaandmed/hudroloogilise-seire-andmestik',
                    ],
                ],
            ];
        @endphp
        <div class="mb-6" x-data="{ source: '{{ $waveSource }}' }">
            <h2 class="font-semibold text-white mb-1">{{ __('Data source') }}</h2>
            <p class="text-sm text-gray-400 mb-4">{{ __('Pick one provider. It feeds the Waves and Sea Temperature tabs and the dashboard water card.') }}</p>

            @foreach($waveProviders as $key => $provider)
                <div class="bg-gray-800/50 rounded-2xl border overflow-hidden mb-4 transition-all duration-200"
                     :class="source === '{{ $key }}' ? 'border-blue-500/60' : 'border-white/10 opacity-80'">

                    {{-- Card header: provider identity + choice --}}
                    <label class="flex items-start justify-between gap-4 p-5 cursor-pointer">
                        <div class="flex items-start gap-4">
                            <span class="text-3xl leading-none mt-0.5">{{ $provider['flag'] }}</span>
                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h3 class="font-semibold text-white text-lg leading-tight">{{ $provider['name'] }}</h3>
                                    <span class="text-xs text-gray-500">{{ $provider['coverage'] }}</span>
                                    <span class="text-xs px-2 py-0.5 bg-gray-700/60 text-gray-400 rounded-full">{{ __('Free · No API key') }}</span>
                                </div>
                                <p class="text-sm text-gray-400 mt-1 max-w-xl">{{ $provider['description'] }}</p>
                            </div>
                        </div>
                        <input type="radio" name="waves_source" value="{{ $key }}" x-model="source"
                               class="mt-1.5 w-4 h-4 accent-blue-500 flex-shrink-0" {{ $waveSource === $key ? 'checked' : '' }}>
                    </label>

                    <div class="border-t border-white/5 p-5 space-y-5">
                        @if($key === 'kaur')
                            <div x-show="source === 'kaur'" x-cloak>
                                @if($waveSource === 'kaur')
                                    <label for="waves_kaur_station_code" class="block text-xs text-gray-500 uppercase tracking-wider mb-2">{{ __('Sea temperature gauge') }}</label>
                                    <select name="waves_kaur_station_code" id="waves_kaur_station_code"
                                            class="w-full px-4 py-2 rounded-xl bg-gray-900/40 border border-white/10 text-white">
                                        <option value="">{{ __('Nearest to the data location') }}</option>
                                        @foreach($kaurStations as $code => $station)
                                            <option value="{{ $code }}" {{ $kaurStationCode === $code ? 'selected' : '' }}>{{ $station['name'] }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <p class="text-xs text-gray-400">{{ __('Save to choose the sea temperature gauge. Until then the gauge nearest the data location is used.') }}</p>
                                @endif
                            </div>
                        @endif

                        <div>
                            <h4 class="text-xs text-gray-500 uppercase tracking-wider mb-2">{{ __('What\'s included') }}</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                @foreach($provider['included'] as [$title, $detail])
                                    <div class="p-3 rounded-xl bg-gray-900/30 border border-white/5">
                                        <div class="text-sm font-medium text-gray-200">{{ $title }}</div>
                                        <div class="text-xs text-gray-400 mt-0.5">{{ $detail }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <h4 class="text-xs text-gray-500 uppercase tracking-wider mb-2">{{ __('About the data') }}</h4>
                            @foreach($provider['about'] as $paragraph)
                                <p class="text-sm text-gray-400 mb-2">{{ $paragraph }}</p>
                            @endforeach
                            <p class="text-xs text-gray-500">
                                {{ __('Source') }}:
                                @foreach($provider['links'] as $label => $url)
                                    <a href="{{ $url }}" target="_blank" rel="noopener" class="underline hover:text-gray-300">{{ $label }}</a>@if(!$loop->last),@endif
                                @endforeach
                            </p>
                        </div>
                    </div>
                </div>
            @endforeach
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
