@extends('weather.layout')
@section('og_image', route('og.home'))
@php
    $activeUnits = $activeUnits ?? 'metric';
    $activeLocale = $activeLocale ?? app()->getLocale();
    $locale = str_replace('-', '_', $activeLocale);
    $cs = $chartSettings ?? [];
@endphp

@section('title', __('History') . ' - ' . \App\Models\Setting::stationName())
@section('meta_description', __('History page meta description', ['location' => \App\Models\Setting::stationLocation() ?: \App\Models\Setting::stationName()]))

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold">{{ __('History') }}</h1>
            <p class="text-ui-muted">{{ __('History page intro', ['location' => \App\Models\Setting::stationLocation() ?: \App\Models\Setting::stationName(), 'year' => $year]) }}</p>
        </div>

        <form method="GET" action="{{ route('history') }}" class="flex items-center gap-3 flex-wrap">
            <select onchange="if(this.value==='year'){window.location='{{ route('history.year') }}?year='+document.querySelector('[name=year]').value;return false;}"
                    class="bg-weather-card border border-ui-line/10 rounded-lg px-4 py-2 text-ui-fg">
                <option value="month" selected>{{ __('Month') }}</option>
                <option value="year">{{ __('Year') }}</option>
            </select>

            <select name="month" class="bg-weather-card border border-ui-line/10 rounded-lg px-4 py-2 text-ui-fg">
                @foreach(range(1, 12) as $m)
                    <option value="{{ $m }}" {{ (int)$m === (int)$month ? 'selected' : '' }}>
                        {{ \Carbon\Carbon::create(null, $m)->locale($locale)->translatedFormat('F') }}
                    </option>
                @endforeach
            </select>

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

    <!-- Month Title -->
    <div class="bg-gradient-to-br from-weather-card to-weather-card/50 rounded-2xl p-6 border border-ui-line/10">
        <h2 class="text-xl md:text-2xl font-bold">
            {{ \Carbon\Carbon::create($year, $month)->locale($locale)->translatedFormat('F Y') }}
        </h2>
    </div>

    <!-- Monthly Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="bg-weather-card rounded-2xl p-5 text-center border border-ui-line/10">
            <p class="text-xs text-ui-muted mb-2">{{ __('Max temperature') }}</p>
            <p class="text-3xl font-bold text-data-amber-500">{{ isset($monthlyStats['temp_high']) ? $unit->temperature($monthlyStats['temp_high'], $activeUnits) : '--' }}</p>
        </div>
        <div class="bg-weather-card rounded-2xl p-5 text-center border border-ui-line/10">
            <p class="text-xs text-ui-muted mb-2">{{ __('Min temperature') }}</p>
            <p class="text-3xl font-bold text-data-cyan-500">{{ isset($monthlyStats['temp_low']) ? $unit->temperature($monthlyStats['temp_low'], $activeUnits) : '--' }}</p>
        </div>
        <div class="bg-weather-card rounded-2xl p-5 text-center border border-ui-line/10">
            <p class="text-xs text-ui-muted mb-2">{{ __('Average') }}</p>
            <p class="text-3xl font-bold">{{ isset($monthlyStats['temp_avg']) ? $unit->temperature($monthlyStats['temp_avg'], $activeUnits) : '--' }}</p>
        </div>
        <div class="bg-weather-card rounded-2xl p-5 text-center border border-ui-line/10">
            <p class="text-xs text-ui-muted mb-2">{{ __('Total precipitation') }}</p>
            <p class="text-3xl font-bold text-data-indigo-500">{{ isset($monthlyStats['rain_total']) ? $unit->rain($monthlyStats['rain_total'], $activeUnits) : '--' }}</p>
        </div>
        <div class="bg-weather-card rounded-2xl p-5 text-center border border-ui-line/10">
            <p class="text-xs text-ui-muted mb-2">{{ __('Max wind') }}</p>
            <p class="text-3xl font-bold">{{ isset($monthlyStats['wind_max']) ? $unit->wind($monthlyStats['wind_max'], $activeUnits) : '--' }}</p>
        </div>
        <div class="bg-weather-card rounded-2xl p-5 text-center border border-ui-line/10">
            <p class="text-xs text-ui-muted mb-2">{{ __('Rain days') }}</p>
            <p class="text-3xl font-bold">{{ $monthlyStats['days_with_rain'] ?? '--' }}</p>
        </div>
    </div>

    <!-- Monthly Charts -->
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

    <!-- Daily Data Table -->
    <div class="bg-weather-card rounded-2xl border border-ui-line/10 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-ui-overlay/5">
                    <tr>
                        <th class="px-4 py-4 text-left font-medium text-ui-muted">{{ __('Date') }}</th>
                        <th class="px-4 py-4 text-right font-medium text-ui-muted">{{ __('Max') }}</th>
                        <th class="px-4 py-4 text-right font-medium text-ui-muted">{{ __('Min') }}</th>
                        <th class="px-4 py-4 text-right font-medium text-ui-muted">{{ __('Avg') }}</th>
                        <th class="px-4 py-4 text-right font-medium text-ui-muted">{{ __('Precipitation') }}</th>
                        <th class="px-4 py-4 text-right font-medium text-ui-muted">{{ __('Max wind') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ui-line/5">
                    @forelse($summaries as $day)
                        <tr class="hover:bg-ui-overlay/5 transition cursor-pointer"
                            onclick="window.location='{{ route('history.day', $day->date->format('Y-m-d')) }}'">
                            <td class="px-4 py-4">
                                <div class="flex items-center gap-3">
                                    <span class="w-8 h-8 rounded-lg bg-ui-overlay/10 flex items-center justify-center font-bold">
                                        {{ $day->date->format('d') }}
                                    </span>
                                    <span class="text-ui-muted">{{ $day->date->locale($locale)->translatedFormat('l') }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-4 text-right">
                                <span class="text-data-amber-500 font-bold">
                                    {{ $day->temp_high !== null ? $unit->temperature($day->temp_high, $activeUnits) : '--' }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-right">
                                <span class="text-data-cyan-500 font-bold">
                                    {{ $day->temp_low !== null ? $unit->temperature($day->temp_low, $activeUnits) : '--' }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-right">
                                {{ $day->temp_avg !== null ? $unit->temperature($day->temp_avg, $activeUnits) : '--' }}
                            </td>
                            <td class="px-4 py-4 text-right">
                                @if($day->rain_total > 0)
                                    <span class="text-data-indigo-500 font-medium">{{ $unit->rain($day->rain_total, $activeUnits) }}</span>
                                @else
                                    <span class="text-ui-subtle">{{ $unit->rain(0, $activeUnits) }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-right">
                                {{ $day->wind_max !== null ? $unit->wind($day->wind_max, $activeUnits) : '--' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-ui-muted">
                                <div class="text-4xl mb-4">📭</div>
                                <p>{{ __('No data available for this month') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Calendar View (Mini) -->
    <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
        <h3 class="font-semibold mb-4">{{ __('Calendar overview') }}</h3>
        <div class="grid grid-cols-7 gap-1 text-center text-xs">
            @foreach(['Ma', 'Di', 'Wo', 'Do', 'Vr', 'Za', 'Zo'] as $dayName)
                <div class="py-2 text-ui-muted font-medium">{{ $dayName }}</div>
            @endforeach

            @php
                $firstDay = \Carbon\Carbon::create($year, $month, 1);
                $daysInMonth = $firstDay->daysInMonth;
                $startDayOfWeek = $firstDay->dayOfWeekIso - 1; // 0 = Monday
                $summaryByDate = $summaries->keyBy(fn($s) => $s->date->format('j'));
            @endphp

            {{-- Empty cells before first day --}}
            @for($i = 0; $i < $startDayOfWeek; $i++)
                <div class="py-2"></div>
            @endfor

            {{-- Days of month --}}
            @for($day = 1; $day <= $daysInMonth; $day++)
                @php
                    $dayData = $summaryByDate->get($day);
                    $hasData = $dayData !== null;
                    $hadRain = $hasData && $dayData->rain_total > 0;
                @endphp
                <a href="{{ route('history.day', \Carbon\Carbon::create($year, $month, $day)->format('Y-m-d')) }}"
                   class="py-2 rounded hover:bg-ui-overlay/10 transition {{ $hasData ? 'bg-ui-overlay/5' : '' }} {{ $hadRain ? 'text-data-blue-400' : '' }}">
                    {{ $day }}
                </a>
            @endfor
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
