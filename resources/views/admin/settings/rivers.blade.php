@extends('layouts.admin')

@section('title', __('River Levels Settings'))

@section('content')
@php
    use App\Models\Setting;
    use App\Services\River\RijkswaterstaatRiverService;
    use App\Services\River\RiverProviderRegistry;

    $s = $settings->keyBy('key');

    /**
     * Build per-provider data bags.
     * Each entry contains: enabled, stations (array), custom_stations (array),
     * catalog (array), catalog_at (Carbon|null).
     */
    $providers        = RiverProviderRegistry::PROVIDERS;
    $providerSettings = [];

    foreach (RiverProviderRegistry::active() as $providerId => $providerMeta) {
        // Enabled flag — with migration fallback for legacy 'rws' flat key
        $enabled = (bool) RiverProviderRegistry::getSetting($providerId, 'enabled', false);

        // Selected stations
        $stationsRaw = RiverProviderRegistry::getSetting(
            $providerId, 'stations',
            $providerId === 'rws' ? null : null
        );
        $stations = is_string($stationsRaw)
            ? (json_decode($stationsRaw, true) ?? \App\Services\River\RiverProviderRegistry::defaultStations($providerId))
            : ($stationsRaw ?? \App\Services\River\RiverProviderRegistry::defaultStations($providerId));
        $stations = array_values(array_filter((array) $stations, fn ($v) => is_string($v) && !is_numeric($v) && $v !== ''));
        if (empty($stations)) {
            $stations = \App\Services\River\RiverProviderRegistry::defaultStations($providerId);
        }

        // Custom stations
        $customRaw      = RiverProviderRegistry::getSetting($providerId, 'custom_stations', '[]');
        $customStations = is_string($customRaw) ? (json_decode($customRaw, true) ?? []) : [];

        // Catalog (if provider has one)
        $catalog   = [];
        $catalogAt = null;
        if (isset($providerMeta['catalog_service'])) {
            $catalogSvc = app($providerMeta['catalog_service']);
            $catalog    = $catalogSvc->getRiverStations();
            $catalogAt  = $catalogSvc->cachedAt();
        }

        $providerSettings[$providerId] = compact('enabled', 'stations', 'customStations', 'catalog', 'catalogAt');
    }
@endphp

{{-- Per-provider catalog data as JSON — avoids giant inline attributes --}}
@foreach(RiverProviderRegistry::active() as $providerId => $providerMeta)
    @if(!empty($providerSettings[$providerId]['catalog']))
        <script type="application/json" id="catalog-{{ $providerId }}">
            {!! json_encode($providerSettings[$providerId]['catalog'], JSON_HEX_TAG | JSON_HEX_AMP) !!}
        </script>
    @endif
@endforeach

{{-- Alpine.js factory: one instance per provider card --}}
<script>
function createRiverProviderState(catalogElementId, initialEnabled, initialStations, initialCustom) {
    return {
        enabled:        initialEnabled,
        selectedCodes:  [...initialStations],
        customStations: [...initialCustom],
        search:         '',
        riverFilter:    '',
        newCode: '', newName: '', newRiver: '',

        get allStations() {
            if (!catalogElementId) return {};
            const el = document.getElementById(catalogElementId);
            if (!el) return {};
            try { return JSON.parse(el.textContent); } catch { return {}; }
        },

        get uniqueRivers() {
            return [...new Set(Object.values(this.allStations).map(m => m.river))].sort();
        },
        get _allMatches() {
            const q  = this.search.toLowerCase().trim();
            const rf = this.riverFilter;
            if (!q && !rf) return [];
            return Object.entries(this.allStations).filter(([code, meta]) => {
                const ms = !q || code.includes(q) || meta.name.toLowerCase().includes(q) || meta.river.toLowerCase().includes(q);
                const mr = !rf || meta.river === rf;
                return ms && mr;
            });
        },
        get filteredResults()  { return this._allMatches.slice(0, 30); },
        get filteredTotal()    { return this._allMatches.length; },
        get hasMoreResults()   { return this._allMatches.length > 30; },
        get isSearching()      { return this.search.trim().length > 0 || this.riverFilter !== ''; },

        isSelected(code)       { return this.selectedCodes.includes(code); },
        toggleStation(code) {
            if (this.isSelected(code)) {
                this.selectedCodes = this.selectedCodes.filter(c => c !== code);
            } else {
                this.selectedCodes = [...this.selectedCodes, code];
            }
        },
        removeStation(code)    { this.selectedCodes = this.selectedCodes.filter(c => c !== code); },
        getStationMeta(code)   { return this.allStations[code] || { name: code, river: '—' }; },

        addCustom() {
            const code = this.newCode.trim().toLowerCase();
            if (!code || this.customStations.find(s => s.code === code)) return;
            this.customStations.push({ code, name: this.newName.trim() || code, river: this.newRiver.trim() || '—' });
            this.newCode = ''; this.newName = ''; this.newRiver = '';
        },
        removeCustom(index) { this.customStations.splice(index, 1); },
    };
}
</script>

<div class="space-y-6">

    {{-- Page header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <svg class="w-8 h-8 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/>
                </svg>
                {{ __('River Levels') }}
            </h1>
            <p class="text-gray-400 mt-1">{{ __('Enable and configure river gauge data providers. Multiple providers can be active at the same time.') }}</p>
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

    <form method="POST" action="{{ route('admin.settings.update', 'rivers') }}">
        @csrf

        {{-- ── Provider cards ── --}}
        @foreach(RiverProviderRegistry::active() as $providerId => $providerMeta)
        @php $ps = $providerSettings[$providerId]; @endphp

        <div x-data="createRiverProviderState(
                '{{ isset($providerMeta['catalog_service']) ? 'catalog-' . $providerId : '' }}',
                {{ json_encode($ps['enabled']) }},
                {{ json_encode($ps['stations']) }},
                {{ json_encode($ps['customStations']) }}
             )"
             class="bg-gray-800/50 rounded-2xl border border-white/10 overflow-hidden mb-6
                    transition-all duration-200"
             :class="enabled ? 'border-white/10' : 'border-white/5 opacity-80'">

            {{-- ── Card header: provider identity + enable toggle ── --}}
            <div class="flex items-start justify-between gap-4 p-5">
                <div class="flex items-start gap-4">
                    <span class="text-3xl leading-none mt-0.5">{{ $providerMeta['flag'] }}</span>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h2 class="font-semibold text-white text-lg leading-tight">{{ $providerMeta['name'] }}</h2>
                            <span class="text-xs px-2 py-0.5 bg-emerald-900/40 text-emerald-400 rounded-full font-mono">
                                {{ $providerMeta['short'] }}
                            </span>
                            <span class="text-xs text-gray-500">{{ $providerMeta['country'] }}</span>
                            @if(!$providerMeta['api_key_required'])
                                <span class="text-xs px-2 py-0.5 bg-gray-700/60 text-gray-400 rounded-full">
                                    {{ __('Free · No API key') }}
                                </span>
                            @endif
                        </div>
                        <p class="text-sm text-gray-400 mt-1 max-w-xl">{{ $providerMeta['description'] }}</p>
                    </div>
                </div>

                {{-- Enable toggle --}}
                <label class="relative inline-flex items-center cursor-pointer flex-shrink-0 mt-1">
                    <input type="checkbox" x-model="enabled" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-600 peer-focus:outline-none peer-focus:ring-2
                                peer-focus:ring-emerald-500 rounded-full peer
                                peer-checked:after:translate-x-full peer-checked:after:border-white
                                after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                                after:bg-white after:border-gray-300 after:border after:rounded-full
                                after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                </label>
            </div>

            {{-- ── Station picker (only for providers that have it, only when enabled) ── --}}
            @if($providerMeta['station_search'] ?? false)
            <div x-show="enabled" x-cloak class="border-t border-white/5">

                {{-- Catalog info bar --}}
                @if(isset($providerMeta['catalog_service']))
                <div class="flex items-center justify-between gap-4 px-5 py-2.5 bg-gray-900/30">
                    <p class="text-xs text-gray-500">
                        {{ count($ps['catalog']) }} {{ __('stations from :source catalog', ['source' => $providerMeta['short']]) }}
                        @if($ps['catalogAt'])
                            · {{ __('updated') }} {{ $ps['catalogAt']->diffForHumans() }}
                        @else
                            · {{ __('just fetched') }}
                        @endif
                    </p>
                    <button type="button"
                            @click="
                                const btn = $el;
                                btn.disabled = true;
                                btn.textContent = '{{ __('Refreshing…') }}';
                                fetch('{{ route('admin.settings.update', 'rivers') }}', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded',
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                    },
                                    body: 'action=refresh_catalog&provider={{ $providerId }}'
                                })
                                .then(() => window.location.reload())
                                .catch(() => { btn.disabled = false; btn.textContent = '↻ {{ __('Refresh list') }}'; });
                            "
                            class="px-2.5 py-1 text-xs text-gray-400 hover:text-white border border-white/10
                                   hover:border-white/20 rounded-lg transition-colors flex-shrink-0 disabled:opacity-50">
                        ↻ {{ __('Refresh list') }}
                    </button>
                </div>
                @endif

                <div class="p-5 space-y-4">

                    {{-- River filter pills --}}
                    <div class="flex flex-wrap gap-2">
                        <button type="button" @click="riverFilter = ''; search = ''"
                                :class="!riverFilter && !search
                                    ? 'bg-emerald-800/50 text-emerald-200 border-emerald-600/50'
                                    : 'bg-white/5 text-gray-400 border-white/10 hover:bg-white/10'"
                                class="px-3 py-1 text-xs border rounded-full transition-colors">
                            {{ __('Show selected') }}
                        </button>
                        <template x-for="river in uniqueRivers" :key="river">
                            <button type="button"
                                    @click="riverFilter = (riverFilter === river ? '' : river); search = ''"
                                    :class="riverFilter === river
                                        ? 'bg-emerald-800/50 text-emerald-200 border-emerald-600/50'
                                        : 'bg-white/5 text-gray-400 border-white/10 hover:bg-white/10'"
                                    class="px-3 py-1 text-xs border rounded-full transition-colors"
                                    x-text="river">
                            </button>
                        </template>
                    </div>

                    {{-- Search input --}}
                    <div class="relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input x-model.debounce.200ms="search" @input="riverFilter = ''" type="text"
                               placeholder="{{ __('Type a city, river or station code…') }}"
                               class="w-full pl-9 pr-9 py-2.5 bg-gray-900/60 border border-white/10 rounded-xl text-sm text-white
                                      placeholder-gray-500 focus:outline-none focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/30">
                        <button x-show="search" @click="search = ''" type="button"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    {{-- Results dropdown --}}
                    <div x-show="isSearching" class="rounded-xl border border-white/10 overflow-hidden">
                        <template x-if="filteredResults.length === 0">
                            <p class="text-sm text-gray-500 text-center py-5">
                                {{ __('No stations match.') }}
                            </p>
                        </template>
                        <div class="divide-y divide-white/5 max-h-64 overflow-y-auto">
                            <template x-for="item in filteredResults" :key="item[0]">
                                <div @click="toggleStation(item[0])"
                                     :class="isSelected(item[0]) ? 'bg-emerald-900/25 hover:bg-emerald-900/35' : 'hover:bg-white/5'"
                                     class="flex items-center gap-3 px-4 py-2.5 cursor-pointer transition-colors select-none">
                                    <div class="w-4 h-4 flex-shrink-0 rounded border transition-all flex items-center justify-center"
                                         :class="isSelected(item[0]) ? 'bg-emerald-600 border-emerald-600' : 'border-gray-600 bg-transparent'">
                                        <svg x-show="isSelected(item[0])" class="w-3 h-3 text-white"
                                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <span class="text-sm font-medium text-white" x-text="item[1].name"></span>
                                        <span class="ml-2 text-xs text-emerald-400 font-medium" x-text="item[1].river"></span>
                                    </div>
                                    <span class="text-xs text-gray-500 font-mono truncate max-w-[12rem] flex-shrink-0"
                                          x-text="item[0]"></span>
                                </div>
                            </template>
                        </div>
                        <div x-show="hasMoreResults"
                             class="px-4 py-2 text-xs text-gray-500 text-center border-t border-white/5 bg-gray-900/20">
                            {{ __('Showing 30 of') }} <span x-text="filteredTotal"></span> {{ __('matches — refine your search') }}
                        </div>
                    </div>

                    {{-- Default state hint --}}
                    <p x-show="!isSearching" class="text-sm text-gray-500">
                        {{ __('Search above or click a river name to browse stations.') }}
                    </p>

                    {{-- Selected station chips --}}
                    <div class="pt-4 border-t border-white/5">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs text-gray-400">
                                <span x-text="selectedCodes.length"></span> {{ __('station(s) selected') }}
                            </span>
                            <button x-show="selectedCodes.length > 0" type="button" @click="selectedCodes = []"
                                    class="text-xs text-gray-600 hover:text-red-400 transition-colors">
                                {{ __('Clear all') }}
                            </button>
                        </div>
                        <div x-show="selectedCodes.length === 0" class="text-xs text-gray-600 italic py-1">
                            {{ __('No stations selected — the Rivers tab will be empty.') }}
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="code in selectedCodes" :key="code">
                                <div class="flex items-center gap-1 pl-3 pr-1 py-1 rounded-full
                                            bg-emerald-900/30 border border-emerald-700/40">
                                    <span class="text-sm font-medium text-emerald-200" x-text="getStationMeta(code).name"></span>
                                    <span class="text-xs text-emerald-600 ml-0.5" x-text="'· ' + getStationMeta(code).river"></span>
                                    <button @click.prevent="removeStation(code)" type="button"
                                            class="ml-1 w-5 h-5 rounded-full flex items-center justify-center
                                                   text-emerald-600 hover:text-white hover:bg-red-600 transition-colors leading-none text-sm">
                                        ×
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>

                </div>
            </div>
            @endif

            {{-- ── Custom station codes ── --}}
            @if($providerMeta['custom_stations'] ?? true)
            <div x-show="enabled" x-cloak class="border-t border-white/5 p-5">
                <h3 class="font-medium text-white text-sm mb-1">{{ __('Custom station codes') }}</h3>
                <p class="text-xs text-gray-400 mb-4">
                    {{ __('Add any station not found in the catalog. Find codes at') }}
                    <a href="https://waterinfo.rws.nl" target="_blank" rel="noopener"
                       class="text-emerald-400 hover:text-emerald-300 underline">waterinfo.rws.nl</a>.
                    {{ __('Use lowercase dot-notation, e.g.') }}
                    <code class="font-mono text-xs text-gray-300">zwolle.ijssel</code>.
                </p>

                <div class="space-y-2 mb-4" x-show="customStations.length > 0">
                    <template x-for="(station, index) in customStations" :key="index">
                        <div class="flex items-center gap-3 p-3 rounded-xl border border-white/10 bg-gray-900/30">
                            <div class="flex-1 min-w-0">
                                <div class="font-medium text-white text-sm" x-text="station.name"></div>
                                <div class="text-xs text-gray-500 flex items-center gap-2 mt-0.5">
                                    <span class="text-emerald-400" x-text="station.river"></span>
                                    <span>·</span>
                                    <code class="font-mono" x-text="station.code"></code>
                                </div>
                            </div>
                            <button @click.prevent="removeCustom(index)" type="button"
                                    class="p-1.5 rounded-lg text-gray-500 hover:text-red-400 hover:bg-red-900/20 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </template>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">{{ __('Station code') }} <span class="text-red-400">*</span></label>
                        <input x-model="newCode" type="text" placeholder="e.g. zwolle.ijssel"
                               @keydown.enter.prevent="addCustom()"
                               class="w-full px-3 py-2 bg-gray-900/60 border border-white/10 rounded-lg text-sm text-white
                                      placeholder-gray-600 font-mono focus:outline-none focus:border-emerald-500/50">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">{{ __('Display name') }}</label>
                        <input x-model="newName" type="text" placeholder="e.g. Zwolle"
                               @keydown.enter.prevent="addCustom()"
                               class="w-full px-3 py-2 bg-gray-900/60 border border-white/10 rounded-lg text-sm text-white
                                      placeholder-gray-600 focus:outline-none focus:border-emerald-500/50">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">{{ __('River') }}</label>
                        <div class="flex gap-2">
                            <input x-model="newRiver" type="text" placeholder="e.g. IJssel"
                                   @keydown.enter.prevent="addCustom()"
                                   class="flex-1 min-w-0 px-3 py-2 bg-gray-900/60 border border-white/10 rounded-lg text-sm text-white
                                          placeholder-gray-600 focus:outline-none focus:border-emerald-500/50">
                            <button @click.prevent="addCustom()" type="button"
                                    :disabled="!newCode.trim()"
                                    class="px-3 py-2 bg-emerald-700 hover:bg-emerald-600 disabled:bg-gray-700
                                           disabled:text-gray-500 text-white rounded-lg text-sm font-medium transition-colors flex-shrink-0">
                                {{ __('Add') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            {{-- ── Hidden form inputs — one JSON field per setting per provider ── --}}
            <input type="hidden" name="providers[{{ $providerId }}][enabled]" :value="enabled ? '1' : '0'">
            <input type="hidden" name="providers[{{ $providerId }}][stations_json]"  :value="JSON.stringify(selectedCodes)">
            <input type="hidden" name="providers[{{ $providerId }}][custom_json]"    :value="JSON.stringify(customStations)">

        </div>
        @endforeach
        {{-- (Adding a new provider = add an entry to RiverProviderRegistry::PROVIDERS + its service class) --}}

        {{-- Save --}}
        <div class="flex items-center gap-4">
            <button type="submit"
                    class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-medium transition-colors">
                {{ __('Save River Settings') }}
            </button>
            @php $anyEnabled = collect($providerSettings)->contains('enabled', true); @endphp
            @if($anyEnabled)
                <a href="{{ route('water') }}" target="_blank"
                   class="text-sm text-gray-400 hover:text-white transition-colors">
                    🏞 {{ __('View River Levels') }} ↗
                </a>
            @endif
        </div>

    </form>

    {{-- About / info box --}}
    <div class="bg-blue-900/20 border border-blue-800/30 rounded-2xl p-5 text-sm text-blue-200">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-blue-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
                <p class="font-medium text-blue-100 mb-1">{{ __('About river levels') }}</p>
                <p class="text-blue-300">
                    {{ __('River levels are real-time gauge measurements in centimetres above NAP (Dutch sea level datum). Data is polled every 15 minutes and cached for 45 minutes. No forecast data is available — river levels are measured, not predicted.') }}
                </p>
                <p class="mt-2 text-blue-300">
                    {{ __('Not all catalog stations report water level (WATHTE) data. Stations that return no data will show "-- cm" on the Water page.') }}
                </p>
                <p class="mt-2 text-blue-300">
                    {{ __('When multiple providers are enabled their data is merged on the Rivers tab — each provider\'s stations appear as separate cards.') }}
                </p>
            </div>
        </div>
    </div>

    {{-- Polling schedule --}}
    <div class="bg-gray-800/30 rounded-2xl p-5 border border-white/5 text-sm text-gray-400">
        <h3 class="font-semibold text-gray-300 mb-3">⏱ {{ __('Polling schedule') }}</h3>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div>
                <div class="text-xs text-gray-500 uppercase tracking-wider">{{ __('Interval') }}</div>
                <div class="text-white font-medium mt-1">15 {{ __('minutes') }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-500 uppercase tracking-wider">{{ __('Cache TTL') }}</div>
                <div class="text-white font-medium mt-1">45 {{ __('minutes') }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-500 uppercase tracking-wider">{{ __('Command') }}</div>
                <div class="text-white font-mono text-xs mt-1">weather:poll-external --source=rivers</div>
            </div>
            <div>
                <div class="text-xs text-gray-500 uppercase tracking-wider">{{ __('Coverage') }}</div>
                <div class="text-white font-medium mt-1">{{ __('Per active provider') }}</div>
            </div>
        </div>
    </div>

</div>
@endsection
