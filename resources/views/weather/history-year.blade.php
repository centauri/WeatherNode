@extends('weather.layout')
@section('og_image', route('og.statistics'))
@php
    $activeUnits = $activeUnits ?? 'metric';
    $activeLocale = $activeLocale ?? app()->getLocale();
    $locale = str_replace('-', '_', $activeLocale);
    $cs = $chartSettings ?? [];
@endphp

@section('title', __('History') . ' ' . $year . ' - ' . \App\Models\Setting::stationName())

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold">{{ __('History') }}</h1>
            <p class="text-ui-muted">{{ __('Yearly overview') }} {{ $year }}</p>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
            <select onchange="if(this.value==='month'){window.location='{{ route('history') }}?year='+document.querySelector('[name=year]').value;return false;}"
                    class="bg-weather-card border border-ui-line/10 rounded-lg px-4 py-2 text-ui-fg">
                <option value="month">{{ __('Month') }}</option>
                <option value="year" selected>{{ __('Year') }}</option>
            </select>

            <form method="GET" action="{{ route('history.year') }}" class="flex items-center gap-3">
                <select name="year" class="bg-weather-card border border-ui-line/10 rounded-lg px-4 py-2 text-ui-fg">
                    @foreach($availableYears as $y)
                        <option value="{{ $y }}" {{ (string)$y === (string)$year ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>

                <button type="submit" class="px-5 py-2 bg-ui-accent-strong text-on-accent rounded-lg hover:bg-ui-action transition font-medium">
                    {{ __('View') }}
                </button>
            </form>
        </div>
    </div>

    <!-- Year Title -->
    <div class="bg-gradient-to-br from-weather-card to-weather-card/50 rounded-2xl p-6 border border-ui-line/10">
        <h2 class="text-xl md:text-2xl font-bold">{{ $year }}</h2>
    </div>

    <!-- Yearly Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="bg-weather-card rounded-2xl p-5 text-center border border-ui-line/10">
            <p class="text-xs text-ui-muted mb-2">{{ __('Max temperature') }}</p>
            <p class="text-3xl font-bold text-data-amber-500">{{ isset($yearlyStats['temp_high']) ? $unit->temperature($yearlyStats['temp_high'], $activeUnits) : '--' }}</p>
        </div>
        <div class="bg-weather-card rounded-2xl p-5 text-center border border-ui-line/10">
            <p class="text-xs text-ui-muted mb-2">{{ __('Min temperature') }}</p>
            <p class="text-3xl font-bold text-data-cyan-500">{{ isset($yearlyStats['temp_low']) ? $unit->temperature($yearlyStats['temp_low'], $activeUnits) : '--' }}</p>
        </div>
        <div class="bg-weather-card rounded-2xl p-5 text-center border border-ui-line/10">
            <p class="text-xs text-ui-muted mb-2">{{ __('Average') }}</p>
            <p class="text-3xl font-bold">{{ isset($yearlyStats['temp_avg']) ? $unit->temperature($yearlyStats['temp_avg'], $activeUnits) : '--' }}</p>
        </div>
        <div class="bg-weather-card rounded-2xl p-5 text-center border border-ui-line/10">
            <p class="text-xs text-ui-muted mb-2">{{ __('Total precipitation') }}</p>
            <p class="text-3xl font-bold text-data-indigo-500">{{ isset($yearlyStats['rain_total']) ? $unit->rain($yearlyStats['rain_total'], $activeUnits) : '--' }}</p>
        </div>
        <div class="bg-weather-card rounded-2xl p-5 text-center border border-ui-line/10">
            <p class="text-xs text-ui-muted mb-2">{{ __('Max wind') }}</p>
            <p class="text-3xl font-bold">{{ isset($yearlyStats['wind_max']) ? $unit->wind($yearlyStats['wind_max'], $activeUnits) : '--' }}</p>
        </div>
        <div class="bg-weather-card rounded-2xl p-5 text-center border border-ui-line/10">
            <p class="text-xs text-ui-muted mb-2">{{ __('Rain days') }}</p>
            <p class="text-3xl font-bold">{{ $yearlyStats['days_with_rain'] ?? '--' }}</p>
        </div>
    </div>

    <!-- Yearly Charts -->
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        @if(in_array('temperature', $cs))
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
            <h3 class="font-semibold mb-4">{{ __('Temperature') }}</h3>
            <div id="history-chart-temps" class="h-72"></div>
        </div>
        @endif
        @if(in_array('wind', $cs))
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
            <h3 class="font-semibold mb-4">{{ __('Wind') }}</h3>
            <div id="history-chart-wind" class="h-72"></div>
        </div>
        @endif
        @if(in_array('humidity', $cs))
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
            <h3 class="font-semibold mb-4">{{ __('Humidity & Dew Point') }}</h3>
            <div id="history-chart-humidity" class="h-72"></div>
        </div>
        @endif
        @if(in_array('solar_uv', $cs))
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
            <h3 class="font-semibold mb-4">{{ __('UV & Solar radiation') }}</h3>
            <div id="history-chart-solar" class="h-72"></div>
        </div>
        @endif
        @if(in_array('precipitation', $cs))
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
            <h3 class="font-semibold mb-4">{{ __('Precipitation & Pressure') }}</h3>
            <div id="history-chart-precip" class="h-72"></div>
        </div>
        @endif
    </div>

    <!-- Monthly Data Table -->
    <div class="bg-weather-card rounded-2xl border border-ui-line/10 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-ui-overlay/5">
                    <tr>
                        <th class="px-4 py-4 text-left font-medium text-ui-muted">{{ __('Month') }}</th>
                        <th class="px-4 py-4 text-right font-medium text-ui-muted">{{ __('Max') }}</th>
                        <th class="px-4 py-4 text-right font-medium text-ui-muted">{{ __('Min') }}</th>
                        <th class="px-4 py-4 text-right font-medium text-ui-muted">{{ __('Avg') }}</th>
                        <th class="px-4 py-4 text-right font-medium text-ui-muted">{{ __('Precipitation') }}</th>
                        <th class="px-4 py-4 text-right font-medium text-ui-muted">{{ __('Max wind') }}</th>
                        <th class="px-4 py-4 text-right font-medium text-ui-muted">{{ __('Rain days') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ui-line/5">
                    @for($m = 1; $m <= 12; $m++)
                        @php $ms = $monthlySummaries[$m] ?? null; @endphp
                        <tr class="hover:bg-ui-overlay/5 transition cursor-pointer"
                            onclick="window.location='{{ route('history', ['month' => $m, 'year' => $year]) }}'">
                            <td class="px-4 py-4">
                                <div class="flex items-center gap-3">
                                    <span class="w-8 h-8 rounded-lg bg-ui-overlay/10 flex items-center justify-center font-bold text-xs">
                                        {{ \Carbon\Carbon::create(null, $m)->locale($locale)->translatedFormat('M') }}
                                    </span>
                                    <span class="text-ui-muted">{{ \Carbon\Carbon::create(null, $m)->locale($locale)->translatedFormat('F') }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-4 text-right">
                                <span class="text-data-amber-500 font-bold">
                                    {{ $ms && $ms['temp_high'] !== null ? $unit->temperature($ms['temp_high'], $activeUnits) : '--' }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-right">
                                <span class="text-data-cyan-500 font-bold">
                                    {{ $ms && $ms['temp_low'] !== null ? $unit->temperature($ms['temp_low'], $activeUnits) : '--' }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-right">
                                {{ $ms && $ms['temp_avg'] !== null ? $unit->temperature($ms['temp_avg'], $activeUnits) : '--' }}
                            </td>
                            <td class="px-4 py-4 text-right">
                                @if($ms && $ms['rain_total'] > 0)
                                    <span class="text-data-indigo-500 font-medium">{{ $unit->rain($ms['rain_total'], $activeUnits) }}</span>
                                @else
                                    <span class="text-ui-subtle">{{ $unit->rain(0, $activeUnits) }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-right">
                                {{ $ms && $ms['wind_max'] !== null ? $unit->wind($ms['wind_max'], $activeUnits) : '--' }}
                            </td>
                            <td class="px-4 py-4 text-right">
                                {{ $ms ? $ms['days_with_rain'] : '--' }}
                            </td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>
    </div>
</div>

<script type="application/json" id="history-chart-data">
{!! json_encode($historyChart ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
</script>
<script type="application/json" id="history-chart-strings">
{!! json_encode([
    'no_data' => __('No data available'),
    'temp_high' => __('Max temperature'),
    'temp_avg' => __('Average'),
    'temp_low' => __('Min temperature'),
    'rain_total' => __('Total precipitation'),
    'rain_rate_max' => __('Rain rate'),
    'wind_max' => __('Max wind'),
    'wind_avg' => __('Average wind'),
    'wind_dir' => __('Wind direction'),
    'pressure_avg' => __('Air Pressure'),
    'humidity_avg' => __('Humidity'),
    'dew_point_avg' => __('Dew Point'),
    'uv_max' => __('UV Index'),
    'solar_max' => __('Solar radiation'),
    'temperature' => __('Temperature'),
    'precipitation' => __('Precipitation'),
    'wind' => __('Wind'),
    'pressure' => __('Pressure'),
    'humidity' => __('Humidity'),
    'uv' => __('UV Index'),
    'solar' => __('Solar'),
    'compass' => collect(\App\Support\WindCompass::POINTS)->mapWithKeys(fn ($point) => [$point => __($point)])->all(),
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
</script>

@push('scripts')
    @vite('resources/js/pages/history-charts.js')
@endpush
@endsection
