<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-public-theme="weathernode" data-custom-theme data-color-mode="dark" class="dark has-weather-bg">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ __('Live preview') }}</title>
    <link rel="stylesheet" href="{{ asset('css/public-theme.css') }}?v={{ filemtime(public_path('css/public-theme.css')) }}">
    @vite('resources/css/app.css')
    <style id="preview-colours"></style>
</head>
<body class="has-weather-bg font-sans text-ui-fg p-5 min-h-screen">
    {{-- Use the dashboard's background layer and shared FX/Flat CSS, not a separate approximation. --}}
    <div id="preview-background" class="weather-bg weather-bg--animated" aria-hidden="true"></div>
    <header class="glass rounded-xl p-4 flex items-center justify-between gap-3 border border-ui-line/10">
        <div><h1 class="text-lg font-bold" id="preview-name">WeatherNode</h1><p class="text-ui-muted text-sm">{{ __('Live preview') }}</p></div>
        <span class="px-3 py-2 rounded-lg bg-ui-accent-strong text-on-accent">{{ __('Home') }}</span>
    </header>
    <main class="space-y-4 mt-5">
        <div class="grid grid-cols-2 gap-4">
            <section class="sortable-widget bg-gradient-to-br from-weather-card to-weather-card/50 card-3d rounded-2xl p-4 glow border border-ui-line/10 relative overflow-hidden">
                {{-- Clear daytime sample of the dashboard's sky visualization; Flat hides this layer. --}}
                <div class="absolute inset-0 pointer-events-none fx-visual" aria-hidden="true">
                    <svg class="w-full h-full opacity-50" viewBox="0 0 200 100" preserveAspectRatio="xMidYMid slice">
                        <defs><linearGradient id="preview-sky" x1="0%" y1="0%" x2="0%" y2="100%"><stop offset="0%" stop-color="rgba(96,165,250,0.35)"/><stop offset="100%" stop-color="rgba(59,130,246,0.4)"/></linearGradient></defs>
                        <rect width="200" height="100" fill="url(#preview-sky)"/>
                        <g opacity="0.35"><circle cx="120" cy="20" r="12" fill="rgba(251,191,36,0.4)"/><circle cx="120" cy="20" r="6" fill="rgba(251,191,36,0.7)"/></g>
                    </svg>
                </div>
                <div class="relative"><h2 class="text-ui-muted text-sm">{{ __('Temperature') }}</h2><p class="text-4xl font-bold mt-3 text-ui-fg">18.7°</p><p class="text-ui-secondary text-sm mt-2">{{ __('Feels like') }} <span class="text-data-amber-500">19°C</span></p></div>
            </section>
            <section class="sortable-widget bg-weather-card border border-ui-line/10 rounded-2xl p-4"><h2 class="text-ui-muted text-sm">{{ __('Wind') }}</h2><p class="text-4xl font-bold mt-3">8.6</p><p class="text-ui-secondary text-sm mt-2">km/h · <span class="text-data-cyan-500">N 351°</span></p></section>
        </div>
        <section class="sortable-widget bg-weather-card border border-ui-line/10 rounded-2xl p-4">
            <div class="flex justify-between items-center"><h2 class="font-semibold">{{ __('Forecast') }}</h2><span class="text-ui-link text-sm">{{ __('More') }} →</span></div>
            <div class="grid grid-cols-3 gap-2 mt-3 text-center">
                @foreach(['Today', 'Tomorrow', 'Friday'] as $day)<div class="bg-ui-overlay/5 rounded-xl p-3"><p class="text-ui-secondary text-xs">{{ __($day) }}</p><p class="text-2xl my-2" aria-hidden="true">☀</p><p class="text-data-amber-500">18° <span class="text-data-cyan-500">/ 14°</span></p></div>@endforeach
            </div>
            <svg viewBox="0 0 400 65" class="w-full mt-4" aria-hidden="true"><path d="M0 55H400M0 25H400" fill="none" style="stroke:rgb(var(--wn-grid))"/><path d="M0 50L70 35L130 40L200 10L270 30L340 25L400 50" fill="none" stroke="#f59e0b" stroke-width="3"/></svg>
            <p class="text-xs text-ui-subtle mt-2">{{ __('Updated just now') }}</p>
        </section>
        <section class="bg-ui-overlay/5 border border-ui-line/10 rounded-xl p-4 flex justify-between gap-3"><span class="text-data-green-500">● {{ __('No active alerts') }}</span><span class="text-ui-link">{{ __('Details') }} →</span></section>
    </main>
    <script type="application/json" id="preview-tokens">@json(array_keys(\App\Support\CustomTheme::TOKENS))</script>
    <script type="module" src="{{ asset('js/theme-preview.js') }}?v={{ filemtime(public_path('js/theme-preview.js')) }}"></script>
</body>
</html>
