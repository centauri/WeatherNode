@extends('weather.layout')

@section('title', __('Water') . ' — ' . \App\Models\Setting::stationName())
@section('meta_description', __('tide_page_meta', [
    'station' => $stationName ?: \App\Models\Setting::stationName(),
    'source'  => $sourceLabel ?: \App\Services\Tide\TideServiceFactory::make()->getName(),
]))

@push('head_scripts')
    @if($activeTab === 'tides')
        @vite('resources/js/pages/tide-charts.js')
    @elseif(in_array($activeTab, ['waves', 'temp']))
        @vite('resources/js/pages/wave-charts.js')
    @endif
@endpush

@section('content')
@php
    use Carbon\Carbon;
    use App\Services\Wave\OpenMeteoWaveService;

    // ── Unit system ───────────────────────────────────────────────────────────
    $isImperial    = ($activeUnits ?? 'metric') === 'imperial';
    $datumLabel    = ($source ?? '') === 'rws' ? 'NAP' : 'MSL';
    $unitLabel     = $isImperial ? 'ft' : 'cm';
    $levelDecimals = $isImperial ? 2 : 0;
    $toUnit        = fn(?float $cm): ?float => $cm !== null
        ? ($isImperial ? round($cm * 0.0328084, 2) : (float) round($cm))
        : null;

    // Wave unit (m or ft)
    $waveUnit      = $isImperial ? 'ft' : 'm';
    $waveDecimals  = $isImperial ? 1 : 2;
    $toWaveUnit    = fn(?float $m): ?float => $m !== null
        ? ($isImperial ? round($m * 3.28084, 1) : round($m, 2))
        : null;

    // SST unit (°C or °F)
    $sstUnit       = $isImperial ? '°F' : '°C';
    $sstDecimals   = 1;
    $toSstUnit     = fn(?float $c): ?float => $c !== null
        ? ($isImperial ? round(($c * 9/5) + 32, 1) : round($c, 1))
        : null;

    // ── Tide section ──────────────────────────────────────────────────────────
    $stationNameDisplay = $stationName ?: \App\Models\Setting::stationName();
    $updatedAt          = ($tideData ?? null) ? Carbon::parse($tideData['updated_at'])->diffForHumans() : null;
    $currentLevel       = $tideData['current_level_cm'] ?? null;
    $trend              = $tideData['trend'] ?? 'steady';
    $trendIcon          = match($trend) { 'rising' => '↑', 'falling' => '↓', default => '→' };
    $trendClass         = match($trend) { 'rising' => 'text-data-cyan-400', 'falling' => 'text-data-blue-400', default => 'text-ui-muted' };
    $nowMs              = now()->timestamp * 1000;

    $upcoming = collect($tideData['tides'] ?? [])
        ->filter(fn($t) => $t['timestamp_unix'] >= $nowMs)
        ->values();
    $nextHigh     = $upcoming->firstWhere('type', 'high');
    $nextLow      = $upcoming->firstWhere('type', 'low');
    $groupedTides = $upcoming->groupBy(fn($t) => Carbon::parse($t['timestamp'])->format('Y-m-d'))->take(3);

    $chartSeries = collect($tideData['series'] ?? [])
        ->filter(fn($p) => $p['timestamp_unix'] >= ($nowMs - 12 * 3_600_000)
                        && $p['timestamp_unix'] <= ($nowMs + 48 * 3_600_000))
        ->values()
        ->map(fn($p) => array_merge($p, ['value' => $toUnit($p['value'])]))
        ->toArray();

    $chartAnnotations = collect($tideData['tides'] ?? [])
        ->filter(fn($t) => $t['timestamp_unix'] >= ($nowMs - 12 * 3_600_000)
                        && $t['timestamp_unix'] <= ($nowMs + 48 * 3_600_000))
        ->values()
        ->map(fn($t) => array_merge($t, ['level_cm' => $toUnit($t['level_cm'])]))
        ->toArray();

    // ── Wave section ──────────────────────────────────────────────────────────
    $waveUpdatedAt = ($waveData ?? null) ? Carbon::parse($waveData['updated_at'])->diffForHumans() : null;
    $waveHeight    = $waveData['current_wave_height_m']       ?? null;
    $wavePeriod    = $waveData['current_wave_period_s']       ?? null;
    $waveDir       = $waveData['current_wave_direction_deg']  ?? null;
    $windWaveH     = $waveData['current_wind_wave_height_m']  ?? null;
    $swellH        = $waveData['current_swell_height_m']      ?? null;
    $swellDir      = $waveData['current_swell_direction_deg'] ?? null;
    $swellPeriod   = $waveData['current_swell_period_s']      ?? null;
    $sstC          = $waveData['current_sst_c']               ?? null;
    $beaufortState = $waveData['beaufort_sea_state']          ?? 0;
    $beaufortKey   = $waveData['beaufort_label_key']          ?? 'wave_beaufort_0';
    $waveLoc       = $waveData['location']                    ?? '';

    $waveCardinal  = $waveDir !== null ? OpenMeteoWaveService::degreesToCardinal($waveDir) : '--';
    $swellCardinal = $swellDir !== null ? OpenMeteoWaveService::degreesToCardinal($swellDir) : '--';

    $waveSeries = collect($waveData['wave_series'] ?? [])
        ->filter(fn($p) => $p['timestamp_unix'] >= ($nowMs - 12 * 3_600_000)
                        && $p['timestamp_unix'] <= ($nowMs + 48 * 3_600_000))
        ->values()
        ->map(fn($p) => array_merge($p, ['value' => $toWaveUnit($p['value'])]))
        ->toArray();

    $sstSeries = collect($waveData['sst_series'] ?? [])
        ->filter(fn($p) => $p['timestamp_unix'] >= ($nowMs - 12 * 3_600_000)
                        && $p['timestamp_unix'] <= ($nowMs + 120 * 3_600_000))
        ->values()
        ->map(fn($p) => array_merge($p, ['value' => $toSstUnit($p['value'])]))
        ->toArray();

    // SST comfort band
    $sstDisplay = $toSstUnit($sstC);
    $sstComfortKey = match(true) {
        $sstC === null => null,
        $sstC < 10     => 'sst_cold',
        $sstC < 15     => 'sst_cool',
        $sstC < 20     => 'sst_comfortable',
        $sstC < 25     => 'sst_warm',
        default        => 'sst_hot',
    };
    $sstComfortClass = match($sstComfortKey) {
        'sst_cold'        => 'bg-blue-900/40 text-data-blue-300 border border-blue-800/30',
        'sst_cool'        => 'bg-cyan-900/40 text-data-cyan-300 border border-cyan-800/30',
        'sst_comfortable' => 'bg-teal-900/40 text-data-teal-300 border border-teal-800/30',
        'sst_warm'        => 'bg-orange-900/40 text-data-orange-300 border border-orange-800/30',
        'sst_hot'         => 'bg-red-900/40 text-data-red-300 border border-red-800/30',
        default           => 'bg-ui-raised/40 text-ui-muted border border-ui-divider/30',
    };

    // ── Tab active-state helpers ───────────────────────────────────────────────
    $tabActive   = fn(string $t) => $activeTab === $t
        ? 'bg-{c}-600 shadow-lg shadow-{c}-600/30 text-ui-fg'  // replaced per-tab below
        : 'bg-ui-overlay/10 text-ui-secondary hover:bg-ui-overlay/20';
    $tabClass = [
        'tides'  => $activeTab === 'tides'  ? 'bg-cyan-600 text-on-accent shadow-lg shadow-cyan-600/30 text-ui-fg'      : 'bg-ui-overlay/10 text-ui-secondary hover:bg-ui-overlay/20',
        'waves'  => $activeTab === 'waves'  ? 'bg-ui-accent-strong text-on-accent shadow-lg shadow-ui-accent/30 text-ui-fg'      : 'bg-ui-overlay/10 text-ui-secondary hover:bg-ui-overlay/20',
        'temp'   => $activeTab === 'temp'   ? 'bg-orange-500 text-on-accent shadow-lg shadow-orange-500/30 text-ui-fg'  : 'bg-ui-overlay/10 text-ui-secondary hover:bg-ui-overlay/20',
        'rivers' => $activeTab === 'rivers' ? 'bg-emerald-600 text-on-accent shadow-lg shadow-emerald-600/30 text-ui-fg': 'bg-ui-overlay/10 text-ui-secondary hover:bg-ui-overlay/20',
    ];
@endphp

{{-- ── Sky & Water top tab strip ─────────────────────────────────────────── --}}
<div class="flex gap-2 mb-4">
    <a href="{{ route('aviation') }}"
       class="px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-ui-overlay/10 text-ui-secondary hover:bg-ui-overlay/20">
        ✈ {{ __('Aviation') }}
    </a>
    <a href="{{ route('water') }}"
       class="px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-cyan-600 text-on-accent shadow-lg shadow-cyan-600/30 text-ui-fg">
        🌊 {{ __('Water') }}
    </a>
</div>

{{-- ── Water sub-tab link row ──────────────────────────────────────────────── --}}
<div class="flex gap-2 flex-wrap mb-6">
    <a href="{{ route('water') }}"
       class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ $tabClass['tides'] }}">
        🌊 {{ __('Tides') }}
    </a>
    <a href="{{ route('water.waves') }}"
       class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ $tabClass['waves'] }}">
        〰 {{ __('Waves') }}
    </a>
    <a href="{{ route('water.temp') }}"
       class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ $tabClass['temp'] }}">
        🌡 {{ __('Sea Temperature') }}
    </a>
    @if($riversEnabled)
    <a href="{{ route('water.rivers') }}"
       class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ $tabClass['rivers'] }}">
        🏞 {{ __('River Levels') }}
    </a>
    @endif
</div>

{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{-- TAB: TIDES                                                                 --}}
{{-- ══════════════════════════════════════════════════════════════════════════ --}}
@if($activeTab === 'tides')
<div class="space-y-6">

    {{-- Page header --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold">🌊 {{ __('Tides') }}</h1>
            <p class="text-ui-muted">{{ $stationNameDisplay }}
                @if($tideData)· {{ __('Updated') }} {{ $updatedAt }}@endif
            </p>
        </div>
        @if($tideData)
            <div class="text-right text-sm text-ui-subtle">
                {{ __('Data source') }}:
                @if($sourceDocUrl)
                    <a href="{{ $sourceDocUrl }}" target="_blank" rel="noopener"
                       class="text-data-blue-400 hover:underline">{{ $sourceLabel }}</a>
                @else
                    <span>{{ $sourceLabel }}</span>
                @endif
            </div>
        @endif
    </div>

    @if(!$tideEnabled)
        <div class="bg-blue-900/30 border border-blue-700/50 rounded-2xl p-6">
            <h2 class="text-lg font-semibold text-ui-fg mb-2">🔧 {{ __('Tides not enabled') }}</h2>
            <p class="text-ui-secondary mb-4">{{ __('Enable tides in settings to show live tide times and water levels.') }}</p>
            <a href="{{ route('admin.settings.group', 'tide') }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-ui-accent-strong text-on-accent hover:bg-ui-action-deep rounded-lg text-sm font-medium transition-colors">
                ⚙️ {{ __('Configure Tides') }}
            </a>
        </div>

    @elseif(!$tideData)
        <div class="bg-yellow-900/30 border border-yellow-700/50 rounded-2xl p-6">
            <h2 class="text-lg font-semibold text-ui-fg mb-2">⏳ {{ __('No tide data yet') }}</h2>
            <p class="text-ui-secondary">{{ __('Tide data is being fetched. Check back in a few minutes, or run the poller manually.') }}</p>
        </div>

    @else
        {{-- Summary cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">

            <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
                <div class="text-xs text-ui-muted uppercase tracking-wider mb-2">{{ __('Current Level') }}</div>
                <div class="flex items-end gap-1">
                    <span class="text-3xl font-bold text-ui-fg">
                        {{ $currentLevel !== null ? number_format($toUnit($currentLevel), $levelDecimals) : '--' }}
                    </span>
                    <span class="text-ui-muted mb-1 text-sm">{{ $unitLabel }} {{ $datumLabel }}</span>
                </div>
                <div class="mt-2 text-sm {{ $trendClass }} font-medium">
                    {{ $trendIcon }} {{ __('tide_trend_' . $trend) }}
                </div>
            </div>

            <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
                <div class="text-xs text-ui-muted uppercase tracking-wider mb-2">{{ __('Next High Tide') }}</div>
                @if($nextHigh)
                    <div class="text-2xl font-bold text-data-cyan-300">
                        {{ Carbon::parse($nextHigh['timestamp'])->format('H:i') }}
                    </div>
                    <div class="text-sm text-ui-muted mt-1">
                        {{ Carbon::parse($nextHigh['timestamp'])->isoFormat('ddd D MMM') }}
                    </div>
                    <div class="text-sm text-ui-secondary mt-1 font-medium">
                        {{ number_format($toUnit($nextHigh['level_cm']), $levelDecimals) }} {{ $unitLabel }}
                    </div>
                @else
                    <div class="text-2xl font-bold text-ui-subtle">--</div>
                @endif
            </div>

            <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
                <div class="text-xs text-ui-muted uppercase tracking-wider mb-2">{{ __('Next Low Tide') }}</div>
                @if($nextLow)
                    <div class="text-2xl font-bold text-data-blue-300">
                        {{ Carbon::parse($nextLow['timestamp'])->format('H:i') }}
                    </div>
                    <div class="text-sm text-ui-muted mt-1">
                        {{ Carbon::parse($nextLow['timestamp'])->isoFormat('ddd D MMM') }}
                    </div>
                    <div class="text-sm text-ui-secondary mt-1 font-medium">
                        {{ number_format($toUnit($nextLow['level_cm']), $levelDecimals) }} {{ $unitLabel }}
                    </div>
                @else
                    <div class="text-2xl font-bold text-ui-subtle">--</div>
                @endif
            </div>

            <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
                <div class="text-xs text-ui-muted uppercase tracking-wider mb-2">{{ __('Tidal Range') }}</div>
                @php
                    $range = ($nextHigh && $nextLow)
                        ? abs($nextHigh['level_cm'] - $nextLow['level_cm'])
                        : null;
                @endphp
                <div class="text-2xl font-bold text-ui-fg">
                    {{ $range !== null ? number_format($toUnit($range), $levelDecimals) : '--' }}
                    @if($range !== null)<span class="text-ui-muted text-lg font-normal"> {{ $unitLabel }}</span>@endif
                </div>
                <div class="text-xs text-ui-muted mt-2">{{ __('high minus low') }}</div>
            </div>

        </div>

        {{-- Tidal chart --}}
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
            <h2 class="font-semibold text-ui-fg mb-4">{{ __('Tidal Chart') }} — {{ $stationNameDisplay }}
                <span class="text-sm font-normal text-ui-muted ml-2">{{ __('(48-hour window)') }}</span>
            </h2>
            <div id="tide-chart" class="w-full" style="min-height:220px;"></div>
        </div>

        {{-- 3-day tide table --}}
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
            <h2 class="font-semibold text-ui-fg mb-4">{{ __('Tide Forecast') }}</h2>

            @forelse($groupedTides as $date => $dayTides)
                @php
                    $dateObj   = Carbon::parse($date);
                    $dateLabel = $dateObj->isToday()
                        ? __('Today')
                        : ($dateObj->isTomorrow() ? __('Tomorrow') : $dateObj->isoFormat('dddd D MMMM'));
                @endphp
                <div class="mb-5 last:mb-0">
                    <div class="text-sm font-semibold text-ui-secondary uppercase tracking-wider mb-3 pb-1 border-b border-ui-line/10">
                        {{ $dateLabel }}
                    </div>
                    <div class="space-y-2">
                        @foreach($dayTides as $tide)
                            @php
                                $isHigh = $tide['type'] === 'high';
                                $time   = Carbon::parse($tide['timestamp'])->format('H:i');
                            @endphp
                            <div class="flex items-center justify-between py-2 px-3 rounded-lg {{ $isHigh ? 'bg-cyan-900/20 border border-cyan-800/30' : 'bg-blue-950/30 border border-blue-900/30' }}">
                                <div class="flex items-center gap-3">
                                    <span class="text-lg">{{ $isHigh ? '🔼' : '🔽' }}</span>
                                    <div>
                                        <span class="font-semibold text-ui-fg text-lg">{{ $time }}</span>
                                        <span class="text-sm ml-2 {{ $isHigh ? 'text-data-cyan-400' : 'text-data-blue-400' }}">
                                            {{ $isHigh ? __('High Tide') : __('Low Tide') }}
                                        </span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="font-bold text-ui-fg text-lg">{{ number_format($toUnit($tide['level_cm']), $levelDecimals) }}</span>
                                    <span class="text-ui-muted text-sm ml-1">{{ $unitLabel }} {{ $datumLabel }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="text-ui-muted">{{ __('No upcoming tide data available.') }}</p>
            @endforelse
        </div>

        {{-- Datum explanation + attribution --}}
        <div class="bg-ui-deep/40 rounded-2xl p-5 border border-ui-line/5 text-sm text-ui-muted">
            @if(($source ?? 'rws') === 'rws')
                <h3 class="font-semibold text-ui-secondary mb-2">{{ __('About NAP') }}</h3>
                <p class="mb-2">{{ __('nap_explanation') }}</p>
            @else
                <h3 class="font-semibold text-ui-secondary mb-2">{{ __('About MSL') }}</h3>
                <p class="mb-2">{{ __('msl_explanation') }}</p>
            @endif
            <p>{{ __('Tide data provided by') }}
                @if($sourceDocUrl)
                    <a href="{{ $sourceDocUrl }}" target="_blank" rel="noopener" class="text-data-blue-400 hover:underline">{{ $sourceLabel }}</a>
                @else
                    <span>{{ $sourceLabel }}</span>
                @endif.
            </p>
        </div>

        {{-- About tides (scientific) --}}
        <article class="bg-weather-card rounded-2xl border border-ui-line/10 p-6 md:p-8" aria-labelledby="water-tides-about-heading">
            <h2 id="water-tides-about-heading" class="text-xl font-semibold mb-4">{{ __('Water tides about heading') }}</h2>
            <div class="prose prose-invert prose-sm max-w-none text-ui-secondary space-y-4">
                <p>{{ __('Water tides about body 1') }}</p>
                <p>{{ __('Water tides about body 2') }}</p>
                <p>{{ __('Water tides about body 3') }}</p>
                <p class="text-data-cyan-200/90 italic border-l-2 border-cyan-500/50 pl-4">{{ __('Water tides about fun') }}</p>
            </div>
            <footer class="mt-6 pt-4 border-t border-ui-line/10">
                <p class="text-xs text-ui-subtle">{{ __('Water tides page sources') }}</p>
            </footer>
        </article>

    @endif

</div>
@endif

{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{-- TAB: WAVES                                                                 --}}
{{-- ══════════════════════════════════════════════════════════════════════════ --}}
@if($activeTab === 'waves')
<div class="space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold">〰 {{ __('Waves') }}</h1>
            <p class="text-ui-muted">
                {{ $waveLoc }}
                @if($waveData)· {{ __('Updated') }} {{ $waveUpdatedAt }}@endif
            </p>
        </div>
        <div class="text-right text-sm text-ui-subtle">
            {{ __('Data source') }}:
            <a href="https://open-meteo.com/en/docs/marine-weather-api" target="_blank" rel="noopener"
               class="text-data-blue-400 hover:underline">Open-Meteo Marine</a>
        </div>
    </div>

    @if(!$waveData)
        <div class="bg-yellow-900/30 border border-yellow-700/50 rounded-2xl p-6">
            <h2 class="text-lg font-semibold text-ui-fg mb-2">⏳ {{ __('No wave data yet') }}</h2>
            <p class="text-ui-secondary">{{ __('Wave data is being fetched. Check back in a few minutes.') }}</p>
        </div>
    @else

        {{-- Summary cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">

            <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
                <div class="text-xs text-ui-muted uppercase tracking-wider mb-2">{{ __('Wave Height') }}</div>
                <div class="flex items-end gap-1">
                    <span class="text-3xl font-bold text-data-cyan-300">
                        {{ $waveHeight !== null ? number_format($toWaveUnit($waveHeight), $waveDecimals) : '--' }}
                    </span>
                    <span class="text-ui-muted mb-1 text-sm">{{ $waveUnit }}</span>
                </div>
                @if($waveHeight !== null)
                    <div class="mt-2">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-cyan-900/40 text-data-cyan-300 border border-cyan-800/30">
                            {{ __($beaufortKey) }}
                        </span>
                    </div>
                @endif
            </div>

            <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
                <div class="text-xs text-ui-muted uppercase tracking-wider mb-2">{{ __('Wave Period') }}</div>
                <div class="flex items-end gap-1">
                    <span class="text-3xl font-bold text-ui-fg">
                        {{ $wavePeriod !== null ? number_format($wavePeriod, 0) : '--' }}
                    </span>
                    <span class="text-ui-muted mb-1 text-sm">s</span>
                </div>
                <div class="mt-2 text-xs text-ui-subtle">{{ __('mean period') }}</div>
            </div>

            <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
                <div class="text-xs text-ui-muted uppercase tracking-wider mb-2">{{ __('Wave Direction') }}</div>
                <div class="flex items-center gap-3 mt-1">
                    @if($waveDir !== null)
                        <span class="text-3xl" style="display:inline-block;transform:rotate({{ $waveDir }}deg);transition:transform 0.5s ease;">↑</span>
                    @else
                        <span class="text-3xl text-ui-subtle">→</span>
                    @endif
                    <div>
                        <div class="text-xl font-bold text-ui-fg">{{ $waveCardinal }}</div>
                        @if($waveDir !== null)
                            <div class="text-xs text-ui-subtle">{{ round($waveDir) }}°</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
                <div class="text-xs text-ui-muted uppercase tracking-wider mb-2">{{ __('Swell Height') }}</div>
                <div class="flex items-end gap-1">
                    <span class="text-3xl font-bold text-data-blue-300">
                        {{ $swellH !== null ? number_format($toWaveUnit($swellH), $waveDecimals) : '--' }}
                    </span>
                    <span class="text-ui-muted mb-1 text-sm">{{ $waveUnit }}</span>
                </div>
                @if($swellPeriod !== null)
                    <div class="mt-2 text-xs text-ui-subtle">{{ number_format($swellPeriod, 0) }} s {{ __('period') }}</div>
                @endif
            </div>

        </div>

        {{-- 48-h wave chart --}}
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
            <h2 class="font-semibold text-ui-fg mb-4">{{ __('Wave Height Forecast') }}
                <span class="text-sm font-normal text-ui-muted ml-2">{{ __('(48-hour window)') }}</span>
            </h2>
            <div id="wave-chart" class="w-full" style="min-height:220px;"></div>
        </div>

        {{-- Wind wave vs swell breakdown --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <div class="bg-blue-950/30 rounded-2xl p-5 border border-blue-900/30">
                <div class="flex items-center gap-2 mb-4">
                    <span class="text-lg">💨</span>
                    <h3 class="font-semibold text-data-blue-300">{{ __('Wind Waves') }}</h3>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <div class="text-xs text-ui-muted mb-1">{{ __('Height') }}</div>
                        <div class="text-xl font-bold text-ui-fg">
                            {{ $windWaveH !== null ? number_format($toWaveUnit($windWaveH), $waveDecimals) : '--' }}
                            <span class="text-sm text-ui-muted font-normal">{{ $waveUnit }}</span>
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-ui-muted mb-1">{{ __('Direction') }}</div>
                        <div class="text-xl font-bold text-ui-fg">{{ $waveCardinal }}</div>
                    </div>
                </div>
            </div>

            <div class="bg-indigo-950/30 rounded-2xl p-5 border border-indigo-900/30">
                <div class="flex items-center gap-2 mb-4">
                    <span class="text-lg">〜</span>
                    <h3 class="font-semibold text-data-indigo-300">{{ __('Swell') }}</h3>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <div class="text-xs text-ui-muted mb-1">{{ __('Height') }}</div>
                        <div class="text-xl font-bold text-ui-fg">
                            {{ $swellH !== null ? number_format($toWaveUnit($swellH), $waveDecimals) : '--' }}
                            <span class="text-sm text-ui-muted font-normal">{{ $waveUnit }}</span>
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-ui-muted mb-1">{{ __('Direction') }}</div>
                        <div class="text-xl font-bold text-ui-fg">
                            {{ $swellCardinal }}
                            @if($swellPeriod !== null)
                                <span class="text-xs text-ui-muted font-normal block">{{ number_format($swellPeriod, 0) }} s</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

        </div>

        {{-- Attribution --}}
        <div class="bg-ui-deep/40 rounded-2xl p-4 border border-ui-line/5 text-sm text-ui-muted">
            {{ __('Wave data provided by') }}
            <a href="https://open-meteo.com/en/docs/marine-weather-api" target="_blank" rel="noopener"
               class="text-data-blue-400 hover:underline">Open-Meteo Marine</a>
            — {{ __('free, model-based, global coverage') }}.
        </div>

        {{-- About waves (scientific) --}}
        <article class="bg-weather-card rounded-2xl border border-ui-line/10 p-6 md:p-8" aria-labelledby="water-waves-about-heading">
            <h2 id="water-waves-about-heading" class="text-xl font-semibold mb-4">{{ __('Water waves about heading') }}</h2>
            <div class="prose prose-invert prose-sm max-w-none text-ui-secondary space-y-4">
                <p>{{ __('Water waves about body 1') }}</p>
                <p>{{ __('Water waves about body 2') }}</p>
                <p>{{ __('Water waves about body 3') }}</p>
                <p class="text-data-blue-200/90 italic border-l-2 border-blue-500/50 pl-4">{{ __('Water waves about fun') }}</p>
            </div>
            <footer class="mt-6 pt-4 border-t border-ui-line/10">
                <p class="text-xs text-ui-subtle">{{ __('Water waves page sources') }}</p>
            </footer>
        </article>

    @endif

</div>
@endif

{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{-- TAB: SEA TEMPERATURE                                                       --}}
{{-- ══════════════════════════════════════════════════════════════════════════ --}}
@if($activeTab === 'temp')
<div class="space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold">🌡 {{ __('Sea Surface Temperature') }}</h1>
            <p class="text-ui-muted">
                {{ $waveLoc }}
                @if($waveData)· {{ __('Updated') }} {{ $waveUpdatedAt }}@endif
            </p>
        </div>
        <div class="text-right text-sm text-ui-subtle">
            {{ __('Data source') }}:
            <a href="https://open-meteo.com/en/docs/marine-weather-api" target="_blank" rel="noopener"
               class="text-data-blue-400 hover:underline">Open-Meteo Marine</a>
        </div>
    </div>

    @if(!$waveData || $sstC === null)
        <div class="bg-yellow-900/30 border border-yellow-700/50 rounded-2xl p-6">
            <h2 class="text-lg font-semibold text-ui-fg mb-2">⏳ {{ __('No sea temperature data yet') }}</h2>
            <p class="text-ui-secondary">{{ __('Sea temperature data is being fetched. Check back in a few minutes.') }}</p>
        </div>
    @else

        {{-- Main SST card --}}
        <div class="bg-weather-card rounded-2xl p-6 border border-ui-line/10">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-6">
                <div>
                    <div class="text-xs text-ui-muted uppercase tracking-wider mb-3">{{ __('Current Sea Surface Temp') }}</div>
                    <div class="flex items-end gap-3">
                        <span class="text-6xl font-bold text-ui-fg">
                            {{ $sstDisplay !== null ? number_format($sstDisplay, $sstDecimals) : '--' }}
                        </span>
                        <span class="text-2xl text-ui-muted mb-2">{{ $sstUnit }}</span>
                    </div>
                    @if($sstComfortKey)
                        <div class="mt-4">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium {{ $sstComfortClass }}">
                                <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                {{ __($sstComfortKey) }}
                            </span>
                        </div>
                    @endif
                </div>

                {{-- 5-day sparkline --}}
                <div class="w-full sm:w-72 flex-shrink-0">
                    <div class="text-xs text-ui-muted mb-2">{{ __('5-day trend') }}</div>
                    <div id="sst-chart" style="min-height:140px;"></div>
                </div>
            </div>
        </div>

        {{-- Comfort guide --}}
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
            <h3 class="font-semibold text-ui-fg mb-4">{{ __('Sea Temperature Guide') }}</h3>
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 text-sm">
                @foreach([
                    ['< 10 °C',  'sst_cold',        'bg-blue-900/40 text-data-blue-300 border-blue-800/30'],
                    ['10–15 °C', 'sst_cool',        'bg-cyan-900/40 text-data-cyan-300 border-cyan-800/30'],
                    ['15–20 °C', 'sst_comfortable', 'bg-teal-900/40 text-data-teal-300 border-teal-800/30'],
                    ['20–25 °C', 'sst_warm',        'bg-orange-900/40 text-data-orange-300 border-orange-800/30'],
                    ['> 25 °C',  'sst_hot',         'bg-red-900/40 text-data-red-300 border-red-800/30'],
                ] as [$range, $key, $cls])
                    <div class="rounded-xl p-3 border {{ $cls }} {{ $sstComfortKey === $key ? 'ring-2 ring-current ring-offset-1 ring-offset-weather-card' : '' }}">
                        <div class="font-medium">{{ __($key) }}</div>
                        <div class="text-xs opacity-70 mt-0.5">{{ $range }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Attribution --}}
        <div class="bg-ui-deep/40 rounded-2xl p-4 border border-ui-line/5 text-sm text-ui-muted">
            {{ __('Sea temperature data provided by') }}
            <a href="https://open-meteo.com/en/docs/marine-weather-api" target="_blank" rel="noopener"
               class="text-data-blue-400 hover:underline">Open-Meteo Marine</a>
            — {{ __('free, model-based, global coverage') }}.
        </div>

        {{-- About sea temperature (scientific) --}}
        <article class="bg-weather-card rounded-2xl border border-ui-line/10 p-6 md:p-8" aria-labelledby="water-temp-about-heading">
            <h2 id="water-temp-about-heading" class="text-xl font-semibold mb-4">{{ __('Water temp about heading') }}</h2>
            <div class="prose prose-invert prose-sm max-w-none text-ui-secondary space-y-4">
                <p>{{ __('Water temp about body 1') }}</p>
                <p>{{ __('Water temp about body 2') }}</p>
                <p>{{ __('Water temp about body 3') }}</p>
                <p class="text-data-orange-200/90 italic border-l-2 border-orange-500/50 pl-4">{{ __('Water temp about fun') }}</p>
            </div>
            <footer class="mt-6 pt-4 border-t border-ui-line/10">
                <p class="text-xs text-ui-subtle">{{ __('Water temp page sources') }}</p>
            </footer>
        </article>

    @endif

</div>
@endif

{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{-- TAB: RIVER LEVELS                                                          --}}
{{-- ══════════════════════════════════════════════════════════════════════════ --}}
@if($activeTab === 'rivers')
<div class="space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold">🏞 {{ __('River Levels') }}</h1>
            <p class="text-ui-muted">{{ __('Real-time gauge measurements') }} · Rijkswaterstaat</p>
        </div>
        <div class="text-right text-sm text-ui-subtle">
            {{ __('Data source') }}:
            <a href="https://waterinfo.rws.nl" target="_blank" rel="noopener"
               class="text-data-blue-400 hover:underline">Rijkswaterstaat</a>
        </div>
    </div>

    @if(!$riversEnabled)
        <div class="bg-blue-900/30 border border-blue-700/50 rounded-2xl p-6">
            <h2 class="text-lg font-semibold text-ui-fg mb-2">🔧 {{ __('River Levels not enabled') }}</h2>
            <p class="text-ui-secondary">{{ __('Enable river levels in settings to show real-time gauge readings.') }}</p>
        </div>

    @elseif(!$riverData)
        <div class="bg-yellow-900/30 border border-yellow-700/50 rounded-2xl p-6">
            <h2 class="text-lg font-semibold text-ui-fg mb-2">⏳ {{ __('No river data yet') }}</h2>
            <p class="text-ui-secondary">{{ __('River data is being fetched. Check back in a few minutes, or run the poller manually.') }}</p>
        </div>

    @else

        {{-- Station cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($riverData as $code => $station)
                @php
                    $riverTrend      = $station['trend'] ?? 'steady';
                    $riverTrendIcon  = match($riverTrend) { 'rising' => '↑', 'falling' => '↓', default => '→' };
                    $riverTrendClass = match($riverTrend) {
                        'rising'  => 'text-data-orange-400',
                        'falling' => 'text-data-blue-400',
                        default   => 'text-ui-muted',
                    };
                    $riverLevel     = $station['level_cm'] ?? null;
                    $riverUpdatedAt = ($station['updated_at'] ?? null)
                        ? Carbon::parse($station['updated_at'])->diffForHumans()
                        : null;
                    $riverStatus      = $station['status'] ?? 'normal';
                    $riverStatusBadge = match($riverStatus) {
                        'warning' => 'bg-orange-500/20 text-data-orange-400 border border-orange-500/30',
                        'watch'   => 'bg-yellow-500/20 text-data-yellow-400 border border-yellow-500/30',
                        default   => 'bg-emerald-500/20 text-data-emerald-400 border border-emerald-500/30',
                    };
                    $riverStatusDot = match($riverStatus) {
                        'warning' => 'bg-orange-400',
                        'watch'   => 'bg-yellow-400',
                        default   => 'bg-emerald-400',
                    };
                    $riverStatusLabel = match($riverStatus) {
                        'warning' => __('Warning'),
                        'watch'   => __('Watch'),
                        default   => __('Normal'),
                    };
                @endphp
                <div class="bg-emerald-900/20 rounded-2xl p-5 border border-emerald-800/30">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <div class="text-xs text-data-emerald-400 uppercase tracking-wider font-medium">
                                {{ $station['river'] }}
                            </div>
                            <div class="font-semibold text-ui-fg mt-0.5">{{ $station['name'] }}</div>
                        </div>
                        <span class="text-2xl">🏞</span>
                    </div>
                    <div class="flex items-end gap-1">
                        <span class="text-3xl font-bold text-ui-fg">
                            {{ $riverLevel !== null ? number_format($riverLevel, 0) : '--' }}
                        </span>
                        <span class="text-ui-muted mb-1 text-sm">cm NAP</span>
                    </div>
                    <div class="mt-2 flex items-center justify-between">
                        <div class="text-sm {{ $riverTrendClass }} font-medium">
                            {{ $riverTrendIcon }}
                            {{ match($riverTrend) {
                                'rising'  => __('Rising'),
                                'falling' => __('Falling'),
                                default   => __('Steady'),
                            } }}
                        </div>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium {{ $riverStatusBadge }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $riverStatusDot }}"></span>
                            {{ $riverStatusLabel }}
                        </span>
                    </div>
                    @if($riverUpdatedAt)
                        <div class="mt-2 text-xs text-ui-subtle">{{ __('Updated') }} {{ $riverUpdatedAt }}</div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Attribution --}}
        <div class="bg-ui-deep/40 rounded-2xl p-4 border border-ui-line/5 text-sm text-ui-muted">
            {{ __('River level data provided by') }}
            <a href="https://waterinfo.rws.nl" target="_blank" rel="noopener"
               class="text-data-blue-400 hover:underline">Rijkswaterstaat WaterWebservices</a>
            — {{ __('real-time gauge measurements') }}.
        </div>

        {{-- About river levels (scientific) --}}
        <article class="bg-weather-card rounded-2xl border border-ui-line/10 p-6 md:p-8" aria-labelledby="water-rivers-about-heading">
            <h2 id="water-rivers-about-heading" class="text-xl font-semibold mb-4">{{ __('Water rivers about heading') }}</h2>
            <div class="prose prose-invert prose-sm max-w-none text-ui-secondary space-y-4">
                <p>{{ __('Water rivers about body 1') }}</p>
                <p>{{ __('Water rivers about body 2') }}</p>
                <p>{{ __('Water rivers about body 3') }}</p>
                <p class="text-data-emerald-200/90 italic border-l-2 border-emerald-500/50 pl-4">{{ __('Water rivers about fun') }}</p>
            </div>
            <footer class="mt-6 pt-4 border-t border-ui-line/10">
                <p class="text-xs text-ui-subtle">{{ __('Water rivers page sources') }}</p>
            </footer>
        </article>

    @endif

</div>
@endif

{{-- ── Chart data (only for the active tab that uses charts) ──────────────── --}}
@if($activeTab === 'tides')
@php
    $tideChartData = [
        'series'      => $chartSeries ?? [],
        'annotations' => $chartAnnotations ?? [],
        'nowMs'       => $nowMs,
        'unitLabel'   => $unitLabel  ?? 'cm',
        'datumLabel'  => $datumLabel ?? 'MSL',
        'decimals'    => $levelDecimals ?? 0,
    ];
@endphp
<script type="application/json" id="tide-chart-data">@json($tideChartData)</script>
@elseif(in_array($activeTab, ['waves', 'temp']))
@php
    $waveChartData = [
        'series'    => $waveSeries ?? [],
        'nowMs'     => $nowMs,
        'unitLabel' => $waveUnit ?? 'm',
        'decimals'  => $waveDecimals ?? 2,
    ];
    $sstChartData = [
        'series'    => $sstSeries ?? [],
        'nowMs'     => $nowMs,
        'unitLabel' => $sstUnit ?? '°C',
        'decimals'  => $sstDecimals ?? 1,
    ];
@endphp
<script type="application/json" id="wave-chart-data">@json($waveChartData)</script>
<script type="application/json" id="sst-chart-data">@json($sstChartData)</script>
@endif

@endsection
