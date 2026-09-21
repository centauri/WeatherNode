@extends('weather.layout')

@section('title', __('Lightning') . ' - ' . \App\Models\Setting::stationName())

@section('meta_description', __('Lightning page meta description', ['location' => $stationLocation]))
@section('og_image', route('og.generic', ['page' => 'lightning']))

@section('content')
<style>
    /* Blitzortung sometimes reserves a left gutter/panel in embedded mode.
       We can't control their UI, but we can crop the gutter visually. */
    .blitz-frame-wrap { --trim-left: 120px; }
    @media (max-width: 640px) { .blitz-frame-wrap { --trim-left: 0px; } }
    .blitz-frame {
        width: calc(100% + var(--trim-left));
        height: 100%;
        transform: translateX(calc(var(--trim-left) * -1));
        border: 0;
    }
</style>

<div class="max-w-6xl mx-auto space-y-8">
    <header>
        <h1 class="text-2xl md:text-3xl font-bold">{{ __('Lightning') }}</h1>
        <p class="text-ui-muted mt-1">{{ __('Lightning page intro', ['location' => $stationLocation]) }}</p>
    </header>

    <div class="bg-weather-card rounded-2xl border border-ui-line/10 overflow-hidden">
        <div data-theme-surface="dark" class="blitz-frame-wrap relative bg-black/10"
             style="height: clamp(380px, 68vh, 760px); height: clamp(380px, 68dvh, 760px);">
            <iframe
                class="blitz-frame absolute inset-0"
                src="{{ $blitzUrl }}"
                title="{{ __('Lightning map title') }}"
                loading="lazy"
                referrerpolicy="no-referrer"
                allow="fullscreen">
            </iframe>
        </div>
        <div class="px-4 py-3 text-xs text-ui-muted border-t border-ui-line/10">
            {{ __('Data source') }}: Blitzortung.org
        </div>
    </div>

    <article class="bg-weather-card rounded-2xl border border-ui-line/10 p-6 md:p-8" aria-labelledby="lightning-about-heading">
        <h2 id="lightning-about-heading" class="text-xl font-semibold mb-4">{{ __('Lightning page about heading') }}</h2>
        <div class="prose prose-invert prose-sm max-w-none text-ui-secondary space-y-4">
            <p>{{ __('Lightning page about body 1') }}</p>
            <p>{{ __('Lightning page about body 2') }}</p>
            <p>{{ __('Lightning page about body 3') }}</p>
        </div>
        <footer class="mt-6 pt-4 border-t border-ui-line/10">
            <p class="text-xs text-ui-subtle">{{ __('Lightning page sources') }}</p>
        </footer>
    </article>
</div>
@endsection
