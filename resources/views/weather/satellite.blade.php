@extends('weather.layout')

@section('title', __('Satellite Data') . ' - ' . \App\Models\Setting::stationName())

@section('meta_description', __('Satellite page meta description', ['location' => \App\Models\Setting::stationLocation() ?: \App\Models\Setting::stationName()]))
@section('og_image', route('og.generic', ['page' => 'satellite']))

@section('content')
@php
    $activeUnits = $activeUnits ?? 'metric';
    $unit = app(\App\Support\UnitFormatter::class);
    $stationLat = \App\Models\Setting::latitude();
    $stationLon = \App\Models\Setting::longitude();
    
    // Check if station is in Netherlands
    $isInNetherlands = ($stationLat >= 50.75 && $stationLat <= 53.7) && 
                       ($stationLon >= 3.2 && $stationLon <= 7.2);

    // The warning below is about the KNMI layer alone, so it only makes sense
    // when that layer is switched on. It used to greet every station outside
    // the Netherlands, including fresh installs with no satellite data at all.
    $knmiLayerEnabled = (bool) \App\Models\Setting::getValue('satellite.wms_enabled', false);
@endphp
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold">🛰️ {{ __('Satellite Data') }}</h1>
            <p class="text-ui-muted">{{ __('Satellite page intro', ['location' => \App\Models\Setting::stationLocation() ?: \App\Models\Setting::stationName()]) }}</p>
        </div>
    </div>

    @if($knmiLayerEnabled && !$isInNetherlands)
        <div class="bg-yellow-500/10 border border-yellow-500/30 rounded-xl p-4">
            <div class="flex items-start gap-3">
                <span class="text-xl flex-shrink-0">⚠️</span>
                <div>
                    <p class="text-sm text-data-yellow-200 font-medium">{{ __('Location Notice') }}</p>
                    <p class="text-xs text-data-yellow-300 mt-1">
                        {{ __('KNMI satellite data covers the Netherlands region. Your station appears to be outside this area, so the data may not be relevant for your location.') }}
                    </p>
                </div>
            </div>
        </div>
    @endif

    <!-- Solar Radiation Forecast -->
    @php
        $solarForecastEnabled = \App\Models\Setting::getValue('solar_forecast.enabled', false);
        $solarForecastHours = \App\Models\Setting::getValue('solar_forecast.forecast_hours', 48);
    @endphp
    @if($solarForecastEnabled)
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10"
             x-data="solarNowcast()" 
             x-init="init()">
            <div class="mb-3">
                <h3 class="font-semibold">☀️ {{ __('Solar Radiation Forecast') }}</h3>
                <p class="text-xs text-ui-muted mt-0.5" x-text="sourceLabel ? '{{ __("Powered by") }} ' + sourceLabel : '{{ __('Loading...') }}'"></p>
            </div>

            <div x-show="loading" class="py-8 text-center">
                <div class="text-sm text-ui-muted">{{ __('Loading solar forecast...') }}</div>
            </div>
            <div x-show="error" class="py-8 text-center p-4">
                <div class="text-sm text-data-red-400 mb-2">{{ __('Error loading data') }}</div>
                <div class="text-xs text-ui-subtle" x-text="error"></div>
            </div>

            <template x-if="!loading && !error && chartData && chartData.length > 0">
                <div class="space-y-3">
                    <!-- Day navigation: arrows + Today | Tomorrow -->
                    <div class="flex items-center justify-center gap-3">
                        <button type="button"
                                @click="goPrev()"
                                :disabled="!canGoPrev"
                                :class="canGoPrev ? 'text-data-amber-400 hover:text-data-amber-300' : 'text-ui-faint cursor-not-allowed'"
                                class="p-2 rounded-lg transition"
                                aria-label="{{ __('Previous day') }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        </button>
                        <div class="flex flex-col items-center min-w-[140px]">
                            <span class="text-sm font-medium" x-text="selectedDayLabel"></span>
                            <span class="text-xs text-ui-muted" x-show="visibleTimeRange" x-text="visibleTimeRange"></span>
                        </div>
                        <button type="button"
                                @click="goNext()"
                                :disabled="!canGoNext"
                                :class="canGoNext ? 'text-data-amber-400 hover:text-data-amber-300' : 'text-ui-faint cursor-not-allowed'"
                                class="p-2 rounded-lg transition"
                                aria-label="{{ __('Next day') }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>

                    <!-- Single chart (today or tomorrow) -->
                    <div x-show="visibleChartData.length > 0" class="bg-black/20 rounded-xl p-3 relative" style="height: 300px;">
                        <svg class="w-full h-full" viewBox="0 0 860 280">
                            <defs>
                                <linearGradient id="solarFillVisible" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="rgba(251,191,36,0.5)"></stop>
                                    <stop offset="100%" stop-color="rgba(251,191,36,0.02)"></stop>
                                </linearGradient>
                            </defs>
                            <path :d="getHorizontalGridPath()" fill="none" stroke="rgb(var(--wn-fg) / 0.12)" stroke-width="1" stroke-dasharray="4 4"></path>
                            <path :d="getVerticalGridPathFor(visibleChartData)" fill="none" stroke="rgb(var(--wn-fg) / 0.12)" stroke-width="1" stroke-dasharray="4 4"></path>
                            <g x-html="getGridLabelsHtml()"></g>
                            <g x-html="getVerticalGridLabelsHtmlFor(visibleChartData)"></g>
                            <path :d="getAreaPathFor(visibleChartData)" fill="url(#solarFillVisible)"></path>
                            <path :d="getLinePathFor(visibleChartData)" fill="none" stroke="#fbbf24" stroke-width="2.5" stroke-linejoin="round"></path>
                        </svg>
                    </div>

                    <div x-show="chartData.length > 0 && todayChartData.length === 0 && tomorrowChartData.length === 0" class="py-4 text-center text-ui-subtle text-sm">
                        {{ __('No daylight in forecast window') }}
                    </div>

                    <!-- Statistics -->
                    <div class="grid grid-cols-3 gap-3">
                        <div data-theme-surface="dark" class="text-center p-3 bg-black/20 rounded-lg">
                            <div class="text-xs text-ui-muted mb-1">{{ __('Current') }}</div>
                            <div class="text-lg font-semibold" x-text="formatValue(currentValue)"></div>
                        </div>
                        <div data-theme-surface="dark" class="text-center p-3 bg-black/20 rounded-lg">
                            <div class="text-xs text-ui-muted mb-1">{{ __('Peak') }}</div>
                            <div class="text-lg font-semibold" x-text="formatValue(peakValue)"></div>
                        </div>
                        <div data-theme-surface="dark" class="text-center p-3 bg-black/20 rounded-lg">
                            <div class="text-xs text-ui-muted mb-1">{{ __('Average') }}</div>
                            <div class="text-lg font-semibold" x-text="formatValue(averageValue)"></div>
                        </div>
                    </div>
                </div>
            </template>

            <div x-show="!loading && !error && (!chartData || chartData.length === 0)" class="py-8 text-center">
                <div class="text-ui-subtle text-sm mb-2">{{ __('No solar forecast data available') }}</div>
                <div class="text-ui-faint text-xs" x-show="(() => { const tz = stationTz(); const h = parseInt(new Intl.DateTimeFormat('en-CA', { hour: '2-digit', hour12: false, timeZone: tz }).format(new Date()), 10); return h < 5 || h > 21; })()">
                    {{ __('Solar radiation data is only available during daytime hours') }}
                </div>
            </div>
        </div>
    @endif

    <!-- KNMI WMS Satellite Layers -->
    @php
        $wmsEnabled = \App\Models\Setting::getValue('satellite.wms_enabled', false);
        $wmsDefaultLayer = \App\Models\Setting::getValue('satellite.wms_default_layer', 'lwe_precipitation_rate');
        $wmsDefaultStyle = \App\Models\Setting::getValue('satellite.wms_default_style', 'precip-rainbow/nearest');
        $wmsDefaultOpacity = \App\Models\Setting::getValue('satellite.wms_default_opacity', 70) / 100;
        $wmsAnimationSpeed = \App\Models\Setting::getValue('satellite.wms_animation_speed', 0.5);
    @endphp
    @if($wmsEnabled)
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10"
             x-data="knmiWmsManager()" 
             x-init="init()">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="font-semibold">🌍 {{ __('Satellite Analysis') }}</h3>
                    <p class="text-xs text-ui-muted mt-1">
                        {{ __('KNMI WMS Layers - Netherlands') }}
                        <span class="inline-block ml-2 px-2 py-0.5 bg-blue-500/20 text-data-blue-300 rounded text-[10px] font-medium">
                            {{ __('HISTORICAL DATA') }}
                        </span>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="toggleAnimation()" 
                            class="px-3 py-1.5 bg-ui-accent-strong text-on-accent hover:bg-ui-action-deep text-ui-fg rounded-lg text-sm transition">
                        <span x-show="!isAnimating">{{ __('Play') }}</span>
                        <span x-show="isAnimating">{{ __('Pause') }}</span>
                    </button>
                    <button @click="showControls = !showControls" 
                            class="px-3 py-1.5 bg-ui-disabled hover:bg-ui-soft text-ui-fg rounded-lg text-sm transition">
                        {{ __('Controls') }}
                    </button>
                </div>
            </div>

            <!-- Controls Panel -->
            <div x-show="showControls" 
                 x-transition
                 data-theme-surface="dark" class="mb-4 p-4 bg-black/20 rounded-xl space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-ui-secondary mb-1">{{ __('Layer') }}</label>
                        <select x-model="currentLayer" 
                                @change="changeLayer()"
                                class="w-full rounded-lg border-ui-border bg-ui-soft text-ui-fg text-sm focus:ring-blue-500 focus:border-blue-500">
                            <template x-for="(layer, key) in availableLayers" :key="key">
                                <option :value="key" x-text="layer.name"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-ui-secondary mb-1">
                            {{ __('Historical Date') }}
                            <span class="text-[10px] text-ui-subtle">({{ __('7 days archive') }})</span>
                        </label>
                        <select x-model="selectedDate" 
                                @change="loadTimesForDate()"
                                class="w-full rounded-lg border-ui-border bg-ui-soft text-ui-fg text-sm focus:ring-blue-500 focus:border-blue-500">
                            <template x-for="date in availableDates" :key="date">
                                <option :value="date" x-text="formatDateOption(date)"></option>
                            </template>
                        </select>
                    </div>
                    <div x-show="currentLayerStyles.length > 1">
                        <label class="block text-xs font-medium text-ui-secondary mb-1">{{ __('Style') }}</label>
                        <select x-model="currentStyle" 
                                @change="changeStyle()"
                                class="w-full rounded-lg border-ui-border bg-ui-soft text-ui-fg text-sm focus:ring-blue-500 focus:border-blue-500">
                            <template x-for="style in currentLayerStyles" :key="style">
                                <option :value="style" x-text="style"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-ui-secondary mb-1">
                            {{ __('Opacity') }}: <span x-text="Math.round(opacity * 100)"></span>%
                        </label>
                        <input type="range" 
                               min="0" 
                               max="100" 
                               x-model="opacityPercent"
                               @input="opacity = opacityPercent / 100; updateLayer()"
                               class="w-full">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ui-secondary mb-1">
                        {{ __('Time') }}: <span x-text="currentTimeLabel"></span>
                    </label>
                    <input type="range" 
                           min="0" 
                           :max="availableTimes.length - 1" 
                           x-model="currentTimeIndex"
                           @input="changeTime()"
                           class="w-full">
                    <div class="flex justify-between text-xs text-ui-muted mt-1">
                        <span x-text="formatTime(availableTimes[0])"></span>
                        <span x-text="formatTime(availableTimes[availableTimes.length - 1])"></span>
                    </div>
                </div>
            </div>

            <div data-theme-surface="dark" class="aspect-video md:aspect-[16/10] bg-black/30 rounded-xl overflow-hidden relative">
                <div x-show="loading" class="absolute inset-0 flex items-center justify-center">
                    <div class="text-center">
                        <div class="text-sm text-ui-muted mb-2">{{ __('Loading satellite data...') }}</div>
                    </div>
                </div>
                <div x-show="error" class="absolute inset-0 flex items-center justify-center">
                    <div class="text-center p-4">
                        <div class="text-sm text-data-red-400 mb-2">{{ __('Error loading data') }}</div>
                        <div class="text-xs text-ui-subtle" x-text="error"></div>
                    </div>
                </div>
                <div id="wms-map" class="w-full h-full" x-show="!loading && !error"></div>
                <div data-theme-surface="dark" class="absolute top-4 right-4 bg-black/70 px-3 py-2 rounded-lg text-xs" x-show="!loading && !error">
                    <div x-show="legendUrl" class="mb-2">
                        <img :src="legendUrl" alt="Legend" class="max-h-32">
                    </div>
                    <div class="text-ui-secondary" x-text="currentLayerInfo?.description || ''"></div>
                </div>
            </div>
        </div>
    @endif

    @if(!$solarForecastEnabled && !$wmsEnabled)
        <div class="bg-weather-card rounded-2xl p-8 border border-ui-line/10 text-center">
            <div class="text-6xl mb-4">🛰️</div>
            <h3 class="text-lg font-semibold mb-2">{{ __('No Satellite Data Enabled') }}</h3>
            <p class="text-ui-muted text-sm">
                {{ __('No satellite data sources have been enabled. Please check back later.') }}
            </p>
        </div>
    @endif

    <!-- About satellite imagery (scientific) -->
    <article class="bg-weather-card rounded-2xl border border-ui-line/10 p-6 md:p-8" aria-labelledby="satellite-about-heading">
        <h2 id="satellite-about-heading" class="text-xl font-semibold mb-4">{{ __('Satellite page about heading') }}</h2>
        <div class="prose prose-invert prose-sm max-w-none text-ui-secondary space-y-4">
            <p>{{ __('Satellite page about body 1') }}</p>
            <p>{{ __('Satellite page about body 2') }}</p>
            <p>{{ __('Satellite page about body 3') }}</p>
        </div>
        <footer class="mt-6 pt-4 border-t border-ui-line/10">
            <p class="text-xs text-ui-subtle">{{ __('Satellite page sources') }}</p>
        </footer>
    </article>
</div>

@push('scripts')
@php
    $solarSourceNames = [
        'open_meteo' => __('Open-Meteo'),
        'forecast_solar' => __('Forecast.Solar'),
        'open_quartz' => __('Open Quartz Solar'),
        'solcast' => __('Solcast'),
    ];
@endphp
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    window.solarNowcast = function() {
        return {
            times: [],
            values: [],
            source: null,
            loading: true,
            error: null,
            sunToday: null,
            sunTomorrow: null,
            selectedDayIndex: 0,
            forecastHours: @json($solarForecastHours ?? 48),
            sourceNames: @json($solarSourceNames),

            get sourceLabel() {
                return this.source && this.sourceNames[this.source] ? this.sourceNames[this.source] : null;
            },

            stationTz() {
                return (typeof window !== 'undefined' && window.Meteo?.stationTimezone) ? window.Meteo.stationTimezone : 'UTC';
            },

            async init() {
                await this.loadSolarData();
            },

            async loadSolarData() {
                this.loading = true;
                this.error = null;

                try {
                    const response = await fetch('/api/weather/solar-nowcast', {
                        headers: window.Meteo?.apiHeaders() || {},
                    });

                    if (!response.ok) {
                        throw new Error(`API error (${response.status})`);
                    }

                    const data = await response.json();

                    if (data.success && data.data) {
                        let times = data.data.times || [];
                        let values = data.data.values || [];
                        if (times.length === values.length && times.length > 0) {
                            const combined = times.map((t, i) => ({ time: t, value: values[i] }));
                            combined.sort((a, b) => new Date(a.time).getTime() - new Date(b.time).getTime());
                            this.times = combined.map(c => c.time);
                            this.values = combined.map(c => c.value);
                        } else {
                            this.times = times;
                            this.values = values;
                        }
                        this.source = data.data.source || null;
                        this.sunToday = data.sun_today || null;
                        this.sunTomorrow = data.sun_tomorrow || null;
                        this.loading = false;
                        if (this.todayChartData.length === 0 && this.tomorrowChartData.length > 0) this.selectedDayIndex = 1;
                    } else {
                        throw new Error(data.message || 'Solar forecast data not available');
                    }
                } catch (e) {
                    console.error('Failed to load solar forecast:', e);
                    this.error = e.message;
                    this.loading = false;
                }
            },

            dateStrInTz(iso, tz) {
                try {
                    return new Date(iso).toLocaleDateString('en-CA', { timeZone: tz });
                } catch (e) { return ''; }
            },

            trimToDaylight(points, threshold = 5) {
                if (!points || points.length === 0) return [];
                let first = -1, last = -1;
                for (let i = 0; i < points.length; i++) {
                    if (points[i].value >= threshold) { first = i; break; }
                }
                for (let i = points.length - 1; i >= 0; i--) {
                    if (points[i].value >= threshold) { last = i; break; }
                }
                if (first < 0 || last < 0 || first > last) return points;
                return points.slice(first, last + 1);
            },

            trimToSunWindow(points, sunriseIso, sunsetIso) {
                if (!points || points.length === 0 || !sunriseIso || !sunsetIso) return points;
                const riseTs = new Date(sunriseIso).getTime();
                const setTs = new Date(sunsetIso).getTime();
                if (isNaN(riseTs) || isNaN(setTs)) return points;
                let filtered = points.filter(d => {
                    const t = new Date(d.time).getTime();
                    return t >= riseTs && t <= setTs;
                });
                if (filtered.length === 0) return [];
                const firstTs = new Date(filtered[0].time).getTime();
                const lastTs = new Date(filtered[filtered.length - 1].time).getTime();
                if (firstTs > riseTs) {
                    filtered.unshift({ time: sunriseIso, value: 0 });
                }
                if (lastTs < setTs) {
                    filtered.push({ time: sunsetIso, value: 0 });
                }
                return filtered;
            },

            get fullPoints() {
                if (this.times.length === 0 || this.values.length === 0) return [];
                return this.times.map((time, i) => ({
                    time: time,
                    value: this.values[i] !== null ? Number(this.values[i]) : null,
                })).filter(d => d.value !== null && Number.isFinite(d.value));
            },

            assignTimeBasedX(trimmed) {
                if (!trimmed || trimmed.length === 0) return [];
                if (trimmed.length === 1) return [{ ...trimmed[0], x: this.getX(0.5) }];
                const timestamps = trimmed.map(d => new Date(d.time).getTime());
                const minT = timestamps[0];
                const maxT = timestamps[timestamps.length - 1];
                const span = maxT - minT || 1;
                return trimmed.map((d, i) => ({
                    ...d,
                    x: this.getX((timestamps[i] - minT) / span)
                }));
            },

            get todayChartData() {
                const tz = this.stationTz();
                const points = this.fullPoints;
                if (points.length === 0) return [];
                const todayStr = new Date().toLocaleDateString('en-CA', { timeZone: tz });
                const dayPoints = points.filter(d => this.dateStrInTz(d.time, tz) === todayStr);
                let trimmed = this.sunToday?.sunrise_iso && this.sunToday?.sunset_iso
                    ? this.trimToSunWindow(dayPoints, this.sunToday.sunrise_iso, this.sunToday.sunset_iso)
                    : this.trimToDaylight(dayPoints);
                return this.assignTimeBasedX(trimmed);
            },

            get tomorrowChartData() {
                const tz = this.stationTz();
                const points = this.fullPoints;
                if (points.length === 0) return [];
                const tomorrow = new Date(Date.now() + 86400000);
                const tomorrowStr = tomorrow.toLocaleDateString('en-CA', { timeZone: tz });
                const dayPoints = points.filter(d => this.dateStrInTz(d.time, tz) === tomorrowStr);
                let trimmed = this.sunTomorrow?.sunrise_iso && this.sunTomorrow?.sunset_iso
                    ? this.trimToSunWindow(dayPoints, this.sunTomorrow.sunrise_iso, this.sunTomorrow.sunset_iso)
                    : this.trimToDaylight(dayPoints);
                return this.assignTimeBasedX(trimmed);
            },

            get visibleChartData() {
                return this.selectedDayIndex === 0 ? this.todayChartData : this.tomorrowChartData;
            },

            get selectedDayLabel() {
                return this.selectedDayIndex === 0 ? '{{ __("Today") }}' : '{{ __("Tomorrow") }}';
            },

            get canGoPrev() {
                return this.selectedDayIndex > 0;
            },

            get canGoNext() {
                return this.selectedDayIndex < 1 && this.tomorrowChartData.length > 0;
            },

            goPrev() {
                if (this.canGoPrev) this.selectedDayIndex--;
            },

            goNext() {
                if (this.canGoNext) this.selectedDayIndex++;
            },

            get visibleTimeRange() {
                const data = this.visibleChartData;
                if (!data || data.length < 2) return '';
                return this.formatTimeLabel(data[0].time) + ' – ' + this.formatTimeLabel(data[data.length - 1].time);
            },

            get chartData() {
                const today = this.todayChartData;
                const tomorrow = this.tomorrowChartData;
                return [...today, ...tomorrow];
            },

            get minValue() {
                if (!this.chartData || this.chartData.length === 0) return 0;
                return Math.min(...this.chartData.map(d => d.value));
            },

            get maxValue() {
                if (!this.chartData || this.chartData.length === 0) return 1000;
                return Math.max(...this.chartData.map(d => d.value));
            },

            get currentValue() {
                if (!this.chartData || this.chartData.length === 0) return null;
                return this.chartData[0]?.value ?? null;
            },

            get peakValue() {
                if (!this.chartData || this.chartData.length === 0) return null;
                return this.maxValue;
            },

            get averageValue() {
                if (!this.chartData || this.chartData.length === 0) return null;
                const sum = this.chartData.reduce((acc, d) => acc + d.value, 0);
                return Math.round(sum / this.chartData.length);
            },

            CX: 50,
            CW: 790,
            CT: 10,
            CB: 240,

            getX(fraction) {
                return this.CX + fraction * this.CW;
            },

            getY(value) {
                if (value === null || !Number.isFinite(value)) return this.CB;
                const range = this.maxValue - this.minValue || 1;
                const normalized = (value - this.minValue) / range;
                return this.CB - normalized * (this.CB - this.CT);
            },

            get gridLineValues() {
                if (!this.chartData || this.chartData.length === 0) return [];
                const max = this.maxValue;
                const step = max > 500 ? 200 : max > 200 ? 100 : 50;
                const lines = [];
                for (let v = 0; v <= Math.ceil(max / step) * step; v += step) {
                    if (v <= max + step * 0.5) lines.push(v);
                }
                return lines;
            },

            getHorizontalGridPath() {
                if (!this.chartData || this.chartData.length === 0) return '';
                const x1 = this.CX, x2 = this.CX + this.CW;
                return this.gridLineValues.map(v => `M ${x1} ${this.getY(v)} L ${x2} ${this.getY(v)}`).join(' ');
            },

            formatTimeLabel(iso) {
                try {
                    const tz = this.stationTz();
                    return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', timeZone: tz });
                } catch (e) { return ''; }
            },

            getGridLabelsHtml() {
                if (!this.chartData || this.chartData.length === 0) return '';
                return this.gridLineValues.map(v => {
                    const y = this.getY(v);
                    return `<text x="${this.CX - 8}" y="${y}" dominant-baseline="middle" text-anchor="end" fill="rgb(var(--wn-fg) / 0.55)" font-size="11" font-family="system-ui,sans-serif">${v}</text>`;
                }).join('');
            },

            getSolarLinePath() {
                return this.getLinePathFor(this.chartData);
            },

            getSolarAreaPath() {
                return this.getAreaPathFor(this.chartData);
            },

            getLinePathFor(data) {
                if (!data || data.length === 0) return '';
                const points = data.map(d => `${d.x},${this.getY(d.value)}`).join(' L ');
                return `M ${points}`;
            },

            getAreaPathFor(data) {
                if (!data || data.length === 0) return '';
                const line = this.getLinePathFor(data);
                const lastPoint = data[data.length - 1];
                const firstPoint = data[0];
                return `${line} L ${lastPoint.x} ${this.CB} L ${firstPoint.x} ${this.CB} Z`;
            },

            getVerticalTicksFor(data) {
                if (!data || data.length < 2) return [];
                const n = data.length;
                const maxTicks = 6;
                const step = Math.max(1, Math.round(n / maxTicks));
                const indices = [];
                for (let i = 0; i < n; i += step) indices.push(i);
                if (indices[indices.length - 1] !== n - 1) indices.push(n - 1);
                return indices.map(i => ({
                    x: data[i].x,
                    label: this.formatTimeLabel(data[i].time)
                }));
            },

            getVerticalGridPathFor(data) {
                if (!data || data.length === 0) return '';
                return this.getVerticalTicksFor(data).map(t => `M ${t.x} ${this.CT} L ${t.x} ${this.CB}`).join(' ');
            },

            getVerticalGridLabelsHtmlFor(data) {
                if (!data || data.length === 0) return '';
                const y = this.CB + 18;
                return this.getVerticalTicksFor(data).map(t => {
                    const escaped = (t.label || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                    return `<text x="${t.x}" y="${y}" text-anchor="middle" fill="rgb(var(--wn-fg) / 0.55)" font-size="11" font-family="system-ui,sans-serif">${escaped}</text>`;
                }).join('');
            },

            getCurrentX() {
                if (!this.chartData || this.chartData.length === 0) return 0;
                return this.chartData[0]?.x ?? 0;
            },

            getCurrentY() {
                if (this.currentValue === null) return this.CB;
                return this.getY(this.currentValue);
            },

            formatValue(value) {
                if (value === null || !Number.isFinite(value)) return '--';
                return `${Math.round(value)} W/m²`;
            }
        };
    }

    window.knmiWmsManager = function() {
        return {
            wmsMap: null,
            wmsLayer: null,
            availableLayers: {},
            availableTimes: [],
            allTimes: [], // Store all times across all dates
            availableDates: [],
            selectedDate: null,
            currentLayer: @json($wmsDefaultLayer),
            currentStyle: @json($wmsDefaultStyle),
            currentTimeIndex: 0,
            opacity: @json($wmsDefaultOpacity),
            opacityPercent: @json(\App\Models\Setting::getValue('satellite.wms_default_opacity', 70)),
            isAnimating: false,
            animationInterval: null,
            animationSpeed: @json($wmsAnimationSpeed) * 1000,
            showControls: false,
            loading: true,
            error: null,
            legendUrl: null,
            currentLayerInfo: null,
            _lastUpdateTime: 0, // Rate limiting timestamp

            async init() {
                // Initialize map first so it's always visible
                this.initMap();
                await this.loadWmsData();
                if (this.availableTimes.length > 0) {
                    this.updateLayer();
                }
            },

            async loadWmsData() {
                this.loading = true;
                this.error = null;

                try {
                    const response = await fetch('/api/weather/wms-layers', {
                        headers: window.Meteo?.apiHeaders() || {},
                    });

                    if (!response.ok) {
                        throw new Error(`API error (${response.status})`);
                    }

                    const data = await response.json();

                    if (data.success && data.data) {
                        this.availableLayers = data.data.layers || {};
                        this.allTimes = data.data.times || [];
                        
                        // Extract unique dates from all times
                        this.availableDates = this.extractDates(this.allTimes);
                        
                        // Select today by default
                        this.selectedDate = this.availableDates[this.availableDates.length - 1];
                        
                        // Filter times for selected date
                        this.loadTimesForDate();
                        
                        this.loading = false;
                    } else {
                        throw new Error(data.message || 'WMS data not available');
                    }
                } catch (e) {
                    console.error('Failed to load WMS data:', e);
                    this.error = e.message;
                    this.loading = false;
                }
            },
            
            extractDates(times) {
                // Extract unique dates (YYYY-MM-DD) from ISO timestamps
                const dateSet = new Set();
                times.forEach(time => {
                    try {
                        const date = new Date(time);
                        const dateStr = date.toISOString().split('T')[0];
                        dateSet.add(dateStr);
                    } catch (e) {
                        // Ignore invalid dates
                    }
                });
                return Array.from(dateSet).sort();
            },
            
            loadTimesForDate() {
                // Filter allTimes to only include times from the selected date
                if (!this.selectedDate) return;
                
                this.availableTimes = this.allTimes.filter(time => {
                    try {
                        const date = new Date(time);
                        const dateStr = date.toISOString().split('T')[0];
                        return dateStr === this.selectedDate;
                    } catch (e) {
                        return false;
                    }
                });
                
                // Reset to latest time for this date
                this.currentTimeIndex = this.availableTimes.length - 1;
                this.updateLayer();
            },
            
            formatDateOption(dateStr) {
                try {
                    const date = new Date(dateStr + 'T12:00:00Z');
                    const today = new Date().toISOString().split('T')[0];
                    const yesterday = new Date(Date.now() - 86400000).toISOString().split('T')[0];
                    
                    if (dateStr === today) {
                        return '{{ __("Today") }} (' + date.toLocaleDateString([], { month: 'short', day: 'numeric' }) + ')';
                    } else if (dateStr === yesterday) {
                        return '{{ __("Yesterday") }} (' + date.toLocaleDateString([], { month: 'short', day: 'numeric' }) + ')';
                    } else {
                        return date.toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric' });
                    }
                } catch (e) {
                    return dateStr;
                }
            },

            initMap() {
                if (this.wmsMap) return;
                
                const mapElement = document.getElementById('wms-map');
                if (!mapElement) {
                    console.error('WMS map element not found');
                    return;
                }
                
                // Check if map is already initialized on this element and remove it
                if (mapElement._leaflet_id) {
                    try {
                        // Try to remove existing map
                        if (this.wmsMap && this.wmsMap.remove) {
                            this.wmsMap.remove();
                        }
                        // Clear the leaflet ID to allow re-initialization
                        delete mapElement._leaflet_id;
                    } catch (e) {
                        // Ignore errors during cleanup
                    }
                }

                try {
                    this.wmsMap = L.map('wms-map', {
                        center: [52.1, 5.2], // Center of Netherlands
                        zoom: 7,
                        minZoom: 6,
                        maxZoom: 10,
                    });

                    // Add base tile layer
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© OpenStreetMap contributors',
                        maxZoom: 19
                    }).addTo(this.wmsMap);
                } catch (e) {
                    console.error('Failed to initialize WMS map:', e);
                    this.error = 'Failed to initialize map: ' + e.message;
                }
            },

            changeLayer() {
                this.currentLayerInfo = this.availableLayers[this.currentLayer];
                this.currentStyle = this.currentLayerInfo?.styles?.[0] || 'default';
                this.updateLayer();
            },

            changeStyle() {
                this.updateLayer();
            },

            changeTime() {
                this.updateLayer();
            },

            async updateLayer() {
                if (!this.wmsMap || !this.currentLayer || this.availableTimes.length === 0) return;

                const time = this.availableTimes[this.currentTimeIndex] || this.availableTimes[this.availableTimes.length - 1];
                
                // Rate limit: wait if last request was too recent
                const now = Date.now();
                if (this._lastUpdateTime && (now - this._lastUpdateTime) < 1000) {
                    // Debounce: ignore rapid requests
                    return;
                }
                this._lastUpdateTime = now;
                
                try {
                    const params = new URLSearchParams({
                        layer: this.currentLayer,
                        style: this.currentStyle,
                        time: time,
                        width: '1024',
                        height: '768',
                        opacity: this.opacity.toString(),
                    });

                    const response = await fetch(`/api/weather/wms-map?${params}`, {
                        headers: window.Meteo?.apiHeaders() || {},
                    });

                    if (!response.ok) {
                        throw new Error(`WMS map error (${response.status})`);
                    }

                    const data = await response.json();

                    if (data.success && data.data) {
                        // Remove existing overlay
                        if (this.wmsLayer) {
                            this.wmsMap.removeLayer(this.wmsLayer);
                        }

                        // Add new image overlay
                        const bounds = [[50.75, 3.2], [53.7, 7.2]]; // Netherlands bounds
                        
                        // Create new image overlay with better error handling
                        const img = new Image();
                        img.onload = () => {
                            if (this.wmsLayer) {
                                this.wmsMap.removeLayer(this.wmsLayer);
                            }
                            this.wmsLayer = L.imageOverlay(data.data.url, bounds, {
                                opacity: this.opacity,
                                attribution: 'KNMI'
                            }).addTo(this.wmsMap);
                        };
                        img.onerror = (e) => {
                            console.error('Failed to load WMS image:', data.data.url);
                            // Don't spam error messages - just log to console
                        };
                        img.src = data.data.url;

                        // Update legend
                        this.legendUrl = data.data.legend || null;
                        this.currentLayerInfo = this.availableLayers[this.currentLayer];
                    }
                } catch (error) {
                    console.error('Error updating WMS layer:', error);
                    // Don't spam error messages
                }
            },

            toggleAnimation() {
                this.isAnimating = !this.isAnimating;
                if (this.isAnimating) {
                    this.startAnimation();
                } else {
                    this.stopAnimation();
                }
            },

            startAnimation() {
                this.stopAnimation();
                
                if (this.availableTimes.length <= 1) return;

                this.animationInterval = setInterval(() => {
                    this.currentTimeIndex = (this.currentTimeIndex + 1) % this.availableTimes.length;
                    this.updateLayer();
                }, this.animationSpeed);
            },

            stopAnimation() {
                if (this.animationInterval) {
                    clearInterval(this.animationInterval);
                    this.animationInterval = null;
                }
                // Reset rate limit timer to allow immediate manual updates
                this._lastUpdateTime = 0;
            },

            get currentLayerStyles() {
                const layer = this.availableLayers[this.currentLayer];
                return layer?.styles || ['default'];
            },

            get currentTimeLabel() {
                if (this.availableTimes.length === 0 || this.currentTimeIndex >= this.availableTimes.length) {
                    return '';
                }
                return this.formatTime(this.availableTimes[this.currentTimeIndex]);
            },

            formatTime(timeStr) {
                if (!timeStr) return '';
                try {
                    const date = new Date(timeStr);
                    return date.toLocaleString([], { 
                        year: 'numeric', 
                        month: '2-digit', 
                        day: '2-digit', 
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                } catch (e) {
                    return timeStr;
                }
            }
        };
    }
</script>
@endpush
@endsection
