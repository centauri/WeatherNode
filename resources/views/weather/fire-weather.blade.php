@extends('weather.layout')

@section('title', __('Fire Weather') . ' — ' . $stationLocation . ' - ' . $stationName)
@section('meta_description', __('Fire weather page meta description', ['location' => $stationLocation]))
@section('og_image', route('og.fire-weather'))

@push('head_scripts')
    @vite('resources/js/pages/fire-weather-charts.js')
    @php
        $jsonLd = json_encode([
            '@context'    => 'https://schema.org',
            '@type'       => 'WebPage',
            'name'        => __('Fire Weather') . ' — ' . $stationLocation,
            'description' => __('Fire weather page meta description', ['location' => $stationLocation]),
            'url'         => url()->current(),
            'inLanguage'  => str_replace('-', '_', app()->getLocale()),
            'about'       => [
                '@type'       => 'Thing',
                'name'        => 'Angström Fire Weather Index',
                'description' => 'A European fire weather index calculated from maximum daily temperature and minimum relative humidity.',
            ],
            'isPartOf'    => [
                '@type' => 'WebSite',
                'name'  => $stationName,
                'url'   => url('/'),
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    @endphp
    <script type="application/ld+json">{!! $jsonLd !!}</script>
@endpush

@section('content')
@php
    $level     = $current['danger_level']   ?? 'unknown';
    $index     = $current['angstrom_index'] ?? null;
    $cdd       = $current['consecutive_dry'] ?? 0;
    $rain7     = $current['rain_7d']  ?? null;
    $rain30    = $current['rain_30d'] ?? null;
    $tempHigh  = $current['temp_high']    ?? null;
    $humidLow  = $current['humidity_low'] ?? null;

    $levelLabel = match($level) {
        'extreme'  => __('Extreme'),
        'high'     => __('High'),
        'moderate' => __('Moderate'),
        'low'      => __('Low'),
        default    => __('No data'),
    };

    $levelDesc = match($level) {
        'extreme'  => __('fire_danger_extreme_desc'),
        'high'     => __('fire_danger_high_desc'),
        'moderate' => __('fire_danger_moderate_desc'),
        'low'      => __('fire_danger_low_desc'),
        default    => __('fire_danger_unknown_desc'),
    };

    $bgClass    = $current['danger_bg_color'] ?? 'bg-ui-disabled/20 border-ui-border/40';
    $textClass  = $current['danger_color']    ?? 'text-ui-muted';

    $formatNum = fn(?float $v, int $d = 1) => $v !== null ? number_format($v, $d) : '--';
@endphp

<div class="space-y-6">

    {{-- Header --}}
    <div>
        <h1 class="text-2xl md:text-3xl font-bold">🔥 {{ __('Fire Weather') }}</h1>
        <p class="text-ui-muted">{{ __('Fire weather page intro', ['location' => $stationLocation]) }}</p>
    </div>

    {{-- Current danger badge + key metrics --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- Big danger badge --}}
        <div class="lg:col-span-1 bg-weather-card rounded-2xl p-6 border border-ui-line/10 flex flex-col items-center justify-center text-center gap-3">
            <div class="text-5xl">🔥</div>
            <div class="text-sm uppercase tracking-widest text-ui-muted">{{ __('Fire Danger') }}</div>
            <div class="px-6 py-3 rounded-xl border {{ $bgClass }} flex flex-col items-center gap-1">
                <span class="text-3xl font-black {{ $textClass }}">{{ $levelLabel }}</span>
                @if($index !== null)
                    <span class="text-xs text-ui-muted">{{ __('Angström') }}: {{ $formatNum($index, 2) }}</span>
                @endif
            </div>
            <p class="text-sm text-ui-muted max-w-xs">{{ $levelDesc }}</p>
        </div>

        {{-- Metrics grid --}}
        <div class="lg:col-span-2 grid grid-cols-2 gap-4">

            <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
                <div class="text-xs uppercase tracking-wide text-ui-subtle mb-1">{{ __('Max temperature') }}</div>
                <div class="text-3xl font-bold text-data-amber-500">
                    {{ $tempHigh !== null ? $formatNum($tempHigh) . ' °C' : '--' }}
                </div>
                <div class="text-xs text-ui-subtle mt-1">{{ __('Used for index calculation') }}</div>
            </div>

            <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
                <div class="text-xs uppercase tracking-wide text-ui-subtle mb-1">{{ __('Min humidity') }}</div>
                <div class="text-3xl font-bold text-data-blue-400">
                    {{ $humidLow !== null ? $formatNum($humidLow, 0) . ' %' : '--' }}
                </div>
                <div class="text-xs text-ui-subtle mt-1">{{ __('Used for index calculation') }}</div>
            </div>

            <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
                <div class="text-xs uppercase tracking-wide text-ui-subtle mb-1">{{ __('Consecutive dry days') }}</div>
                <div class="text-3xl font-bold {{ $cdd >= 10 ? 'text-data-orange-400' : 'text-ui-body' }}">
                    {{ $cdd }}
                </div>
                <div class="text-xs text-ui-subtle mt-1">{{ __('Days with < 1 mm rain') }}</div>
            </div>

            <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
                <div class="text-xs uppercase tracking-wide text-ui-subtle mb-1">{{ __('Precipitation (30 days)') }}</div>
                <div class="text-3xl font-bold text-data-indigo-500">
                    {{ $rain30 !== null ? $formatNum($rain30) . ' mm' : '--' }}
                </div>
                <div class="text-xs text-ui-subtle mt-1">
                    {{ __('Last 7 days') }}: {{ $rain7 !== null ? $formatNum($rain7) . ' mm' : '--' }}
                </div>
            </div>

        </div>
    </div>

    {{-- Historical chart --}}
    <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
        <h2 class="text-lg font-semibold mb-4">{{ __('Angström Index — last 90 days') }}</h2>
        <div id="fire-weather-chart" class="w-full" style="min-height:260px;"></div>
    </div>

    {{-- Danger scale legend --}}
    <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
        <h2 class="text-lg font-semibold mb-4">{{ __('Danger scale') }}</h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            @foreach([
                ['low',      '> 4.0',      __('Low'),      'bg-green-400/20  border-green-400/40',  'text-data-green-400'],
                ['moderate', '2.5 – 4.0',  __('Moderate'), 'bg-yellow-400/20 border-yellow-400/40', 'text-data-yellow-400'],
                ['high',     '1.0 – 2.5',  __('High'),     'bg-orange-500/20 border-orange-500/40', 'text-data-orange-400'],
                ['extreme',  '< 1.0',      __('Extreme'),  'bg-red-600/20    border-red-600/40',    'text-data-red-400'],
            ] as [$lvl, $range, $label, $bg, $tc])
                <div class="rounded-xl border {{ $bg }} p-4 text-center">
                    <div class="text-lg font-bold {{ $tc }}">{{ $label }}</div>
                    <div class="text-xs text-ui-muted mt-1">{{ __('Angström') }} {{ $range }}</div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Info cards: Angström + FWI side by side on larger screens --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        {{-- Angström info --}}
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10 text-sm text-ui-muted space-y-2">
            <h2 class="text-base font-semibold text-ui-body">{{ __('About the Angström Index') }}</h2>
            <p>{{ __('angstrom_info_text') }}</p>
            <p class="font-mono text-xs text-ui-subtle bg-ui-overlay/5 rounded p-2 mt-2">{{ __('angstrom_formula_text') }}</p>
        </div>

        {{-- FWI info --}}
        <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10 text-sm text-ui-muted space-y-2">
            <h2 class="text-base font-semibold text-ui-body">{{ __('About the Fire Weather Index (FWI)') }}</h2>
            <p>{{ __('fwi_info_text') }}</p>
        </div>

    </div>

</div>

{{-- Chart data for JS --}}
@php
    $chartData = [
        'dates'  => $history['dates']  ?? [],
        'values' => $history['values'] ?? [],
    ];
@endphp
<script type="application/json" id="fire-chart-data">@json($chartData)</script>
@endsection
