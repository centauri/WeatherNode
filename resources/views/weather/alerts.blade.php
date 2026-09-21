@extends('weather.layout')

@section('title', __('Weather Alerts') . ($regionName ? ' — ' . $regionName : '') . ' | ' . \App\Models\Setting::stationName())

@php
    $_activeCount = count($allAlerts);
    $_metaDesc    = $_activeCount > 0
        ? $_activeCount . ' ' . __('active alerts for') . ' ' . $regionName . '.'
        : __('No active weather alerts for') . ' ' . $regionName . '.';
@endphp
@section('meta_description', $_metaDesc)

@push('head_scripts')
    {{-- JSON-LD structured data — SpecialAnnouncement per active external alert --}}
    @if(count($externalAlerts) > 0)
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@type": "ItemList",
        "name": "{{ __('Weather Alerts') }}",
        "itemListElement": [
            @foreach($externalAlerts as $i => $alert)
            {
                "@type": "SpecialAnnouncement",
                "name": "{{ e($alert['title']) }}",
                "text": "{{ e($alert['description']) }}",
                "datePosted": "{{ now()->toIso8601String() }}",
                "category": "https://www.wikidata.org/wiki/Q1183543",
                "@id": "{{ url('/alerts') }}#alert-{{ $i }}"
            }{{ !$loop->last ? ',' : '' }}
            @endforeach
        ]
    }
    </script>
    @endif
@endpush

@section('content')
<div x-data="{
    async refreshAlerts() {
        try {
            const html = await fetch('{{ route('alerts.partial') }}').then(r => r.text());
            const wrapper = document.getElementById('alerts-wrapper');
            if (wrapper) wrapper.innerHTML = html;
        } catch(e) {}
    },
    init() {
        setInterval(() => this.refreshAlerts(), 300000); // 5 minutes
    }
}">

    {{-- Page header --}}
    <div class="mb-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-ui-fg">{{ __('Weather Alerts') }}</h1>
                @if($regionName)
                    <p class="text-sm text-ui-muted mt-0.5">{{ $regionName }}</p>
                @endif
            </div>
            <div class="flex items-center gap-2 mt-1">
                @if($_activeCount > 0)
                    @php
                        $maxSev   = max(array_column($allAlerts, 'severity'));
                        $maxColor = match(true) {
                            $maxSev >= 4 => '#BB2739',
                            $maxSev >= 3 => '#F19E39',
                            default      => '#FBEA55',
                        };
                    @endphp
                    <span data-weather-colour-text class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold"
                          style="background: {{ $maxColor }}22; color: {{ $maxColor }}; border: 1px solid {{ $maxColor }}44">
                        <span class="w-2 h-2 rounded-full animate-pulse" style="background: {{ $maxColor }}"></span>
                        {{ $_activeCount }} {{ $_activeCount === 1 ? __('active warning') : __('active warnings') }}
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/20 text-data-emerald-400 border border-emerald-500/30">
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                        {{ __('All clear') }}
                    </span>
                @endif
            </div>
        </div>

        @unless($enabled)
            <div class="mt-4 p-3 rounded-lg bg-ui-soft/50 text-xs text-ui-muted">
                {{ __('Weather alert monitoring is disabled. Enable it in admin settings.') }}
            </div>
        @endunless
    </div>

    {{-- Main content — server-rendered for SEO, swapped on AJAX refresh --}}
    <div id="alerts-wrapper">
        @include('weather.alerts-partial', [
            'externalAlerts' => $externalAlerts,
            'statusSections' => $statusSections,
            'regionName'     => $regionName,
        ])
    </div>
</div>
@endsection
