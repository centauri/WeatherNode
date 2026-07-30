@extends('weather.layout')

@section('title', __('Astronomy') . ' - ' . \App\Models\Setting::stationName())
@section('meta_description', __('Astronomy page meta description', ['location' => \App\Models\Setting::stationLocation() ?: \App\Models\Setting::stationName()]))
@section('og_image', route('og.astronomy'))

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    /* ISS Flip Card Styles - min-height ensures Altitude/Speed row stays inside the card */
    .iss-card-container {
        perspective: 1000px;
        min-height: 520px;
        align-self: start; /* avoid grid stretching so this card keeps its min-height */
    }
    
    .iss-flip-card {
        position: relative;
        width: 100%;
        height: 100%;
        min-height: 520px;
    }
    
    .iss-flip-card-inner {
        position: relative;
        width: 100%;
        height: 100%;
        min-height: 520px;
        transition: transform 0.6s;
        transform-style: preserve-3d;
    }
    
    .iss-flip-card.flipped .iss-flip-card-inner {
        transform: rotateY(180deg);
    }
    
    .iss-flip-card-front,
    .iss-flip-card-back {
        position: absolute;
        width: 100%;
        height: 100%;
        min-height: 520px;
        overflow: visible;
        backface-visibility: hidden;
        -webkit-backface-visibility: hidden;
    }
    
    .iss-flip-card-back {
        transform: rotateY(180deg);
    }
    
    #iss-map {
        z-index: 1;
        background: #1a1a1a;
    }
    
    .iss-marker {
        background: transparent !important;
        border: none !important;
    }
</style>
@endpush

@section('content')
<div class="space-y-6" x-data="astronomyPage()" x-init="init()">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold flex items-center gap-2">
	                <img src="{{ asset(($weatherIconsPath ?? 'icons/weather') . '/starry-night.svg') }}"
	                     :src="(backgroundEffectsEnabled ? window.Meteo.iconsAnimatedBaseUrl : window.Meteo.iconsStaticBaseUrl) + '/starry-night.svg'"
	                     class="w-8 h-8 md:w-10 md:h-10" alt="">
                {{ __('Astronomy') }}
            </h1>
            <p class="text-gray-400">{{ __('Astronomy page intro', ['location' => \App\Models\Setting::stationLocation() ?: \App\Models\Setting::stationName()]) }}</p>
        </div>
        <div class="text-right text-sm text-gray-400">
            <span x-show="loading">{{ __('Loading...') }}</span>
            <span x-show="!loading && lastUpdated" x-text="'{{ __('Updated') }}: ' + lastUpdated"></span>
        </div>
    </div>

    <!-- Sun & Moon Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Sun -->
        <div class="bg-gradient-to-br from-amber-900/30 to-weather-card rounded-2xl p-6 border border-amber-500/20">
            <div class="flex items-center gap-4 mb-6">
	                <img src="{{ asset(($weatherIconsPath ?? 'icons/weather') . '/clear-day.svg') }}"
	                     :src="(backgroundEffectsEnabled ? window.Meteo.iconsAnimatedBaseUrl : window.Meteo.iconsStaticBaseUrl) + '/clear-day.svg'"
	                     class="w-14 h-14" alt="Sun">
                <div>
                    <h2 class="text-xl font-semibold">{{ __('Sun') }}</h2>
                    <p class="text-gray-400" x-text="formatDate(new Date())"></p>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4 mb-6">
                <div class="text-center p-4 bg-white/5 rounded-xl">
	                    <img src="{{ asset(($weatherIconsPath ?? 'icons/weather') . '/sunrise.svg') }}"
	                         :src="(backgroundEffectsEnabled ? window.Meteo.iconsAnimatedBaseUrl : window.Meteo.iconsStaticBaseUrl) + '/sunrise.svg'"
	                         class="w-8 h-8 mx-auto mb-2" alt="Sunrise">
                    <div class="text-xs text-gray-400">{{ __('Sunrise') }}</div>
                    <div class="text-2xl font-bold" x-text="sun?.sunrise ?? '--:--'"></div>
                </div>
                <div class="text-center p-4 bg-white/5 rounded-xl">
	                    <img src="{{ asset(($weatherIconsPath ?? 'icons/weather') . '/sunset.svg') }}"
	                         :src="(backgroundEffectsEnabled ? window.Meteo.iconsAnimatedBaseUrl : window.Meteo.iconsStaticBaseUrl) + '/sunset.svg'"
	                         class="w-8 h-8 mx-auto mb-2" alt="Sunset">
                    <div class="text-xs text-gray-400">{{ __('Sunset') }}</div>
                    <div class="text-2xl font-bold" x-text="sun?.sunset ?? '--:--'"></div>
                </div>
            </div>

            <!-- Sun position arc -->
            <div class="relative h-24 mb-4">
                <div class="absolute inset-x-0 bottom-0 h-0.5 bg-white/20"></div>
                <svg class="absolute inset-0 w-full h-full" viewBox="0 0 200 80">
                    <defs>
                        <linearGradient id="sunGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                            <stop offset="0%" style="stop-color:#1e3a5f;stop-opacity:1" />
                            <stop offset="25%" style="stop-color:#f97316;stop-opacity:1" />
                            <stop offset="50%" style="stop-color:#fbbf24;stop-opacity:1" />
                            <stop offset="75%" style="stop-color:#f97316;stop-opacity:1" />
                            <stop offset="100%" style="stop-color:#1e3a5f;stop-opacity:1" />
                        </linearGradient>
                    </defs>
                    <path d="M 10 70 Q 100 -20 190 70" fill="none" stroke="url(#sunGrad)" stroke-width="3" stroke-linecap="round"/>
                    <circle x-show="sun?.is_up"
                            :cx="10 + (sun?.position_percent ?? 50) * 1.8"
                            :cy="70 - Math.sin((sun?.position_percent ?? 50) / 100 * Math.PI) * 60"
                            :style="'opacity:' + (sun?.elevation != null ? Math.min(Math.max(sun.elevation / 5, 0), 1) : 1)"
                            r="10" fill="#fbbf24" class="drop-shadow-lg"/>
                </svg>
                <div class="absolute left-2 bottom-2 text-xs text-gray-500" x-text="sun?.sunrise ?? '--:--'"></div>
                <div class="absolute right-2 bottom-2 text-xs text-gray-500" x-text="sun?.sunset ?? '--:--'"></div>
                <div class="absolute left-1/2 -translate-x-1/2 top-1 text-xs text-gray-500" x-text="sun?.solar_noon ?? '--:--'"></div>
            </div>

            <div class="grid grid-cols-4 gap-2 text-sm">
                <div class="text-center">
                    <div class="text-gray-400 text-xs">{{ __('Daylight') }}</div>
                    <div class="font-bold text-amber-400" x-text="sun?.day_length ?? '--:--'"></div>
                </div>
                <div class="text-center">
                    <div class="text-gray-400 text-xs">{{ __('Change') }}</div>
                    <div class="font-bold" :class="sun?.day_length_change_seconds > 0 ? 'text-green-400' : 'text-red-400'" x-text="sun?.day_length_change ?? '--'"></div>
                </div>
                <div class="text-center">
                    <div class="text-gray-400 text-xs">{{ __('Elevation') }}</div>
                    <div class="font-bold" x-text="sun?.elevation ? sun.elevation + '°' : '--°'"></div>
                </div>
                <div class="text-center">
                    <div class="text-gray-400 text-xs">{{ __('Azimuth') }}</div>
                    <div class="font-bold" x-text="sun?.azimuth ? sun.azimuth + '° ' + sun.direction : '--°'"></div>
                </div>
            </div>
        </div>

        <!-- Moon -->
        <div class="bg-gradient-to-br from-slate-700/50 to-weather-card rounded-2xl p-6 border border-slate-500/20">
            <div class="flex items-center gap-4 mb-6">
                <img :src="(backgroundEffectsEnabled ? window.Meteo.iconsAnimatedBaseUrl : window.Meteo.iconsStaticBaseUrl) + '/' + (moon?.icon ?? 'moon-waxing-crescent') + '.svg'"
	                     class="w-14 h-14" alt="Moon phase">
                <div>
                    <h2 class="text-xl font-semibold">{{ __('Moon') }}</h2>
                    <p class="text-gray-400" x-text="translateMoonPhase(moon?.phase_name) || '{{ __('Loading...') }}'"></p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-6">
                <div class="text-center p-4 bg-white/5 rounded-xl">
		                    <img src="{{ asset(($weatherIconsPath ?? 'icons/weather') . '/moonrise.svg') }}"
		                         :src="(backgroundEffectsEnabled ? window.Meteo.iconsAnimatedBaseUrl : window.Meteo.iconsStaticBaseUrl) + '/moonrise.svg'"
		                         class="w-8 h-8 mx-auto mb-2" alt="Moonrise">
                    <div class="text-xs text-gray-400">{{ __('Moonrise') }}</div>
                    <div class="text-2xl font-bold" x-text="moon?.moonrise ?? '--:--'"></div>
                </div>
                <div class="text-center p-4 bg-white/5 rounded-xl">
		                    <img src="{{ asset(($weatherIconsPath ?? 'icons/weather') . '/moonset.svg') }}"
		                         :src="(backgroundEffectsEnabled ? window.Meteo.iconsAnimatedBaseUrl : window.Meteo.iconsStaticBaseUrl) + '/moonset.svg'"
		                         class="w-8 h-8 mx-auto mb-2" alt="Moonset">
                    <div class="text-xs text-gray-400">{{ __('Moonset') }}</div>
                    <div class="text-2xl font-bold" x-text="moon?.moonset ?? '--:--'"></div>
                </div>
            </div>

            <!-- Moon position arc -->
            <div class="relative h-24 mb-4" x-show="moon?.moonrise || moon?.moonset">
                <div class="absolute inset-x-0 bottom-0 h-0.5 bg-white/20"></div>
                <svg class="absolute inset-0 w-full h-full" viewBox="0 0 200 80">
                    <defs>
                        <linearGradient id="moonGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                            <stop offset="0%" style="stop-color:#0f172a;stop-opacity:1" />
                            <stop offset="25%" style="stop-color:#475569;stop-opacity:1" />
                            <stop offset="50%" style="stop-color:#94a3b8;stop-opacity:1" />
                            <stop offset="75%" style="stop-color:#475569;stop-opacity:1" />
                            <stop offset="100%" style="stop-color:#0f172a;stop-opacity:1" />
                        </linearGradient>
                    </defs>
                    <path d="M 10 70 Q 100 -20 190 70" fill="none" stroke="url(#moonGrad)" stroke-width="3" stroke-linecap="round"/>
                    <circle x-show="moonPositionPercent() != null"
                            :cx="10 + (moonPositionPercent() ?? 50) * 1.8"
                            :cy="70 - Math.sin((moonPositionPercent() ?? 50) / 100 * Math.PI) * 60"
                            :style="'opacity:' + (moon?.elevation != null ? Math.min(Math.max((moon.elevation + 5) / 10, 0), 1) : 1)"
                            r="10" fill="#e2e8f0" class="drop-shadow-lg"/>
                </svg>
                <div class="absolute left-2 bottom-2 text-xs text-gray-500" x-text="moon?.moonrise ?? '--:--'"></div>
                <div class="absolute right-2 bottom-2 text-xs text-gray-500" x-text="moon?.moonset ?? '--:--'"></div>
            </div>

            <div class="grid grid-cols-4 gap-2 text-sm">
                <div class="text-center">
                    <div class="text-gray-400 text-xs">{{ __('Illumination') }}</div>
                    <div class="font-bold" x-text="moon?.illumination ? moon.illumination + '%' : '--%'"></div>
                </div>
                <div class="text-center">
                    <div class="text-gray-400 text-xs">{{ __('Age') }}</div>
                    <div class="font-bold" x-text="moon?.age ? Math.round(moon.age) + ' {{ __('days') }}' : '--'"></div>
                </div>
                <div class="text-center">
                    <div class="text-gray-400 text-xs">{{ __('Distance') }}</div>
                    <div class="font-bold" x-text="formatDistance(moon?.distance)"></div>
                </div>
                <div class="text-center">
                    <div class="text-gray-400 text-xs">{{ __('Elevation') }}</div>
                    <div class="font-bold" x-text="moon?.elevation ? moon.elevation + '°' : '--°'"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Moon Phases -->
    <div class="bg-weather-card rounded-2xl p-5 border border-white/10">
        <h3 class="font-semibold mb-4 flex items-center gap-2">
	            <img src="{{ asset(($weatherIconsPath ?? 'icons/weather') . '/moon-waxing-crescent.svg') }}"
	                 :src="(backgroundEffectsEnabled ? window.Meteo.iconsAnimatedBaseUrl : window.Meteo.iconsStaticBaseUrl) + '/moon-waxing-crescent.svg'"
	                 class="w-5 h-5" alt="">
            {{ __('Moon phases') }}
        </h3>
        <div class="grid grid-cols-4 gap-4">
            <div class="text-center p-3 bg-white/5 rounded-xl">
	                <img src="{{ asset(($weatherIconsPath ?? 'icons/weather') . '/moon-new.svg') }}"
	                     :src="(backgroundEffectsEnabled ? window.Meteo.iconsAnimatedBaseUrl : window.Meteo.iconsStaticBaseUrl) + '/moon-new.svg'"
	                     class="w-10 h-10 mx-auto mb-2" alt="New moon">
                <div class="text-xs text-gray-400">{{ __('New moon') }}</div>
                <div class="text-sm font-bold" x-text="moon?.next_new_moon ?? '--'"></div>
            </div>
            <div class="text-center p-3 bg-white/5 rounded-xl">
	                <img src="{{ asset(($weatherIconsPath ?? 'icons/weather') . '/moon-first-quarter.svg') }}"
	                     :src="(backgroundEffectsEnabled ? window.Meteo.iconsAnimatedBaseUrl : window.Meteo.iconsStaticBaseUrl) + '/moon-first-quarter.svg'"
	                     class="w-10 h-10 mx-auto mb-2" alt="First quarter">
                <div class="text-xs text-gray-400">{{ __('First quarter') }}</div>
                <div class="text-sm font-bold" x-text="moon?.next_first_quarter ?? '--'"></div>
            </div>
            <div class="text-center p-3 bg-white/5 rounded-xl">
	                <img src="{{ asset(($weatherIconsPath ?? 'icons/weather') . '/moon-full.svg') }}"
	                     :src="(backgroundEffectsEnabled ? window.Meteo.iconsAnimatedBaseUrl : window.Meteo.iconsStaticBaseUrl) + '/moon-full.svg'"
	                     class="w-10 h-10 mx-auto mb-2" alt="Full moon">
                <div class="text-xs text-gray-400">{{ __('Full moon') }}</div>
                <div class="text-sm font-bold" x-text="moon?.next_full_moon ?? '--'"></div>
            </div>
            <div class="text-center p-3 bg-white/5 rounded-xl">
	                <img src="{{ asset(($weatherIconsPath ?? 'icons/weather') . '/moon-last-quarter.svg') }}"
	                     :src="(backgroundEffectsEnabled ? window.Meteo.iconsAnimatedBaseUrl : window.Meteo.iconsStaticBaseUrl) + '/moon-last-quarter.svg'"
	                     class="w-10 h-10 mx-auto mb-2" alt="Last quarter">
                <div class="text-xs text-gray-400">{{ __('Last quarter') }}</div>
                <div class="text-sm font-bold" x-text="moon?.next_last_quarter ?? '--'"></div>
            </div>
        </div>
    </div>

    <!-- Aurora / Space Weather -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Aurora / Kp Index -->
        <div class="bg-weather-card rounded-2xl p-5 border border-white/10">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold flex items-center gap-2">
	                    <img src="{{ asset(($weatherIconsPath ?? 'icons/weather') . '/star.svg') }}"
	                         :src="(backgroundEffectsEnabled ? window.Meteo.iconsAnimatedBaseUrl : window.Meteo.iconsStaticBaseUrl) + '/star.svg'"
	                         class="w-5 h-5" alt="">
                    {{ __('Aurora') }} / {{ __('Kp Index') }}
                </h3>
                <span class="text-xs px-2 py-1 rounded" 
                      :class="getKpBadgeClass(aurora?.kp)" 
                      x-text="translateAuroraStorm(aurora?.storm?.name) || '{{ __('Loading...') }}'"></span>
            </div>
            
            <div class="flex items-center gap-6">
                <div class="w-24 h-24 rounded-full flex items-center justify-center" 
                     :style="'background-color: ' + (aurora?.color ?? '#22c55e') + '33'">
                    <div class="text-center">
                        <div class="text-3xl font-bold" :style="'color: ' + (aurora?.color ?? '#22c55e')" x-text="aurora?.kp ?? '--'"></div>
                        <div class="text-xs text-gray-400">{{ __('Kp') }}</div>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-gray-400 text-sm mb-2" x-text="translateAuroraDescription(aurora?.aurora?.description) || '{{ __('Loading...') }}'"></p>
                    <p class="text-xs text-gray-500" x-text="translateAuroraRadio(aurora?.radio?.description)"></p>
                </div>
            </div>

            <div class="mt-4 pt-4 border-t border-white/10">
                <div class="flex h-4 rounded overflow-hidden gap-0.5">
                    <template x-for="(level, idx) in aurora?.scale ?? []" :key="idx">
                        <div class="flex-1 transition-opacity" 
                             :style="'background-color: ' + level.color"
                             :class="aurora?.kp >= level.value ? 'opacity-100' : 'opacity-30'"
                             :title="'Kp ' + level.value + ': ' + translateAuroraScaleLabel(level.label)"></div>
                    </template>
                </div>
                <div class="flex justify-between text-xs text-gray-500 mt-1">
                    <span>{{ __('Kp') }} 0</span>
                    <span>{{ __('Kp') }} 9</span>
                </div>
            </div>

            <div class="mt-4 pt-4 border-t border-white/10 grid grid-cols-2 gap-4 text-sm">
                <div>
                    <div class="text-gray-400 text-xs">{{ __('A-index (estimated)') }}</div>
                    <div class="font-bold" x-text="aurora?.a_index ?? '--'"></div>
                </div>
                <div>
                    <div class="text-gray-400 text-xs">{{ __('Geomagnetic storm') }}</div>
                    <div class="font-bold" x-text="aurora?.storm?.level ?? '{{ __('None') }}'"></div>
                </div>
            </div>

            <p class="text-xs text-gray-500 mt-4">
                {{ __('Data') }}: <a href="https://www.swpc.noaa.gov" target="_blank" class="text-blue-400 hover:underline">NOAA Space Weather Prediction Center</a>
            </p>
        </div>

        <!-- ISS / Tiangong -->
        <div class="iss-card-container relative" x-data="{ flipped: false, currentStation: 'iss' }" @click.away="flipped = false">
            <div class="iss-flip-card" :class="{ 'flipped': flipped }">
                <div class="iss-flip-card-inner">
                    <!-- Front of card -->
                    <div class="iss-flip-card-front bg-weather-card rounded-2xl p-5 border border-white/10 cursor-pointer" @click="flipped = !flipped; $nextTick(() => { setTimeout(() => { if (window.astronomyPageInstance && typeof L !== 'undefined' && !window.astronomyPageInstance.mapInitialized) { window.astronomyPageInstance.initISSTracker(currentStation); } }, 300); })">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <!-- Left arrow (show if Tiangong is available and not on ISS) -->
                                <button @click.stop="if (tiangong && currentStation === 'iss') currentStation = 'tiangong'; else if (iss && currentStation === 'tiangong') currentStation = 'iss';" 
                                        x-show="(iss && tiangong) || (currentStation === 'tiangong' && iss)"
                                        class="p-1 rounded hover:bg-white/10 transition" 
                                        :class="{ 'opacity-50 cursor-not-allowed': !iss || (currentStation === 'iss' && !tiangong) }">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                    </svg>
                                </button>
                                
                                <h3 class="font-semibold">
                                    <span x-show="currentStation === 'iss'">🛸 ISS ({{ __('International Space Station') }})</span>
                                    <span x-show="currentStation === 'tiangong'">🇨🇳 Tiangong ({{ __('Chinese Space Station') }})</span>
                                </h3>
                                
                                <!-- Right arrow (show if ISS is available and not on Tiangong) -->
                                <button @click.stop="if (iss && currentStation === 'tiangong') currentStation = 'iss'; else if (tiangong && currentStation === 'iss') currentStation = 'tiangong';" 
                                        x-show="(iss && tiangong) || (currentStation === 'iss' && tiangong)"
                                        class="p-1 rounded hover:bg-white/10 transition"
                                        :class="{ 'opacity-50 cursor-not-allowed': !tiangong || (currentStation === 'tiangong' && !iss) }">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </button>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-gray-400">{{ __('Click for map') }}</span>
                                <a :href="currentStation === 'iss' ? 'https://spotthestation.nasa.gov' : 'https://www.cnsa.gov.cn'" 
                                   target="_blank" @click.stop 
                                   class="text-xs text-blue-400 hover:underline">
                                    <span x-show="currentStation === 'iss'">{{ __('NASA') }} →</span>
                                    <span x-show="currentStation === 'tiangong'">{{ __('CNSA') }} →</span>
                                </a>
                            </div>
                        </div>
                        
                        <div class="space-y-4">
                            <!-- Current location -->
                            <div class="p-3 bg-white/5 rounded-lg">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <div class="font-semibold">{{ __('Current position') }}</div>
                                        <div class="text-sm text-gray-400" x-show="currentStation === 'iss' && iss?.location?.success">
                                            <span x-text="iss?.location?.latitude?.toFixed(2) ?? '--'"></span>°, 
                                            <span x-text="iss?.location?.longitude?.toFixed(2) ?? '--'"></span>°
                                        </div>
                                        <div class="text-sm text-gray-400" x-show="currentStation === 'tiangong' && tiangong?.location?.success">
                                            <span x-text="tiangong?.location?.latitude?.toFixed(2) ?? '--'"></span>°, 
                                            <span x-text="tiangong?.location?.longitude?.toFixed(2) ?? '--'"></span>°
                                        </div>
                                        <div class="text-sm text-gray-400" x-show="(currentStation === 'iss' && !iss?.location?.success) || (currentStation === 'tiangong' && !tiangong?.location?.success)">{{ __('Not available') }}</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xl font-bold" x-show="currentStation === 'iss'" x-text="formatDistance(iss?.distance_km)"></div>
                                        <div class="text-xl font-bold" x-show="currentStation === 'tiangong'" x-text="formatDistance(tiangong?.distance_km)"></div>
                                        <div class="text-xs text-gray-400">{{ __('Distance') }}</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Next pass -->
                            <div class="p-3 bg-white/5 rounded-lg">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <div class="font-semibold">{{ __('Next pass') }}</div>
                                        <div class="text-sm text-gray-400" x-show="currentStation === 'iss'" x-text="iss?.next_pass?.visible ? '✓ {{ __('Visible') }}' : '{{ __('Possibly not visible') }}'"></div>
                                        <div class="text-sm text-gray-400" x-show="currentStation === 'tiangong'" x-text="tiangong?.next_pass?.visible ? '✓ {{ __('Visible') }}' : '{{ __('Possibly not visible') }}'"></div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xl font-bold" x-show="currentStation === 'iss'" x-text="iss?.next_pass?.rise_time_formatted ?? '--'"></div>
                                        <div class="text-xl font-bold" x-show="currentStation === 'tiangong'" x-text="tiangong?.next_pass?.rise_time_formatted ?? '--'"></div>
                                        <div class="text-xs text-gray-400" x-show="currentStation === 'iss'" x-text="iss?.next_pass?.duration_formatted ? '{{ __('Duration') }}: ' + iss.next_pass.duration_formatted : ''"></div>
                                        <div class="text-xs text-gray-400" x-show="currentStation === 'tiangong'" x-text="tiangong?.next_pass?.duration_formatted ? '{{ __('Duration') }}: ' + tiangong.next_pass.duration_formatted : ''"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Astronauts (only for ISS) -->
                            <div class="p-3 bg-white/5 rounded-lg" x-show="currentStation === 'iss' && astronauts?.success">
                                <div class="flex justify-between items-center mb-2">
                                    <div>
                                        <div class="font-semibold">{{ __('Astronauts in space') }}</div>
                                        <div class="text-sm text-gray-400">{{ __('Right now') }}</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-2xl font-bold" x-text="astronauts?.number ?? '--'"></div>
                                    </div>
                                </div>
                                <div class="text-xs text-gray-500 mt-2" x-show="astronauts?.breakdown">
                                    <span x-text="translations.iss + ': ' + (astronauts?.breakdown?.iss ?? 0)"></span>
                                    <span x-show="astronauts?.breakdown?.tiangong > 0" x-text="' • ' + translations.tiangong + ': ' + (astronauts?.breakdown?.tiangong ?? 0)"></span>
                                </div>
                            </div>

                            <p class="text-xs text-gray-500" x-show="currentStation === 'iss' && iss?.pass_note" x-text="iss?.pass_note"></p>
                            <p class="text-xs text-gray-500" x-show="currentStation === 'tiangong' && tiangong?.pass_note" x-text="tiangong?.pass_note"></p>
                        </div>

                        <div class="mt-4 pt-4 border-t border-white/10 grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <div class="text-gray-400 text-xs">{{ __('Altitude') }}</div>
                                <div class="font-bold" x-show="currentStation === 'iss'" x-text="iss?.altitude_km ? formatDistanceLabel(iss.altitude_km) : '~' + formatDistanceLabel(408)"></div>
                                <div class="font-bold" x-show="currentStation === 'tiangong'" x-text="tiangong?.altitude_km ? formatDistanceLabel(tiangong.altitude_km) : '~' + formatDistanceLabel(380)"></div>
                            </div>
                            <div>
                                <div class="text-gray-400 text-xs">{{ __('Speed') }}</div>
                                <div class="font-bold" x-show="currentStation === 'iss'" x-text="iss?.speed_kmh ? formatSpeed(iss.speed_kmh) : '--'"></div>
                                <div class="font-bold" x-show="currentStation === 'tiangong'" x-text="tiangong?.speed_kmh ? formatSpeed(tiangong.speed_kmh) : '--'"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Back of card - Map -->
                    <div class="iss-flip-card-back bg-weather-card rounded-2xl p-5 border border-white/10">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-semibold">
                                <span x-show="currentStation === 'iss'">🛸 {{ __('ISS Live Tracking') }}</span>
                                <span x-show="currentStation === 'tiangong'">🇨🇳 {{ __('Tiangong Live Tracking') }}</span>
                            </h3>
                            <button @click.stop="flipped = false" class="text-xs text-blue-400 hover:underline">← {{ __('Back') }}</button>
                        </div>
                        <div id="iss-map" class="w-full h-64 rounded-lg" style="min-height: 256px;"></div>
                        <div class="mt-3 text-xs text-gray-400 text-center" x-show="currentStation === 'iss' && iss?.location?.success">
                            <span x-text="iss?.location?.latitude?.toFixed(2) ?? '--'"></span>°, 
                            <span x-text="iss?.location?.longitude?.toFixed(2) ?? '--'"></span>° 
                            • 
                            <span x-text="formatDistanceLabel(iss?.distance_km, ' {{ __('Distance') }}')"></span>
                        </div>
        <div class="mt-3 text-xs text-gray-400 text-center" x-show="currentStation === 'tiangong' && tiangong?.location?.success">
            <span x-text="tiangong?.location?.latitude?.toFixed(2) ?? '--'"></span>°, 
            <span x-text="tiangong?.location?.longitude?.toFixed(2) ?? '--'"></span>° 
            • 
            <span x-text="formatDistanceLabel(tiangong?.distance_km, ' {{ __('Distance') }}')"></span>
        </div>
    </div>
</div>
            </div>
        </div>
    </div>

    <!-- Twilight Times -->
    <div class="bg-weather-card rounded-2xl p-5 border border-white/10">
        <h3 class="font-semibold mb-4 flex items-center gap-2">
	            <img src="{{ asset(($weatherIconsPath ?? 'icons/weather') . '/horizon.svg') }}"
	                 :src="(backgroundEffectsEnabled ? window.Meteo.iconsAnimatedBaseUrl : window.Meteo.iconsStaticBaseUrl) + '/horizon.svg'"
	                 class="w-5 h-5" alt="">
            {{ __('Twilight times') }}
        </h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="text-center p-4 bg-white/5 rounded-xl">
                <div class="text-xs text-gray-400 mb-2">{{ __('Astronomical dawn') }}</div>
                <div class="text-lg font-bold" x-text="sun?.astronomical_twilight_begin ?? '--:--'"></div>
                <div class="text-xs text-gray-500">{{ __('sun -18° below horizon') }}</div>
            </div>
            <div class="text-center p-4 bg-white/5 rounded-xl">
                <div class="text-xs text-gray-400 mb-2">{{ __('Nautical dawn') }}</div>
                <div class="text-lg font-bold" x-text="sun?.nautical_twilight_begin ?? '--:--'"></div>
                <div class="text-xs text-gray-500">{{ __('sun -12° below horizon') }}</div>
            </div>
            <div class="text-center p-4 bg-white/5 rounded-xl">
                <div class="text-xs text-gray-400 mb-2">{{ __('Civil dawn') }}</div>
                <div class="text-lg font-bold" x-text="sun?.civil_twilight_begin ?? '--:--'"></div>
                <div class="text-xs text-gray-500">{{ __('sun -6° below horizon') }}</div>
            </div>
            <div class="text-center p-4 bg-white/5 rounded-xl">
                <div class="text-xs text-gray-400 mb-2">{{ __('Sunrise') }}</div>
                <div class="text-lg font-bold text-amber-400" x-text="sun?.sunrise ?? '--:--'"></div>
                <div class="text-xs text-gray-500">{{ __('sun above horizon') }}</div>
            </div>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
            <div class="text-center p-4 bg-white/5 rounded-xl">
                <div class="text-xs text-gray-400 mb-2">{{ __('Sunset') }}</div>
                <div class="text-lg font-bold text-orange-400" x-text="sun?.sunset ?? '--:--'"></div>
                <div class="text-xs text-gray-500">{{ __('sun below horizon') }}</div>
            </div>
            <div class="text-center p-4 bg-white/5 rounded-xl">
                <div class="text-xs text-gray-400 mb-2">{{ __('Civil twilight') }}</div>
                <div class="text-lg font-bold" x-text="sun?.civil_twilight_end ?? '--:--'"></div>
                <div class="text-xs text-gray-500">{{ __('end') }}</div>
            </div>
            <div class="text-center p-4 bg-white/5 rounded-xl">
                <div class="text-xs text-gray-400 mb-2">{{ __('Nautical twilight') }}</div>
                <div class="text-lg font-bold" x-text="sun?.nautical_twilight_end ?? '--:--'"></div>
                <div class="text-xs text-gray-500">{{ __('end') }}</div>
            </div>
            <div class="text-center p-4 bg-white/5 rounded-xl">
                <div class="text-xs text-gray-400 mb-2">{{ __('Astronomical twilight') }}</div>
                <div class="text-lg font-bold" x-text="sun?.astronomical_twilight_end ?? '--:--'"></div>
                <div class="text-xs text-gray-500">{{ __('total darkness') }}</div>
            </div>
        </div>
    </div>

    <!-- Meteor Showers -->
    <div class="bg-weather-card rounded-2xl p-5 border border-white/10">
        <h3 class="font-semibold mb-4 flex items-center gap-2">
	            <img src="{{ asset(($weatherIconsPath ?? 'icons/weather') . '/falling-stars.svg') }}"
	                 :src="(backgroundEffectsEnabled ? window.Meteo.iconsAnimatedBaseUrl : window.Meteo.iconsStaticBaseUrl) + '/falling-stars.svg'"
	                 class="w-5 h-5" alt="">
            {{ __('Meteor showers') }}
        </h3>
        <div class="overflow-x-auto">
            <div class="flex gap-3 pb-2">
                <template x-for="meteor in getActiveMeteors()" :key="meteor.name">
                    <div class="flex-shrink-0 p-4 bg-gradient-to-br from-purple-900/30 to-weather-card rounded-xl border border-purple-500/20 min-w-[200px]">
                        <div class="flex items-center gap-2 mb-2">
	                            <img src="{{ asset(($weatherIconsPath ?? 'icons/weather') . '/falling-stars.svg') }}"
	                                 :src="(backgroundEffectsEnabled ? window.Meteo.iconsAnimatedBaseUrl : window.Meteo.iconsStaticBaseUrl) + '/falling-stars.svg'"
	                                 class="w-8 h-8" alt="">
                            <span class="font-semibold" x-text="meteor.name"></span>
                        </div>
                        <div class="text-sm text-gray-400" x-text="meteor.from + ' - ' + meteor.to"></div>
                        <div class="text-xs mt-2 px-2 py-1 rounded bg-purple-500/20 text-purple-300 inline-block" x-show="meteor.peak">{{ __('PEAK') }}</div>
                    </div>
                </template>
                <div x-show="getActiveMeteors().length === 0" class="text-gray-500">
                    {{ __('No active meteor showers at the moment') }}
                </div>
            </div>
        </div>
        <p class="text-xs text-gray-500 mt-4">
            {{ __('Tip: Meteor showers are best seen on clear, moonless nights far away from light pollution.') }}
        </p>
    </div>

    <!-- Upcoming Events -->
    <div class="bg-weather-card rounded-2xl p-5 border border-white/10" x-show="events.length > 0" x-data="{ showAll: false }">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold">📅 {{ __('Upcoming astronomical events') }}</h3>
            <button x-show="events.length > 12" @click="showAll = !showAll" class="text-xs text-violet-400 hover:text-violet-300">
                <span x-text="showAll ? '{{ __('Show less') }}' : '{{ __('Show all') }} (' + events.length + ')'"></span>
            </button>
        </div>
        <div class="space-y-2 max-h-[500px] overflow-y-auto" :class="{ 'max-h-none': showAll }">
            <template x-for="event in (showAll ? events : events.slice(0, 12))" :key="event.date + event.event">
                <div class="flex items-center justify-between p-3 bg-white/5 rounded-lg hover:bg-white/10 transition-colors">
                    <div class="flex items-center gap-3">
                        <span class="text-2xl" x-text="event.emoji"></span>
                        <div>
                            <div class="font-semibold" x-text="translateEvent(event.event)"></div>
                            <div class="text-sm text-gray-400 flex items-center gap-2">
                                <span x-text="event.formatted_date"></span>
                                <!-- Visibility indicator for eclipses -->
                                <template x-if="event.type === 'eclipse' && event.visible_here">
                                    <span class="text-green-400 text-xs">✓ {{ __('Visible here') }}</span>
                                </template>
                                <!-- Meteor shower rate -->
                                <template x-if="event.type === 'meteor' && event.rate">
                                    <span class="text-yellow-400 text-xs" x-text="'~' + event.rate + '/hr'"></span>
                                </template>
                            </div>
                            <template x-if="event.hint">
                                <div class="text-xs text-gray-500 mt-1" x-text="translateEvent(event.hint)"></div>
                            </template>
                        </div>
                    </div>
                    <!-- Event type badge -->
                    <div class="text-xs px-2 py-1 rounded-full"
                         :class="{
                             'bg-blue-500/20 text-blue-400': event.type === 'moon',
                             'bg-orange-500/20 text-orange-400': event.type === 'seasonal',
                             'bg-purple-500/20 text-purple-400': event.type === 'eclipse',
                             'bg-yellow-500/20 text-yellow-400': event.type === 'meteor',
                             'bg-cyan-500/20 text-cyan-400': event.type === 'planet',
                             'bg-green-500/20 text-green-400': event.type === 'earth',
                             'bg-pink-500/20 text-pink-400': event.type === 'comet',
                             'bg-indigo-500/20 text-indigo-400': event.type === 'special',
                             'bg-amber-500/20 text-amber-400': event.type === 'transit'
                         }"
                         x-text="eventTypeLabels[event.type] || event.type">
                    </div>
                </div>
            </template>
        </div>
    </div>

    <article class="bg-weather-card rounded-2xl p-6 border border-white/10 prose prose-invert prose-sm max-w-none">
        <h2 class="text-lg font-semibold mb-3">{{ __('Astronomy page about heading') }}</h2>
        <p class="text-gray-300 mb-3">{{ __('Astronomy page about body 1') }}</p>
        <p class="text-gray-300 mb-3">{{ __('Astronomy page about body 2') }}</p>
        <p class="text-gray-300 mb-3">{{ __('Astronomy page about body 3') }}</p>
        <footer class="text-xs text-gray-500 mt-4 pt-4 border-t border-white/10">{{ __('Astronomy page sources') }}</footer>
    </article>
</div>

@push('scripts')
<script>
function astronomyPage() {
    return {
        sun: null,
        moon: null,
        aurora: null,
        iss: null,
        tiangong: null,
        astronauts: null,
        events: [],
        meteors: [],
        loading: true,
        lastUpdated: null,
        locale: window.Meteo?.jsLocale || 'en-US',
        units: window.Meteo?.activeUnits || 'metric',
        moonPhaseLabels: {
            'New Moon': @json(__('New Moon')),
            'Waxing Crescent': @json(__('Waxing Crescent')),
            'First Quarter': @json(__('First Quarter')),
            'Waxing Gibbous': @json(__('Waxing Gibbous')),
            'Full Moon': @json(__('Full Moon')),
            'Waning Gibbous': @json(__('Waning Gibbous')),
            'Last Quarter': @json(__('Last Quarter')),
            'Waning Crescent': @json(__('Waning Crescent')),
        },
        auroraStormLabels: {
            'Extreme Storm': @json(__('Extreme Storm')),
            'Severe Storm': @json(__('Severe Storm')),
            'Strong Storm': @json(__('Strong Storm')),
            'Moderate Storm': @json(__('Moderate Storm')),
            'Minor Storm': @json(__('Minor Storm')),
            'Active': @json(__('Active')),
            'Unsettled': @json(__('Unsettled')),
            'Quiet': @json(__('Quiet')),
        },
        auroraScaleLabels: {
            'Quiet': @json(__('Quiet')),
            'Unsettled': @json(__('Unsettled')),
            'Active': @json(__('Active')),
            'G1 Minor': @json(__('G1 Minor')),
            'G2 Moderate': @json(__('G2 Moderate')),
            'G3 Strong': @json(__('G3 Strong')),
            'G4 Severe': @json(__('G4 Severe')),
            'G5 Extreme': @json(__('G5 Extreme')),
        },
        auroraDescriptionLabels: {
            'Aurora may be visible in the Netherlands with clear skies.': @json(__('Aurora may be visible in the Netherlands with clear skies.')),
            'Aurora may be visible in Northern Netherlands/Scandinavia.': @json(__('Aurora may be visible in Northern Netherlands/Scandinavia.')),
            'Aurora possible in Scandinavia and Northern Germany.': @json(__('Aurora possible in Scandinavia and Northern Germany.')),
            'Aurora possible in Northern Scandinavia.': @json(__('Aurora possible in Northern Scandinavia.')),
            'Aurora only visible in polar regions.': @json(__('Aurora only visible in polar regions.')),
            'No aurora expected.': @json(__('No aurora expected.')),
        },
        translations: {
            iss: @json(__('ISS')),
            tiangong: @json(__('Tiangong')),
        },
        auroraRadioLabels: {
            'Strong radio aurora, 28-433MHz possible over 1500+ km.': @json(__('Strong radio aurora, 28-433MHz possible over 1500+ km.')),
            'Radio aurora active, 28-144MHz possible over long distance.': @json(__('Radio aurora active, 28-144MHz possible over long distance.')),
            'Radio aurora, 50-144MHz possible.': @json(__('Radio aurora, 50-144MHz possible.')),
            'Radio aurora, 50-144MHz possible at high latitudes.': @json(__('Radio aurora, 50-144MHz possible at high latitudes.')),
            'Weak radio aurora possible on 50-144MHz.': @json(__('Weak radio aurora possible on 50-144MHz.')),
            'Very weak radio aurora possible.': @json(__('Very weak radio aurora possible.')),
            'No radio aurora.': @json(__('No radio aurora.')),
        },
        eventLabels: {
            // Moon phases
            'New Moon': @json(__('New Moon')),
            'First Quarter': @json(__('First Quarter')),
            'Full Moon': @json(__('Full Moon')),
            'Last Quarter': @json(__('Last Quarter')),
            'Supermoon': @json(__('Supermoon')),
            // Seasons
            'Spring Equinox': @json(__('Spring Equinox')),
            'Summer Solstice': @json(__('Summer Solstice')),
            'Autumn Equinox': @json(__('Autumn Equinox')),
            'Winter Solstice': @json(__('Winter Solstice')),
            // Eclipses
            'Total Solar Eclipse': @json(__('Total Solar Eclipse')),
            'Partial Solar Eclipse': @json(__('Partial Solar Eclipse')),
            'Annular Solar Eclipse': @json(__('Annular Solar Eclipse')),
            'Hybrid Solar Eclipse': @json(__('Hybrid Solar Eclipse')),
            'Total Lunar Eclipse': @json(__('Total Lunar Eclipse')),
            'Partial Lunar Eclipse': @json(__('Partial Lunar Eclipse')),
            'Penumbral Lunar Eclipse': @json(__('Penumbral Lunar Eclipse')),
            // Meteor showers
            'Quadrantids': @json(__('Quadrantids')),
            'Quadrantids peak': @json(__('Quadrantids peak')),
            'Lyrids': @json(__('Lyrids')),
            'Lyrids peak': @json(__('Lyrids peak')),
            'Eta Aquariids': @json(__('Eta Aquariids')),
            'Eta Aquariids peak': @json(__('Eta Aquariids peak')),
            'Delta Aquariids': @json(__('Delta Aquariids')),
            'Delta Aquariids peak': @json(__('Delta Aquariids peak')),
            'Perseids': @json(__('Perseids')),
            'Perseids peak': @json(__('Perseids peak')),
            'Draconids': @json(__('Draconids')),
            'Draconids peak': @json(__('Draconids peak')),
            'Orionids': @json(__('Orionids')),
            'Orionids peak': @json(__('Orionids peak')),
            'Taurids': @json(__('Taurids')),
            'Taurids peak': @json(__('Taurids peak')),
            'South Taurids peak': @json(__('South Taurids peak')),
            'North Taurids peak': @json(__('North Taurids peak')),
            'Leonids': @json(__('Leonids')),
            'Leonids peak': @json(__('Leonids peak')),
            'Geminids': @json(__('Geminids')),
            'Geminids peak': @json(__('Geminids peak')),
            'Ursids': @json(__('Ursids')),
            'Ursids peak': @json(__('Ursids peak')),
            // Planetary events
            'Mercury at greatest elongation': @json(__('Mercury at greatest elongation')),
            'Venus at greatest elongation': @json(__('Venus at greatest elongation')),
            'Mars at opposition': @json(__('Mars at opposition')),
            'Jupiter at opposition': @json(__('Jupiter at opposition')),
            'Saturn at opposition': @json(__('Saturn at opposition')),
            'Uranus at opposition': @json(__('Uranus at opposition')),
            'Neptune at opposition': @json(__('Neptune at opposition')),
            'Mars-Saturn conjunction': @json(__('Mars-Saturn conjunction')),
            'Jupiter-Mercury conjunction': @json(__('Jupiter-Mercury conjunction')),
            'Venus-Saturn conjunction': @json(__('Venus-Saturn conjunction')),
            'Venus-Jupiter conjunction': @json(__('Venus-Jupiter conjunction')),
            'Venus-Mars conjunction': @json(__('Venus-Mars conjunction')),
            'Venus-Neptune conjunction': @json(__('Venus-Neptune conjunction')),
            'Saturn-Neptune conjunction': @json(__('Saturn-Neptune conjunction')),
            'Jupiter-Saturn great conjunction': @json(__('Jupiter-Saturn great conjunction')),
            'Transit of Mercury': @json(__('Transit of Mercury')),
            // Earth orbital
            'Earth at Perihelion': @json(__('Earth at Perihelion')),
            'Earth at Aphelion': @json(__('Earth at Aphelion')),
            // Venus brilliancy
            'Venus at greatest brilliancy': @json(__('Venus at greatest brilliancy')),
            // Blue Moon
            'Blue Moon': @json(__('Blue Moon')),
            // Zodiacal light
            'Zodiacal Light (evening)': @json(__('Zodiacal Light (evening)')),
            'Zodiacal Light (morning)': @json(__('Zodiacal Light (morning)')),
            // Planetary parades
            'Seven-planet parade': @json(__('Seven-planet parade')),
            'Six-planet alignment (morning)': @json(__('Six-planet alignment (morning)')),
            'Six-planet alignment (evening)': @json(__('Six-planet alignment (evening)')),
            'Seven-planet parade hint': @json(__('Seven-planet parade hint')),
            'Six-planet alignment morning hint': @json(__('Six-planet alignment morning hint')),
            'Six-planet alignment evening hint': @json(__('Six-planet alignment evening hint')),
            'Annular Solar Eclipse hint': @json(__('Annular Solar Eclipse hint')),
            'Autumn Equinox hint': @json(__('Autumn Equinox hint')),
            'Blue Moon hint': @json(__('Blue Moon hint')),
            'Comet hint': @json(__('Comet hint')),
            'Earth at Aphelion hint': @json(__('Earth at Aphelion hint')),
            'Earth at Perihelion hint': @json(__('Earth at Perihelion hint')),
            'First Quarter hint': @json(__('First Quarter hint')),
            'Full Moon hint': @json(__('Full Moon hint')),
            'Hybrid Solar Eclipse hint': @json(__('Hybrid Solar Eclipse hint')),
            'Jupiter at opposition hint': @json(__('Jupiter at opposition hint')),
            'Jupiter-Saturn great conjunction hint': @json(__('Jupiter-Saturn great conjunction hint')),
            'Last Quarter hint': @json(__('Last Quarter hint')),
            'Mars at opposition hint': @json(__('Mars at opposition hint')),
            'Mercury at greatest elongation hint': @json(__('Mercury at greatest elongation hint')),
            'Meteor shower peak hint': @json(__('Meteor shower peak hint')),
            'Neptune at opposition hint': @json(__('Neptune at opposition hint')),
            'New Moon hint': @json(__('New Moon hint')),
            'Partial Lunar Eclipse hint': @json(__('Partial Lunar Eclipse hint')),
            'Partial Solar Eclipse hint': @json(__('Partial Solar Eclipse hint')),
            'Penumbral Lunar Eclipse hint': @json(__('Penumbral Lunar Eclipse hint')),
            'Planetary conjunction hint': @json(__('Planetary conjunction hint')),
            'Saturn at opposition hint': @json(__('Saturn at opposition hint')),
            'Spring Equinox hint': @json(__('Spring Equinox hint')),
            'Summer Solstice hint': @json(__('Summer Solstice hint')),
            'Supermoon hint': @json(__('Supermoon hint')),
            'Total Lunar Eclipse hint': @json(__('Total Lunar Eclipse hint')),
            'Total Solar Eclipse hint': @json(__('Total Solar Eclipse hint')),
            'Transit of Mercury hint': @json(__('Transit of Mercury hint')),
            'Uranus at opposition hint': @json(__('Uranus at opposition hint')),
            'Venus at greatest brilliancy hint': @json(__('Venus at greatest brilliancy hint')),
            'Venus at greatest elongation hint': @json(__('Venus at greatest elongation hint')),
            'Winter Solstice hint': @json(__('Winter Solstice hint')),
            'Zodiacal Light (evening) hint': @json(__('Zodiacal Light (evening) hint')),
            'Zodiacal Light (morning) hint': @json(__('Zodiacal Light (morning) hint')),
        },
        eventTypeLabels: {
            'moon': @json(__('Moon')),
            'seasonal': @json(__('Season')),
            'eclipse': @json(__('Eclipse')),
            'meteor': @json(__('Meteor')),
            'planet': @json(__('Planet')),
            'earth': @json(__('Earth')),
            'comet': @json(__('Comet')),
            'special': @json(__('Special')),
            'transit': @json(__('Transit')),
        },
        isPageVisible: true,
        isRefreshing: false,
        lastUpdateTime: null,
        previousDataHash: null,

        distanceUnit() {
            return (this.units === 'imperial' || this.units === 'uk') ? 'mi' : 'km';
        },
        formatDistance(value) {
            if (value === null || value === undefined) return '--';
            const distance = (this.units === 'imperial' || this.units === 'uk')
                ? (value * 0.621371)
                : value;
            return Math.round(distance).toLocaleString(this.locale) + ' ' + this.distanceUnit();
        },
        formatDistanceLabel(value, suffix) {
            if (value === null || value === undefined) return '';
            const distance = (this.units === 'imperial' || this.units === 'uk')
                ? (value * 0.621371)
                : value;
            return distance.toLocaleString(this.locale) + ' ' + this.distanceUnit() + (suffix || '');
        },
        speedUnit() {
            if (this.units === 'imperial' || this.units === 'uk') return @json(__('mph'));
            if (this.units === 'scandinavia') return @json(__('m/s'));
            return @json(__('km/h'));
        },
        formatSpeed(value) {
            if (value === null || value === undefined) return '--';
            const speed = (this.units === 'imperial' || this.units === 'uk')
                ? (value * 0.621371)
                : (this.units === 'scandinavia' ? (value / 3.6) : value);
            return speed.toLocaleString(this.locale) + ' ' + this.speedUnit();
        },
        translateMoonPhase(name) {
            if (!name) return '';
            return this.moonPhaseLabels[name] || name;
        },
        /**
         * Moon position as 0-100 between moonrise and moonset (today).
         * Falls back to elevation-based position when rise/set times are incomplete.
         */
        moonPositionPercent() {
            const m = this.moon;
            if (!m) return null;

            // If moon is up, try to calculate position
            if (m.is_up && m.elevation != null) {
                // If we have both rise and set times, use time-based calculation
                if (m.moonrise && m.moonset) {
                    const riseMatch = String(m.moonrise).match(/^(\d{1,2}):(\d{2})$/);
                    const setMatch = String(m.moonset).match(/^(\d{1,2}):(\d{2})$/);
                    if (riseMatch && setMatch) {
                        const riseH = parseInt(riseMatch[1], 10);
                        const riseM = parseInt(riseMatch[2], 10);
                        const setH = parseInt(setMatch[1], 10);
                        const setM = parseInt(setMatch[2], 10);
                        if (!isNaN(riseH) && !isNaN(riseM) && !isNaN(setH) && !isNaN(setM)) {
                            const today = new Date();
                            const riseMs = new Date(today.getFullYear(), today.getMonth(), today.getDate(), riseH, riseM).getTime();
                            let setMs = new Date(today.getFullYear(), today.getMonth(), today.getDate(), setH, setM).getTime();
                            if (setMs <= riseMs) setMs += 24 * 60 * 60 * 1000;
                            const now = Date.now();
                            if (now >= riseMs && now <= setMs) {
                                return (now - riseMs) / (setMs - riseMs) * 100;
                            }
                        }
                    }
                }

                // Fallback: use elevation to estimate position (0° = horizon, 90° = zenith)
                // Map elevation to arc position: 0° elevation = edges (0% or 100%), max elevation = 50%
                // Assuming max elevation is around 60-70°, normalize to 0-50-100 range
                const elevation = Math.max(0, Math.min(90, m.elevation));
                // When elevation is 0, we're at the edge. When elevation is at max, we're at 50%.
                // Use a sine-based approximation: position = 50 if at peak, moving towards edges as elevation drops
                // Simple approach: estimate based on whether we're ascending or descending
                // Since we don't know direction, use elevation directly: higher = closer to 50%
                const normalizedElevation = elevation / 60; // Assume 60° is typical max
                return 50 * Math.min(1, normalizedElevation) + (m.moonrise ? 0 : 25);
            }

            return null;
        },
        translateAuroraStorm(name) {
            if (!name) return '';
            return this.auroraStormLabels[name] || name;
        },
        translateAuroraDescription(text) {
            if (!text) return '';
            return this.auroraDescriptionLabels[text] || text;
        },
        translateAuroraRadio(text) {
            if (!text) return '';
            return this.auroraRadioLabels[text] || text;
        },
        translateAuroraScaleLabel(label) {
            if (!label) return '';
            return this.auroraScaleLabels[label] || label;
        },
        translateEvent(name) {
            if (!name) return '';
            return this.eventLabels[name] || this.moonPhaseLabels[name] || name;
        },

        async init() {
            // Store instance globally for flip card access
            window.astronomyPageInstance = this;

            // Page Visibility API
            document.addEventListener('visibilitychange', () => {
                this.isPageVisible = !document.hidden;
            });

            await this.fetchData();

            // Smart polling - 5 minutes, only when visible
            setInterval(() => {
                if (this.isPageVisible) {
                    this.fetchData();
                }
            }, 300000);
        },

        async fetchData() {
            if (!this.isPageVisible) {
                console.log('Astronomy: Skipping fetch - page hidden');
                return;
            }

            try {
                this.isRefreshing = true;
                this.loading = true;
                const res = await fetch('/api/weather/astronomy', {
                    headers: window.Meteo.apiHeaders(),
                });
                const data = await res.json();

                if (data.success) {
                    // Simple change detection via hash
                    const dataHash = JSON.stringify({
                        sun: data.sun?.sunrise,
                        moon: data.moon?.phase,
                        aurora: data.aurora?.kp,
                        iss: data.iss?.next_pass?.rise_time
                    });

                    if (dataHash !== this.previousDataHash) {
                        console.log('Astronomy data changed');
                        this.sun = data.sun;
                        this.moon = data.moon;
                        this.aurora = data.aurora;
                        this.iss = data.iss;
                        this.tiangong = data.tiangong;
                        this.astronauts = data.astronauts;
                        this.events = data.events || [];
                        this.meteors = data.meteors || [];
                        this.lastUpdated = new Date().toLocaleTimeString(this.locale, { hour: '2-digit', minute: '2-digit' });
                        this.previousDataHash = dataHash;

                        // Update map if initialized (will use current station from card state)
                        if (this.mapInitialized) {
                            // Get current station from the card's Alpine.js state
                            const cardElement = document.querySelector('.iss-card-container');
                            if (cardElement && cardElement.__x) {
                                const currentStation = cardElement.__x.$data.currentStation || 'iss';
                                this.updateISSPosition(currentStation);
                            } else {
                                this.updateISSPosition('iss');
                            }
                        }
                    } else {
                        console.log('Astronomy: No changes detected');
                    }

                    this.lastUpdateTime = Date.now();
                }
            } catch (e) {
                console.error('Failed to fetch astronomy data:', e);
            } finally {
                this.loading = false;
                this.isRefreshing = false;
            }
        },

        formatDate(date) {
            return date.toLocaleDateString(this.locale, { 
                weekday: 'long', 
                day: 'numeric', 
                month: 'long', 
                year: 'numeric' 
            });
        },

        getKpBadgeClass(kp) {
            if (kp >= 7) return 'bg-red-500/20 text-red-400';
            if (kp >= 5) return 'bg-orange-500/20 text-orange-400';
            if (kp >= 4) return 'bg-yellow-500/20 text-yellow-400';
            return 'bg-green-500/20 text-green-400';
        },

        initISSTracker(station = 'iss') {
            const stationData = station === 'iss' ? this.iss : this.tiangong;
            if (this.mapInitialized || !stationData?.location?.success) {
                return;
            }
            
            // Wait for Leaflet to be available
            if (typeof L === 'undefined') {
                setTimeout(() => this.initISSTracker(station), 100);
                return;
            }
            
            const lat = stationData.location.latitude;
            const lon = stationData.location.longitude;
            
            // Initialize map centered on ISS
            this.issMap = L.map('iss-map', {
                zoomControl: true,
                attributionControl: true,
            }).setView([lat, lon], 3);
            
            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19,
            }).addTo(this.issMap);
            
            // Add station marker with custom icon
            const markerColor = station === 'iss' ? '#ff6b6b' : '#4a90e2';
            const markerEmoji = station === 'iss' ? '🛸' : '🇨🇳';
            const stationName = station === 'iss' ? 'ISS' : 'Tiangong';
            
            const issIcon = L.divIcon({
                className: 'iss-marker',
                html: `<div style="background: ${markerColor}; width: 20px; height: 20px; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 10px rgba(${station === 'iss' ? '255,107,107' : '74,144,226'},0.8); animation: pulse 2s infinite;"></div>`,
                iconSize: [20, 20],
                iconAnchor: [10, 10],
            });
            
            this.issMarker = L.marker([lat, lon], { icon: issIcon }).addTo(this.issMap);
            
            // Add popup with station info
            const popupContent = `
                <div style="text-align: center; min-width: 150px;">
                    <div style="font-weight: bold; margin-bottom: 5px;">${markerEmoji} ${stationName}</div>
                    <div style="font-size: 0.85em; color: #666;">
                        ${lat.toFixed(2)}°, ${lon.toFixed(2)}°<br>
                        ${this.formatDistanceLabel(stationData?.distance_km, ' {{ __('Distance') }}')}
                    </div>
                </div>
            `;
            this.issMarker.bindPopup(popupContent).openPopup();
            
            // Add orbit path visualization (simplified - shows recent positions)
            // Since we only have current position, we'll show a circle representing the orbit
            const orbitCircle = L.circle([lat, lon], {
                radius: 6371000, // Earth radius in meters
                fillColor: markerColor,
                fillOpacity: 0.1,
                color: markerColor,
                weight: 2,
                opacity: 0.5,
            }).addTo(this.issMap);
            
            this.mapInitialized = true;
        },
        
        updateISSPosition(station = 'iss') {
            const stationData = station === 'iss' ? this.iss : this.tiangong;
            if (!this.issMap || !stationData?.location?.success) {
                return;
            }
            
            const lat = stationData.location.latitude;
            const lon = stationData.location.longitude;
            
            // Update marker position
            if (this.issMarker) {
                this.issMarker.setLatLng([lat, lon]);
                this.issMap.setView([lat, lon], this.issMap.getZoom());
                
                // Update popup content
                const markerEmoji = station === 'iss' ? '🛸' : '🇨🇳';
                const stationName = station === 'iss' ? 'ISS' : 'Tiangong';
                const popupContent = `
                    <div style="text-align: center; min-width: 150px;">
                        <div style="font-weight: bold; margin-bottom: 5px;">${markerEmoji} ${stationName}</div>
                        <div style="font-size: 0.85em; color: #666;">
                            ${lat.toFixed(2)}°, ${lon.toFixed(2)}°<br>
                            ${this.formatDistanceLabel(stationData?.distance_km, ' {{ __('Distance') }}')}
                        </div>
                    </div>
                `;
                this.issMarker.setPopupContent(popupContent);
            }
        },

        getActiveMeteors() {
            const now = new Date();
            return this.meteors.filter(m => {
                const from = new Date(m.from);
                const to = new Date(m.to);
                to.setHours(23, 59, 59); // End of day
                return now >= from && now <= to;
            });
        }
    };
}
</script>
@endpush

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
@endpush

@endsection
