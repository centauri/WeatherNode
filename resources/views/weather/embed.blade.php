<!DOCTYPE html>
<html @if(isset($publicAppearance['custom'])) data-custom-theme @endif data-public-theme="{{ $publicAppearance['palette'] ?? 'weathernode' }}" data-default-color-mode="{{ $publicAppearance['mode'] ?? 'dark' }}" data-color-mode="{{ ($publicAppearance['mode'] ?? 'dark') === 'light' ? 'light' : 'dark' }}" lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ ($publicAppearance['mode'] ?? 'dark') === 'light' ? '' : 'dark' }}">
<head>
    <x-public-theme-head />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $stationName }}</title>
    @vite(['resources/css/app.css'])
    <style>
        html, body {
            margin: 0;
            padding: 0;
            background: transparent;
            font-family: ui-sans-serif, system-ui, -apple-system, sans-serif;
            font-size: 14px;
            color: rgb(var(--wn-fg));
        }
        body { background: rgb(var(--wn-card)); }
        /* Ensure iframe content is not clipped */
        .widget-root { min-height: 100vh; display: flex; align-items: flex-start; justify-content: stretch; }
    </style>
</head>
<body>
<div class="widget-root">
<div class="bg-gradient-to-br from-ui-raised to-ui-deep border border-ui-line/10 rounded-2xl p-4 w-full shadow-2xl">

    {{-- Header: station name + link --}}
    <div class="flex items-center justify-between mb-3 gap-2">
        <a href="{{ $siteUrl }}" target="_blank" rel="noopener noreferrer"
           class="flex items-center gap-1.5 text-xs text-ui-muted hover:text-ui-fg transition-colors truncate min-w-0">
            <svg class="w-3 h-3 flex-shrink-0 text-data-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <span class="truncate">{{ $stationLocation }}</span>
        </a>
        <span class="text-xs text-ui-faint flex-shrink-0" id="w-update-time" title="{{ __('Last update') }}">
            {{ $lastUpdate ?? '' }}
        </span>
    </div>

    @if($reading)

    {{-- Main temperature display --}}
    <div class="flex items-end justify-between mb-4">
        <div>
            <div class="flex items-end gap-1 leading-none">
                <span class="text-5xl font-bold text-ui-fg" id="w-temp">
                    {{ $temp !== null ? number_format($temp, 1) : '--' }}
                </span>
                <span class="text-2xl font-light text-ui-secondary mb-1">{{ $tempUnit }}</span>
            </div>
            @if($feelsLike !== null)
            <div class="text-xs text-ui-subtle mt-1">
                {{ __('Feels like') }} <span id="w-feels">{{ number_format($feelsLike, 1) }}</span>{{ $tempUnit }}
            </div>
            @endif
            @if($beaufort)
            <div class="text-xs text-ui-muted mt-0.5" id="w-condition">{{ __($beaufort) }}</div>
            @endif
        </div>
        {{-- Rain indicator --}}
        @if(($rainDaily ?? 0) > 0)
        <div class="text-right">
            <div class="text-xs text-data-blue-400/70 mb-0.5">{{ __('Rain today') }}</div>
            <div class="text-lg font-semibold text-data-blue-300" id="w-rain">{{ number_format($rainDaily, 1) }} mm</div>
        </div>
        @endif
    </div>

    {{-- Stats grid --}}
    <div class="grid grid-cols-3 gap-2">
        {{-- Humidity --}}
        <div class="bg-ui-overlay/5 rounded-xl p-2.5 text-center">
            <div class="text-lg mb-0.5">💧</div>
            <div class="text-sm font-semibold text-ui-fg" id="w-humid">
                {{ $humidity !== null ? $humidity . '%' : '--' }}
            </div>
            <div class="text-xs text-ui-subtle">{{ __('Humidity') }}</div>
        </div>
        {{-- Wind --}}
        <div class="bg-ui-overlay/5 rounded-xl p-2.5 text-center">
            <div class="text-lg mb-0.5">💨</div>
            <div class="text-sm font-semibold text-ui-fg" id="w-wind">
                @if($windSpeed !== null)
                    {{ $windDir ? $windDir . ' ' : '' }}{{ number_format($windSpeed, 0) }}&nbsp;{{ $windUnit }}
                @else
                    --
                @endif
            </div>
            <div class="text-xs text-ui-subtle">{{ __('Wind') }}</div>
        </div>
        {{-- Pressure --}}
        <div class="bg-ui-overlay/5 rounded-xl p-2.5 text-center">
            <div class="text-lg mb-0.5">🌡️</div>
            <div class="text-sm font-semibold text-ui-fg" id="w-pressure">
                {{ $pressure !== null ? number_format($pressure, 0) : '--' }}&nbsp;hPa
            </div>
            <div class="text-xs text-ui-subtle">{{ __('Pressure') }}</div>
        </div>
    </div>

    @else
    {{-- No data state --}}
    <div class="text-center py-6 text-ui-subtle">
        <div class="text-3xl mb-2">📡</div>
        <div class="text-sm">{{ __('No data available') }}</div>
    </div>
    @endif

    {{-- Footer link --}}
    <div class="mt-3 pt-2 border-t border-ui-line/5 flex items-center justify-between">
        <a href="{{ $siteUrl }}" target="_blank" rel="noopener noreferrer"
           class="text-xs text-ui-faint hover:text-ui-muted transition-colors truncate">
            {{ $stationName }}
        </a>
        <a href="{{ $siteUrl }}" target="_blank" rel="noopener noreferrer"
           class="text-xs text-data-blue-500 hover:text-data-blue-400 transition-colors flex-shrink-0 ml-2">
            {{ __('Live data') }} →
        </a>
    </div>

</div>
</div>

<script>
(function () {
    var units = '{{ $activeUnits }}';
    var tempUnit = '{{ $tempUnit }}';
    var windUnit = '{{ $windUnit }}';

    function toDisplay(val, key) {
        if (val === null || val === undefined) return '--';
        if (key === 'temperature' || key === 'feels_like') {
            if (units === 'imperial') val = val * 9 / 5 + 32;
            return val.toFixed(1);
        }
        if (key === 'wind_speed') {
            if (units === 'imperial') val = val * 0.621371;
            else if (units === 'scandinavia') val = val / 3.6;
            return Math.round(val).toString();
        }
        return val;
    }

    function update() {
        fetch('/api/weather/current', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.success || !d.data) return;
                var data = d.data;

                var el;
                el = document.getElementById('w-temp');
                if (el && data.temperature != null) el.textContent = toDisplay(data.temperature, 'temperature');

                el = document.getElementById('w-feels');
                if (el && data.feels_like != null) el.textContent = toDisplay(data.feels_like, 'feels_like');

                el = document.getElementById('w-humid');
                if (el && data.humidity != null) el.textContent = data.humidity + '%';

                el = document.getElementById('w-wind');
                if (el && data.wind_speed != null) {
                    var dir = data.wind_direction_compass ? data.wind_direction_compass + ' ' : '';
                    el.textContent = dir + toDisplay(data.wind_speed, 'wind_speed') + '\u00a0' + windUnit;
                }

                el = document.getElementById('w-pressure');
                if (el && data.pressure_rel != null) el.textContent = Math.round(data.pressure_rel) + '\u00a0hPa';

                el = document.getElementById('w-rain');
                if (el && data.rain_daily != null) el.textContent = data.rain_daily.toFixed(1) + ' mm';

                el = document.getElementById('w-condition');
                if (el && data.beaufort_description) el.textContent = data.beaufort_description;

                el = document.getElementById('w-update-time');
                if (el && data.recorded_at) {
                    var now = new Date(), t = new Date(data.recorded_at);
                    var mins = Math.round((now - t) / 60000);
                    el.textContent = mins < 2 ? 'just now' : mins + ' min ago';
                }
            })
            .catch(function () {});
    }

    // Poll every 2 minutes
    setInterval(update, 120000);
}());
</script>
</body>
</html>
