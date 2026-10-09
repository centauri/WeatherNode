@extends('layouts.admin')

@section('title', __('Tide Settings'))

@section('content')
@php
    use App\Models\Setting;
    use App\Services\Tide\TideServiceFactory;

    $s = $settings->keyBy('key');

    $enabled     = (bool)   ($s->get('tide.enabled')?->getCastedValue()       ?? false);
    $source      = (string) ($s->get('tide.source')?->value ?: TideServiceFactory::DEFAULT_SOURCE);
    // Prefer the per-source station key so switching between sources retains each source's station.
    $stationCode = (string) ($s->get("tide.{$source}_station_code")?->value
                          ?: $s->get('tide.station_code')?->value
                          ?: '');
    $stationName = (string) ($s->get('tide.station_name')?->value             ?? '');
    $mareaKey         = (string) ($s->get('tide.marea_api_key')?->value           ?? '');
    $copernicusUser   = (string) ($s->get('tide.copernicus_username')?->value    ?? '');
    $copernicusPass   = (string) ($s->get('tide.copernicus_password')?->value    ?? '');

    $allSources  = TideServiceFactory::all();

    // Split into implemented vs coming-soon for the UI
    $implemented = array_filter($allSources, fn ($s) => $s['implemented']);
    $planned     = array_filter($allSources, fn ($s) => !$s['implemented']);

    // Stations for the currently active source (only relevant when station-based)
    $currentDriver   = TideServiceFactory::make($source);
    $stations        = $currentDriver->getStations();
    $isStationBased  = $currentDriver->isStationBased();
    $requiresKey     = $currentDriver->requiresApiKey();
@endphp

<div class="space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <svg class="w-8 h-8 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
                </svg>
                {{ __('Tides & Water Levels') }}
            </h1>
            <p class="text-gray-400 mt-1">{{ __('Configure tide data source for the Water page') }}</p>
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

    <form action="{{ route('admin.settings.update', 'tide') }}" method="POST" class="space-y-6" x-data="{ source: '{{ $source }}' }">
        @csrf

        {{-- ── Enable toggle ────────────────────────────────────────────── --}}
        <div class="bg-gray-800/50 rounded-xl border border-gray-700 p-6">
            <label class="flex items-center gap-4 cursor-pointer">
                <input type="hidden" name="tide_enabled" value="0">
                <div class="relative">
                    <input type="checkbox" name="tide_enabled" value="1" id="tide_enabled"
                           {{ $enabled ? 'checked' : '' }} class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-600 peer-checked:bg-cyan-600 rounded-full transition-colors peer-focus:ring-2 peer-focus:ring-cyan-400"></div>
                    <div class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition-transform peer-checked:translate-x-5"></div>
                </div>
                <div>
                    <p class="font-medium text-white">{{ __('Enable Tide Data') }}</p>
                    <p class="text-xs text-gray-400">{{ __('Show tide times, water levels, and tidal chart on the Water page.') }}</p>
                </div>
            </label>
        </div>

        {{-- ── Source selection ─────────────────────────────────────────── --}}
        <div class="bg-gray-800/50 rounded-xl border border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-white mb-1">{{ __('Data Source') }}</h2>
            <p class="text-sm text-gray-400 mb-4">{{ __('Select the tide API to use. Implemented sources fetch live data; planned sources are placeholders for future integrations.') }}</p>

            <div class="grid grid-cols-1 gap-3" id="source-cards">
                {{-- Implemented sources --}}
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mt-1">{{ __('Available') }}</p>
                @foreach($implemented as $key => $info)
                    <label class="flex items-start gap-3 p-4 rounded-lg border cursor-pointer transition-colors
                                  {{ $source === $key
                                      ? 'border-cyan-500 bg-cyan-900/20'
                                      : 'border-gray-600 bg-gray-700/30 hover:border-gray-500' }}">
                        <input type="radio" name="tide_source" value="{{ $key }}" x-model="source"
                               {{ $source === $key ? 'checked' : '' }}
                               class="mt-1 accent-cyan-500">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-medium text-white text-sm">{{ $info['name'] }}</span>
                                <span class="px-1.5 py-0.5 rounded text-xs bg-emerald-900/50 text-emerald-400 font-mono">{{ $info['region'] }}</span>
                                @if($info['requires_key'])
                                    <span class="px-1.5 py-0.5 rounded text-xs bg-yellow-900/50 text-yellow-400">{{ __('API key') }}</span>
                                @else
                                    <span class="px-1.5 py-0.5 rounded text-xs bg-cyan-900/50 text-cyan-400">{{ __('Free') }}</span>
                                @endif
                            </div>
                            <p class="text-xs text-gray-400 mt-0.5">{{ $info['coverage_area'] }}</p>
                            @if($info['api_doc_url'])
                                <a href="{{ $info['api_doc_url'] }}" target="_blank" rel="noopener"
                                   class="text-xs text-blue-400 hover:underline">{{ __('API docs') }} ↗</a>
                            @endif
                        </div>
                    </label>
                @endforeach

                {{-- Planned / coming-soon sources --}}
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mt-3">{{ __('Coming Soon') }}</p>
                @foreach($planned as $key => $info)
                    <div class="flex items-start gap-3 p-4 rounded-lg border border-gray-700 bg-gray-800/30 opacity-60">
                        <div class="mt-1 w-4 h-4 rounded-full border border-gray-600 flex-shrink-0"></div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-medium text-gray-300 text-sm">{{ $info['name'] }}</span>
                                <span class="px-1.5 py-0.5 rounded text-xs bg-gray-700 text-gray-400 font-mono">{{ $info['region'] }}</span>
                                @if($info['requires_key'])
                                    <span class="px-1.5 py-0.5 rounded text-xs bg-gray-700 text-gray-400">{{ __('API key') }}</span>
                                @endif
                                <span class="px-1.5 py-0.5 rounded text-xs bg-gray-700 text-gray-500 italic">{{ __('coming soon') }}</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $info['coverage_area'] }}</p>
                            @if($info['api_doc_url'])
                                <a href="{{ $info['api_doc_url'] }}" target="_blank" rel="noopener"
                                   class="text-xs text-gray-500 hover:text-gray-400">{{ __('API docs') }} ↗</a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div x-show="source !== '{{ $source }}'" x-cloak class="bg-gray-800/50 rounded-xl border border-gray-700 p-6 text-sm text-gray-400">
            {{ __('Save to see the station and other settings for the chosen source.') }}
        </div>

        {{-- ── Station (only for station-based sources) ────────────────── --}}
        <fieldset x-show="source === '{{ $source }}'" :disabled="source !== '{{ $source }}'" class="min-w-0">
        @if($isStationBased && !empty($stations))
        <div class="bg-gray-800/50 rounded-xl border border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-white mb-3">{{ __('Station') }}</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm text-gray-300 mb-1" for="tide_station_code">
                        {{ __('Tide Station') }}
                    </label>
                    <select name="tide_station_code" id="tide_station_code"
                            class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-cyan-500">
                        <option value="" {{ $stationCode === '' ? 'selected' : '' }}>
                            {{ __('Select a station…') }}
                        </option>
                        @foreach($stations as $code => $info)
                            <option value="{{ $code }}" {{ $stationCode === $code ? 'selected' : '' }}>
                                {{ $info['name'] }} ({{ $code }})
                            </option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-1">{{ __('Select the nearest tidal gauge station.') }}</p>
                </div>
                <div>
                    <label class="block text-sm text-gray-300 mb-1" for="tide_station_name">
                        {{ __('Display Name') }}
                    </label>
                    <input type="text" name="tide_station_name" id="tide_station_name"
                           value="{{ old('tide_station_name', $stationName) }}"
                           class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-cyan-500">
                    <p class="text-xs text-gray-500 mt-1">{{ __('Name shown on the water page and dashboard widget.') }}</p>
                </div>
            </div>
        </div>
        @elseif(!$isStationBased)
        <div class="bg-gray-800/50 rounded-xl border border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-white mb-1">{{ __('Location') }}</h2>
            <p class="text-sm text-gray-400">
                {{ __('This source uses the site coordinates from') }}
                <a href="{{ route('admin.settings.group', 'general') }}" class="text-blue-400 hover:underline">
                    {{ __('Settings → General') }}
                </a>
                {{ __('— no station selection required.') }}
            </p>
        </div>
        @endif
        </fieldset>

        <div x-show="source === '{{ $source }}'" class="space-y-6">
        {{-- ── API key (Marea) ──────────────────────────────────────────── --}}
        @if($source === 'marea')
        <div class="bg-gray-800/50 rounded-xl border border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-white mb-1">{{ __('Marea API Key') }}</h2>
            <p class="text-sm text-gray-400 mb-3">
                {{ __('Optional — without a key the free rate limit applies. Register at') }}
                <a href="https://api.marea.ooo" target="_blank" rel="noopener" class="text-blue-400 hover:underline">api.marea.ooo ↗</a>.
            </p>
            <input type="text" name="tide_marea_api_key"
                   value="{{ old('tide_marea_api_key', $mareaKey) }}"
                   placeholder="{{ __('Leave blank to use without a key') }}"
                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white text-sm font-mono focus:outline-none focus:border-cyan-500">
        </div>
        @endif

        {{-- ── API credentials (Copernicus) ───────────────────────────── --}}
        @if($source === 'copernicus')
        <div class="bg-gray-800/50 rounded-xl border border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-white mb-1">{{ __('Copernicus Marine Credentials') }}</h2>
            <p class="text-sm text-gray-400 mb-3">
                {{ __('A free account is required. Register at') }}
                <a href="https://marine.copernicus.eu" target="_blank" rel="noopener" class="text-blue-400 hover:underline">marine.copernicus.eu ↗</a>.
            </p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm text-gray-300 mb-1" for="tide_copernicus_username">
                        {{ __('Username') }}
                    </label>
                    <input type="text" name="tide_copernicus_username" id="tide_copernicus_username"
                           value="{{ old('tide_copernicus_username', $copernicusUser) }}"
                           placeholder="{{ __('CMEMS username') }}"
                           class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white text-sm font-mono focus:outline-none focus:border-cyan-500">
                </div>
                <div>
                    <label class="block text-sm text-gray-300 mb-1" for="tide_copernicus_password">
                        {{ __('Password') }}
                    </label>
                    <input type="password" name="tide_copernicus_password" id="tide_copernicus_password"
                           value="{{ old('tide_copernicus_password', $copernicusPass) }}"
                           placeholder="{{ __('CMEMS password') }}"
                           class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white text-sm font-mono focus:outline-none focus:border-cyan-500">
                </div>
            </div>
        </div>
        @endif
        </div>

        {{-- ── Dashboard widget ─────────────────────────────────────────── --}}
        <div class="bg-gray-800/50 rounded-xl border border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-white mb-3">{{ __('Dashboard Widget') }}</h2>
            <p class="text-sm text-gray-400">
                {{ __('Enable the Tides widget on the home dashboard via') }}
                <a href="{{ route('admin.settings.group', 'widgets') }}"
                   class="text-blue-400 hover:underline">{{ __('Settings → Widgets') }}</a>.
            </p>
        </div>

        {{-- ── Poller schedule ──────────────────────────────────────────── --}}
        <div class="bg-gray-800/50 rounded-xl border border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-white mb-3">{{ __('Poller Schedule') }}</h2>
            <p class="text-sm text-gray-400 mb-2">
                {{ __('Tide predictions are stable; data is refreshed every 60 minutes.') }}
                {{ __('The cache covers 12 hours of past measurements and 72 hours of predictions.') }}
            </p>
            <p class="text-sm text-gray-400">
                {{ __('To refresh immediately, run:') }}
                <code class="bg-gray-700 px-2 py-0.5 rounded text-cyan-300 text-xs ml-1">php artisan weather:poll-external --source=tide --force</code>
            </p>
        </div>

        <div class="flex justify-end">
            <button type="submit"
                    class="px-6 py-2 bg-cyan-600 hover:bg-cyan-700 text-white rounded-lg text-sm font-medium transition-colors">
                {{ __('Save Settings') }}
            </button>
        </div>
    </form>

</div>
@endsection
