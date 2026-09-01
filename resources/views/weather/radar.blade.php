@extends('weather.layout')

@section('title', __('Radar') . ' - ' . \App\Models\Setting::stationName())

@section('meta_description', __('Radar page meta description', ['location' => \App\Models\Setting::stationLocation() ?: \App\Models\Setting::stationName()]))
@section('og_image', route('og.generic', ['page' => 'radar']))

@section('content')
@php
    $activeUnits = $activeUnits ?? 'metric';
    $unit = app(\App\Support\UnitFormatter::class);
    $radarProvider = \App\Models\Setting::getValue('radar.provider', 'rainviewer');
    $radarUrl = \App\Models\Setting::getValue('radar.url', '');
    $stationLocation = \App\Models\Setting::stationLocation() ?: \App\Models\Setting::stationName();
    $stationLat = \App\Models\Setting::latitude();
    $stationLon = \App\Models\Setting::longitude();
    $rainviewerMode = \App\Models\Setting::getValue('radar.rainviewer_mode', 'api');
    $rainviewerZoom = \App\Models\Setting::getValue('radar.rainviewer_zoom', 7);
    $useProxy = \App\Models\Setting::getValue('radar.use_proxy', false);
    $frameDelay = (int) \App\Models\Setting::getValue('radar.frame_delay', 1000);
    // Which sources appear is an admin choice rather than something derived
    // from the main provider, which is always shown on top of the selection.
    $radarSources = \App\Support\RadarSourceRegistry::all();
    $visibleSources = \App\Support\RadarSourceRegistry::visible(
        \App\Models\Setting::getValue('radar.card_sources', ''),
        $radarProvider
    );
    $showKnmi = in_array('knmi', $visibleSources, true);
    $showBuienradar = in_array('buienradar', $visibleSources, true);
    $satelliteEnabled = (bool) \App\Models\Setting::getValue('satellite.enabled', true);
    $providerLabels = [
        'knmi' => 'KNMI',
        'buienradar' => 'Buienradar',
        'rainviewer' => 'RainViewer'
    ];
    $providerLabel = $providerLabels[$radarProvider] ?? 'KNMI';
@endphp
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold">📡 {{ __('Precipitation radar') }}</h1>
            <p class="text-gray-400">{{ __('Radar page intro', ['location' => $stationLocation]) }}</p>
        </div>
        <div class="flex gap-2" x-data="{ activeProvider: '{{ $radarProvider }}' }" x-init="$watch('activeProvider', value => window.switchRadarProvider(value))">
            @foreach($visibleSources as $sourceId)
            <button @click="activeProvider = '{{ $sourceId }}'" 
                    :class="activeProvider === '{{ $sourceId }}' ? 'bg-blue-600' : 'bg-white/10 hover:bg-white/20'"
                    class="px-4 py-2 rounded-lg text-sm transition-colors">{{ $radarSources[$sourceId]['label'] }}</button>
            @endforeach
        </div>
    </div>

    <!-- Main Radar -->
    <div class="bg-weather-card rounded-2xl p-4 border border-white/10" 
         x-data="radarDisplay()" 
         x-init="init(); window.radarDisplayInstance = $data">
        <div class="aspect-[4/5] md:aspect-[16/10] [@media(max-height:600px)]:max-h-[70vh] bg-black/30 rounded-xl overflow-hidden relative radar-main-stage">
            
            @if($showKnmi)
            {{-- KNMI Radar --}}
            <div x-show="currentProvider === 'knmi'" class="w-full h-full">
                <img id="radar-main-image-knmi" 
                     src="{{ $radarUrl ?: 'https://cdn.knmi.nl/knmi/map/page/weer/actueel-weer/neerslagradar/WWWRADARTMP_loop.gif' }}?t={{ time() }}" 
                     alt="{{ __('Precipitation radar for :location', ['location' => $stationLocation]) }}" 
                     class="w-full h-full object-contain"
                     onerror="this.parentElement.innerHTML='<div class=\'absolute inset-0 flex items-center justify-center text-gray-500\'>📡 {{ __('Radar not available') }}</div>'">
            </div>
            
            @endif

            @if($showBuienradar)
            {{-- Buienradar --}}
            <div x-show="currentProvider === 'buienradar'" class="w-full h-full">
                <img id="radar-main-image-buienradar" 
                     src="https://api.buienradar.nl/image/1.0/radarmapnl?w=1200&h=800&t={{ time() }}" 
                     alt="Buienradar" 
                     class="w-full h-full object-contain"
                     onerror="this.parentElement.innerHTML='<div class=\'absolute inset-0 flex items-center justify-center text-gray-500\'>{{ __('Radar not available') }}</div>'">
            </div>
            
            @endif

            {{-- RainViewer API --}}
            <div x-show="currentProvider === 'rainviewer' && rainviewerMode === 'api'" class="w-full h-full">
                <div id="radar-map-main" class="w-full h-full radar-main-map"></div>
            </div>
            
            {{-- RainViewer Iframe --}}
            <div x-show="currentProvider === 'rainviewer' && rainviewerMode === 'iframe'" class="w-full h-full">
                <iframe 
                    id="radar-rainviewer-iframe"
                    src="https://www.rainviewer.com/map.html?loc={{ $stationLat }},{{ $stationLon }},{{ $rainviewerZoom }}&oC=true&oCS=1&c=3&o=83&lm=1&layer=radar&sm=1&sn=1"
                    class="w-full h-full"
                    frameborder="0"
                    style="border:0;"
                    allowfullscreen>
                </iframe>
            </div>
            
            <div class="absolute bottom-6 left-2 md:bottom-4 md:left-4 radar-overlay-panel pointer-events-none text-xs bg-black/70 px-2 py-1.5 md:px-3 md:py-2 rounded-lg max-w-[60%] md:max-w-none">
                <div class="flex items-center gap-2">
                    <span class="live-indicator inline-block w-2 h-2 bg-green-500 rounded-full"></span>
                    <span x-text="'{{ __('Live') }} - ' + getProviderLabel(currentProvider)"></span>
                </div>
                <div class="mt-1 text-[11px] text-gray-300 truncate" x-text="stationLocationLabel"></div>
            </div>
            <div class="absolute top-2 right-2 md:top-4 md:right-4 radar-overlay-panel pointer-events-none space-y-2">
                <div class="bg-black/70 px-3 py-2 rounded-lg text-xs text-right">
                    <div class="text-gray-300">{{ __('Last update') }}</div>
                    <div class="font-semibold" x-text="radarFrameTimeLabel || radarFrameTimeFallback"></div>
                </div>
                <div class="bg-black/70 px-3 py-2 rounded-lg text-xs">
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 bg-blue-300 rounded-sm"></div>
                        <span>{{ __('Light intensity') }}</span>
                    </div>
                    <div class="flex items-center gap-2 mt-1">
                        <div class="w-3 h-3 bg-blue-500 rounded-sm"></div>
                        <span>{{ __('Moderate') }}</span>
                    </div>
                    <div class="flex items-center gap-2 mt-1">
                        <div class="w-3 h-3 bg-blue-700 rounded-sm"></div>
                        <span>{{ __('Heavy intensity') }}</span>
                    </div>
                    <div class="flex items-center gap-2 mt-1">
                        <div class="w-3 h-3 bg-purple-600 rounded-sm"></div>
                        <span>{{ __('Very heavy') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Radar Views -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Buienradar -->
        @if($showBuienradar)
        <div class="bg-weather-card rounded-2xl p-4 border border-white/10">
            <h3 class="font-semibold mb-3">Buienradar</h3>
            <div class="aspect-square md:aspect-video [@media(max-height:600px)]:max-h-[70vh] bg-black/30 rounded-xl overflow-hidden relative">
                <img id="radar-buienradar-image" 
                     src="https://api.buienradar.nl/image/1.0/radarmapnl?w=500&h=512&t={{ time() }}" 
                     alt="Buienradar" 
                     class="w-full h-full object-contain"
                     onerror="this.parentElement.innerHTML='<div class=\'absolute inset-0 flex items-center justify-center text-gray-500\'>{{ __('Radar not available') }}</div>'">
            </div>
        </div>

        @endif

        <!-- Satellite -->
        @if($satelliteEnabled)
        <div class="bg-weather-card rounded-2xl p-4 border border-white/10 md:col-span-2">
            @php
                $yesterdayUtc = gmdate('Y-m-d', time() - 86400);
                $satelliteProvider = \App\Models\Setting::getValue('satellite.provider', 'knmi');
                $satelliteRegion = \App\Models\Setting::getValue('satellite.display_region', 'europe');
                $europeUrl = \App\Models\Setting::getValue("satellite.sources.{$satelliteProvider}.europe_url", '');
                $worldUrl = \App\Models\Setting::getValue("satellite.sources.{$satelliteProvider}.world_url", '');
                $satZoom = \App\Models\Setting::getValue("satellite.sources.{$satelliteProvider}.zoom", 4);
                $chosenUrl = ($satelliteRegion === 'world' && is_string($worldUrl) && trim($worldUrl) !== '') ? $worldUrl : $europeUrl;

                $providerLabels = [
                    'knmi' => 'KNMI',
                    'nasa' => 'NASA',
                    'custom' => __('Custom'),
                ];
                $providerLabel = $providerLabels[$satelliteProvider] ?? __('Custom');

                $looksLikeTile = function (?string $url): bool {
                    if (!is_string($url)) return false;
                    return str_contains($url, '{z}')
                        && (str_contains($url, '{x}') || str_contains($url, '{col}'))
                        && (str_contains($url, '{y}') || str_contains($url, '{row}'));
                };
            @endphp
            <div class="flex items-start justify-between gap-4 mb-3">
                <h3 class="font-semibold">{{ __('Satellite image') }}</h3>
                <div class="text-right">
                    <div class="text-sm text-gray-300">
                        <span id="satellite-source-text">{{ $providerLabel ?? '' }}</span>
                    </div>
                    <div class="text-xs text-gray-400 leading-tight">
                        @php
                            $isGibs = isset($chosenUrl) && is_string($chosenUrl) && (str_contains($chosenUrl, 'gibs.earthdata.nasa.gov') || str_contains($chosenUrl, 'earthdata.nasa.gov'));
                        @endphp
                        @if($isGibs)
                            {{-- Will be updated by JS (NRT can include time). --}}
                            <span id="satellite-time-text">UTC {{ $yesterdayUtc }}</span>
                        @else
                            <span id="satellite-time-text">{{ __('Updated frequently') }}</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="aspect-square md:aspect-video [@media(max-height:600px)]:max-h-[70vh] bg-black/30 rounded-xl overflow-hidden relative">
                @if($looksLikeTile($chosenUrl))
                    <div id="satellite-map-main"
                         class="w-full h-full"
                         data-tile-url="{{ $chosenUrl }}"
                         data-zoom="{{ (int) $satZoom }}"
                         data-region="{{ $satelliteRegion }}"></div>
                @else
                    <img id="radar-satellite-image" 
                         src="{{ $chosenUrl }}?t={{ time() }}" 
                         alt="{{ __('Satellite image') }}" 
                         class="w-full h-full object-contain"
                         onerror="this.parentElement.innerHTML='<div class=\'absolute inset-0 flex items-center justify-center text-gray-500\'>{{ __('Satellite image not available') }}</div>'">
                @endif
            </div>
        </div>
        @endif
    </div>

    <!-- Local Forecast -->
    <div class="bg-weather-card rounded-2xl p-5 border border-white/10"
         x-data="precipForecast()"
         x-init="init()">
        <div class="flex items-start justify-between gap-4 mb-4">
            <h3 class="font-semibold flex items-center gap-2">
	                <img src="{{ asset(($weatherIconsPath ?? 'icons/weather') . '/rain.svg') }}"
	                     :src="(backgroundEffectsEnabled ? window.Meteo.iconsAnimatedBaseUrl : window.Meteo.iconsStaticBaseUrl) + '/rain.svg'"
	                     class="w-5 h-5" alt="">
                {{ __('Precipitation forecast for the coming hours') }}
            </h3>
            <div class="text-right text-xs text-gray-400 leading-tight" x-show="sourceUpdatedLabel">
                <div x-text="sourceUpdatedLabel"></div>
            </div>
        </div>

        <div class="precip-panel rounded-xl border border-white/10 overflow-hidden relative">
            <div class="precip-grid pointer-events-none absolute inset-0 opacity-70"></div>
            <div class="p-4 md:p-5 relative">
                <div class="h-24 md:h-28">
                    <svg class="w-full h-full" viewBox="0 0 1000 120" preserveAspectRatio="none" aria-hidden="true">
                        <defs>
                            <linearGradient id="precipFill" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="rgba(59,130,246,0.55)"></stop>
                                <stop offset="100%" stop-color="rgba(59,130,246,0.05)"></stop>
                            </linearGradient>
                            <linearGradient id="precipStroke" x1="0" y1="0" x2="1" y2="0">
                                <stop offset="0%" stop-color="rgba(147,197,253,0.95)"></stop>
                                <stop offset="40%" stop-color="rgba(59,130,246,0.95)"></stop>
                                <stop offset="100%" stop-color="rgba(30,64,175,0.95)"></stop>
                            </linearGradient>
                            <filter id="precipGlow" x="-20%" y="-20%" width="140%" height="140%">
                                <feGaussianBlur stdDeviation="2.5" result="blur"></feGaussianBlur>
                                <feColorMatrix in="blur" type="matrix"
                                    values="1 0 0 0 0
                                            0 1 0 0 0
                                            0 0 1 0 0
                                            0 0 0 0.6 0" />
                                <feMerge>
                                    <feMergeNode />
                                    <feMergeNode in="SourceGraphic" />
                                </feMerge>
                            </filter>
                        </defs>

                        <path x-show="!loading && !error" :d="areaPath" fill="url(#precipFill)"></path>
                        <path x-show="!loading && !error" :d="linePath" fill="none" stroke="url(#precipStroke)" stroke-width="3" filter="url(#precipGlow)"></path>

                        <!-- Baseline -->
                        <line x1="0" y1="102" x2="1000" y2="102" stroke="rgba(255,255,255,0.10)" stroke-width="1"></line>
                    </svg>
                </div>

                <div class="mt-4 grid grid-cols-6 md:grid-cols-12 gap-2">
                    <template x-for="slot in slots" :key="slot.key">
                        <div class="text-center">
                            <div class="text-xs text-gray-300/80 mb-2" x-text="slot.label"></div>
                            <div class="h-16 bg-white/5 rounded-lg relative overflow-hidden border border-white/5">
                                <div class="absolute inset-0 precip-bar-sheen opacity-30"></div>
                                <div class="absolute bottom-0 left-0 right-0 precip-bar-fill transition-all duration-700 ease-out"
                                     :style="`height: ${slot.height}%`"></div>
                            </div>
                            <div class="text-xs text-gray-400 mt-1" x-text="slot.amount"></div>
                        </div>
                    </template>
                    <template x-if="loading">
                        <div class="col-span-6 md:col-span-12 text-center text-sm text-gray-400 py-4">
                            <span x-text="loadingText"></span>
                        </div>
                    </template>
                    <template x-if="!loading && error">
                        <div class="col-span-6 md:col-span-12 text-center text-sm text-red-300 py-4">
                            <span x-text="error"></span>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- KNMI Radar Nowcast (2-Hour Precipitation Forecast) -->
    @php
        $nowcastEnabled = \App\Models\Setting::getValue('radar.nowcast_enabled', false);
        $nowcastAnimationSpeed = \App\Models\Setting::getValue('radar.nowcast_animation_speed', 0.5);
        $nowcastAutoPlay = \App\Models\Setting::getValue('radar.nowcast_autoplay', false);
    @endphp
    @if($nowcastEnabled)
        <div class="bg-weather-card rounded-2xl p-5 border border-white/10" 
             x-data="radarNowcast()" 
             x-init="init()">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="font-semibold">{{ __('2-Hour Precipitation Forecast') }}</h3>
                    <p class="text-xs text-gray-400 mt-1">{{ __('KNMI Radar Nowcast - Netherlands') }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="togglePlay()" 
                            class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm transition">
                        <span x-show="!isPlaying">{{ __('Play') }}</span>
                        <span x-show="isPlaying">{{ __('Pause') }}</span>
                    </button>
                    <span class="text-xs text-gray-400" x-text="currentTimeLabel"></span>
                </div>
            </div>
            <div class="aspect-video md:aspect-[16/10] bg-black/30 rounded-xl overflow-hidden relative">
                <div x-show="loading" class="absolute inset-0 flex items-center justify-center">
                    <div class="text-center">
                        <div class="text-sm text-gray-400 mb-2">{{ __('Loading forecast data...') }}</div>
                    </div>
                </div>
                <div x-show="error" class="absolute inset-0 flex items-center justify-center">
                    <div class="text-center p-4">
                        <div class="text-sm text-red-400 mb-2">{{ __('Error loading data') }}</div>
                        <div class="text-xs text-gray-500" x-text="error"></div>
                    </div>
                </div>
                <div id="nowcast-map" class="w-full h-full" x-show="!error"></div>
                <div class="absolute bottom-4 left-4 right-4" x-show="!loading && !error && totalSteps > 0">
                    <input type="range" 
                           min="0" 
                           :max="totalSteps - 1" 
                           x-model="currentStep" 
                           @input="showFrame(currentStep)"
                           class="w-full h-2 bg-white/20 rounded-lg appearance-none cursor-pointer">
                    <div class="flex justify-between text-xs text-gray-300 mt-1">
                        <span>Now</span>
                        <span>+2 hours</span>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- About precipitation radar (scientific) -->
    <article class="bg-weather-card rounded-2xl border border-white/10 p-6 md:p-8" aria-labelledby="radar-about-heading">
        <h2 id="radar-about-heading" class="text-xl font-semibold mb-4">{{ __('Radar page about heading') }}</h2>
        <div class="prose prose-invert prose-sm max-w-none text-gray-300 space-y-4">
            <p>{{ __('Radar page about body 1') }}</p>
            <p>{{ __('Radar page about body 2') }}</p>
            <p>{{ __('Radar page about body 3') }}</p>
        </div>
        <footer class="mt-6 pt-4 border-t border-white/10">
            <p class="text-xs text-gray-500">{{ __('Radar page sources') }}</p>
        </footer>
    </article>
</div>

@push('scripts')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
    /* Radar precipitation panel: subtle technical grid + restrained gradients (business-like). */
    .precip-panel {
        background:
            radial-gradient(1200px 240px at 20% 0%, rgba(59, 130, 246, 0.20), transparent 55%),
            radial-gradient(900px 240px at 85% 10%, rgba(14, 165, 233, 0.16), transparent 60%),
            linear-gradient(180deg, rgba(15, 23, 42, 0.62), rgba(2, 6, 23, 0.40));
    }
    .precip-grid {
        background-image:
            linear-gradient(to right, rgba(255, 255, 255, 0.06) 1px, transparent 1px),
            linear-gradient(to bottom, rgba(255, 255, 255, 0.06) 1px, transparent 1px),
            radial-gradient(circle at 30% 20%, rgba(255, 255, 255, 0.06), transparent 45%);
        background-size: 64px 64px, 64px 64px, 100% 100%;
        mask-image: linear-gradient(to bottom, rgba(0,0,0,0.9), rgba(0,0,0,0.35));
    }
    .precip-bar-fill {
        background: linear-gradient(180deg, rgba(59, 130, 246, 0.75), rgba(37, 99, 235, 0.35));
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.20);
    }
    .precip-bar-sheen {
        background: linear-gradient(110deg, transparent 0%, rgba(255,255,255,0.08) 45%, transparent 70%);
        background-size: 220% 100%;
        animation: precip-sheen 3.4s ease-in-out infinite;
    }
    @keyframes precip-sheen {
        0% { background-position: 0% 0%; }
        50% { background-position: 100% 0%; }
        100% { background-position: 0% 0%; }
    }
    .radar-main-stage {
        isolation: isolate;
    }
    .radar-main-map {
        position: relative;
        z-index: 1;
    }
    .radar-main-map .leaflet-container,
    .radar-main-map .leaflet-pane,
    .radar-main-map .leaflet-control-container {
        z-index: 1;
    }
    .radar-overlay-panel {
        z-index: 5000 !important;
    }
</style>
<script>
function radarDisplay() {
    return {
        currentProvider: '{{ $radarProvider }}',
        rainviewerMode: '{{ $rainviewerMode }}',
        stationLat: {{ $stationLat }},
        stationLon: {{ $stationLon }},
        stationLocationLabel: @json($stationLocation),
        rainviewerZoom: {{ $rainviewerZoom }},
        useProxy: {{ $useProxy ? 'true' : 'false' }},
        radarMap: null,
        radarLayer: null,
        frameLayers: {},
        activeFrameLayerIndex: null,
        radarFrames: [],
        radarFrameTimeLabel: '',
        radarFrameTimeFallback: @json(__('Not updated yet')),
        animationInterval: null,
        isPlaying: true,
        frameDelay: {{ $frameDelay }},
        radarHost: '',
        currentFrameIndex: 0,
        refreshInterval: null,
        lastRadarGenerated: null,
        visibilityHandler: null,
        futureFramesProvider: 'none',
        
        init() {
            this.setRadarFrameTimeToNow();
            // Initialize based on current provider
            if (this.currentProvider === 'rainviewer' && this.rainviewerMode === 'api') {
                this.initRainViewerMap();
            }
            this.setupImageRefresh();
        },
        
        getProviderLabel(provider) {
            const labels = {
                'knmi': 'KNMI',
                'buienradar': 'Buienradar',
                'rainviewer': 'RainViewer'
            };
            return labels[provider] || 'KNMI';
        },
        
        switchProvider(provider) {
            this.currentProvider = provider;
            this.setRadarFrameTimeToNow();
            
            // Clean up previous provider
            if (this.animationInterval) {
                clearInterval(this.animationInterval);
                this.animationInterval = null;
            }
            if (this.refreshInterval) {
                clearInterval(this.refreshInterval);
                this.refreshInterval = null;
            }
            if (this.visibilityHandler) {
                document.removeEventListener('visibilitychange', this.visibilityHandler);
                this.visibilityHandler = null;
            }
            if (this.radarMap) {
                // Drop the per-frame layers before the map goes, otherwise the
                // next provider inherits references to layers on a dead map.
                this.clearFrameLayers();
                this.radarMap.remove();
                this.radarMap = null;
            }
            
            // Initialize new provider
            if (provider === 'rainviewer' && this.rainviewerMode === 'api') {
                setTimeout(() => this.initRainViewerMap(), 100);
            } else if (provider === 'rainviewer' && this.rainviewerMode === 'iframe') {
                const iframe = document.getElementById('radar-rainviewer-iframe');
                if (iframe) {
                    iframe.src = `https://www.rainviewer.com/map.html?loc=${this.stationLat},${this.stationLon},${this.rainviewerZoom}&oC=true&oCS=1&c=3&o=83&lm=1&layer=radar&sm=1&sn=1`;
                }
            }
            
            this.setupImageRefresh();
        },
        
        initRainViewerMap() {
            const mapContainer = document.getElementById('radar-map-main');
            if (!mapContainer) return;
            
            // Check if map is already initialized on this element and remove it
            if (mapContainer._leaflet_id) {
                try {
                    // Try to remove existing map
                    if (this.radarMap && this.radarMap.remove) {
                        this.radarMap.remove();
                    }
                    // Clear the leaflet ID to allow re-initialization
                    delete mapContainer._leaflet_id;
                } catch (e) {
                    // Ignore errors during cleanup
                }
            }
            
            const zoom = Math.min(Math.max(this.rainviewerZoom, 0), 7);
            
            this.radarMap = L.map('radar-map-main', {
                center: [this.stationLat, this.stationLon],
                zoom: zoom,
                zoomControl: true,
                attributionControl: true,
                maxZoom: 7,
                minZoom: 0
            });
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                minZoom: 0,
                maxZoom: 7
            }).addTo(this.radarMap);
            
            const stationIcon = L.divIcon({
                className: 'station-marker',
                html: '<div style="width: 16px; height: 16px; background: #10b981; border: 3px solid white; border-radius: 50%; box-shadow: 0 2px 6px rgba(0,0,0,0.4);"></div>',
                iconSize: [16, 16],
                iconAnchor: [8, 8]
            });
            L.marker([this.stationLat, this.stationLon], { icon: stationIcon }).addTo(this.radarMap);
            
            // This is a brand new map with no radar layers on it. loadRadarData()
            // skips the render when the frame signature is unchanged, which is
            // exactly the case after switching provider away and back, so the
            // map would stay empty until RainViewer published a new frame.
            this.lastRadarGenerated = null;
            this.loadRadarData();

            // Refresh the frames periodically so the animation advances with new timestamps.
            // This is safe because the API endpoints are cache-only and updated by the scheduler.
            this.refreshInterval = setInterval(() => {
                if (document.hidden) return;
                if (this.currentProvider !== 'rainviewer') return;
                if (this.rainviewerMode !== 'api') return;
                this.loadRadarData();
            }, 300000);

            this.visibilityHandler = () => {
                if (document.hidden) {
                    if (this.animationInterval) {
                        clearInterval(this.animationInterval);
                        this.animationInterval = null;
                    }
                    return;
                }
                if (this.currentProvider !== 'rainviewer' || this.rainviewerMode !== 'api') return;
                this.loadRadarData();
                if (this.radarFrames.length > 0) {
                    this.startAnimation();
                }
            };
            document.addEventListener('visibilitychange', this.visibilityHandler);
        },
        
        async loadRadarData() {
            try {
                const apiUrl = this.useProxy ? '/api/radar/frames' : '/api/weather/radar';
                const response = await fetch(apiUrl, {
                    headers: window.Meteo.apiHeaders(),
                });
                const data = await response.json();
                
                if (data.success && data.data && data.data.radar && data.data.radar.past) {
                    const rainviewerFrames = Array.isArray(data.data.radar.past)
                        ? data.data.radar.past
                        : [];

                    const futureFramesPayload = await this.loadRadarFutureFrames();
                    const futureFrames = Array.isArray(futureFramesPayload.frames)
                        ? futureFramesPayload.frames
                        : [];

                    const latestRainviewerTs = this.getRadarFrameUnix(
                        rainviewerFrames[rainviewerFrames.length - 1]
                    );
                    const filteredFutureFrames = futureFrames.filter((frame) => {
                        const frameTs = this.getRadarFrameUnix(frame);
                        if (!Number.isFinite(frameTs)) return false;
                        if (!Number.isFinite(latestRainviewerTs)) return true;
                        return frameTs > latestRainviewerTs;
                    });

                    const generated = data.data.generated || null;
                    const baseSignature = generated
                        || `${rainviewerFrames.length}:${String(rainviewerFrames[rainviewerFrames.length - 1]?.path || '')}`;
                    const futureProvider = String(futureFramesPayload?.provider || 'none');
                    const futureSignature = filteredFutureFrames.length > 0
                        ? `|future:${futureProvider}:${filteredFutureFrames.length}:${this.getRadarFrameUnix(filteredFutureFrames[0])}:${this.getRadarFrameUnix(filteredFutureFrames[filteredFutureFrames.length - 1])}`
                        : `|future:${futureProvider}:0`;
                    const frameSignature = `${baseSignature}${futureSignature}`;
                    if (this.lastRadarGenerated && frameSignature === this.lastRadarGenerated) {
                        return;
                    }
                    this.lastRadarGenerated = frameSignature;
                    this.futureFramesProvider = futureProvider;

                    const taggedRainviewerFrames = rainviewerFrames.map((frame) => ({
                        ...frame,
                        source: 'rainviewer',
                    }));
                    this.radarFrames = filteredFutureFrames.length > 0
                        ? [...taggedRainviewerFrames, ...filteredFutureFrames]
                        : taggedRainviewerFrames;

                    // Cached layers belong to the previous frame list.
                    this.clearFrameLayers();

                    // Use proxy or direct RainViewer based on setting
                    this.radarHost = this.useProxy
                        ? (data.data.host || '/api/radar/tile')
                        : (data.data.host || 'https://tilecache.rainviewer.com');
                    
                    if (this.radarFrames.length > 0) {
                        this.currentFrameIndex = 0;
                        this.startAnimation();
                    }
                }
            } catch (error) {
                console.error('Failed to load radar data:', error);
            }
        },

        async loadRadarFutureFrames() {
            try {
                const response = await fetch('/api/weather/radar-future-frames', {
                    headers: window.Meteo.apiHeaders(),
                });
                const data = await response.json();

                const provider = String(data?.data?.provider || 'none');
                const frames = data?.data?.frames;
                if (!response.ok || !data?.success || !Array.isArray(frames)) {
                    return { provider, frames: [] };
                }

                const normalizedFrames = frames
                    .map((frame) => {
                        if (!frame || typeof frame !== 'object') return null;

                        const kind = String(frame.kind || 'image_overlay');
                        if (kind === 'image_overlay') {
                            const imageUrl = String(frame.url || frame.imageUrl || '');
                            if (!imageUrl) return null;

                            return {
                                source: 'future_provider',
                                provider: String(frame.provider || provider || ''),
                                kind,
                                time: frame.time ?? frame.timestamp ?? null,
                                timestamp: frame.timestamp ?? null,
                                imageUrl,
                                proxyUrl: frame.proxy_url || frame.proxyUrl || null,
                                bounds: Array.isArray(frame.bounds) ? frame.bounds : null,
                                attribution: frame.attribution || null,
                                opacity: frame.opacity,
                            };
                        }

                        if (kind === 'tile_layer') {
                            const tileUrlTemplate = String(frame.url || frame.tile_url_template || frame.tileUrlTemplate || '');
                            if (!tileUrlTemplate) return null;

                            return {
                                source: 'future_provider',
                                provider: String(frame.provider || provider || ''),
                                kind,
                                time: frame.time ?? frame.timestamp ?? null,
                                timestamp: frame.timestamp ?? null,
                                tileUrlTemplate,
                                attribution: frame.attribution || null,
                                opacity: frame.opacity,
                                minZoom: Number(frame.min_zoom ?? frame.minZoom ?? 0),
                                maxZoom: Number(frame.max_zoom ?? frame.maxZoom ?? 7),
                            };
                        }

                        return null;
                    })
                    .filter(Boolean)
                    .sort((a, b) => {
                        const aTs = this.getRadarFrameUnix(a) || 0;
                        const bTs = this.getRadarFrameUnix(b) || 0;
                        return aTs - bTs;
                    });

                return {
                    provider,
                    frames: normalizedFrames,
                };
            } catch (error) {
                console.warn('Failed to load future radar frames for radar page:', error);
                return { provider: 'none', frames: [] };
            }
        },

        normalizeRadarBounds(bounds, fallback = [[50.75, 3.2], [53.7, 7.2]]) {
            if (!Array.isArray(bounds) || bounds.length !== 2) {
                return fallback;
            }

            const sw = bounds[0];
            const ne = bounds[1];
            if (!Array.isArray(sw) || !Array.isArray(ne) || sw.length !== 2 || ne.length !== 2) {
                return fallback;
            }

            const swLat = Number(sw[0]);
            const swLon = Number(sw[1]);
            const neLat = Number(ne[0]);
            const neLon = Number(ne[1]);
            if (![swLat, swLon, neLat, neLon].every(Number.isFinite)) {
                return fallback;
            }

            return [[swLat, swLon], [neLat, neLon]];
        },

        getRadarFrameUnix(frame) {
            const direct = Number(frame?.time ?? frame?.timestamp ?? null);
            if (Number.isFinite(direct) && direct > 0) {
                return direct > 1e12 ? Math.floor(direct / 1000) : direct;
            }

            const isoSource = frame?.time ?? frame?.timestamp ?? null;
            if (typeof isoSource === 'string' && isoSource.length > 0) {
                const parsedMs = Date.parse(isoSource);
                if (Number.isFinite(parsedMs) && parsedMs > 0) {
                    return Math.floor(parsedMs / 1000);
                }
            }

            const path = String(frame?.path || '');
            const unixInPath = path.match(/(?:^|\/)(\d{10,13})(?:\/|$)/);
            if (!unixInPath) return null;

            const parsed = Number(unixInPath[1]);
            if (!Number.isFinite(parsed) || parsed <= 0) return null;
            return parsed > 1e12 ? Math.floor(parsed / 1000) : parsed;
        },

        formatUnixTimestamp(unixTs) {
            if (!Number.isFinite(unixTs)) {
                return '';
            }

            const locale = window.Meteo?.jsLocale || 'en-US';
            const tz = window.Meteo?.stationTimezone || 'UTC';
            const date = new Date(unixTs * 1000);
            const timeLabel = date.toLocaleTimeString(locale, {
                timeZone: tz,
                hour: '2-digit',
                minute: '2-digit',
            });

            const today = new Date().toLocaleDateString('en-CA', { timeZone: tz });
            const frameDay = date.toLocaleDateString('en-CA', { timeZone: tz });
            if (today !== frameDay) {
                const shortDate = date.toLocaleDateString(locale, {
                    timeZone: tz,
                    day: '2-digit',
                    month: 'short',
                });
                return `${shortDate} ${timeLabel}`;
            }

            return timeLabel;
        },

        formatRadarFrameTimeLabel(frame) {
            const unixTs = this.getRadarFrameUnix(frame);
            if (!Number.isFinite(unixTs)) {
                return '';
            }
            return this.formatUnixTimestamp(unixTs);
        },

        setRadarFrameTimeToNow() {
            this.radarFrameTimeLabel = this.formatUnixTimestamp(Math.floor(Date.now() / 1000));
        },
        
        startAnimation() {
            if (this.animationInterval) {
                clearInterval(this.animationInterval);
            }
            
            this.showFrame(0);
            
            if (this.isPlaying && this.radarFrames.length > 1) {
                this.animationInterval = setInterval(() => {
                    this.currentFrameIndex = (this.currentFrameIndex + 1) % this.radarFrames.length;
                    this.showFrame(this.currentFrameIndex);
                }, this.frameDelay);
            }
        },
        
        // Build the Leaflet layer for a frame without adding it to the map.
        // Returns null when the frame carries nothing renderable.
        buildFrameLayer(frame) {
            const isFutureProviderFrame = frame?.source === 'future_provider';

            if (isFutureProviderFrame && frame?.kind === 'image_overlay' && typeof frame?.imageUrl === 'string') {
                const bounds = this.normalizeRadarBounds(frame.bounds);
                const rawImageUrl = (typeof frame.proxyUrl === 'string' && frame.proxyUrl)
                    ? frame.proxyUrl
                    : frame.imageUrl;
                const isAbsoluteImage = /^https?:\/\//i.test(rawImageUrl);
                const imageUrl = (!isAbsoluteImage && typeof window.Meteo?.appendApiKey === 'function')
                    ? window.Meteo.appendApiKey(rawImageUrl)
                    : rawImageUrl;
                const layerOpacity = Number.isFinite(Number(frame.opacity)) ? Number(frame.opacity) : 0.7;
                const useCrossOrigin = !isAbsoluteImage || rawImageUrl.startsWith(window.location.origin);

                return {
                    opacity: layerOpacity,
                    layer: L.imageOverlay(imageUrl, bounds, {
                        opacity: 0,
                        attribution: String(frame.attribution || 'Future radar'),
                        crossOrigin: useCrossOrigin
                    })
                };
            }

            if (isFutureProviderFrame && frame?.kind === 'tile_layer' && typeof frame?.tileUrlTemplate === 'string') {
                const rawTileUrlTemplate = frame.tileUrlTemplate;
                const isAbsoluteTemplate = /^https?:\/\//i.test(rawTileUrlTemplate);
                const tileUrlTemplate = (!isAbsoluteTemplate && typeof window.Meteo?.appendApiKey === 'function')
                    ? window.Meteo.appendApiKey(rawTileUrlTemplate)
                    : rawTileUrlTemplate;
                const layerOpacity = Number.isFinite(Number(frame.opacity)) ? Number(frame.opacity) : 0.7;
                const minZoom = Number.isFinite(Number(frame.minZoom)) ? Number(frame.minZoom) : 0;
                const maxZoom = Number.isFinite(Number(frame.maxZoom)) ? Number(frame.maxZoom) : 7;

                return {
                    opacity: layerOpacity,
                    layer: L.tileLayer(tileUrlTemplate, {
                        opacity: 0,
                        attribution: String(frame.attribution || 'Future radar'),
                        minZoom,
                        maxZoom,
                        tms: false,
                        crossOrigin: true
                    })
                };
            }

            if (!frame?.path) {
                return null;
            }

            const rawTileUrl = this.radarHost + frame.path + '/512/{z}/{x}/{y}/1/1_0.png';
            const isAbsolute = /^https?:\/\//i.test(rawTileUrl);
            const tileUrl = (this.useProxy && !isAbsolute) ? window.Meteo.appendApiKey(rawTileUrl) : rawTileUrl;

            return {
                opacity: 0.7,
                layer: L.tileLayer(tileUrl, {
                    opacity: 0,
                    attribution: 'RainViewer',
                    minZoom: 0,
                    maxZoom: 7,
                    tms: false,
                    crossOrigin: true
                })
            };
        },

        // Layers are built once per frame and kept on the map at opacity 0.
        // Rebuilding one per animation step meant the old layer was removed
        // before Leaflet had faded the new one in, so nothing was drawn for
        // roughly 145ms of every frame.
        frameLayerAt(frameIndex) {
            if (this.frameLayers[frameIndex]) {
                return this.frameLayers[frameIndex];
            }

            const built = this.buildFrameLayer(this.radarFrames[frameIndex]);
            if (!built) {
                return null;
            }

            built.layer.addTo(this.radarMap);
            this.frameLayers[frameIndex] = built;

            return built;
        },

        clearFrameLayers() {
            Object.values(this.frameLayers).forEach((entry) => {
                if (this.radarMap && this.radarMap.hasLayer(entry.layer)) {
                    this.radarMap.removeLayer(entry.layer);
                }
            });
            this.frameLayers = {};
            this.activeFrameLayerIndex = null;
            this.radarLayer = null;
        },

        showFrame(index) {
            if (this.radarFrames.length === 0 || !this.radarMap) return;

            const frameIndex = index % this.radarFrames.length;
            const frame = this.radarFrames[frameIndex];
            this.radarFrameTimeLabel = this.formatRadarFrameTimeLabel(frame);

            const next = this.frameLayerAt(frameIndex);
            if (!next) return;

            if (this.activeFrameLayerIndex !== null && this.activeFrameLayerIndex !== frameIndex) {
                this.frameLayers[this.activeFrameLayerIndex]?.layer.setOpacity(0);
            }

            next.layer.setOpacity(next.opacity);
            this.activeFrameLayerIndex = frameIndex;
            this.radarLayer = next.layer;
        },
        
        setupImageRefresh() {
            // Refresh KNMI image
            const knmiImg = document.getElementById('radar-main-image-knmi');
            if (knmiImg && this.currentProvider === 'knmi') {
                setInterval(() => {
                    if (!document.hidden && this.currentProvider === 'knmi') {
                        this.refreshImage(knmiImg);
                    }
                }, 60000);
            }
            
            // Refresh Buienradar image
            const buienradarImg = document.getElementById('radar-main-image-buienradar');
            if (buienradarImg && this.currentProvider === 'buienradar') {
                setInterval(() => {
                    if (!document.hidden && this.currentProvider === 'buienradar') {
                        this.refreshImage(buienradarImg);
                    }
                }, 60000);
            }
        },
        
        refreshImage(imgElement) {
            if (!imgElement) return;
            
            const currentSrc = imgElement.src;
            const url = new URL(currentSrc);
            url.searchParams.delete('t');
            url.searchParams.set('t', Date.now());
            
            const newSrc = url.toString();
            if (newSrc !== currentSrc) {
                imgElement.style.opacity = '0.8';
                imgElement.onload = () => {
                    imgElement.style.opacity = '1';
                    this.setRadarFrameTimeToNow();
                };
                imgElement.onerror = () => { imgElement.style.opacity = '1'; };
                imgElement.src = newSrc;
            }
        }
    };
}

function precipForecast() {
    return {
        slots: [],
        loading: true,
        error: null,
        loadingText: @json(__('Loading...')),
        refreshMs: 10 * 60 * 1000, // 10 min
        sourceUpdatedLabel: '',
        linePath: '',
        areaPath: '',

        init() {
            this.load();
            setInterval(() => this.load(), this.refreshMs);
        },

        buildSmoothPath(points) {
            if (!points || points.length < 2) return '';
            let d = `M ${points[0].x} ${points[0].y}`;
            for (let i = 1; i < points.length; i++) {
                const prev = points[i - 1];
                const curr = points[i];
                const c1x = prev.x + (curr.x - prev.x) / 2;
                const c1y = prev.y;
                const c2x = prev.x + (curr.x - prev.x) / 2;
                const c2y = curr.y;
                d += ` C ${c1x} ${c1y} ${c2x} ${c2y} ${curr.x} ${curr.y}`;
            }
            return d;
        },

        updatePaths() {
            if (!this.slots || this.slots.length < 2) {
                this.linePath = '';
                this.areaPath = '';
                return;
            }

            const n = this.slots.length;
            const width = 1000;
            const baselineY = 102;
            const topPad = 14;
            const chartHeight = baselineY - topPad;
            const step = n > 1 ? (width / (n - 1)) : width;

            const points = this.slots.map((s, i) => {
                const x = i * step;
                const y = topPad + (1 - (s.height / 100)) * chartHeight;
                return { x, y: Math.max(topPad, Math.min(baselineY, y)) };
            });

            const line = this.buildSmoothPath(points);
            const area = line
                ? `${line} L ${points[points.length - 1].x} ${baselineY} L ${points[0].x} ${baselineY} Z`
                : '';

            this.linePath = line;
            this.areaPath = area;
        },

        formatAmount(value, unit) {
            if (!Number.isFinite(value) || value <= 0) return `0 ${unit}`;
            // Keep it compact for the small widget.
            const fixed = unit === 'in' ? 2 : (value < 10 ? 1 : 0);
            return `${value.toFixed(fixed)} ${unit}`;
        },

        async load() {
            this.loading = true;
            this.error = null;
            this.slots = [];

            try {
                // Cache-only endpoint (populated by the configured forecast provider poller).
                const res = await fetch('/api/weather/dashboard', {
                    headers: window.Meteo.apiHeaders(),
                });

                if (!res.ok) {
                    throw new Error(`Forecast API error (${res.status})`);
                }

                const payload = await res.json();
                const hourly = payload?.hourlyForecast || [];
                const updatedAt = payload?.health_status?.forecast?.last_update;
                if (updatedAt) {
                    try {
                        const dt = new Date(updatedAt);
                        const locale = window.Meteo?.jsLocale || 'en-US';
                        this.sourceUpdatedLabel = `${@json(__('Updated'))}: ${dt.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' })}`;
                    } catch (e) {
                        this.sourceUpdatedLabel = '';
                    }
                } else {
                    this.sourceUpdatedLabel = '';
                }

                const now = Date.now();
                const upcoming = hourly
                    .filter((h) => {
                        const t = new Date(h.time || h.datetime || h.date || '').getTime();
                        return Number.isFinite(t) && t >= (now - 30 * 60 * 1000); // allow slight overlap
                    })
                    .slice(0, 12);

                const maxMm = Math.max(
                    0,
                    ...upcoming.map((h) => Number(h.precipitation_1h ?? 0) || 0)
                );

                const units = window.Meteo?.activeUnits || 'metric';
                const useImperial = (units === 'imperial' || units === 'uk');
                const unitLabel = useImperial ? 'in' : 'mm';
                const convert = useImperial ? 0.0393700787 : 1;

                const locale = window.Meteo?.jsLocale || 'en-US';
                this.slots = upcoming.map((h, idx) => {
                    const timeStr = h.time || h.datetime || h.date || '';
                    const dt = new Date(timeStr);
                    const mm = Number(h.precipitation_1h ?? 0) || 0;
                    const amount = mm * convert;
                    const height = maxMm > 0 ? Math.min(100, (mm / maxMm) * 100) : 0;

                    return {
                        key: `${timeStr || idx}`,
                        label: Number.isFinite(dt.getTime())
                            ? dt.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' })
                            : '--:--',
                        height: Math.round(height),
                        amount: this.formatAmount(amount, unitLabel),
                    };
                });

                if (!this.slots.length) {
                    this.error = @json(__('No data'));
                }
                this.updatePaths();
            } catch (e) {
                this.error = e?.message || 'Failed to load forecast';
            } finally {
                this.loading = false;
            }
        },
    };
}

(function() {
    
    
    // Global function for provider switching
    window.switchRadarProvider = function(provider) {
        if (window.radarDisplayInstance) {
            window.radarDisplayInstance.switchProvider(provider);
        }
    };

    // Auto-refresh satellite image when new ones are available
    function refreshImage(imgElement) {
        if (!imgElement) return;
        
        const currentSrc = imgElement.src;
        const url = new URL(currentSrc);
        
        // Remove existing timestamp parameter if present
        url.searchParams.delete('t');
        url.searchParams.delete('_t');
        url.searchParams.delete('cache');
        
        // Add new timestamp to force refresh
        url.searchParams.set('t', Date.now());
        
        // Only update if URL actually changed (avoid unnecessary reloads)
        const newSrc = url.toString();
        if (newSrc !== currentSrc) {
            // Use a small delay to prevent flicker
            imgElement.style.opacity = '0.8';
            imgElement.onload = () => {
                imgElement.style.opacity = '1';
            };
            imgElement.onerror = () => {
                imgElement.style.opacity = '1';
            };
            imgElement.src = newSrc;
        }
    }

    // Refresh satellite every 5 minutes (updates less frequently)
    const radarSatellite = document.getElementById('radar-satellite-image');
    if (radarSatellite) {
        setInterval(() => {
            if (!document.hidden) {
                refreshImage(radarSatellite);
            }
        }, 300000); // 5 minutes
    }
    
    function formatDateYYYYMMDD(date) {
        const y = date.getUTCFullYear();
        const m = String(date.getUTCMonth() + 1).padStart(2, '0');
        const d = String(date.getUTCDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    function normalizeTileUrlTemplate(url) {
        if (!url) return '';
        let out = String(url);

        // GIBS WMTS ResourceURL templates are .../{TileMatrix}/{TileRow}/{TileCol}
        // Leaflet uses {x}=col and {y}=row, so GIBS needs .../{z}/{y}/{x}.
        if (out.includes('gibs.earthdata.nasa.gov')) {
            out = out
                // If a stored template is in col/row order, correct it.
                .replaceAll('/{z}/{x}/{y}', '/{z}/{y}/{x}')
                .replaceAll('/{z}/{col}/{row}', '/{z}/{row}/{col}');
        }

        // Compatibility: older NASA NRT templates used "/250m/" as TileMatrixSet, which is invalid
        // for some layers in EPSG:3857. The VIIRS granule NRT layer uses GoogleMapsCompatible_Level9.
        if (
            out.includes('gibs.earthdata.nasa.gov')
            && out.includes('VIIRS_SNPP_CorrectedReflectance_TrueColor_Granule_v2_NRT')
            && out.includes('/250m/')
        ) {
            out = out.replace('/250m/', '/GoogleMapsCompatible_Level9/');
        }

        // Support {datetime} placeholder for ISO timestamp (UTC).
        // NRT imagery often has a few hours latency; use "now - 4h" and round to 10 minutes.
        const nrt = new Date(Date.now() - 4 * 60 * 60 * 1000);
        nrt.setUTCSeconds(0, 0);
        const roundedMin = Math.floor(nrt.getUTCMinutes() / 10) * 10;
        nrt.setUTCMinutes(roundedMin);
        const iso = nrt.toISOString().replace(/\.\d{3}Z$/, 'Z'); // keep seconds, drop ms

        // Support {time} placeholder for YYYY-MM-DD.
        // Default (daily mosaics): use yesterday UTC to avoid "today not fully processed yet" gaps/black tiles.
        // NRT layers that still use {time}: prefer today UTC for recency.
        const now = new Date();
        const todayYmd = formatDateYYYYMMDD(now);
        const yesterday = new Date(Date.now() - 24 * 60 * 60 * 1000);
        const yesterdayYmd = formatDateYYYYMMDD(yesterday);
        const ymd = out.includes('_NRT') ? todayYmd : yesterdayYmd;

        return out
            .replaceAll('{datetime}', iso)
            .replaceAll('{time_today}', todayYmd)
            .replaceAll('{time_yesterday}', yesterdayYmd)
            // {time_auto}: default to yesterday; we may upgrade to today if available (see initSatelliteTileMap)
            .replaceAll('{time_auto}', yesterdayYmd)
            .replaceAll('{time}', ymd);
    }

    function updateSatelliteBadge(tileUrlTemplate) {
        const sourceEl = document.getElementById('satellite-source-text');
        const timeEl = document.getElementById('satellite-time-text');
        if (!timeEl) return;

        if (tileUrlTemplate && (tileUrlTemplate.includes('gibs.earthdata.nasa.gov') || tileUrlTemplate.includes('earthdata.nasa.gov'))) {
            if (sourceEl) sourceEl.textContent = 'NASA GIBS';

            if (tileUrlTemplate.includes('{datetime}')) {
                const nrt = new Date(Date.now() - 4 * 60 * 60 * 1000);
                nrt.setUTCSeconds(0, 0);
                const roundedMin = Math.floor(nrt.getUTCMinutes() / 10) * 10;
                nrt.setUTCMinutes(roundedMin);
                // Display without milliseconds
                const iso = nrt.toISOString().replace(/\.\d{3}Z$/, 'Z');
                timeEl.textContent = `UTC ${iso}`;

                // Best-effort: ask GIBS what it actually served (nearest available time).
                // GIBS exposes `layer-time-actual` via CORS for tile requests.
                try {
                    const normalized = normalizeTileUrlTemplate(tileUrlTemplate);
                    const sample = normalized
                        .replaceAll('{z}', '2')
                        .replaceAll('{y}', '1')
                        .replaceAll('{x}', '2');

                    fetch(sample, { method: 'HEAD' })
                        .then(res => {
                            const actual = res.headers.get('layer-time-actual') || res.headers.get('layer-time-request');
                            if (actual) timeEl.textContent = `UTC ${actual}`;
                        })
                        .catch(() => {});
                } catch (e) {
                    // ignore
                }
                return;
            }

            // Date-based layers: show the date we will request (NRT -> today, otherwise yesterday).
            // If TIME=default is used, prefer the actual time from GIBS headers.
            const now = new Date();
            const todayYmd = formatDateYYYYMMDD(now);
            const yesterday = new Date(Date.now() - 24 * 60 * 60 * 1000);
            const yesterdayYmd = formatDateYYYYMMDD(yesterday);
            const ymd = tileUrlTemplate.includes('{time_today}')
                ? todayYmd
                : (tileUrlTemplate.includes('{time_yesterday}')
                    ? yesterdayYmd
                    : (tileUrlTemplate.includes('_NRT') ? todayYmd : yesterdayYmd));
            timeEl.textContent = tileUrlTemplate.includes('/default/default/')
                ? 'UTC …'
                : `UTC ${ymd}`;

            // Best-effort: ask GIBS what it actually served.
            try {
                const normalized = normalizeTileUrlTemplate(tileUrlTemplate);
                const sample = normalized
                    .replaceAll('{z}', '2')
                    .replaceAll('{y}', '1')
                    .replaceAll('{x}', '2');
                fetch(sample, { method: 'HEAD' })
                    .then(res => {
                        const actual = res.headers.get('layer-time-actual') || res.headers.get('layer-time-request');
                        if (actual) timeEl.textContent = `UTC ${actual}`;
                    })
                    .catch(() => {});
            } catch (e) {
                // ignore
            }
            return;
        }

        // For static images, we generally can't know capture time (CORS/metadata).
        // Show a best-effort label.
        if (tileUrlTemplate) {
            timeEl.textContent = __('Updated frequently');
        } else {
            timeEl.textContent = __('Unknown');
        }
    }

    function initSatelliteTileMap(el, tileUrlTemplate, options) {
        if (!el) return null;
        const tileUrl = normalizeTileUrlTemplate(tileUrlTemplate);
        if (!tileUrl || !(tileUrl.includes('{z}') && tileUrl.includes('{x}') && tileUrl.includes('{y}'))) {
            return null;
        }

        // GIBS GoogleMapsCompatible_Level9 only supports native zoom 0..9.
        const isGibsLevel9 = tileUrl.includes('GoogleMapsCompatible_Level9');
        const isGibsLevel6 = tileUrl.includes('GoogleMapsCompatible_Level6');
        // Some GIBS "250m" matrix sets have a limited zoom range; clamp to a sensible max.
        const isGibs250m = tileUrl.includes('/250m/');
        const maxNativeZoom = isGibsLevel9 ? 9 : (isGibsLevel6 ? 6 : (isGibs250m ? 8 : (options.maxNativeZoom ?? options.maxZoom ?? 19)));
        const maxZoom = isGibsLevel9 ? 9 : (isGibsLevel6 ? 6 : (isGibs250m ? 8 : (options.maxZoom ?? 19)));

        const map = L.map(el, {
            center: options.center,
            zoom: Math.min(options.zoom, maxZoom),
            zoomControl: true,
            attributionControl: true,
            maxZoom,
            minZoom: 1
        });

        // Optional base map for context if satellite tiles have transparency
        if (options.base === 'osm') {
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap',
                maxZoom
            }).addTo(map);
        }

        let retryStage = 0; // 0=original, 1=viirs default time, 2=modis default time
        // Make tile errors show the base map instead of black/empty blocks.
        // (A transparent PNG data URL)
        const transparentTile =
            'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO5r7uQAAAAASUVORK5CYII=';

        const layer = L.tileLayer(tileUrl, {
            attribution: options.attribution || 'Satellite Imagery',
            maxZoom,
            maxNativeZoom,
            crossOrigin: true,
            errorTileUrl: transparentTile
        })
        .on('tileerror', function (e) {
            // If you only see the base map, your tile URL template is likely invalid.
            console.warn('Satellite tile failed to load', { template: tileUrl, attempted: e?.tile?.src, error: e });

            // True color: if a date-specific VIIRS mosaic tile 404s, fall back to TIME=default (latest available).
            // This happens when "today" mosaics are not yet available, while TIME=default still resolves.
            const attempted = String(e?.tile?.src || '');
            const isViirsTrueColor = attempted.includes('/VIIRS_SNPP_CorrectedReflectance_TrueColor/');
            const hasDatedTime = /\/default\/\d{4}-\d{2}-\d{2}\//.test(attempted);
            const isAlreadyDefault = attempted.includes('/default/default/');

            if (isViirsTrueColor && retryStage === 0 && hasDatedTime && !isAlreadyDefault) {
                retryStage = 1;
                try {
                    const fallback = tileUrl.replace(/\/default\/\d{4}-\d{2}-\d{2}\//, '/default/default/');
                    map.removeLayer(layer);
                    const fallbackLayer = L.tileLayer(fallback, {
                        attribution: options.attribution || 'Satellite Imagery',
                        maxZoom,
                        maxNativeZoom,
                        crossOrigin: true
                    }).addTo(map);

                    // Update header time/source using the same logic (will HEAD one tile and show layer-time-actual).
                    updateSatelliteBadge(fallback);
                    console.info('Retried satellite layer with TIME=default', { fallback });

                    // Keep logging tile errors on the fallback too.
                    fallbackLayer.on('tileerror', (e2) => {
                        console.warn('Satellite tile failed to load (fallback)', { template: fallback, attempted: e2?.tile?.src, error: e2 });
                    });
                } catch (err) {
                    console.warn('Failed to retry satellite layer with TIME=default', err);
                }
                return;
            }

            // (No further fallback here; {time_auto} logic below is the preferred fix.)
        })
        .addTo(map);

        // If the template used {time_auto}, we initially use yesterday (more complete),
        // then probe "today" for a tile near the station; if it exists, swap to today.
        if (tileUrlTemplate.includes('{time_auto}')) {
            try {
                // pick a probe tile at current zoom near station center
                const z = Math.min(Math.max(options.zoom, 1), maxZoom);
                const proj = map.project(L.latLng(options.center[0], options.center[1]), z);
                const x = Math.floor(proj.x / 256);
                const y = Math.floor(proj.y / 256);

                const today = formatDateYYYYMMDD(new Date());
                const probeTemplate = String(tileUrlTemplate).replaceAll('{time_auto}', '{time_today}');
                const probeUrl = normalizeTileUrlTemplate(probeTemplate)
                    .replaceAll('{z}', String(z))
                    .replaceAll('{x}', String(x))
                    .replaceAll('{y}', String(y));

                fetch(probeUrl, { method: 'HEAD' })
                    .then(res => {
                        if (!res.ok) return;
                        // swap to today
                        const todayUrl = normalizeTileUrlTemplate(probeTemplate); // {time_today} -> today
                        map.removeLayer(layer);
                        const todayLayer = L.tileLayer(todayUrl, {
                            attribution: options.attribution || 'Satellite Imagery',
                            maxZoom,
                            maxNativeZoom,
                            crossOrigin: true,
                            errorTileUrl: transparentTile
                        }).addTo(map);
                        updateSatelliteBadge(todayUrl);
                        todayLayer.on('tileerror', (e2) => {
                            console.warn('Satellite tile failed to load (today)', { template: todayUrl, attempted: e2?.tile?.src, error: e2 });
                        });
                        console.info('Satellite {time_auto}: upgraded to today', { probeUrl, today });
                    })
                    .catch(() => {});
            } catch (e) {
                // ignore
            }
        }

        if (options.marker) {
            const stationIcon = L.divIcon({
                className: 'station-marker',
                html: '<div style="width: 12px; height: 12px; background: #10b981; border: 2px solid white; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>',
                iconSize: [12, 12],
                iconAnchor: [6, 6]
            });
            L.marker(options.marker, { icon: stationIcon }).addTo(map);
        }

        return map;
    }

    // Satellite tile map (selected region) - any provider if tile template configured
    const satEl = document.getElementById('satellite-map-main');
    if (satEl) {
        const stationLat = {{ $stationLat }};
        const stationLon = {{ $stationLon }};
        const region = (satEl.dataset.region || '').toLowerCase();
        const zoomSetting = parseInt(satEl.dataset.zoom || '4', 10);
        const minZoomForLocal = region === 'europe' ? 5 : 1; // "europe" == Local in settings
        const zoom = Math.min(Math.max(isNaN(zoomSetting) ? 4 : zoomSetting, minZoomForLocal), 12);
        const tileUrlTemplate = (satEl.dataset.tileUrl || '').trim();

        updateSatelliteBadge(tileUrlTemplate);

        initSatelliteTileMap(satEl, tileUrlTemplate, {
            center: [stationLat, stationLon],
            zoom,
            base: 'osm',
            attribution: (tileUrlTemplate.includes('gibs.earthdata.nasa.gov') || tileUrlTemplate.includes('earthdata.nasa.gov')) ? 'NASA GIBS' : 'Satellite Imagery',
            marker: [stationLat, stationLon],
            maxZoom: 19
        });
    } else {
        // Static image case: show generic info.
        updateSatelliteBadge('');
    }

    // Satellite tile map (Custom) - if present
    const customEl = document.getElementById('satellite-map-custom');
    if (customEl) {
        const stationLat = {{ $stationLat }};
        const stationLon = {{ $stationLon }};
        const zoom = Math.min(Math.max(parseInt(customEl.dataset.zoom || '4', 10), 1), 12);
        const tileUrlTemplate = (customEl.dataset.tileUrl || '').trim();

        initSatelliteTileMap(customEl, tileUrlTemplate, {
            center: [stationLat, stationLon],
            zoom,
            base: 'osm',
            attribution: 'Custom Satellite Imagery',
            marker: [stationLat, stationLon],
            maxZoom: 19
        });
    }

    window.radarNowcast = function() {
        return {
            nowcastMap: null,
            nowcastLayer: null,
            times: [],
            urls: [],
            currentStep: 0,
            totalSteps: 0,
            isPlaying: @json($nowcastAutoPlay ?? false),
            animationInterval: null,
            animationSpeed: @json($nowcastAnimationSpeed ?? 0.5) * 1000, // Convert to milliseconds
            loading: true,
            error: null,
            imageCache: {}, // Cache loaded images to avoid re-fetching
            preloadQueue: [], // Queue of images to preload
            isPreloading: false,
            nowcastDataBounds: [[50.75, 3.2], [53.7, 7.2]],
            nowcastViewInitialized: false,

            async init() {
                // Initialize map first so it's always visible
                this.initMap();
                await this.loadNowcastData();
                if (this.totalSteps > 0) {
                    this.showFrame(0);
                    // Start preloading images sequentially with rate limiting
                    this.startPreloading();
                    if (this.isPlaying) {
                        this.startAnimation();
                    }
                }
            },
            
            startPreloading() {
                // Preload images sequentially to avoid rate limiting (KNMI: 1 req/sec)
                this.preloadQueue = [...this.times];
                this.preloadNextImage();
            },
            
            preloadNextImage() {
                if (this.preloadQueue.length === 0) {
                    this.isPreloading = false;
                    console.log('All nowcast images preloaded');
                    return;
                }
                
                this.isPreloading = true;
                const time = this.preloadQueue.shift();
                const url = this.urls[time];
                
                if (!url || this.imageCache[time]) {
                    // Already cached or no URL, move to next
                    setTimeout(() => this.preloadNextImage(), 100);
                    return;
                }
                
                console.log('Preloading nowcast image:', time);
                
                const img = new Image();
                img.crossOrigin = 'anonymous';
                img.onload = () => {
                    // Convert to blob URL to avoid re-fetching
                    const canvas = document.createElement('canvas');
                    canvas.width = img.width;
                    canvas.height = img.height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0);
                    
                    canvas.toBlob((blob) => {
                        if (blob) {
                            this.imageCache[time] = URL.createObjectURL(blob);
                            console.log('Cached nowcast image:', time);
                        }
                        // Wait 1.1 seconds before next request (KNMI rate limit: 1 req/sec)
                        setTimeout(() => this.preloadNextImage(), 1100);
                    }, 'image/png');
                };
                img.onerror = (e) => {
                    console.error('Failed to preload nowcast image:', time, url, e);
                    // Continue preloading despite error
                    setTimeout(() => this.preloadNextImage(), 1100);
                };
                img.src = url;
            },

            async loadNowcastData() {
                this.loading = true;
                this.error = null;

                try {
                    const response = await fetch('/api/weather/radar-nowcast', {
                        headers: window.Meteo?.apiHeaders() || {},
                    });

                    if (!response.ok) {
                        throw new Error(`API error (${response.status})`);
                    }

                    const data = await response.json();

                    if (data.success && data.data) {
                        this.times = data.data.times || [];
                        this.urls = data.data.urls || {};
                        this.totalSteps = this.times.length;
                        this.loading = false;

                        this.$nextTick(() => {
                            if (!this.nowcastMap) return;
                            this.nowcastMap.invalidateSize();
                            this.fitNowcastView(true);
                        });
                    } else {
                        throw new Error(data.message || 'Nowcast data not available');
                    }
                } catch (e) {
                    console.error('Failed to load nowcast data:', e);
                    this.error = e.message;
                    this.loading = false;
                }
            },

            initMap() {
                if (this.nowcastMap) return;
                
                const mapElement = document.getElementById('nowcast-map');
                if (!mapElement) {
                    console.error('Nowcast map element not found');
                    return;
                }
                
                // Check if map is already initialized on this element and remove it
                if (mapElement._leaflet_id) {
                    try {
                        // Try to remove existing map
                        if (this.nowcastMap && this.nowcastMap.remove) {
                            this.nowcastMap.remove();
                        }
                        // Clear the leaflet ID to allow re-initialization
                        delete mapElement._leaflet_id;
                    } catch (e) {
                        // Ignore errors during cleanup
                    }
                }

                try {
                    const dataBounds = L.latLngBounds(this.nowcastDataBounds);
                    const focusBounds = dataBounds.pad(0.16);
                    this.nowcastMap = L.map('nowcast-map', {
                        center: [52.1, 5.2], // Center of Netherlands
                        zoom: 7,
                        minZoom: 6,
                        maxZoom: 10,
                        maxBounds: focusBounds.pad(0.45),
                        maxBoundsViscosity: 0.9,
                    });

                    // Add base tile layer
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© OpenStreetMap contributors',
                        maxZoom: 19
                    }).addTo(this.nowcastMap);

                    this.fitNowcastView(true);
                    requestAnimationFrame(() => {
                        if (!this.nowcastMap) return;
                        this.nowcastMap.invalidateSize();
                        this.fitNowcastView(true);
                    });
                } catch (e) {
                    console.error('Failed to initialize nowcast map:', e);
                    this.error = 'Failed to initialize map: ' + e.message;
                }
            },

            fitNowcastView(force = false) {
                if (!this.nowcastMap || !window.L) return;
                if (this.nowcastViewInitialized && !force) return;

                const dataBounds = L.latLngBounds(this.nowcastDataBounds);
                const focusBounds = dataBounds.pad(0.16);
                this.nowcastMap.fitBounds(focusBounds, {
                    padding: [10, 10],
                    maxZoom: 8,
                    animate: false,
                });

                this.nowcastMap.setMaxBounds(focusBounds.pad(0.45));
                const currentZoom = this.nowcastMap.getZoom();
                const minimumZoom = Math.max(6, currentZoom - 1);
                this.nowcastMap.setMinZoom(minimumZoom);
                this.nowcastViewInitialized = true;
            },

            showFrame(step) {
                if (this.totalSteps === 0 || !this.nowcastMap) return;

                const index = Math.max(0, Math.min(step, this.totalSteps - 1));
                const time = this.times[index];
                const url = this.urls[time];

                if (!url) return;

                // Remove existing layer
                if (this.nowcastLayer) {
                    this.nowcastMap.removeLayer(this.nowcastLayer);
                }

                // Check if image is already cached (blob URL)
                const cachedUrl = this.imageCache[time];
                if (cachedUrl) {
                    // Use cached blob URL - no HTTP request!
                    this.nowcastLayer = L.imageOverlay(cachedUrl, this.nowcastDataBounds, {
                        opacity: 0.7,
                        attribution: 'KNMI'
                    }).addTo(this.nowcastMap);
                    this.currentStep = index;
                } else {
                    // Image not yet loaded, skip this frame
                    console.log('Frame not yet loaded:', time);
                    this.currentStep = index;
                }
            },

            togglePlay() {
                this.isPlaying = !this.isPlaying;
                if (this.isPlaying) {
                    this.startAnimation();
                } else {
                    this.stopAnimation();
                }
            },

            startAnimation() {
                this.stopAnimation();
                
                if (this.totalSteps <= 1) return;

                this.animationInterval = setInterval(() => {
                    this.currentStep = (this.currentStep + 1) % this.totalSteps;
                    this.showFrame(this.currentStep);
                }, this.animationSpeed);
            },

            stopAnimation() {
                if (this.animationInterval) {
                    clearInterval(this.animationInterval);
                    this.animationInterval = null;
                }
            },

            get currentTimeLabel() {
                if (this.times.length === 0 || this.currentStep >= this.times.length) {
                    return '';
                }
                const time = this.times[this.currentStep];
                if (!time) return '';
                
                try {
                    const date = new Date(time);
                    const now = new Date();
                    const diffMinutes = Math.round((date - now) / (1000 * 60));
                    
                    if (diffMinutes === 0) return 'Now';
                    if (diffMinutes > 0) return `+${diffMinutes} min`;
                    return `${diffMinutes} min`;
                } catch (e) {
                    return '';
                }
            }
        };
    }
})();
</script>
@endpush
@endsection
