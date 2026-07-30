@extends('weather.layout')

@section('title', __('Forecast') . ' - ' . \App\Models\Setting::stationName())
@section('meta_description', __('Forecast page meta description', ['location' => \App\Models\Setting::stationLocation() ?: \App\Models\Setting::stationName()]))
@section('og_image', route('og.forecast'))

@section('content')
<div class="space-y-6" x-data="forecastPage()" x-init="init()">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold">{{ __('Forecast') }}</h1>
            <p class="text-gray-400">{{ __('Forecast page intro', ['location' => \App\Models\Setting::stationLocation() ?: \App\Models\Setting::stationName()]) }}</p>
        </div>
        <div class="flex gap-2">
            <button @click="view = 'daily'" :class="view === 'daily' ? 'bg-blue-600' : 'bg-white/10 hover:bg-white/20'" class="px-4 py-2 rounded-lg text-sm transition-colors">{{ __('Daily') }}</button>
            <button @click="view = 'hourly'" :class="view === 'hourly' ? 'bg-blue-600' : 'bg-white/10 hover:bg-white/20'" class="px-4 py-2 rounded-lg text-sm transition-colors">{{ __('Hourly') }}</button>
        </div>
    </div>

    <!-- Today's Summary -->
    <div class="bg-gradient-to-br from-weather-card to-weather-card/50 rounded-2xl p-4 sm:p-6 border border-white/10">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 md:gap-6">
            <div class="flex items-center gap-4 md:gap-6">
                <img :src="(backgroundEffectsEnabled ? window.Meteo.iconsAnimatedBaseUrl : window.Meteo.iconsStaticBaseUrl) + '/' + getTodayIcon() + '.svg'"
	                     class="w-12 h-12 sm:w-16 sm:h-16 md:w-20 md:h-20 flex-shrink-0" alt="Weather">
                <div class="min-w-0">
                    <h2 class="text-base sm:text-xl font-semibold truncate" x-text="getTodayDescription()">{{ __('Loading...') }}</h2>
                    <p class="text-sm text-gray-400" x-text="formatFullDate(new Date())"></p>
                </div>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-6 md:gap-8">
                <div class="text-center">
                    <div class="text-[10px] sm:text-xs text-gray-400 mb-0.5">{{ __('Max') }}</div>
                    <div class="text-xl sm:text-2xl md:text-3xl font-bold text-weather-warm" x-text="formatTemp(forecast[0]?.temp_high)"></div>
                </div>
                <div class="text-center">
                    <div class="text-[10px] sm:text-xs text-gray-400 mb-0.5">{{ __('Min') }}</div>
                    <div class="text-xl sm:text-2xl md:text-3xl font-bold text-weather-cold" x-text="formatTemp(forecast[0]?.temp_low)"></div>
                </div>
                <div class="text-center">
                    <div class="text-[10px] sm:text-xs text-gray-400 mb-0.5">{{ __('Precipitation') }}</div>
                    <div class="text-xl sm:text-2xl md:text-3xl font-bold text-weather-rain" x-text="formatRain(forecast[0]?.precipitation)"></div>
                </div>
                <div class="text-center">
                    <div class="text-[10px] sm:text-xs text-gray-400 mb-0.5">{{ __('Wind') }}</div>
                    <div class="text-xl sm:text-2xl md:text-3xl font-bold" x-text="formatWind(forecast[0]?.wind_speed, 0)"></div>
                </div>
            </div>
        </div>
        <div class="mt-4 pt-4 border-t border-white/10 text-[10px] sm:text-xs text-gray-500">
            {{ __('Source') }}: <span x-text="forecastMeta?.forecast_source || ''"></span>
        </div>
    </div>

    <!-- Daily Forecast -->
    <div x-show="view === 'daily'" class="space-y-3">
        <template x-for="(day, idx) in forecast" :key="day.date">
            <div class="bg-weather-card rounded-xl border border-white/10 overflow-hidden"
                 :class="hasHourlyDataForDay(day.date) ? 'hover:bg-white/5 transition-colors cursor-pointer' : ''"
                 @click="hasHourlyDataForDay(day.date) && (view = 'hourly', selectedDate = day.date)">
                <!-- Forecast Data Row -->
                <div class="p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4 flex-1">
                            <img :src="(backgroundEffectsEnabled ? window.Meteo.iconsAnimatedBaseUrl : window.Meteo.iconsStaticBaseUrl) + '/' + getWeatherIcon(day.symbol) + '.svg'"
	                                 class="w-10 h-10" alt="Weather">
                            <div class="min-w-[100px]">
                                <div class="font-semibold" x-text="formatDay(day.date)"></div>
                                <div class="text-xs text-gray-400" x-text="formatShortDate(day.date)"></div>
                            </div>
                            <div class="hidden md:block text-sm text-gray-400 flex-1" x-text="getDescription(day.symbol)"></div>
                        </div>
                        <div class="flex items-center gap-6 md:gap-8">
                            <div class="text-center min-w-[60px]">
                                <span class="text-xl font-bold text-weather-warm" x-text="formatTemp(day.temp_high)"></span>
                                <span class="text-gray-500 mx-1">/</span>
                                <span class="text-xl font-bold text-weather-cold" x-text="formatTemp(day.temp_low)"></span>
                            </div>
                            <div class="hidden sm:flex items-center gap-2 min-w-[80px]">
	                                    <img src="{{ asset(($weatherIconsPath ?? 'icons/weather') . '/raindrops.svg') }}"
	                                         :src="(backgroundEffectsEnabled ? window.Meteo.iconsAnimatedBaseUrl : window.Meteo.iconsStaticBaseUrl) + '/raindrops.svg'"
	                                         class="w-4 h-4" alt="">
                                <span x-text="formatRain(day.precipitation || 0)"></span>
                            </div>
                            <div class="hidden md:flex items-center gap-2 min-w-[80px]">
	                                <img src="{{ asset(($weatherIconsPath ?? 'icons/weather') . '/wind.svg') }}"
	                                     :src="(backgroundEffectsEnabled ? window.Meteo.iconsAnimatedBaseUrl : window.Meteo.iconsStaticBaseUrl) + '/wind.svg'"
	                                     class="w-4 h-4" alt="">
                                <span x-text="formatWind(day.wind_speed || 0, 0)"></span>
                            </div>
                        </div>
                    </div>
                    <!-- Source for raw data -->
                    <div class="mt-3 pt-3 border-t border-white/5 text-xs text-gray-500">
                        {{ __('Source') }}: <span x-text="forecastMeta?.forecast_source || ''"></span>
                    </div>
                </div>
                
                <!-- NLG Text (for all days that have it) -->
                <template x-if="day.nlg_text">
                    <div class="px-4 pb-4 border-t border-white/10 pt-3 bg-white/2">
                        <p class="text-sm text-gray-300 leading-relaxed mb-3" x-text="day.nlg_text"></p>
                        <!-- Source for NLG -->
                        <div class="text-xs text-gray-500">
                            <span>{{ __('Generated via NLG (Natural Language Generation)') }}</span>
                            <template x-if="day.nlg_meta?.status_label">
                                <span> • <span x-text="day.nlg_meta.status_label"></span></span>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>

    <!-- Hourly Forecast -->
    <div x-show="view === 'hourly'" class="space-y-4">
        <!-- Back button and day selector -->
        <div class="flex items-center justify-between mb-4">
            <button @click="view = 'daily'; selectedDate = null" 
                    class="flex items-center gap-2 text-gray-400 hover:text-white transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                {{ __('Back to Daily') }}
            </button>
            <div class="text-sm text-gray-400" x-show="selectedDate">
                <span x-text="selectedDate ? formatDay(selectedDate) + ', ' + formatShortDate(selectedDate) : ''"></span>
            </div>
        </div>
        
        <div class="bg-weather-card rounded-2xl p-5 border border-white/10">
            <h3 class="font-semibold mb-4" x-text="selectedDate ? formatDay(selectedDate) + ' - ' + __('Hourly Forecast') : __('Next 24 hours')"></h3>
            <div class="flex gap-3 overflow-x-auto pb-2">
                <template x-for="(hour, idx) in getFilteredHourly()" :key="'hour-'+idx">
                    <div class="text-center p-3 min-w-[80px] bg-white/5 rounded-xl flex-shrink-0 hover:bg-white/10 transition-colors"
                         :class="idx === 0 ? 'bg-white/10 ring-1 ring-blue-500/50' : ''">
                        <div class="text-xs text-gray-400" x-text="formatHourTime(hour.time)"></div>
                        <img :src="(backgroundEffectsEnabled ? window.Meteo.iconsAnimatedBaseUrl : window.Meteo.iconsStaticBaseUrl) + '/' + getWeatherIcon(hour.symbol, hour.time) + '.svg'"
	                             class="w-8 h-8 mx-auto my-2" alt="Weather">
                        <div class="font-bold" x-text="formatTemp(hour.temperature)"></div>
                        <div class="text-xs text-blue-400 mt-1" x-show="hour.precipitation_1h > 0">
                            💧<span x-text="formatRain(hour.precipitation_1h)"></span>
                        </div>
                        <div class="text-xs text-gray-500 mt-1" x-show="!hour.precipitation_1h || hour.precipitation_1h === 0">
                            <span x-text="Math.round(hour.humidity) + '%'"></span> 💧
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Wind & Details -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-weather-card rounded-2xl p-5 border border-white/10">
                <h3 class="font-semibold mb-4 flex items-center gap-2">
	                    <img src="{{ asset(($weatherIconsPath ?? 'icons/weather') . '/wind.svg') }}"
	                         :src="(backgroundEffectsEnabled ? window.Meteo.iconsAnimatedBaseUrl : window.Meteo.iconsStaticBaseUrl) + '/wind.svg'"
	                         class="w-5 h-5" alt="">
                    {{ __('Wind in the coming hours') }}
                </h3>
                <div class="space-y-2">
                    <template x-for="(hour, idx) in getFilteredHourly().slice(0, 8)" :key="'wind-'+idx">
                        <div class="flex items-center justify-between text-sm py-2 border-b border-white/5 last:border-0">
                            <span class="text-gray-400" x-text="formatHourTime(hour.time)"></span>
                            <div class="flex items-center gap-3">
                                <span x-text="formatWind(hour.wind_speed, 0)"></span>
                                <span class="text-xs px-2 py-1 bg-white/10 rounded" x-text="getWindDirection(hour.wind_direction)"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
            
            <div class="bg-weather-card rounded-2xl p-5 border border-white/10">
                <h3 class="font-semibold mb-4 flex items-center gap-2">
	                    <img src="{{ asset(($weatherIconsPath ?? 'icons/weather') . '/thermometer.svg') }}"
	                         :src="(backgroundEffectsEnabled ? window.Meteo.iconsAnimatedBaseUrl : window.Meteo.iconsStaticBaseUrl) + '/thermometer.svg'"
	                         class="w-5 h-5" alt="">
                    {{ __('Temperature trend') }}
                </h3>
                <div class="space-y-2">
                    <template x-for="(hour, idx) in getFilteredHourly().slice(0, 8)" :key="'temp-'+idx">
                        <div class="flex items-center gap-3 text-sm py-1">
                            <span class="text-gray-400 w-16" x-text="formatHourTime(hour.time)"></span>
                            <div class="flex-1 bg-white/5 rounded-full h-2 overflow-hidden">
                                <div class="h-full bg-gradient-to-r from-weather-cold to-weather-warm" 
                                     :style="'width: ' + Math.max(10, (hour.temperature + 10) * 3) + '%'"></div>
                            </div>
                            <span class="font-bold w-10 text-right" x-text="formatTemp(hour.temperature)"></span>
                        </div>
                    </template>
                </div>
            </div>
        </div>
        <div class="mt-4 pt-4 border-t border-white/10 text-xs text-gray-500">
            {{ __('Source') }}: <span x-text="forecastMeta?.forecast_source || ''"></span>
        </div>
    </div>

    <article class="bg-weather-card rounded-2xl p-6 border border-white/10 prose prose-invert prose-sm max-w-none">
        <h2 class="text-lg font-semibold mb-3">{{ __('Forecast page about heading') }}</h2>
        <p class="text-gray-300 mb-3">{{ __('Forecast page about body 1') }}</p>
        <p class="text-gray-300 mb-3">{{ __('Forecast page about body 2') }}</p>
        <p class="text-gray-300 mb-3">{{ __('Forecast page about body 3') }}</p>
        <footer class="text-xs text-gray-500 mt-4 pt-4 border-t border-white/10">{{ __('Forecast page sources') }}</footer>
    </article>
</div>

@push('scripts')
<script>
function forecastPage() {
    return {
        forecast: [],
        hourly: [],
        forecastMeta: {},
        view: 'daily',
        selectedDate: null,
        loading: true,
        locale: window.Meteo?.jsLocale || 'nl-NL',
        units: window.Meteo?.activeUnits || 'metric',

        tempUnit() {
            return this.units === 'imperial' ? '°F' : '°C';
        },
        rainUnit() {
            return this.units === 'imperial' ? 'in' : 'mm';
        },
        windUnit() {
            if (this.units === 'scandinavia') return 'm/s';
            if (this.units === 'imperial' || this.units === 'uk') return 'mph';
            return 'km/h';
        },
        normalizeDecimals(value, fallback = 1) {
            const n = Number(value);
            if (!Number.isFinite(n)) return fallback;
            return Math.max(0, Math.min(4, Math.trunc(n)));
        },
        temperatureDecimals() {
            return this.normalizeDecimals(window.Meteo?.temperatureDecimals, 1);
        },
        windDecimals() {
            return this.normalizeDecimals(window.Meteo?.windDecimals, 1);
        },
        rainDecimals() {
            return this.normalizeDecimals(window.Meteo?.rainDecimals, 1);
        },
        formatTempValue(value, decimals = null) {
            if (value === null || value === undefined) return '--';
            const temp = this.units === 'imperial' ? (value * 9 / 5 + 32) : value;
            const useDecimals = decimals === null ? this.temperatureDecimals() : this.normalizeDecimals(decimals, this.temperatureDecimals());
            return temp.toFixed(useDecimals);
        },
        formatTemp(value, decimals = null) {
            const formatted = this.formatTempValue(value, decimals);
            return formatted === '--' ? '--' : `${formatted}${this.tempUnit()}`;
        },
        formatRainValue(value, decimals = null) {
            if (value === null || value === undefined) return '--';
            const rain = this.units === 'imperial' ? (value * 0.0393700787) : value;
            const useDecimals = decimals === null ? this.rainDecimals() : this.normalizeDecimals(decimals, this.rainDecimals());
            return rain.toFixed(useDecimals);
        },
        formatRain(value, decimals = null) {
            const formatted = this.formatRainValue(value, decimals);
            return formatted === '--' ? '--' : `${formatted} ${this.rainUnit()}`;
        },
        formatWindValue(value, decimals = null) {
            if (value === null || value === undefined) return '--';
            let speed = value;
            if (this.units === 'imperial' || this.units === 'uk') {
                speed = value * 0.6213711922;
            } else if (this.units === 'scandinavia') {
                speed = value / 3.6;
            }
            const useDecimals = decimals === null ? this.windDecimals() : this.normalizeDecimals(decimals, this.windDecimals());
            return speed.toFixed(useDecimals);
        },
        formatWind(value, decimals = null) {
            const formatted = this.formatWindValue(value, decimals);
            return formatted === '--' ? '--' : `${formatted} ${this.windUnit()}`;
        },

        async init() {
            // Load forecast data initially
            await this.loadForecast();
        },

        async loadForecast() {
            this.loading = true;
            try {
                // Get current lang/units from URL params if present, otherwise fall back to active dashboard settings
                const urlParams = new URLSearchParams(window.location.search);
                const lang = urlParams.get('lang') || (window.Meteo?.activeLocale ?? null);
                const units = urlParams.get('units') || (window.Meteo?.activeUnits ?? null);
                
                // Build API URL with same lang/units parameters so NLG uses the correct locale
                let apiUrl = '/api/weather/forecast';
                const apiParams = [];
                if (lang) apiParams.push(`lang=${encodeURIComponent(lang)}`);
                if (units) apiParams.push(`units=${encodeURIComponent(units)}`);
                if (apiParams.length > 0) {
                    apiUrl += '?' + apiParams.join('&');
                }
                
                const res = await fetch(apiUrl, {
                    credentials: 'same-origin',
                    headers: window.Meteo.apiHeaders({
                        'X-Requested-With': 'XMLHttpRequest'
                    }),
                });
                const data = await res.json();
                if (data.success && data.data) {
                    this.forecast = data.data.daily || [];
                    this.hourly = data.data.hourly || [];
                    this.forecastMeta = data.meta || {};
                }
            } catch (e) {
                console.error('Forecast error:', e);
            } finally {
                this.loading = false;
            }
        },

        getTodayIcon() {
            if (!this.forecast[0]?.symbol) return 'partly-cloudy-day';
            return this.getWeatherIcon(this.forecast[0].symbol);
        },

        getTodayDescription() {
            if (!this.forecast[0]?.symbol) return @json(__('Loading...'));
            return this.getDescription(this.forecast[0].symbol);
        },

        getWeatherIcon(symbol, timeStr = null) {
            if (!symbol) return 'partly-cloudy-day';
            const s = symbol.toLowerCase();

            // Determine night from symbol suffix or time
            let isNight = s.includes('_night') || s.includes('_polartwilight');
            if (!isNight && timeStr) {
                const hour = new Date(timeStr).getHours();
                isNight = hour < 6 || hour >= 21;
            }
            const suffix = isNight ? '-night' : '-day';

            // Thunderstorms
            if (s.includes('thunder')) {
                if (s.includes('snow')) return isNight ? 'thunderstorms-night-snow' : 'thunderstorms-day-snow';
                if (s.includes('_day') || s.includes('_night')) {
                    return isNight ? 'thunderstorms-night-rain' : 'thunderstorms-day-rain';
                }
                return 'thunderstorms-rain';
            }

            // Clear sky
            if (s.includes('clearsky')) return `clear${suffix}`;

            // Fair
            if (s.includes('fair')) return `partly-cloudy${suffix}`;

            // Fog
            if (s.includes('fog')) return `fog${suffix}`;

            // Snow
            if (s.includes('snow')) {
                if (s.includes('showers')) return `partly-cloudy${suffix}-snow`;
                return 'snow';
            }

            // Sleet
            if (s.includes('sleet')) {
                if (s.includes('showers')) return `partly-cloudy${suffix}-sleet`;
                return 'sleet';
            }

            // Rain
            if (s.includes('rain')) {
                if (s.includes('light')) {
                    if (s.includes('showers')) return `partly-cloudy${suffix}-drizzle`;
                    return 'drizzle';
                }
                if (s.includes('showers')) return `partly-cloudy${suffix}-rain`;
                return 'rain';
            }

            // Partly cloudy
            if (s.includes('partlycloud')) return `partly-cloudy${suffix}`;

            // Cloudy
            if (s.includes('cloud')) return 'cloudy';

            return `partly-cloudy${suffix}`;
        },

        getDescription(symbol) {
            if (!symbol) return '';
            const s = symbol.toLowerCase();
            if (s.includes('clearsky')) return @json(__('Clear sky'));
            if (s.includes('fair')) return @json(__('Mostly sunny'));
            if (s.includes('partlycloud')) return @json(__('Partly cloudy'));
            if (s.includes('cloud')) return @json(__('Cloudy'));
            if (s.includes('lightrain')) return @json(__('Light rain'));
            if (s.includes('heavyrain')) return @json(__('Heavy rain'));
            if (s.includes('rain')) return @json(__('Rain'));
            if (s.includes('snow')) return @json(__('Snow'));
            if (s.includes('sleet')) return @json(__('Sleet'));
            if (s.includes('thunder')) return @json(__('Thunder'));
            if (s.includes('fog')) return @json(__('Fog'));
            return @json(__('Variable cloudiness'));
        },

        formatDay(dateStr) {
            const date = new Date(dateStr);
            const today = new Date();
            const tomorrow = new Date(today);
            tomorrow.setDate(tomorrow.getDate() + 1);
            
            if (date.toDateString() === today.toDateString()) return @json(__('Today'));
            if (date.toDateString() === tomorrow.toDateString()) return @json(__('Tomorrow'));
            return date.toLocaleDateString(this.locale, { weekday: 'long' });
        },

        formatShortDate(dateStr) {
            return new Date(dateStr).toLocaleDateString(this.locale, { day: 'numeric', month: 'short' });
        },

        formatFullDate(date) {
            return date.toLocaleDateString(this.locale, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
        },

        getHourLabel(offset) {
            const d = new Date();
            d.setHours(d.getHours() + offset);
            return d.toLocaleTimeString(this.locale, { hour: '2-digit', minute: '2-digit' });
        },
        
        formatHourTime(timeStr) {
            if (!timeStr) return '--:--';
            const date = new Date(timeStr);
            return date.toLocaleTimeString(this.locale, { hour: '2-digit', minute: '2-digit' });
        },
        
        getWindDirection(degrees) {
            if (degrees === null || degrees === undefined) return '-';
            const directions = ['N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE', 'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW'];
            const index = Math.round(degrees / 22.5) % 16;
            return directions[index];
        },
        
        getFilteredHourly() {
            if (!this.selectedDate) {
                return this.hourly.slice(0, 24);
            }
            // Filter hourly data for selected date
            const selectedDateStr = this.selectedDate;
            return this.hourly.filter(hour => {
                if (!hour.time) return false;
                const hourDate = hour.time.substring(0, 10);
                return hourDate === selectedDateStr;
            }).slice(0, 24);
        },
        
        hasHourlyDataForDay(dateStr) {
            if (!dateStr || !this.hourly || this.hourly.length === 0) return false;
            return this.hourly.some(hour => {
                if (!hour.time) return false;
                const hourDate = hour.time.substring(0, 10);
                return hourDate === dateStr;
            });
        }
    };
}
</script>
@endpush
@endsection
