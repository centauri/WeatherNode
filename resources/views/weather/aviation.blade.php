@extends('weather.layout')

@section('title', __('Aviation Weather') . ' ' . ($activeIcao ?? '') . ' - ' . \App\Models\Setting::stationName())

@section('meta_description', __('Live METAR aviation weather for :icao with atmospheric profile, cloud layers, visibility, wind, and flight category.', ['icao' => $activeIcao ?? '']))
@section('og_image', ($activeIcao ?: \App\Models\Setting::getValue('metar.primary_icao', ''))
    ? route('og.aviation', ['icao' => $activeIcao ?: \App\Models\Setting::getValue('metar.primary_icao', '')])
    : route('og.home'))

@push('head_scripts')
    @vite('resources/js/pages/aviation.js')
@endpush

@section('content')
@php
    $activeUnits = $activeUnits ?? 'metric';
    $unit = app(\App\Support\UnitFormatter::class);
@endphp

<script type="application/json" id="aviation-translations">
{!! json_encode([
    'noData' => __('No METAR data available'),
    'loading' => __('Loading...'),
    'invalidIcao' => __('Invalid ICAO code'),
    'ground' => __('Ground'),
    'altitude' => __('Altitude'),
    'flightCategory' => __('Flight Category'),
    'ago' => __('ago'),
    'justNow' => __('just now'),
    'minutesAgo' => __(':min min ago'),
    'hoursAgo' => __(':hours h ago'),
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
</script>
<script>
    window.aviationConfig = {
        defaultIcao: @js($activeIcao),
        primaryIcao: @js($primaryIcao),
        metarEnabled: @js($metarEnabled),
        baseUrl: @js(route('aviation')),
    };
</script>

{{-- Sky & Water Tab Strip --}}
<div class="flex gap-2 mb-6">
    <a href="{{ route('aviation') }}"
       class="px-4 py-2 rounded-lg text-sm font-medium transition-colors weather-section-tab bg-sky-600 text-on-accent shadow-lg shadow-sky-600/30 text-ui-fg">
        ✈ {{ __('Aviation') }}
    </a>
    <a href="{{ route('water') }}"
       class="px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-ui-overlay/10 text-ui-secondary hover:bg-ui-overlay/20">
        🌊 {{ __('Water') }}
    </a>
</div>

<div class="space-y-6" x-data="aviationWeather()">
    <!-- Header + Search -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold">{{ __('Aviation Weather') }}</h1>
            <p class="text-ui-muted" x-text="stationName ? stationName + ' (' + currentIcao + ')' : currentIcao">{{ ($ssrMetar['name'] ?? '') ? $ssrMetar['name'] . ' (' . $activeIcao . ')' : $activeIcao }}</p>
            <p class="text-ui-muted text-sm mt-1">{{ __('Aviation page intro', ['icao' => $activeIcao ?? '']) }}</p>
        </div>
        <div class="flex items-center gap-2">
            <div class="relative">
                <input type="text"
                       x-model="searchInput"
                       @keydown.enter="searchIcao()"
                       @input="searchInput = searchInput.toUpperCase().replace(/[^A-Z]/g, '').slice(0, 4)"
                       placeholder="{{ __('Search ICAO code') }}"
                       class="bg-ui-overlay/10 border border-ui-line/20 rounded-lg px-4 py-2 text-base sm:text-sm text-ui-fg placeholder-ui-muted focus:outline-none focus:border-blue-500 w-40">
                <!-- Recent searches dropdown -->
                <div x-show="recentSearches.length > 0 && searchFocused" x-cloak
                     @click.outside="searchFocused = false"
                     class="absolute top-full left-0 mt-1 w-full bg-weather-card border border-ui-line/10 rounded-lg shadow-xl z-10 overflow-hidden">
                    <div class="text-xs text-ui-muted px-3 py-1.5">{{ __('Recent searches') }}</div>
                    <template x-for="icao in recentSearches" :key="icao">
                        <button @click="searchInput = icao; searchIcao(); searchFocused = false"
                                class="block w-full text-left px-3 py-2 text-sm hover:bg-ui-overlay/10 text-ui-body"
                                x-text="icao"></button>
                    </template>
                </div>
            </div>
            <button @click="searchIcao()"
                    :disabled="searchInput.length !== 4 || loading"
                    class="bg-ui-accent-strong text-on-accent hover:bg-ui-action-deep disabled:opacity-50 disabled:cursor-not-allowed px-4 py-2 rounded-lg text-sm transition-colors">
                <span x-show="!loading">{{ __('Search') }}</span>
                <span x-show="loading" x-cloak>...</span>
            </button>
        </div>
    </div>

    <!-- Atmospheric Profile — the showpiece -->
    <div class="bg-weather-card rounded-2xl p-4 md:p-5 border border-ui-line/10 relative overflow-hidden"
         :class="flightCategoryBorderClass()">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-3">
            <div class="flex items-center gap-3 flex-wrap min-w-0">
                <h2 class="font-semibold text-lg">{{ __('Live Atmospheric Profile') }} <span class="text-ui-muted" x-text="currentIcao">{{ $activeIcao }}</span></h2>
                <div class="flex items-center gap-2">
                    <label for="aviation-scene" class="text-xs font-medium text-ui-secondary whitespace-nowrap">{{ __('Atmospheric scene') }}</label>
                    <select id="aviation-scene"
                            :value="scene"
                            @change="selectScene($event.target.value)"
                            aria-describedby="atmospheric-scene-help"
                            class="bg-ui-overlay/10 border border-ui-line/20 rounded-lg px-2.5 py-1.5 text-sm text-ui-fg focus:outline-none focus:border-blue-500 max-w-full">
                        <option value="village">{{ __('Original village') }}</option>
                        <option value="schiphol">{{ __('Schiphol-inspired airport') }}</option>
                        <option value="arctic">{{ __('Arctic research airstrip') }}</option>
                        <option value="volcanic">{{ __('Volcanic island airport') }}</option>
                    </select>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-xs px-2 py-1 rounded-full font-medium"
                      :class="flightCategoryBadgeClass()"
                      x-text="metar?.flight_category || '--'"
                      x-show="metar">{{ $ssrMetar['flight_category'] ?? '' }}</span>
                <span class="text-xs text-ui-muted" x-text="observedAgo" x-show="metar"></span>
            </div>
        </div>
        <p id="atmospheric-scene-help" class="text-xs text-ui-muted mb-3">{{ __('Scenery and aircraft are illustrative; weather follows the selected airport’s METAR. This does not track live flights.') }}</p>
        <div class="relative">
            <canvas id="atmosphere-canvas" class="w-full rounded-xl" style="height: 500px;"
                    role="img"
                    aria-label="{{ __('Live atmospheric profile visualization for :icao showing cloud layers, wind patterns, and weather conditions at various altitudes from ground level to 45,000 feet', ['icao' => $activeIcao]) }}"></canvas>
            <!-- Legend overlay -->
            <div data-theme-surface="dark" class="absolute bottom-3 right-3 bg-black/60 backdrop-blur-sm rounded-lg px-3 py-2 text-xs space-y-1" x-show="metar">
                <div class="text-ui-secondary font-medium mb-1">{{ __('Cloud layers') }}</div>
                <div class="flex items-center gap-2"><span class="w-4 h-2 rounded-sm bg-ui-overlay/20 inline-block"></span> <span class="text-ui-muted">FEW</span></div>
                <div class="flex items-center gap-2"><span class="w-4 h-2 rounded-sm bg-ui-overlay/40 inline-block"></span> <span class="text-ui-muted">SCT</span></div>
                <div class="flex items-center gap-2"><span class="w-4 h-2 rounded-sm bg-ui-overlay/60 inline-block"></span> <span class="text-ui-muted">BKN</span></div>
                <div class="flex items-center gap-2"><span class="w-4 h-2 rounded-sm bg-ui-overlay/80 inline-block"></span> <span class="text-ui-muted">OVC</span></div>
            </div>
            <noscript>
                <div data-theme-surface="dark" class="bg-black/40 rounded-xl p-6 text-ui-secondary text-sm space-y-2" style="min-height: 200px;">
                    <p class="font-semibold text-ui-fg">{{ __('Atmospheric Profile for :icao', ['icao' => $activeIcao]) }}</p>
                    @if($ssrMetar)
                    <p>{{ __('Flight category') }}: <strong>{{ $ssrMetar['flight_category'] ?? 'N/A' }}</strong></p>
                    @if(!empty($ssrMetar['clouds']))
                    <p>{{ __('Cloud layers') }}:
                        @foreach($ssrMetar['clouds'] as $cloud)
                            {{ $cloud['code'] }} {{ __('at') }} {{ number_format($cloud['base_feet'] ?? 0) }} ft{{ !$loop->last ? ',' : '' }}
                        @endforeach
                    </p>
                    @endif
                    @if(isset($ssrMetar['visibility']['meters']))
                    <p>{{ __('Visibility') }}: {{ $ssrMetar['visibility']['meters'] >= 9999 ? '10+ km' : number_format($ssrMetar['visibility']['meters'] / 1000, 1) . ' km' }}</p>
                    @endif
                    @if(isset($ssrMetar['wind']))
                    <p>{{ __('Wind') }}: {{ $ssrMetar['wind']['direction'] ?? 'VRB' }}° / {{ isset($ssrMetar['wind']['speed_kmh']) ? round($ssrMetar['wind']['speed_kmh']) . ' km/h' : '--' }}</p>
                    @endif
                    <p class="text-xs text-ui-subtle mt-3">{{ __('Enable JavaScript to see the animated atmospheric profile visualization with cloud layers, precipitation, and wind patterns.') }}</p>
                    @else
                    <p>{{ __('No METAR data available') }}</p>
                    @endif
                </div>
            </noscript>
            <!-- No data overlay -->
            <div x-show="!metar && !loading" x-cloak
                 class="absolute inset-0 flex items-center justify-center">
                <p class="text-ui-muted">{{ __('No METAR data available') }}</p>
            </div>
            <!-- Loading overlay -->
            <div x-show="loading" x-cloak
                 data-theme-surface="dark" class="absolute inset-0 flex items-center justify-center bg-black/30 rounded-xl">
                <div class="text-ui-secondary animate-pulse">{{ __('Loading...') }}</div>
            </div>
        </div>
    </div>

    <!-- Raw METAR -->
    <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10" x-show="metar">
        <h3 class="font-semibold mb-2">{{ __('Raw METAR') }}</h3>
        <div data-theme-surface="dark" class="font-mono text-sm text-data-green-400 bg-black/30 rounded-lg px-4 py-3 break-all" x-text="metar?.raw || ''">{{ $ssrMetar['raw'] ?? '' }}</div>
    </div>

    <!-- Data Panel -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4" x-show="metar">
        <!-- Temperature -->
        <div class="bg-weather-card rounded-2xl p-4 border border-ui-line/10">
            <div class="text-xs text-ui-muted mb-1">{{ __('Temperature') }}</div>
            <div class="text-xl font-bold" x-text="metar?.temperature != null ? metar.temperature + '°C' : '--'">{{ isset($ssrMetar['temperature']) ? $ssrMetar['temperature'] . '°C' : '--' }}</div>
        </div>
        <!-- Dewpoint -->
        <div class="bg-weather-card rounded-2xl p-4 border border-ui-line/10">
            <div class="text-xs text-ui-muted mb-1">{{ __('Dewpoint') }}</div>
            <div class="text-xl font-bold" x-text="metar?.dewpoint != null ? metar.dewpoint + '°C' : '--'">{{ isset($ssrMetar['dewpoint']) ? $ssrMetar['dewpoint'] . '°C' : '--' }}</div>
        </div>
        <!-- Humidity -->
        <div class="bg-weather-card rounded-2xl p-4 border border-ui-line/10">
            <div class="text-xs text-ui-muted mb-1">{{ __('Humidity') }}</div>
            <div class="text-xl font-bold" x-text="metar?.humidity != null ? metar.humidity + '%' : '--'">{{ isset($ssrMetar['humidity']) ? $ssrMetar['humidity'] . '%' : '--' }}</div>
        </div>
        <!-- Wind -->
        <div class="bg-weather-card rounded-2xl p-4 border border-ui-line/10">
            <div class="text-xs text-ui-muted mb-1">{{ __('Wind') }}</div>
            <div class="text-xl font-bold" x-text="formatWind()">@if($ssrMetar){{ ($ssrMetar['wind']['direction'] ?? '--') . '° / ' . (isset($ssrMetar['wind']['speed_kmh']) ? round($ssrMetar['wind']['speed_kmh']) . ' km/h' : '--') }}@else--@endif</div>
            <div class="text-xs text-ui-muted" x-show="metar?.wind?.gust_kts" x-text="'{{ __('Gusts') }}: ' + (metar?.wind?.gust_kts ? Math.round(metar.wind.gust_kts * 1.852) + ' km/h' : '')">@if(isset($ssrMetar['wind']['gust_kts'])){{ __('Gusts') }}: {{ round($ssrMetar['wind']['gust_kts'] * 1.852) }} km/h @endif</div>
        </div>
        <!-- Visibility -->
        <div class="bg-weather-card rounded-2xl p-4 border border-ui-line/10">
            <div class="text-xs text-ui-muted mb-1">{{ __('Visibility') }}</div>
            <div class="text-xl font-bold" x-text="formatVisibility()">@if(isset($ssrMetar['visibility']['meters']))@if($ssrMetar['visibility']['meters'] >= 9999)10+ km @elseif($ssrMetar['visibility']['meters'] >= 1000){{ number_format($ssrMetar['visibility']['meters'] / 1000, 1) }} km @else{{ round($ssrMetar['visibility']['meters']) }} m @endif @else--@endif</div>
        </div>
        <!-- Pressure -->
        <div class="bg-weather-card rounded-2xl p-4 border border-ui-line/10">
            <div class="text-xs text-ui-muted mb-1">{{ __('Pressure') }}</div>
            <div class="text-xl font-bold" x-text="metar?.pressure != null ? metar.pressure + ' hPa' : '--'">{{ isset($ssrMetar['pressure']) ? $ssrMetar['pressure'] . ' hPa' : '--' }}</div>
        </div>
    </div>

    <!-- Clouds & Conditions Detail -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4" x-show="metar && (metar.clouds?.length > 0 || metar.conditions?.length > 0)">
        <!-- Cloud Layers Table -->
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10" x-show="metar?.clouds?.length > 0">
            <h3 class="font-semibold mb-3">{{ __('Cloud layers') }}</h3>
            <div class="space-y-2">
                <template x-for="(cloud, i) in metar?.clouds || []" :key="i">
                    <div class="flex justify-between items-center text-sm">
                        <div>
                            <span class="text-ui-fg font-mono font-medium" x-text="cloud.code"></span>
                            <span class="text-ui-muted ml-1" x-text="cloud.text ? '(' + cloud.text + ')' : ''"></span>
                        </div>
                        <div class="text-ui-muted">
                            <span x-text="cloud.base_feet != null ? cloud.base_feet.toLocaleString() + ' ft' : ''"></span>
                            <span class="text-ui-subtle ml-1" x-text="cloud.base_meters != null ? '(' + Math.round(cloud.base_meters) + ' m)' : ''"></span>
                        </div>
                    </div>
                </template>
            </div>
        </div>
        <!-- Weather Conditions -->
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10" x-show="metar?.conditions?.length > 0">
            <h3 class="font-semibold mb-3">{{ __('Weather conditions') }}</h3>
            <div class="space-y-2">
                <template x-for="(cond, i) in metar?.conditions || []" :key="i">
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-ui-fg font-mono font-medium" x-text="cond.code"></span>
                        <span class="text-ui-muted" x-text="cond.text || ''"></span>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Error message -->
    <div x-show="error" x-cloak class="bg-red-500/10 border border-red-500/30 rounded-2xl p-4 text-data-red-400 text-sm" x-text="error"></div>

    <!-- Disclaimer -->
    <div class="text-xs text-ui-subtle text-center">
        {{ __('For informational purposes only. Not for flight planning. Always consult official aviation weather services.') }}
    </div>

    <!-- Understanding METAR — crawlable content for long-tail keywords -->
    <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
        <h2 class="font-semibold text-lg mb-3">{{ __('Understanding METAR weather reports') }}</h2>
        <div class="text-sm text-ui-muted space-y-2">
            <p>{{ __('METAR (Meteorological Aerodrome Report) is the international standard format for reporting current weather conditions at airports worldwide. Issued every 30 minutes to one hour, METAR reports contain wind speed and direction, visibility, cloud layers, temperature, dewpoint, and barometric pressure.') }}</p>
            <p>{{ __('Flight categories are determined by ceiling and visibility: VFR (Visual Flight Rules) means ceiling above 3,000 ft and visibility greater than 5 statute miles. MVFR (Marginal VFR) has ceiling 1,000-3,000 ft or visibility 3-5 miles. IFR (Instrument Flight Rules) means ceiling 500-999 ft or visibility 1-3 miles. LIFR (Low IFR) indicates ceiling below 500 ft or visibility under 1 mile.') }}</p>
            @if($ssrMetar)
            <p>{{ __('Current conditions at :icao: :cat, temperature :temp°C, wind :wind° at :speed km/h, visibility :vis, pressure :qnh hPa.', [
                'icao' => $activeIcao,
                'cat' => $ssrMetar['flight_category'] ?? 'N/A',
                'temp' => $ssrMetar['temperature'] ?? '--',
                'wind' => $ssrMetar['wind']['direction'] ?? 'VRB',
                'speed' => isset($ssrMetar['wind']['speed_kmh']) ? round($ssrMetar['wind']['speed_kmh']) : '--',
                'vis' => isset($ssrMetar['visibility']['meters']) ? ($ssrMetar['visibility']['meters'] >= 9999 ? '10+ km' : number_format($ssrMetar['visibility']['meters'] / 1000, 1) . ' km') : '--',
                'qnh' => $ssrMetar['pressure'] ?? '--',
            ]) }}</p>
            @endif
        </div>
    </div>

    <!-- Popular airports — internal links for SEO -->
    <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
        <h2 class="font-semibold mb-3">{{ __('Popular airports') }}</h2>
        @php
            $popularAirports = [
                'EHAM' => 'Amsterdam Schiphol',
                'EGLL' => 'London Heathrow',
                'EDDF' => 'Frankfurt',
                'LFPG' => 'Paris CDG',
                'LEMD' => 'Madrid Barajas',
                'LIRF' => 'Rome Fiumicino',
                'KJFK' => 'New York JFK',
                'KLAX' => 'Los Angeles LAX',
                'KORD' => 'Chicago O\'Hare',
                'KATL' => 'Atlanta Hartsfield',
                'KSFO' => 'San Francisco SFO',
                'RJTT' => 'Tokyo Haneda',
                'VHHH' => 'Hong Kong',
                'WSSS' => 'Singapore Changi',
                'OMDB' => 'Dubai',
                'YSSY' => 'Sydney',
            ];
        @endphp
        <div class="flex flex-wrap gap-2">
            @foreach($popularAirports as $icao => $name)
                <a href="{{ route('aviation', ['icao' => $icao]) }}"
                   class="text-xs px-3 py-1.5 rounded-lg transition-colors {{ ($activeIcao ?? '') === $icao ? 'bg-ui-accent-strong text-on-accent text-ui-fg' : 'bg-ui-overlay/5 hover:bg-ui-overlay/10 text-ui-muted hover:text-ui-fg' }}"
                   title="{{ __('METAR weather :name', ['name' => $name]) }}">
                    {{ $icao }} <span class="hidden sm:inline text-ui-subtle">{{ $name }}</span>
                </a>
            @endforeach
        </div>
        <p class="text-xs text-ui-subtle mt-3">{{ __('Search any ICAO airport code above to view live METAR conditions, flight category, and atmospheric profile.') }}</p>
    </div>

    <article class="bg-weather-card rounded-2xl p-6 border border-ui-line/10 prose prose-invert prose-sm max-w-none">
        <h2 class="text-lg font-semibold mb-3">{{ __('Aviation page about heading') }}</h2>
        <p class="text-ui-secondary mb-3">{{ __('Aviation page about body 1') }}</p>
        <p class="text-ui-secondary mb-3">{{ __('Aviation page about body 2') }}</p>
        <p class="text-ui-secondary mb-3">{{ __('Aviation page about body 3') }}</p>
        <footer class="text-xs text-ui-subtle mt-4 pt-4 border-t border-ui-line/10">{{ __('Aviation page sources') }}</footer>
    </article>
</div>

{{-- JSON-LD structured data for SEO --}}
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'WebPage',
    'name' => __('Aviation Weather') . ' ' . ($activeIcao ?? '') . ($ssrMetar['name'] ?? '' ? ' — ' . $ssrMetar['name'] : ''),
    'description' => __('Live METAR aviation weather for :icao with atmospheric profile, cloud layers, visibility, wind, and flight category.', ['icao' => $activeIcao ?? '']),
    'url' => url()->current(),
    'dateModified' => $ssrMetar['observed'] ?? now()->toIso8601String(),
    'isPartOf' => [
        '@type' => 'WebSite',
        'name' => \App\Models\Setting::stationName(),
        'url' => url('/'),
    ],
    'breadcrumb' => [
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => __('Home'), 'item' => url('/')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => __('Aviation Weather'), 'item' => route('aviation')],
            ['@type' => 'ListItem', 'position' => 3, 'name' => ($ssrMetar['name'] ?? '') ? $ssrMetar['name'] . ' (' . $activeIcao . ')' : $activeIcao],
        ],
    ],
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}
</script>

{{-- Airport structured data --}}
@if($ssrMetar)
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Airport',
    'name' => ($ssrMetar['name'] ?? $activeIcao),
    'iataCode' => $ssrMetar['iata'] ?? null,
    'icaoCode' => $activeIcao,
    'url' => url()->current(),
    ...( isset($ssrMetar['latitude'], $ssrMetar['longitude']) ? [
        'geo' => [
            '@type' => 'GeoCoordinates',
            'latitude' => $ssrMetar['latitude'],
            'longitude' => $ssrMetar['longitude'],
        ],
    ] : []),
    'additionalProperty' => array_filter([
        [
            '@type' => 'PropertyValue',
            'name' => 'Flight Category',
            'value' => $ssrMetar['flight_category'] ?? null,
        ],
        [
            '@type' => 'PropertyValue',
            'name' => 'Temperature',
            'value' => isset($ssrMetar['temperature']) ? $ssrMetar['temperature'] . '°C' : null,
            'unitCode' => 'CEL',
        ],
        [
            '@type' => 'PropertyValue',
            'name' => 'Barometric Pressure',
            'value' => isset($ssrMetar['pressure']) ? $ssrMetar['pressure'] . ' hPa' : null,
            'unitCode' => 'A97',
        ],
        [
            '@type' => 'PropertyValue',
            'name' => 'Wind',
            'value' => isset($ssrMetar['wind']['speed_kmh'])
                ? ($ssrMetar['wind']['direction'] ?? 'VRB') . '° at ' . round($ssrMetar['wind']['speed_kmh']) . ' km/h'
                : null,
        ],
        [
            '@type' => 'PropertyValue',
            'name' => 'METAR',
            'value' => $ssrMetar['raw'] ?? null,
        ],
    ], fn($p) => $p['value'] !== null),
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}
</script>
@endif

{{-- Interactive visualization (VideoObject) for the animated atmospheric profile --}}
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'VideoObject',
    'name' => __('Live Atmospheric Profile') . ' ' . $activeIcao . ($ssrMetar['name'] ?? '' ? ' — ' . $ssrMetar['name'] : ''),
    'description' => __('Interactive animated atmospheric profile showing real-time cloud layers, wind patterns, precipitation, and flight category for :icao. Visualizes METAR data from ground level to 45,000 feet.', ['icao' => $activeIcao]),
    'thumbnailUrl' => $activeIcao ? route('og.aviation', ['icao' => $activeIcao]) : route('og.home'),
    'uploadDate' => $ssrMetar['observed'] ?? now()->toIso8601String(),
    'contentUrl' => url()->current(),
    'embedUrl' => url()->current(),
    'interactionStatistic' => [
        '@type' => 'InteractionCounter',
        'interactionType' => ['@type' => 'WatchAction'],
        'userInteractionCount' => 0,
    ],
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}
</script>

{{-- HowTo structured data for Understanding METAR section --}}
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'HowTo',
    'name' => __('How to read a METAR aviation weather report'),
    'description' => __('Learn how to interpret METAR reports including flight categories, wind, visibility, cloud layers, temperature, and pressure for any airport worldwide.'),
    'step' => [
        [
            '@type' => 'HowToStep',
            'name' => __('Identify the airport'),
            'text' => __('The first group in a METAR is the 4-letter ICAO airport code (e.g. :icao). This tells you which aerodrome the observation was taken at.', ['icao' => $activeIcao]),
        ],
        [
            '@type' => 'HowToStep',
            'name' => __('Read the observation time'),
            'text' => __('The second group shows day and time in UTC (Zulu time), e.g. 031250Z means the 3rd day of the month at 12:50 UTC.'),
        ],
        [
            '@type' => 'HowToStep',
            'name' => __('Decode wind information'),
            'text' => __('Wind is shown as direction (degrees) and speed in knots, e.g. 27015KT means wind from 270° at 15 knots. Gusts are indicated with G, e.g. 27015G25KT.'),
        ],
        [
            '@type' => 'HowToStep',
            'name' => __('Check visibility'),
            'text' => __('Visibility is given in meters (9999 means 10 km or more). Values below 5000m may indicate reduced flight conditions.'),
        ],
        [
            '@type' => 'HowToStep',
            'name' => __('Interpret cloud layers'),
            'text' => __('Cloud coverage uses codes: FEW (1-2 oktas), SCT (3-4 oktas), BKN (5-7 oktas), OVC (8 oktas). The number after indicates height in hundreds of feet, e.g. SCT020 means scattered clouds at 2,000 ft.'),
        ],
        [
            '@type' => 'HowToStep',
            'name' => __('Determine the flight category'),
            'text' => __('VFR: ceiling above 3,000 ft and visibility over 5 miles. MVFR: ceiling 1,000-3,000 ft or visibility 3-5 miles. IFR: ceiling 500-999 ft or visibility 1-3 miles. LIFR: ceiling below 500 ft or visibility under 1 mile.'),
        ],
    ],
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}
</script>

{{-- FAQ structured data for rich results --}}
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => [
        [
            '@type' => 'Question',
            'name' => __('What is a METAR report?'),
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => __('A METAR (Meteorological Aerodrome Report) is a standardized weather observation format used at airports worldwide. It includes wind, visibility, clouds, temperature, dewpoint, and pressure data, typically updated every 30-60 minutes.'),
            ],
        ],
        [
            '@type' => 'Question',
            'name' => __('What do VFR, MVFR, IFR, and LIFR mean?'),
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => __('These are flight categories based on ceiling and visibility. VFR (Visual Flight Rules): ceiling above 3,000 ft, visibility over 5 miles. MVFR (Marginal VFR): ceiling 1,000-3,000 ft or visibility 3-5 miles. IFR (Instrument Flight Rules): ceiling 500-999 ft or visibility 1-3 miles. LIFR (Low IFR): ceiling below 500 ft or visibility under 1 mile.'),
            ],
        ],
        [
            '@type' => 'Question',
            'name' => __('What does :icao METAR show?', ['icao' => $activeIcao]),
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $ssrMetar
                    ? __('The current METAR for :icao (:name) shows :cat conditions with temperature :temp°C, visibility :vis, and wind from :wind° at :speed km/h.', [
                        'icao' => $activeIcao,
                        'name' => $ssrMetar['name'] ?? $activeIcao,
                        'cat' => $ssrMetar['flight_category'] ?? 'N/A',
                        'temp' => $ssrMetar['temperature'] ?? '--',
                        'vis' => isset($ssrMetar['visibility']['meters']) ? ($ssrMetar['visibility']['meters'] >= 9999 ? '10+ km' : number_format($ssrMetar['visibility']['meters'] / 1000, 1) . ' km') : '--',
                        'wind' => $ssrMetar['wind']['direction'] ?? 'variable',
                        'speed' => isset($ssrMetar['wind']['speed_kmh']) ? round($ssrMetar['wind']['speed_kmh']) : '--',
                    ])
                    : __('METAR data for :icao shows current weather conditions including temperature, wind, visibility, cloud layers, and flight category.', ['icao' => $activeIcao]),
            ],
        ],
    ],
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}
</script>
@endsection
