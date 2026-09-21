@extends('weather.layout')

@section('title', __('Weather statistics, phenology and records') . ' - ' . \App\Models\Setting::stationName())
@section('meta_description', __('Statistics page meta description', ['location' => \App\Models\Setting::stationLocation() ?: \App\Models\Setting::stationName()]))
@section('og_image', route('og.statistics', ['year' => request('year', date('Y'))]))

@section('content')
@php
    $activeUnits = $activeUnits ?? 'metric';
    $activeLocale = $activeLocale ?? app()->getLocale();
    $locale = str_replace('-', '_', $activeLocale);
    $stationLocation = \App\Models\Setting::stationLocation() ?: \App\Models\Setting::stationName();
@endphp
<div class="space-y-6" x-data="statisticsPage()">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold">{{ __('Weather statistics, phenology and records') }}</h1>
            <p class="text-ui-muted max-w-3xl">{{ __('Monthly averages, climate normals, phenology, growing degree days and weather records for :location.', ['location' => $stationLocation]) }}</p>
        </div>
        <form method="GET" action="{{ route('statistics') }}" class="flex gap-2">
            <select name="year" onchange="this.form.submit()" class="bg-weather-card border border-ui-line/10 rounded-lg px-3 py-2 text-sm">
                @foreach($availableYears as $y)
                    <option value="{{ $y }}" {{ (string)$y === (string)$year ? 'selected' : '' }}>{{ $y }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <!-- Year Overview -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
            <div class="text-xs text-ui-muted mb-2">{{ __('Highest temperature') }}</div>
            <div class="text-3xl font-bold text-data-amber-500">{{ $yearlyStats ? $unit->temperature($yearlyStats['temp_high'], $activeUnits) : '--' }}</div>
            <div class="text-xs text-ui-muted mt-1">{{ $yearlyStats['temp_high_date'] ?? '--' }}</div>
        </div>
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
            <div class="text-xs text-ui-muted mb-2">{{ __('Lowest temperature') }}</div>
            <div class="text-3xl font-bold text-data-cyan-500">{{ $yearlyStats ? $unit->temperature($yearlyStats['temp_low'], $activeUnits) : '--' }}</div>
            <div class="text-xs text-ui-muted mt-1">{{ $yearlyStats['temp_low_date'] ?? '--' }}</div>
        </div>
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
            <div class="text-xs text-ui-muted mb-2">{{ __('Total precipitation') }}</div>
            <div class="text-3xl font-bold text-data-indigo-500">{{ $yearlyStats ? $unit->rain($yearlyStats['rain_total'], $activeUnits) : '--' }}</div>
            <div class="text-xs text-ui-muted mt-1">{{ $yearlyStats ? $yearlyStats['rain_days'] . ' ' . __('Rain days') : '--' }}</div>
        </div>
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
            <div class="text-xs text-ui-muted mb-2">{{ __('Strongest wind gust') }}</div>
            <div class="text-3xl font-bold">{{ $yearlyStats && $yearlyStats['wind_max'] ? $unit->wind($yearlyStats['wind_max'], $activeUnits) : '--' }}</div>
            <div class="text-xs text-ui-muted mt-1">{{ $yearlyStats['wind_max_date'] ?? '--' }}</div>
        </div>
    </div>

    <!-- Monthly Averages -->
    <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
        <h2 class="font-semibold mb-4">{{ __('Monthly averages') }}</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-ui-muted border-b border-ui-line/10">
                        <th class="text-left py-3 px-2">{{ __('Month') }}</th>
                        <th class="text-right py-3 px-2">{{ __('Avg temp') }}</th>
                        <th class="text-right py-3 px-2">{{ __('Max temp') }}</th>
                        <th class="text-right py-3 px-2">{{ __('Min temp') }}</th>
                        <th class="text-right py-3 px-2">{{ __('Precipitation') }}</th>
                        <th class="text-right py-3 px-2">{{ __('Rain days') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @for($m = 1; $m <= 12; $m++)
                        <tr class="border-b border-ui-line/5 hover:bg-ui-overlay/5">
                            <td class="py-3 px-2 font-medium">{{ \Carbon\Carbon::create(null, $m)->locale($locale)->translatedFormat('M') }}</td>
                            @if(isset($monthlyStats[$m]) && $monthlyStats[$m])
                                <td class="text-right py-3 px-2">{{ $unit->temperature($monthlyStats[$m]['temp_avg'], $activeUnits) }}</td>
                                <td class="text-right py-3 px-2 text-data-amber-500">{{ $unit->temperature($monthlyStats[$m]['temp_high'], $activeUnits) }}</td>
                                <td class="text-right py-3 px-2 text-data-cyan-500">{{ $unit->temperature($monthlyStats[$m]['temp_low'], $activeUnits) }}</td>
                                <td class="text-right py-3 px-2 text-data-indigo-500">{{ $unit->rain($monthlyStats[$m]['rain_total'], $activeUnits) }}</td>
                                <td class="text-right py-3 px-2">{{ $monthlyStats[$m]['rain_days'] }}</td>
                            @else
                                <td class="text-right py-3 px-2 text-ui-subtle">--</td>
                                <td class="text-right py-3 px-2 text-ui-subtle">--</td>
                                <td class="text-right py-3 px-2 text-ui-subtle">--</td>
                                <td class="text-right py-3 px-2 text-ui-subtle">--</td>
                                <td class="text-right py-3 px-2 text-ui-subtle">--</td>
                            @endif
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>
    </div>

    <!-- Season Tracker (Phenology) -->
    @if(($phenology['has_data'] ?? false))
    @php
        $ph = $phenology;
        $dc = $ph['day_counts'];

        $dayTypes = [
            ['key' => 'spring_days',   'label' => __('Spring days'),        'sub' => __('Days with T_max ≥ 15°C'), 'color' => 'text-data-green-400',  'icon' => '🌸'],
            ['key' => 'summer_days',   'label' => __('Summer days'),        'sub' => __('Days with T_max ≥ 25°C'), 'color' => 'text-data-amber-400',  'icon' => '☀️'],
            ['key' => 'tropical_days', 'label' => __('Tropical days'),      'sub' => __('Days with T_max ≥ 30°C'), 'color' => 'text-data-red-400',    'icon' => '🔥'],
            ['key' => 'frost_days',    'label' => __('Frost days'),         'sub' => __('Days with T_min < 0°C'),  'color' => 'text-data-blue-400',   'icon' => '❄️'],
            ['key' => 'ice_days',      'label' => __('Ice days'),           'sub' => __('Days with T_max < 0°C'),  'color' => 'text-data-cyan-400',   'icon' => '🧊'],
            ['key' => 'precip_days',   'label' => __('Precipitation days'), 'sub' => __('Days with rain ≥ 0.1 mm'),'color' => 'text-data-indigo-500','icon' => '🌧️'],
        ];

        $milestoneRows = [
            ['key' => 'first_spring',       'label' => __('First spring day'),    'icon' => '🌸'],
            ['key' => 'first_summer',       'label' => __('First summer day'),    'icon' => '☀️'],
            ['key' => 'first_tropical',     'label' => __('First tropical day'),  'icon' => '🌡️'],
            ['key' => 'last_spring_frost',  'label' => __('Last spring frost'),   'icon' => '❄️'],
            ['key' => 'first_autumn_frost', 'label' => __('First autumn frost'),  'icon' => '🍂'],
            ['key' => 'first_ice',          'label' => __('First ice day'),       'icon' => '🧊'],
        ];
    @endphp
    <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
        <h2 class="font-semibold mb-4">{{ __('Season Tracker') }} <span class="text-sm text-ui-muted font-normal">{{ $year }}</span></h2>

        {{-- Day-type count grid --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-6">
            @foreach($dayTypes as $dt)
            <div class="bg-ui-overlay/5 rounded-xl p-4">
                <div class="text-xs text-ui-subtle mb-1">{{ $dt['icon'] }} {{ $dt['label'] }}</div>
                <div class="text-3xl font-bold {{ $dt['color'] }}">{{ $dc[$dt['key']] }}</div>
                <div class="text-xs text-ui-faint mt-1">{{ $dt['sub'] }}</div>
            </div>
            @endforeach
        </div>

        {{-- Seasonal milestones table --}}
        <h3 class="text-sm font-medium text-ui-muted mb-3">{{ __('Seasonal milestones') }}</h3>
        <div class="overflow-x-auto mb-6">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-ui-muted border-b border-ui-line/10">
                        <th class="text-left py-2 px-2">{{ __('Milestone') }}</th>
                        <th class="text-right py-2 px-2">{{ $year }}</th>
                        <th class="text-right py-2 px-2 hidden sm:table-cell">{{ __('Historical average') }}</th>
                        <th class="text-right py-2 px-2">{{ __('Difference') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($milestoneRows as $row)
                    @php
                        $ms = $ph['milestones'][$row['key']] ?? null;
                    @endphp
                    <tr class="border-b border-ui-line/5 hover:bg-ui-overlay/5">
                        <td class="py-2 px-2 font-medium">{{ $row['icon'] }} {{ $row['label'] }}</td>
                        <td class="text-right py-2 px-2 tabular-nums">
                            {{ $ms ? $ms['formatted'] : '—' }}
                        </td>
                        <td class="text-right py-2 px-2 text-ui-muted tabular-nums hidden sm:table-cell">
                            @if($ms && $ms['avg_doy'] !== null)
                                @php
                                    $avgDate = \Carbon\Carbon::create((int)$year, 1, 1)->addDays($ms['avg_doy'] - 1);
                                @endphp
                                {{ $avgDate->format('d M') }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-right py-2 px-2 tabular-nums">
                            @if($ms && $ms['diff_days'] !== null)
                                @php
                                    $d = $ms['diff_days'];
                                    if ($d < 0) {
                                        $cls  = 'text-data-blue-400';
                                        $text = abs($d) . ' ' . __('days') . ' ' . __('earlier');
                                    } elseif ($d > 0) {
                                        $cls  = 'text-data-amber-400';
                                        $text = $d . ' ' . __('days') . ' ' . __('later');
                                    } else {
                                        $cls  = 'text-ui-muted';
                                        $text = __('on average');
                                    }
                                @endphp
                                <span class="{{ $cls }}">{{ $text }}</span>
                            @else
                                <span class="text-ui-faint">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- GDD accumulation chart --}}
        @if(!empty($ph['gdd']['dates']))
        @php
            $gddBestPeriod = $ph['gdd']['best_period'] ?? null;
            $gddWindowDays = $ph['gdd']['peak_window_days'] ?? 14;
        @endphp
        <h3 class="text-sm font-medium text-ui-muted mb-1 flex items-center gap-2 flex-wrap">
            {{ __('Growing Degree Days (base 10 °C)') }}
            <span class="text-ui-faint font-normal">— {{ __('Total GDD') }}: <span class="text-data-green-400 font-medium">{{ $ph['gdd']['total'] }}</span></span>
            <button @click="gddModalOpen = true"
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-ui-overlay/5 text-ui-muted hover:bg-ui-overlay/10 hover:text-ui-body border border-ui-line/10 transition-colors"
                    aria-label="{{ __('Explain Growing Degree Days') }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 16v-4m0-4h.01"/></svg>
                {{ __('Explain') }}
            </button>
        </h3>
        <div id="gdd-chart" class="w-full"></div>

        @if($gddBestPeriod)
        @php
            $gddBestStart = \Carbon\Carbon::parse($gddBestPeriod['start_date'])->locale($locale);
            $gddBestEnd = \Carbon\Carbon::parse($gddBestPeriod['end_date'])->locale($locale);
        @endphp
        <div class="mt-5 grid gap-4 xl:grid-cols-[minmax(0,1fr)_18rem] xl:items-start">
            <div>
                <h4 class="text-sm font-medium text-ui-muted mb-1">{{ __('Peak GDD period') }}</h4>
                <p class="text-xs text-ui-subtle mb-3">{{ __('Shows daily GDD bars and a :days-day rolling total so warm stretches stand out.', ['days' => $gddWindowDays]) }}</p>
                <div id="gdd-period-chart" class="w-full"></div>
            </div>
            <div class="rounded-2xl border border-amber-400/20 bg-amber-400/10 p-4">
                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-data-amber-200/80">{{ __('Best :days-day period', ['days' => $gddWindowDays]) }}</div>
                <div class="mt-3 text-lg font-semibold text-ui-fg">
                    {{ $gddBestStart->translatedFormat('j M') }}
                    @if($gddBestPeriod['start_date'] !== $gddBestPeriod['end_date'])
                        – {{ $gddBestEnd->translatedFormat('j M') }}
                    @endif
                </div>
                <div class="mt-3 text-3xl font-bold text-data-amber-300">{{ number_format($gddBestPeriod['total'], 1) }} GDD</div>
                <div class="mt-1 text-sm text-data-amber-100/80">{{ __('Average per day') }}: {{ number_format($gddBestPeriod['average_per_day'], 1) }} GDD</div>
            </div>
        </div>
        @endif

        {{-- GDD explanation modal --}}
        <div x-show="gddModalOpen"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             @keydown.escape.window="gddModalOpen = false">
            {{-- backdrop --}}
            <div data-theme-surface="dark" class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="gddModalOpen = false"></div>
            {{-- panel --}}
            <div class="relative z-10 bg-ui-deep border border-ui-line/10 rounded-2xl shadow-2xl max-w-md w-full p-6 text-sm text-ui-secondary space-y-4">
                <div class="flex items-start justify-between gap-4">
                    <h4 class="text-base font-semibold text-ui-fg">{{ __('What are Growing Degree Days?') }}</h4>
                    <button @click="gddModalOpen = false" class="text-ui-subtle hover:text-ui-body transition-colors flex-shrink-0" aria-label="{{ __('Close') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <p>{{ __('Growing Degree Days (GDD) measure accumulated heat above a base temperature — not calendar days.') }}</p>
                <div class="bg-ui-overlay/5 rounded-xl px-4 py-3 font-mono text-center text-data-green-400">
                    GDD = max(0, (T<sub class="text-xs">max</sub> + T<sub class="text-xs">min</sub>) / 2 − 10)
                </div>
                <p>{{ __('Each day contributes a value equal to how many degrees the average temperature exceeds 10 °C. A day with a high of 26 °C and a low of 12 °C contributes (26 + 12) / 2 − 10 = 9 GDD.') }}</p>
                <p>{{ __('The total GDD for a year is the sum of all daily values. A warm summer can easily produce 1 000 – 1 500 GDD, which is why the total is much larger than 365.') }}</p>
                <p class="text-ui-subtle text-xs">{{ __('Base temperature: 10 °C (growing threshold for most plants in temperate climates).') }}</p>
                <button @click="gddModalOpen = false"
                        class="w-full mt-2 py-2 rounded-xl bg-ui-accent-strong text-on-accent hover:bg-ui-action text-ui-fg text-sm font-medium transition-colors">
                    {{ __('Close') }}
                </button>
            </div>
        </div>
        @endif
    </div>
    @endif

    <!-- All-Time Records -->
    @if($records)
    <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
        <h2 class="font-semibold mb-4">{{ __('All-time records') }}</h2>

        <!-- Category Tabs -->
        <div class="flex flex-wrap gap-2 mb-6">
            <template x-for="tab in recordTabs" :key="tab.key">
                <button @click="activeRecordTab = tab.key"
                        :class="activeRecordTab === tab.key ? 'bg-ui-accent-strong text-on-accent text-ui-fg shadow-lg shadow-ui-accent/30' : 'bg-ui-overlay/5 text-ui-secondary hover:bg-ui-overlay/10'"
                        class="px-3 py-1.5 rounded-lg text-sm transition-colors"
                        x-text="tab.label">
                </button>
            </template>
        </div>

        <!-- Temperature Records -->
        <div x-show="activeRecordTab === 'temperature'" x-cloak>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @include('weather.partials.statistics-record', ['label' => __('Highest temperature'), 'record' => $records['temperature']['highest'], 'format' => 'temperature', 'color' => 'text-data-amber-500', 'id' => 'rec-temp-high'])
                @include('weather.partials.statistics-record', ['label' => __('Lowest temperature'), 'record' => $records['temperature']['lowest'], 'format' => 'temperature', 'color' => 'text-data-cyan-500', 'id' => 'rec-temp-low'])
                @include('weather.partials.statistics-record', ['label' => __('Warmest day (avg)'), 'record' => $records['temperature']['warmest_avg'], 'format' => 'temperature', 'color' => 'text-data-amber-500', 'id' => 'rec-temp-warm-avg'])
                @include('weather.partials.statistics-record', ['label' => __('Coldest day (avg)'), 'record' => $records['temperature']['coldest_avg'], 'format' => 'temperature', 'color' => 'text-data-cyan-500', 'id' => 'rec-temp-cold-avg'])
                @include('weather.partials.statistics-record', ['label' => __('Largest daily range'), 'record' => $records['temperature']['largest_range'], 'format' => 'temperature', 'color' => 'text-data-purple-400', 'id' => 'rec-temp-range'])
            </div>
        </div>

        <!-- Precipitation Records -->
        <div x-show="activeRecordTab === 'precipitation'" x-cloak>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @include('weather.partials.statistics-record', ['label' => __('Most precipitation (day)'), 'record' => $records['precipitation']['wettest_day'], 'format' => 'rain', 'color' => 'text-data-indigo-500', 'id' => 'rec-rain-total'])
                @include('weather.partials.statistics-record', ['label' => __('Highest rain rate'), 'record' => $records['precipitation']['highest_rate'], 'format' => 'rain', 'color' => 'text-data-indigo-500', 'id' => 'rec-rain-rate'])
            </div>
        </div>

        <!-- Wind Records -->
        <div x-show="activeRecordTab === 'wind'" x-cloak>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @include('weather.partials.statistics-record', ['label' => __('Strongest wind gust'), 'record' => $records['wind']['strongest_gust'], 'format' => 'wind', 'color' => 'text-data-sky-400', 'id' => 'rec-wind-gust'])
                @include('weather.partials.statistics-record', ['label' => __('Highest average wind'), 'record' => $records['wind']['highest_avg'], 'format' => 'wind', 'color' => 'text-data-sky-400', 'id' => 'rec-wind-avg'])
            </div>
        </div>

        <!-- Pressure Records -->
        <div x-show="activeRecordTab === 'pressure'" x-cloak>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @include('weather.partials.statistics-record', ['label' => __('Highest pressure'), 'record' => $records['pressure']['highest'], 'format' => 'pressure', 'color' => 'text-data-violet-400', 'id' => 'rec-pressure-high'])
                @include('weather.partials.statistics-record', ['label' => __('Lowest pressure'), 'record' => $records['pressure']['lowest'], 'format' => 'pressure', 'color' => 'text-data-violet-400', 'id' => 'rec-pressure-low'])
            </div>
        </div>

        <!-- Humidity Records -->
        <div x-show="activeRecordTab === 'humidity'" x-cloak>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @include('weather.partials.statistics-record', ['label' => __('Highest humidity'), 'record' => $records['humidity']['highest'], 'format' => 'percent', 'color' => 'text-data-blue-400', 'id' => 'rec-humidity-high'])
                @include('weather.partials.statistics-record', ['label' => __('Lowest humidity'), 'record' => $records['humidity']['lowest'], 'format' => 'percent', 'color' => 'text-data-blue-400', 'id' => 'rec-humidity-low'])
            </div>
        </div>

        <!-- Solar/UV Records -->
        <div x-show="activeRecordTab === 'solar'" x-cloak>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @include('weather.partials.statistics-record', ['label' => __('Highest UV index'), 'record' => $records['solar']['highest_uv'], 'format' => 'number', 'color' => 'text-data-amber-400', 'id' => 'rec-uv-max'])
                @include('weather.partials.statistics-record', ['label' => __('Highest solar radiation'), 'record' => $records['solar']['highest_solar'], 'format' => 'solar', 'color' => 'text-data-yellow-400', 'id' => 'rec-solar-max'])
                @include('weather.partials.statistics-record', ['label' => __('Most sunshine hours'), 'record' => $records['solar']['most_solar_hours'], 'format' => 'hours', 'color' => 'text-data-yellow-400', 'id' => 'rec-solar-hours'])
            </div>
        </div>
    </div>
    @endif

    <!-- Climate Normals -->
    @if(($climateData['has_data'] ?? false))
    <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
        <h2 class="font-semibold mb-4">{{ __('Climate normals') }} <span class="text-sm text-ui-muted font-normal">{{ __('vs') }} {{ $year }}</span></h2>

        <!-- Climate Chart -->
        <div id="climate-normals-chart" class="h-72 mb-6"></div>

        <!-- Departure Table -->
        <h3 class="text-sm font-medium text-ui-muted mb-3">{{ __('Departure from normal') }}</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-ui-muted border-b border-ui-line/10">
                        <th class="text-left py-2 px-2">{{ __('Month') }}</th>
                        <th class="text-right py-2 px-2">{{ __('Normal avg') }}</th>
                        <th class="text-right py-2 px-2">{{ __('Actual avg') }}</th>
                        <th class="text-right py-2 px-2">{{ __('Departure') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @for($m = 1; $m <= 12; $m++)
                        <tr class="border-b border-ui-line/5 hover:bg-ui-overlay/5">
                            <td class="py-2 px-2 font-medium">{{ \Carbon\Carbon::create(null, $m)->locale($locale)->translatedFormat('M') }}</td>
                            @if(isset($climateData['normals'][$m]) && isset($climateData['actuals'][$m]) && isset($climateData['departures'][$m]))
                                <td class="text-right py-2 px-2 text-ui-muted">{{ $unit->temperature($climateData['normals'][$m]['avg_temp'], $activeUnits) }}</td>
                                <td class="text-right py-2 px-2">{{ $unit->temperature($climateData['actuals'][$m]['avg_temp'], $activeUnits) }}</td>
                                @php
                                    $dep = $climateData['departures'][$m]['temp'];
                                    // Departure is a delta, so for imperial convert without the +32 offset
                                    $depConverted = $activeUnits === 'imperial' ? round($dep * 9 / 5, 1) : $dep;
                                    $depSuffix = $activeUnits === 'imperial' ? 'F' : 'C';
                                @endphp
                                <td class="text-right py-2 px-2 font-medium {{ $dep > 0 ? 'text-data-amber-500' : ($dep < 0 ? 'text-data-cyan-500' : 'text-ui-muted') }}">
                                    {{ $dep > 0 ? '+' : '' }}{{ number_format($depConverted, 1) }} {{ $depSuffix }}
                                </td>
                            @else
                                <td class="text-right py-2 px-2 text-ui-subtle">--</td>
                                <td class="text-right py-2 px-2 text-ui-subtle">--</td>
                                <td class="text-right py-2 px-2 text-ui-subtle">--</td>
                            @endif
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- Compare Tool -->
    <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
        <h2 class="font-semibold mb-4">{{ __('Compare periods') }}</h2>

        <!-- Mode Toggle -->
        <div class="flex gap-2 mb-4">
            <button @click="compareMode = 'year'" :class="compareMode === 'year' ? 'bg-ui-accent-strong text-on-accent text-ui-fg' : 'bg-ui-overlay/5 text-ui-secondary hover:bg-ui-overlay/10'" class="px-3 py-1.5 rounded-lg text-sm transition-colors">
                {{ __('Compare years') }}
            </button>
            <button @click="compareMode = 'month'" :class="compareMode === 'month' ? 'bg-ui-accent-strong text-on-accent text-ui-fg' : 'bg-ui-overlay/5 text-ui-secondary hover:bg-ui-overlay/10'" class="px-3 py-1.5 rounded-lg text-sm transition-colors">
                {{ __('Compare months') }}
            </button>
        </div>

        <!-- Year Comparison Selectors -->
        <div x-show="compareMode === 'year'" class="flex flex-wrap items-end gap-4 mb-4">
            <div>
                <label class="text-xs text-ui-muted block mb-1">{{ __('Period A') }}</label>
                <select x-model="compareA" class="bg-weather-dark border border-ui-line/10 rounded-lg px-3 py-2 text-sm">
                    @foreach($availableYears as $y)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div class="text-ui-subtle pb-2">{{ __('vs') }}</div>
            <div>
                <label class="text-xs text-ui-muted block mb-1">{{ __('Period B') }}</label>
                <select x-model="compareB" class="bg-weather-dark border border-ui-line/10 rounded-lg px-3 py-2 text-sm">
                    @foreach($availableYears as $y)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <button @click="fetchComparison()" :disabled="compareLoading" class="px-4 py-2 bg-ui-accent-strong text-on-accent hover:bg-ui-action disabled:opacity-50 rounded-lg text-sm transition-colors">
                <span x-show="!compareLoading">{{ __('Compare') }}</span>
                <span x-show="compareLoading" x-cloak>{{ __('Loading...') }}</span>
            </button>
        </div>

        <!-- Month Comparison Selectors -->
        <div x-show="compareMode === 'month'" class="flex flex-wrap items-end gap-4 mb-4">
            <div>
                <label class="text-xs text-ui-muted block mb-1">{{ __('Period A') }}</label>
                <div class="flex gap-2">
                    <select x-model="compareMonthYearA" class="bg-weather-dark border border-ui-line/10 rounded-lg px-3 py-2 text-sm">
                        @foreach($availableYears as $y)
                            <option value="{{ $y }}">{{ $y }}</option>
                        @endforeach
                    </select>
                    <select x-model="compareMonthA" class="bg-weather-dark border border-ui-line/10 rounded-lg px-3 py-2 text-sm">
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}">{{ \Carbon\Carbon::create(null, $m)->locale($locale)->translatedFormat('M') }}</option>
                        @endfor
                    </select>
                </div>
            </div>
            <div class="text-ui-subtle pb-2">{{ __('vs') }}</div>
            <div>
                <label class="text-xs text-ui-muted block mb-1">{{ __('Period B') }}</label>
                <div class="flex gap-2">
                    <select x-model="compareMonthYearB" class="bg-weather-dark border border-ui-line/10 rounded-lg px-3 py-2 text-sm">
                        @foreach($availableYears as $y)
                            <option value="{{ $y }}">{{ $y }}</option>
                        @endforeach
                    </select>
                    <select x-model="compareMonthB" class="bg-weather-dark border border-ui-line/10 rounded-lg px-3 py-2 text-sm">
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}">{{ \Carbon\Carbon::create(null, $m)->locale($locale)->translatedFormat('M') }}</option>
                        @endfor
                    </select>
                </div>
            </div>
            <button @click="fetchComparison()" :disabled="compareLoading" class="px-4 py-2 bg-ui-accent-strong text-on-accent hover:bg-ui-action disabled:opacity-50 rounded-lg text-sm transition-colors">
                <span x-show="!compareLoading">{{ __('Compare') }}</span>
                <span x-show="compareLoading" x-cloak>{{ __('Loading...') }}</span>
            </button>
        </div>

        <!-- Comparison Results -->
        <template x-if="compareResult">
            <div>
                <!-- Summary Cards -->
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div class="bg-ui-overlay/5 rounded-lg p-4">
                        <div class="text-xs text-data-blue-400 font-medium mb-2" x-text="compareResult.a.label"></div>
                        <template x-if="compareResult.a.summary">
                            <div class="space-y-1 text-sm">
                                <div class="flex justify-between"><span class="text-ui-muted">{{ __('Highest temp') }}</span><span class="text-data-amber-500 font-medium" x-text="formatTemp(compareResult.a.summary.temp_high)"></span></div>
                                <div class="flex justify-between"><span class="text-ui-muted">{{ __('Lowest temp') }}</span><span class="text-data-cyan-500 font-medium" x-text="formatTemp(compareResult.a.summary.temp_low)"></span></div>
                                <div class="flex justify-between"><span class="text-ui-muted">{{ __('Precipitation') }}</span><span class="text-data-indigo-500 font-medium" x-text="formatRain(compareResult.a.summary.rain_total)"></span></div>
                                <div class="flex justify-between"><span class="text-ui-muted">{{ __('Max wind') }}</span><span class="font-medium" x-text="formatWind(compareResult.a.summary.wind_max)"></span></div>
                            </div>
                        </template>
                        <template x-if="!compareResult.a.summary">
                            <p class="text-sm text-ui-subtle">{{ __('No data') }}</p>
                        </template>
                    </div>
                    <div class="bg-ui-overlay/5 rounded-lg p-4">
                        <div class="text-xs text-data-emerald-400 font-medium mb-2" x-text="compareResult.b.label"></div>
                        <template x-if="compareResult.b.summary">
                            <div class="space-y-1 text-sm">
                                <div class="flex justify-between"><span class="text-ui-muted">{{ __('Highest temp') }}</span><span class="text-data-amber-500 font-medium" x-text="formatTemp(compareResult.b.summary.temp_high)"></span></div>
                                <div class="flex justify-between"><span class="text-ui-muted">{{ __('Lowest temp') }}</span><span class="text-data-cyan-500 font-medium" x-text="formatTemp(compareResult.b.summary.temp_low)"></span></div>
                                <div class="flex justify-between"><span class="text-ui-muted">{{ __('Precipitation') }}</span><span class="text-data-indigo-500 font-medium" x-text="formatRain(compareResult.b.summary.rain_total)"></span></div>
                                <div class="flex justify-between"><span class="text-ui-muted">{{ __('Max wind') }}</span><span class="font-medium" x-text="formatWind(compareResult.b.summary.wind_max)"></span></div>
                            </div>
                        </template>
                        <template x-if="!compareResult.b.summary">
                            <p class="text-sm text-ui-subtle">{{ __('No data') }}</p>
                        </template>
                    </div>
                </div>

                <!-- Comparison Chart -->
                <div id="compare-chart" class="h-72"></div>
            </div>
        </template>
    </div>
</div>

<!-- Climate chart data + translated strings for JS -->
@if(($climateData['has_data'] ?? false))
<script type="application/json" id="climate-chart-data">@json($climateData['chart'])</script>
@php
    $chartStrings = [
        'normal_range' => __('Normal range'),
        'actual_high'  => __('Actual high'),
        'actual_low'   => __('Actual low'),
    ];
@endphp
<script type="application/json" id="statistics-chart-strings">@json($chartStrings)</script>
@endif

<!-- GDD chart data -->
@if(($phenology['has_data'] ?? false) && !empty($phenology['gdd']['dates']))
<script type="application/json" id="gdd-chart-data">@json($phenology['gdd'])</script>
@php
    $gddStrings = [
        'gdd_label'    => __('Growing Degree Days (base 10 °C)'),
        'gdd_axis'     => __('GDD (°C)'),
        'daily_gdd'    => __('Daily GDD'),
        'rolling_gdd'  => __('GDD over :days days'),
        'best_period'  => __('Best period'),
    ];
@endphp
<script type="application/json" id="gdd-chart-strings">@json($gddStrings)</script>
@endif

@endsection

@push('head_scripts')
@vite('resources/js/pages/statistics-charts.js')
@endpush

@push('scripts')
<script>
function statisticsPage() {
    const years = @json($availableYears);
    const currentYear = @json($year);
    const units = window.Meteo?.activeUnits || 'metric';
    const locale = window.Meteo?.jsLocale || 'en-US';

    const convertTemp = (v) => {
        if (v === null || v === undefined) return null;
        return units === 'imperial' ? (v * 9 / 5 + 32) : v;
    };
    const convertRain = (v) => {
        if (v === null || v === undefined) return null;
        return units === 'imperial' ? (v * 0.0393700787) : v;
    };
    const convertWind = (v) => {
        if (v === null || v === undefined) return null;
        if (units === 'imperial' || units === 'uk') return v * 0.6213711922;
        if (units === 'scandinavia') return v / 3.6;
        return v;
    };

    const fmt = (value, decimals = 1) => {
        if (value === null || value === undefined) return '--';
        return new Intl.NumberFormat(locale, { minimumFractionDigits: decimals, maximumFractionDigits: decimals }).format(value);
    };

    const tempUnit = units === 'imperial' ? '°F' : '°C';
    const rainUnit = units === 'imperial' ? ' in' : ' mm';
    const windUnit = units === 'scandinavia' ? ' m/s' : (units === 'imperial' || units === 'uk' ? ' mph' : ' km/h');

    return {
        // GDD modal
        gddModalOpen: false,

        // Records tabs
        activeRecordTab: 'temperature',
        recordTabs: [
            { key: 'temperature', label: @json(__('Temperature')) },
            { key: 'precipitation', label: @json(__('Precipitation')) },
            { key: 'wind', label: @json(__('Wind')) },
            { key: 'pressure', label: @json(__('Pressure')) },
            { key: 'humidity', label: @json(__('Humidity')) },
            { key: 'solar', label: @json(__('Solar & UV')) },
        ],

        // Compare tool
        compareMode: 'year',
        compareA: years[0] || currentYear,
        compareB: years[1] || currentYear,
        compareMonthYearA: currentYear,
        compareMonthA: String(new Date().getMonth() + 1).padStart(2, '0'),
        compareMonthYearB: years[1] || currentYear,
        compareMonthB: String(new Date().getMonth() + 1).padStart(2, '0'),
        compareLoading: false,
        compareResult: null,
        compareChart: null,

        formatTemp(v) { return v !== null && v !== undefined ? fmt(convertTemp(v)) + tempUnit : '--'; },
        formatRain(v) { return v !== null && v !== undefined ? fmt(convertRain(v)) + rainUnit : '--'; },
        formatWind(v) { return v !== null && v !== undefined ? fmt(convertWind(v)) + windUnit : '--'; },

        async fetchComparison() {
            this.compareLoading = true;
            this.compareResult = null;

            let url;
            if (this.compareMode === 'year') {
                url = `/statistics/compare?type=year&a=${this.compareA}&b=${this.compareB}`;
            } else {
                const a = `${this.compareMonthYearA}-${this.compareMonthA}`;
                const b = `${this.compareMonthYearB}-${this.compareMonthB}`;
                url = `/statistics/compare?type=month&a=${encodeURIComponent(a)}&b=${encodeURIComponent(b)}`;
            }

            try {
                const resp = await fetch(window.Meteo.appendApiKey(url), { headers: window.Meteo.apiHeaders() });
                if (!resp.ok) throw new Error('Failed to fetch');
                this.compareResult = await resp.json();
                this.$nextTick(() => this.renderCompareChart());
            } catch (e) {
                console.error('Compare fetch failed:', e);
            } finally {
                this.compareLoading = false;
            }
        },

        async renderCompareChart() {
            const el = document.getElementById('compare-chart');
            if (!el || !this.compareResult) return;

            // Wait up to 3s for the Vite-loaded statistics-charts.js to expose ApexCharts
            let tries = 0;
            while (!window._statsApexCharts && tries < 30) {
                await new Promise(r => setTimeout(r, 100));
                tries++;
            }
            if (!window._statsApexCharts) return;

            if (this.compareChart) {
                try { this.compareChart.destroy(); } catch {}
            }

            const r = this.compareResult;
            const isDark = document.documentElement.classList.contains('dark');
            const axisLabelColor = isDark ? '#cbd5f5' : '#475569';
            const gridColor = isDark ? '#1f2937' : '#e2e8f0';

            let categories, seriesA, seriesB;

            if (r.type === 'year') {
                categories = Array.from({ length: 12 }, (_, i) => {
                    const d = new Date(2024, i, 1);
                    return d.toLocaleString(locale, { month: 'short' });
                });
                seriesA = categories.map((_, i) => {
                    const m = r.a.data[i + 1];
                    return m ? convertTemp(m.temp_avg) : null;
                });
                seriesB = categories.map((_, i) => {
                    const m = r.b.data[i + 1];
                    return m ? convertTemp(m.temp_avg) : null;
                });
            } else {
                const maxDay = Math.max(
                    ...Object.keys(r.a.data || {}).map(Number),
                    ...Object.keys(r.b.data || {}).map(Number),
                    28
                );
                categories = Array.from({ length: maxDay }, (_, i) => i + 1);
                seriesA = categories.map(d => {
                    const day = r.a.data[d];
                    return day ? convertTemp(day.temp_avg) : null;
                });
                seriesB = categories.map(d => {
                    const day = r.b.data[d];
                    return day ? convertTemp(day.temp_avg) : null;
                });
            }

            this.compareChart = new (window._statsApexCharts)(el, {
                chart: {
                    height: 280,
                    type: 'line',
                    toolbar: { show: false },
                    zoom: { enabled: false },
                    background: 'transparent',
                    animations: { enabled: !document.body.classList.contains('effects-disabled') },
                },
                series: [
                    { name: r.a.label, data: seriesA },
                    { name: r.b.label, data: seriesB },
                ],
                xaxis: { categories, labels: { style: { colors: axisLabelColor } } },
                yaxis: {
                    labels: {
                        style: { colors: axisLabelColor },
                        formatter: (v) => fmt(v),
                    },
                    title: { text: `${@json(__('Avg temperature'))} (${tempUnit})`, style: { color: axisLabelColor } },
                },
                grid: { borderColor: gridColor },
                stroke: { curve: 'smooth', width: [3, 3] },
                colors: ['#3b82f6', '#10b981'],
                legend: { labels: { colors: axisLabelColor } },
                tooltip: {
                    theme: isDark ? 'dark' : 'light',
                    y: { formatter: (v) => v !== null && v !== undefined ? `${fmt(v)} ${tempUnit}` : '-' },
                },
                theme: { mode: isDark ? 'dark' : 'light' },
            });
            this.compareChart.render();
        },
    };
}
</script>
@endpush
