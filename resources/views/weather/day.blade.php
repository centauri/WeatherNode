@extends('weather.layout')
@section('og_image', isset($date) ? route('og.history', ['date' => $date]) : route('og.home'))
@php
    // A day inside the record with no readings is a real URL, but there is
    // nothing on it worth indexing.
    $seoNoIndex = ($readingsCount ?? 0) === 0;
    $activeUnits = $activeUnits ?? 'metric';
    $activeLocale = $activeLocale ?? app()->getLocale();
    $locale = str_replace('-', '_', $activeLocale);
@endphp

@section('title', \Carbon\Carbon::parse($date)->locale($locale)->translatedFormat('j F Y') . ' - ' . \App\Models\Setting::stationName())

@section('content')
@php
    $dateObj = \Carbon\Carbon::parse($date);
@endphp
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <nav class="text-sm mb-2">
                <a href="{{ route('history') }}" class="text-ui-muted hover:text-ui-fg transition">← {{ __('Back to overview') }}</a>
            </nav>
            <h1 class="text-2xl md:text-3xl font-bold">{{ $dateObj->locale($locale)->translatedFormat('l j F Y') }}</h1>
            <p class="text-ui-muted">{{ __('Daily weather data') }}</p>
        </div>
        
        <div class="flex gap-2">
            @php
                $prevDay = $dateObj->copy()->subDay();
                $nextDay = $dateObj->copy()->addDay();
                // Stop at the first recorded day. Linking past it produced an
                // endless run of empty pages for anything following links.
                $hasPrevDay = !isset($firstRecordedDay) || $firstRecordedDay === null
                    || $prevDay->gte($firstRecordedDay);
            @endphp
            @if($hasPrevDay)
                <a href="{{ route('history.day', $prevDay->format('Y-m-d')) }}"
                   class="px-4 py-2 bg-ui-overlay/10 hover:bg-ui-overlay/20 rounded-lg transition">
                    ← {{ $prevDay->locale($locale)->translatedFormat('j M') }}
                </a>
            @endif
            @if($nextDay->lte(now()))
                <a href="{{ route('history.day', $nextDay->format('Y-m-d')) }}" 
                   class="px-4 py-2 bg-ui-overlay/10 hover:bg-ui-overlay/20 rounded-lg transition">
                    {{ $nextDay->locale($locale)->translatedFormat('j M') }} →
                </a>
            @endif
        </div>
    </div>

    <!-- Day Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-gradient-to-br from-weather-warm/20 to-weather-card rounded-2xl p-5 border border-weather-warm/30">
            <p class="text-xs text-ui-muted mb-2">{{ __('Maximum') }}</p>
            <p class="text-4xl font-bold text-data-amber-500">{{ $summary->temp_high !== null ? $unit->temperature($summary->temp_high, $activeUnits) : '--' }}</p>
        </div>
        <div class="bg-gradient-to-br from-weather-cold/20 to-weather-card rounded-2xl p-5 border border-weather-cold/30">
            <p class="text-xs text-ui-muted mb-2">{{ __('Minimum') }}</p>
            <p class="text-4xl font-bold text-data-cyan-500">{{ $summary->temp_low !== null ? $unit->temperature($summary->temp_low, $activeUnits) : '--' }}</p>
        </div>
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
            <p class="text-xs text-ui-muted mb-2">{{ __('Average') }}</p>
            <p class="text-4xl font-bold">{{ $summary->temp_avg !== null ? $unit->temperature($summary->temp_avg, $activeUnits) : '--' }}</p>
        </div>
        <div class="bg-gradient-to-br from-weather-rain/20 to-weather-card rounded-2xl p-5 border border-weather-rain/30">
            <p class="text-xs text-ui-muted mb-2">{{ __('Precipitation') }}</p>
            <p class="text-4xl font-bold text-data-indigo-500">{{ $summary->rain_total !== null ? $unit->rain($summary->rain_total, $activeUnits) : $unit->rain(0, $activeUnits) }}</p>
        </div>
    </div>

    <!-- Additional Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
            <p class="text-xs text-ui-muted mb-2">💨 {{ __('Max wind') }}</p>
            <p class="text-2xl font-bold">{{ $summary->wind_max !== null ? $unit->wind($summary->wind_max, $activeUnits) : '--' }}</p>
        </div>
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
            <p class="text-xs text-ui-muted mb-2">💨 {{ __('Average wind') }}</p>
            <p class="text-2xl font-bold">{{ $summary->wind_avg !== null ? $unit->wind($summary->wind_avg, $activeUnits) : '--' }}</p>
        </div>
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
            <p class="text-xs text-ui-muted mb-2">💧 {{ __('Humidity') }}</p>
            <p class="text-2xl font-bold">{{ $summary->humidity_avg ? number_format($summary->humidity_avg, 0) : '--' }}%</p>
        </div>
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
            <p class="text-xs text-ui-muted mb-2">📊 {{ __('Pressure') }}</p>
            <p class="text-2xl font-bold">{{ $summary->pressure_avg !== null ? $unit->pressure($summary->pressure_avg, $activeUnits) : '--' }}</p>
        </div>
    </div>

    <!-- Daily Charts -->
    @php
        $cs = $chartSettings ?? [];
    @endphp
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        @if(in_array('temperature', $cs))
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
            <h3 class="font-semibold mb-4">{{ __('Temperature') }}</h3>
            <div id="day-chart-temps" class="h-72"></div>
        </div>
        @endif
        @if(in_array('wind', $cs))
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
            <h3 class="font-semibold mb-4">{{ __('Wind') }}</h3>
            <div id="day-chart-wind" class="h-72"></div>
        </div>
        @endif
        @if(in_array('humidity', $cs))
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
            <h3 class="font-semibold mb-4">{{ __('Humidity & Dew Point') }}</h3>
            <div id="day-chart-humidity" class="h-72"></div>
        </div>
        @endif
        @if(in_array('solar_uv', $cs))
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
            <h3 class="font-semibold mb-4">{{ __('UV & Solar radiation') }}</h3>
            <div id="day-chart-solar" class="h-72"></div>
        </div>
        @endif
        @if(in_array('precipitation', $cs))
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
            <h3 class="font-semibold mb-4">{{ __('Precipitation & Pressure') }}</h3>
            <div id="day-chart-precip" class="h-72"></div>
        </div>
        @endif

        {{-- Sensor charts: only render if admin enabled AND data exists --}}
        @if(in_array('soil', $cs) && ($availableSensors['soil'] ?? false))
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
            <h3 class="font-semibold mb-4">{{ __('Soil') }}</h3>
            <div id="day-chart-soil" class="h-72"></div>
        </div>
        @endif
        @if(in_array('leaf_wetness', $cs) && ($availableSensors['leaf_wetness'] ?? false))
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
            <h3 class="font-semibold mb-4">{{ __('Leaf Wetness') }}</h3>
            <div id="day-chart-leaf" class="h-72"></div>
        </div>
        @endif
        @if(in_array('air_quality', $cs) && ($availableSensors['air_quality'] ?? false))
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
            <h3 class="font-semibold mb-4">{{ __('Air Quality') }}</h3>
            <div id="day-chart-airquality" class="h-72"></div>
        </div>
        @endif
        @if(in_array('co2', $cs) && ($availableSensors['co2'] ?? false))
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
            <h3 class="font-semibold mb-4">{{ __('CO₂') }}</h3>
            <div id="day-chart-co2" class="h-72"></div>
        </div>
        @endif
        @if(in_array('lightning', $cs) && ($availableSensors['lightning'] ?? false))
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
            <h3 class="font-semibold mb-4">{{ __('Lightning') }}</h3>
            <div id="day-chart-lightning" class="h-72"></div>
        </div>
        @endif
        @if(in_array('water_temp', $cs) && ($availableSensors['water_temp'] ?? false))
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
            <h3 class="font-semibold mb-4">{{ __('Water Temperature') }}</h3>
            <div id="day-chart-water" class="h-72"></div>
        </div>
        @endif
        @if(in_array('extra_sensors', $cs) && ($availableSensors['extra_sensors'] ?? false))
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
            <h3 class="font-semibold mb-4">{{ __('Extra Sensors') }}</h3>
            <div id="day-chart-extra" class="h-72"></div>
        </div>
        @endif
    </div>

    <!-- Hourly Data (loaded progressively to avoid slow initial page render) -->
    @if(($readingsCount ?? 0) > 0)
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 mb-4">
                <h3 class="font-semibold">⏰ {{ __('Hourly measurements') }}</h3>

                <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                    <div id="day-readings-status" class="text-xs text-ui-muted flex items-center gap-2">
                        <svg id="day-readings-spinner" class="w-4 h-4 animate-spin text-ui-secondary" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                        <span id="day-readings-status-text">{{ __('Loading…') }}</span>
                    </div>

                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-3">
                        <div class="flex items-center gap-2">
                            <button id="day-readings-prev" type="button"
                                    class="px-3 py-2 bg-ui-overlay/10 hover:bg-ui-overlay/20 rounded-lg transition disabled:opacity-40 disabled:hover:bg-ui-overlay/10"
                                    disabled>
                                ← {{ __('Prev') }}
                            </button>
                            <button id="day-readings-next" type="button"
                                    class="px-3 py-2 bg-ui-overlay/10 hover:bg-ui-overlay/20 rounded-lg transition disabled:opacity-40 disabled:hover:bg-ui-overlay/10"
                                    disabled>
                                {{ __('Next') }} →
                            </button>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 text-xs text-ui-muted">
                            <span>{{ __('Page') }}</span>
                            <input id="day-readings-page-input" type="number" min="1" step="1"
                                   class="w-20 bg-ui-overlay/5 border border-ui-line/10 rounded-lg px-2 py-1 text-ui-fg"
                                   value="{{ (int) ($readingsPage ?? 1) }}">
                            <span id="day-readings-page-of"></span>

                            <span class="mx-2 hidden sm:inline-block">•</span>

                            <label for="day-readings-per-page" class="sr-only">{{ __('Rows per page') }}</label>
                            <select id="day-readings-per-page"
                                    class="bg-ui-overlay/5 border border-ui-line/10 rounded-lg px-2 py-1 text-ui-fg">
                                @foreach([60, 250, 500, 750, 1000, 1500, 2000] as $opt)
                                    <option value="{{ $opt }}" {{ (int) ($readingsPerPage ?? 60) === $opt ? 'selected' : '' }}>
                                        {{ $opt }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-ui-overlay/5">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-ui-muted">{{ __('Time') }}</th>
                            <th class="px-4 py-3 text-right font-medium text-ui-muted">{{ __('Temp') }}</th>
                            <th class="px-4 py-3 text-right font-medium text-ui-muted">{{ __('Feels like') }}</th>
                            <th class="px-4 py-3 text-right font-medium text-ui-muted">{{ __('Dew point') }}</th>
                            <th class="px-4 py-3 text-right font-medium text-ui-muted">{{ __('RH') }}</th>
                            <th class="px-4 py-3 text-right font-medium text-ui-muted">{{ __('Wind') }}</th>
                            <th class="px-4 py-3 text-right font-medium text-ui-muted">{{ __('Pressure') }}</th>
                            <th class="px-4 py-3 text-right font-medium text-ui-muted">{{ __('Rain') }}</th>
                            <th class="px-4 py-3 text-right font-medium text-ui-muted">{{ __('UV') }}</th>
                            <th class="px-4 py-3 text-right font-medium text-ui-muted">{{ __('Solar') }}</th>
                        </tr>
                    </thead>
                    <tbody id="day-readings-body" class="divide-y divide-ui-line/5">
                        {{-- Skeleton rows (replaced by JS) --}}
                        @for($i = 0; $i < 12; $i++)
                            <tr class="animate-pulse">
                                <td class="px-4 py-3">
                                    <div class="h-3 w-10 bg-ui-overlay/10 rounded"></div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="h-3 w-14 bg-ui-overlay/10 rounded ml-auto"></div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="h-3 w-14 bg-ui-overlay/10 rounded ml-auto"></div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="h-3 w-14 bg-ui-overlay/10 rounded ml-auto"></div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="h-3 w-10 bg-ui-overlay/10 rounded ml-auto"></div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="h-3 w-28 bg-ui-overlay/10 rounded ml-auto"></div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="h-3 w-20 bg-ui-overlay/10 rounded ml-auto"></div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="h-3 w-24 bg-ui-overlay/10 rounded ml-auto"></div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="h-3 w-10 bg-ui-overlay/10 rounded ml-auto"></div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="h-3 w-20 bg-ui-overlay/10 rounded ml-auto"></div>
                                </td>
                            </tr>
                        @endfor
                    </tbody>
                </table>
            </div>

            <noscript>
                <p class="mt-3 text-xs text-ui-muted">
                    {{ __('This table loads with JavaScript enabled.') }}
                </p>
            </noscript>
        </div>
    @endif

    <!-- Navigation to month -->
    <div class="text-center">
        <a href="{{ route('history', ['month' => $dateObj->month, 'year' => $dateObj->year]) }}" 
           class="inline-flex items-center gap-2 px-6 py-3 bg-ui-overlay/10 hover:bg-ui-overlay/20 rounded-lg transition">
            <span>📅</span>
            <span>{{ __('View full month') }} {{ $dateObj->locale($locale)->translatedFormat('F Y') }}</span>
        </a>
    </div>
</div>

<script type="application/json" id="day-chart-data">
{!! json_encode($dayChart ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
</script>
<script type="application/json" id="day-chart-strings">
{!! json_encode([
    'no_data' => __('No data available'),
    'temp_high' => __('Max temperature'),
    'temp_avg' => __('Average'),
    'temp_low' => __('Min temperature'),
    'rain_total' => __('Total precipitation'),
    'wind_avg' => __('Average wind'),
    'wind_gust' => __('Wind gust'),
    'wind_dir' => __('Wind direction'),
    'pressure_avg' => __('Air Pressure'),
    'temperature' => __('Temperature'),
    'precipitation' => __('Precipitation'),
    'wind' => __('Wind'),
    'pressure' => __('Pressure'),
    'feels_like' => __('Feels like'),
    'humidity' => __('Humidity'),
    'dew_point' => __('Dew point'),
    'rain_rate' => __('Rain rate'),
    'uv_index' => __('UV Index'),
    'solar_radiation' => __('Solar Radiation'),
    'soil_moisture' => __('Soil Moisture'),
    'soil_temp' => __('Soil Temperature'),
    'leaf_wetness' => __('Leaf Wetness'),
    'pm25' => __('PM2.5'),
    'pm10' => __('PM10'),
    'co2' => __('CO₂'),
    'co2_temp' => __('CO₂ Temperature'),
    'co2_humidity' => __('CO₂ Humidity'),
    'lightning_count' => __('Strike Count'),
    'lightning_distance' => __('Distance'),
    'water_temp' => __('Water Temperature'),
    'extra_temp' => __('Temperature'),
    'extra_humidity' => __('Humidity'),
    'sensor' => __('Sensor'),
    'channel' => __('Channel'),
    'compass' => collect(\App\Support\WindCompass::POINTS)->mapWithKeys(fn ($point) => [$point => __($point)])->all(),
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
</script>

@push('scripts')
    @vite('resources/js/pages/day-charts.js')

    <script>
        (() => {
            const tbody = document.getElementById('day-readings-body');
            if (!tbody) return;

            const statusText = document.getElementById('day-readings-status-text');
            const spinner = document.getElementById('day-readings-spinner');
            const btnPrev = document.getElementById('day-readings-prev');
            const btnNext = document.getElementById('day-readings-next');
            const pageInput = document.getElementById('day-readings-page-input');
            const pageOf = document.getElementById('day-readings-page-of');
            const perPageSelect = document.getElementById('day-readings-per-page');

            const endpoint = @json(route('history.day.readings', $dateObj->format('Y-m-d')));
            const total = Number(@json($readingsCount ?? 0));
            const initialPage = Number(@json((int) ($readingsPage ?? 1))) || 1;
            const initialPerPage = Number(@json((int) ($readingsPerPage ?? 60))) || 60;

            const locale = window.Meteo?.jsLocale || 'en-US';
            const units = window.Meteo?.activeUnits || 'metric';
            const rainRateUnitSuffix = window.Meteo?.rainRateUnit === '/min' ? '/min' : '/h';
            const tempDecimals = Number(window.Meteo?.temperatureDecimals ?? 1) || 0;
            const windDecimals = Number(window.Meteo?.windDecimals ?? 1) || 0;
            const rainDecimals = Number(window.Meteo?.rainDecimals ?? 1) || 0;
            const pressureDecimals = Number(window.Meteo?.pressureDecimals ?? 1) || 0;

            const formatNumber = (value, decimals = 0) => {
                if (value === null || value === undefined || Number.isNaN(value)) return '--';
                return new Intl.NumberFormat(locale, {
                    minimumFractionDigits: decimals,
                    maximumFractionDigits: decimals,
                }).format(value);
            };

            const convertTemp = (value) => {
                if (value === null || value === undefined) return null;
                return units === 'imperial' ? (value * 9 / 5 + 32) : value;
            };
            const convertRain = (value) => {
                if (value === null || value === undefined) return null;
                return units === 'imperial' ? (value * 0.0393700787) : value;
            };
            const convertRainRate = (value) => {
                const converted = convertRain(value);
                if (converted === null) return null;
                return rainRateUnitSuffix === '/min' ? (converted / 60) : converted;
            };
            const convertWind = (value) => {
                if (value === null || value === undefined) return null;
                if (units === 'imperial' || units === 'uk') return value * 0.6213711922;
                if (units === 'scandinavia') return value / 3.6;
                return value;
            };
            const convertPressure = (value) => {
                if (value === null || value === undefined) return null;
                if (units === 'imperial' || units === 'uk') return value * 0.0295299830714;
                return value;
            };

            const tempUnit = units === 'imperial' ? 'F' : 'C';
            const rainUnit = units === 'imperial' ? 'in' : 'mm';
            const windUnit = units === 'scandinavia' ? 'm/s' : (units === 'imperial' || units === 'uk' ? 'mph' : 'km/h');
            const pressureUnit = units === 'imperial' || units === 'uk' ? 'inHg' : 'hPa';

            const maxPages = (perPage) => Math.max(1, Math.ceil((total || 0) / perPage));

            const setLoading = (isLoading) => {
                if (spinner) spinner.classList.toggle('hidden', !isLoading);
                if (btnPrev) btnPrev.disabled = isLoading;
                if (btnNext) btnNext.disabled = isLoading;
                if (pageInput) pageInput.disabled = isLoading;
                if (perPageSelect) perPageSelect.disabled = isLoading;
                if (statusText) statusText.textContent = isLoading ? @json(__('Loading…')) : '';
            };

            const clearRows = () => { tbody.innerHTML = ''; };

            const compassLabels = @json(collect(\App\Support\WindCompass::POINTS)->mapWithKeys(fn ($point) => [$point => __($point)])->all());

            const compass16 = (deg) => {
                if (deg === null || deg === undefined || Number.isNaN(deg)) return null;
                const directions = ['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSW','SW','WSW','W','WNW','NW','NNW'];
                const i = Math.round((((deg % 360) + 360) % 360) / 22.5) % 16;
                const key = directions[i];
                return key ? (compassLabels[key] || key) : null;
            };

            const renderRows = (rows) => {
                const frag = document.createDocumentFragment();
                for (const row of rows) {
                    const tr = document.createElement('tr');
                    tr.className = 'hover:bg-ui-overlay/5';

                    const tempRaw = row.temperature;
                    const temp = convertTemp(tempRaw);
                    const feels = convertTemp(row.feels_like);
                    const dew = convertTemp(row.dew_point);
                    const wind = convertWind(row.wind_speed);
                    const gust = convertWind(row.wind_gust);
                    const windAvg10m = convertWind(row.wind_speed_avg_10m);
                    const dirDegRaw = row.wind_direction_avg_10m ?? row.wind_direction;
                    const dirDeg = (dirDegRaw === null || dirDegRaw === undefined) ? null : (Number(dirDegRaw) % 360);
                    const dirTxt = dirDeg === null ? null : `${Math.round(dirDeg)}° ${compass16(dirDeg) ?? ''}`.trim();

                    const pressRel = convertPressure(row.pressure_rel);
                    const pressAbs = convertPressure(row.pressure_abs);

                    const rainRate = convertRainRate(row.rain_rate);
                    const rainHourly = convertRain(row.rain_hourly);
                    const rainDaily = convertRain(row.rain_daily);

                    let tempClass = '';
                    if (tempRaw !== null && tempRaw !== undefined) {
                        if (tempRaw > 20) tempClass = 'text-data-amber-500';
                        if (tempRaw < 5) tempClass = 'text-data-cyan-500';
                    }

                    const fmt = (v, d = 0) => (v === null ? '--' : formatNumber(v, d));

                    // Wind cell: speed + gust + direction (and optional 10m avg)
                    const windParts = [];
                    if (wind !== null) windParts.push(`${fmt(wind, windDecimals)} ${windUnit}`);
                    if (gust !== null) windParts.push(`${@json(__('gust'))}: ${fmt(gust, windDecimals)} ${windUnit}`);
                    if (windAvg10m !== null) windParts.push(`10m: ${fmt(windAvg10m, windDecimals)} ${windUnit}`);
                    if (dirTxt) windParts.push(dirTxt);

                    // Pressure cell: rel + abs
                    const pressParts = [];
                    if (pressRel !== null) pressParts.push(`${fmt(pressRel, pressureDecimals)} ${pressureUnit}`);
                    if (pressAbs !== null) pressParts.push(`${@json(__('abs'))}: ${fmt(pressAbs, pressureDecimals)} ${pressureUnit}`);

                    // Rain cell: rate + hourly + daily
                    const rainParts = [];
                    if (rainRate !== null) rainParts.push(`${@json(__('rate'))}: ${fmt(rainRate, rainDecimals)} ${rainUnit}${rainRateUnitSuffix}`);
                    if (rainHourly !== null) rainParts.push(`${@json(__('hour'))}: ${fmt(rainHourly, rainDecimals)} ${rainUnit}`);
                    if (rainDaily !== null) rainParts.push(`${@json(__('day'))}: ${fmt(rainDaily, rainDecimals)} ${rainUnit}`);

                    const uv = row.uv_index === null || row.uv_index === undefined ? null : Number(row.uv_index);
                    const solar = row.solar_radiation === null || row.solar_radiation === undefined ? null : Number(row.solar_radiation);
                    const lux = row.lux === null || row.lux === undefined ? null : Number(row.lux);

                    tr.innerHTML = `
                        <td class="px-4 py-3 font-medium">${row.time ?? '--'}</td>
                        <td class="px-4 py-3 text-right">
                            <span class="${tempClass}">
                                ${temp === null ? '--' : `${formatNumber(temp, tempDecimals)} ${tempUnit}`}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">${feels === null ? '--' : `${formatNumber(feels, tempDecimals)} ${tempUnit}`}</td>
                        <td class="px-4 py-3 text-right">${dew === null ? '--' : `${formatNumber(dew, tempDecimals)} ${tempUnit}`}</td>
                        <td class="px-4 py-3 text-right">${row.humidity === null || row.humidity === undefined ? '--' : `${formatNumber(row.humidity, 0)}%`}</td>
                        <td class="px-4 py-3 text-right">
                            ${windParts.length ? windParts.map((p, idx) => idx === 0 ? `<div class="font-medium text-ui-fg">${p}</div>` : `<div class="text-[11px] text-ui-muted">${p}</div>`).join('') : '--'}
                        </td>
                        <td class="px-4 py-3 text-right">
                            ${pressParts.length ? pressParts.map((p, idx) => idx === 0 ? `<div class="font-medium text-ui-fg">${p}</div>` : `<div class="text-[11px] text-ui-muted">${p}</div>`).join('') : '--'}
                        </td>
                        <td class="px-4 py-3 text-right">
                            ${rainParts.length ? rainParts.map((p) => `<div class="text-[11px] text-ui-body">${p}</div>`).join('') : '<span class="text-ui-subtle">--</span>'}
                        </td>
                        <td class="px-4 py-3 text-right">${uv === null ? '--' : formatNumber(uv, 1)}</td>
                        <td class="px-4 py-3 text-right">
                            ${solar === null && lux === null ? '--' : `
                                <div class="font-medium text-ui-fg">${solar === null ? '--' : `${formatNumber(solar, 0)} W/m²`}</div>
                                <div class="text-[11px] text-ui-muted">${lux === null ? '' : `${formatNumber(lux, 0)} lux`}</div>
                            `}
                        </td>
                    `;
                    frag.appendChild(tr);
                }
                tbody.appendChild(frag);
            };

            const syncUrl = (page, perPage, push = true) => {
                const url = new URL(window.location.href);
                url.searchParams.set('readings_page', String(page));
                url.searchParams.set('readings_per_page', String(perPage));
                if (push) {
                    window.history.pushState({ readings_page: page, readings_per_page: perPage }, '', url);
                } else {
                    window.history.replaceState({ readings_page: page, readings_per_page: perPage }, '', url);
                }
            };

            const readUrlState = () => {
                const params = new URLSearchParams(window.location.search);
                const page = Math.max(1, parseInt(params.get('readings_page') || String(initialPage), 10) || 1);
                const perPage = Math.max(60, Math.min(2000, parseInt(params.get('readings_per_page') || String(initialPerPage), 10) || initialPerPage));
                return { page, perPage };
            };

            const updateControls = (page, perPage) => {
                const pages = maxPages(perPage);
                const clampedPage = Math.max(1, Math.min(pages, page));

                if (pageInput) {
                    pageInput.value = String(clampedPage);
                    pageInput.max = String(pages);
                }
                if (pageOf) {
                    pageOf.textContent = ` / ${formatNumber(pages, 0)}`;
                }
                if (perPageSelect) {
                    perPageSelect.value = String(perPage);
                }
                if (btnPrev) btnPrev.disabled = clampedPage <= 1;
                if (btnNext) btnNext.disabled = clampedPage >= pages;
                if (statusText) {
                    statusText.textContent = total ? `${formatNumber(total, 0)} • ${formatNumber(perPage, 0)} / page` : '';
                }
            };

            const showSkeleton = () => {
                // Recreate a small skeleton quickly while loading
                tbody.innerHTML = '';
                for (let i = 0; i < 10; i++) {
                    const tr = document.createElement('tr');
                    tr.className = 'animate-pulse';
                    tr.innerHTML = `
                        <td class="px-4 py-3"><div class="h-3 w-10 bg-ui-overlay/10 rounded"></div></td>
                        <td class="px-4 py-3 text-right"><div class="h-3 w-14 bg-ui-overlay/10 rounded ml-auto"></div></td>
                        <td class="px-4 py-3 text-right"><div class="h-3 w-14 bg-ui-overlay/10 rounded ml-auto"></div></td>
                        <td class="px-4 py-3 text-right"><div class="h-3 w-14 bg-ui-overlay/10 rounded ml-auto"></div></td>
                        <td class="px-4 py-3 text-right"><div class="h-3 w-10 bg-ui-overlay/10 rounded ml-auto"></div></td>
                        <td class="px-4 py-3 text-right"><div class="h-3 w-28 bg-ui-overlay/10 rounded ml-auto"></div></td>
                        <td class="px-4 py-3 text-right"><div class="h-3 w-20 bg-ui-overlay/10 rounded ml-auto"></div></td>
                        <td class="px-4 py-3 text-right"><div class="h-3 w-24 bg-ui-overlay/10 rounded ml-auto"></div></td>
                        <td class="px-4 py-3 text-right"><div class="h-3 w-10 bg-ui-overlay/10 rounded ml-auto"></div></td>
                        <td class="px-4 py-3 text-right"><div class="h-3 w-20 bg-ui-overlay/10 rounded ml-auto"></div></td>
                    `;
                    tbody.appendChild(tr);
                }
            };

            let inFlight = null;

            const loadPage = async (page, perPage, pushUrl = true) => {
                const pages = maxPages(perPage);
                const clamped = Math.max(1, Math.min(pages, page));

                if (inFlight) {
                    try { inFlight.abort(); } catch (_) {}
                }
                const controller = new AbortController();
                inFlight = controller;

                updateControls(clamped, perPage);
                syncUrl(clamped, perPage, pushUrl);
                setLoading(true);
                showSkeleton();

                try {
                    const url = `${endpoint}?per_page=${encodeURIComponent(perPage)}&page=${encodeURIComponent(clamped)}`;
                    const res = await fetch(url, { headers: { 'Accept': 'application/json' }, signal: controller.signal });
                    if (!res.ok) throw new Error(`HTTP ${res.status}`);
                    const json = await res.json();

                    clearRows();
                    renderRows(json.data || []);
                    updateControls(clamped, perPage);
                    setLoading(false);
                } catch (e) {
                    if (e && e.name === 'AbortError') return;
                    setLoading(false);
                    clearRows();
                    const tr = document.createElement('tr');
                    tr.innerHTML = `<td class="px-4 py-6 text-sm text-ui-muted" colspan="10">${@json(__('Failed to load data.'))}</td>`;
                    tbody.appendChild(tr);
                }
            };

            const init = () => {
                // Ensure URL contains the shareable params on first load (without pushing a new history entry)
                const s = readUrlState();
                syncUrl(s.page, s.perPage, false);
                updateControls(s.page, s.perPage);
                loadPage(s.page, s.perPage, false);

                if (btnPrev) btnPrev.addEventListener('click', () => {
                    const s2 = readUrlState();
                    loadPage(Math.max(1, s2.page - 1), s2.perPage, true);
                });
                if (btnNext) btnNext.addEventListener('click', () => {
                    const s2 = readUrlState();
                    loadPage(s2.page + 1, s2.perPage, true);
                });
                if (pageInput) pageInput.addEventListener('change', () => {
                    const s2 = readUrlState();
                    const requested = Math.max(1, parseInt(pageInput.value || '1', 10) || 1);
                    loadPage(requested, s2.perPage, true);
                });
                if (perPageSelect) perPageSelect.addEventListener('change', () => {
                    const per = Math.max(60, Math.min(2000, parseInt(perPageSelect.value || '60', 10) || 60));
                    loadPage(1, per, true);
                });

                window.addEventListener('popstate', () => {
                    const s2 = readUrlState();
                    loadPage(s2.page, s2.perPage, false);
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
        })();
    </script>
@endpush
@endsection
