@extends('weather.layout')

@section('title', __($pageTitle) . ' - ' . \App\Models\Setting::stationName())
@section('meta_description', __($metaDescription))

@push('styles')
<style>
    .legal-content h1,
    .legal-content h2,
    .legal-content h3,
    .legal-content h4 {
        color: rgb(255 255 255);
        font-weight: 700;
        margin-top: 1.25rem;
        margin-bottom: 0.75rem;
        line-height: 1.25;
    }

    .legal-content h1 {
        font-size: 1.5rem;
        margin-top: 0;
    }

    .legal-content h2 {
        font-size: 1.125rem;
    }

    .legal-content p,
    .legal-content li {
        color: rgb(209 213 219);
        line-height: 1.7;
    }

    .legal-content ul {
        list-style: disc;
        margin-left: 1.25rem;
        margin-top: 0.5rem;
        margin-bottom: 0.75rem;
    }

    .legal-content a {
        color: rgb(96 165 250);
        text-decoration: underline;
    }

    .legal-content img {
        max-width: min(100%, 260px);
        height: auto;
        display: block;
        margin: 0.75rem auto 1rem;
    }
</style>
@endpush

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('home') }}" class="text-sm text-ui-muted hover:text-ui-fg transition-colors">← {{ __('Back to dashboard') }}</a>
    </div>

    <article class="bg-weather-card rounded-2xl border border-ui-line/10 p-5 md:p-8">
        <header class="mb-6 pb-4 border-b border-ui-line/10">
            <h1 class="text-2xl md:text-3xl font-bold text-ui-fg">{{ __($pageTitle) }}</h1>
            <p class="text-xs text-ui-muted mt-2">{{ __('Last updated') }}: {{ $lastUpdated }}</p>
        </header>

        <div class="legal-content space-y-4">
            @if(!empty($pageContentText))
                <pre class="whitespace-pre-wrap break-words text-sm text-ui-body bg-black/30 border border-ui-line/10 rounded-xl p-4">{{ $pageContentText }}</pre>
            @else
                {!! $pageContentHtml !!}
            @endif
        </div>
    </article>
</div>
@endsection
