<!DOCTYPE html>
<html lang="{{ $jsLocale ?? app()->getLocale() }}" class="dark has-weather-bg">
@php
    $activeLocale = $activeLocale ?? app()->getLocale();
    $activeUnits = $activeUnits ?? 'metric';
    $jsLocale = $jsLocale ?? $activeLocale;
    $localeOptions = $localeOptions ?? config('localization.locales', []);
    $unitOptions = $unitOptions ?? config('localization.units', []);
    $unitShort = match ($activeUnits) {
        'imperial' => 'F',
        'uk' => 'UK',
        'scandinavia' => 'm/s',
        default => 'C',
    };
@endphp
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        $toSeoString = function ($value, string $locale, string $fallback = ''): string {
            // Some settings may be stored as per-locale arrays (e.g. JSON). Normalize to a safe string.
            if (is_array($value)) {
                $value = $value[$locale]
                    ?? $value[str_replace('-', '_', $locale)]
                    ?? $value[explode('-', $locale)[0] ?? $locale]
                    ?? (count($value) ? reset($value) : $fallback);
            }

            if ($value instanceof \Stringable) {
                return (string) $value;
            }

            if (is_null($value)) {
                return $fallback;
            }

            if (is_bool($value)) {
                return $value ? '1' : '0';
            }

            if (is_scalar($value)) {
                return (string) $value;
            }

            return $fallback;
        };

        $seoSiteTitleRaw = \App\Models\Setting::getValue('seo.site_title', \App\Models\Setting::stationName());
        $seoSiteDescriptionRaw = \App\Models\Setting::getValue('seo.site_description', __('Live weather in Uitgeest, North Holland. Live weather data from a local station.'));
        $seoSiteKeywordsRaw = \App\Models\Setting::getValue('seo.site_keywords', '');
        $seoOgImageRaw = \App\Models\Setting::getValue('seo.og_image', '');

        $seoSiteTitle = $toSeoString($seoSiteTitleRaw, $activeLocale, \App\Models\Setting::stationName());
        $seoSiteDescription = $toSeoString($seoSiteDescriptionRaw, $activeLocale, '');
        $seoSiteKeywords = $toSeoString($seoSiteKeywordsRaw, $activeLocale, '');
        $seoOgImage = '';
        if (is_string($seoOgImageRaw) && trim($seoOgImageRaw) !== '') {
            $seoOgImageRaw = trim($seoOgImageRaw);
            $seoOgImage = str_starts_with($seoOgImageRaw, 'http://') || str_starts_with($seoOgImageRaw, 'https://')
                ? $seoOgImageRaw
                : url($seoOgImageRaw);
        }
        // Dynamic OG image: individual views push a URL via @section('og_image').
        // When og.enabled is true, this overrides the static admin-uploaded image.
        $dynamicOgImage = '';
        if (\App\Models\Setting::getValue('og.enabled', false)) {
            $yielded = trim($__env->yieldContent('og_image'));
            if ($yielded !== '') {
                $dynamicOgImage = $yielded;
            }
        }
        $resolvedOgImage = $dynamicOgImage ?: $seoOgImage;
        $seoTitle = trim($__env->yieldContent('title')) !== '' ? trim($__env->yieldContent('title')) : $seoSiteTitle;
        $seoDescription = trim($__env->yieldContent('meta_description')) !== '' ? trim($__env->yieldContent('meta_description')) : $seoSiteDescription;
        // Locale-aware canonical: matches the hreflang alternates exactly and collapses
        // the default-locale duplicate (prefixed /nl-nl/x and unprefixed /x → one canonical).
        $seoCanonical = localeCanonicalUrl($activeLocale);
        $seoTwitterCard = $resolvedOgImage ? 'summary_large_image' : 'summary';
    @endphp

    <title>{{ $seoTitle }}</title>
    <meta name="description" content="{{ $seoDescription }}">
    @if(is_string($seoSiteKeywords) && trim($seoSiteKeywords) !== '')
        <meta name="keywords" content="{{ trim($seoSiteKeywords) }}">
    @endif
    <link rel="canonical" href="{{ $seoCanonical }}">
    @foreach($localeOptions as $code => $meta)
        <link rel="alternate" hreflang="{{ $code }}" href="{{ localeCanonicalUrl($code) }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ localeCanonicalUrl($defaultLocale) }}">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $seoSiteTitle }}">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:url" content="{{ $seoCanonical }}">
    <meta property="og:locale" content="{{ str_replace('-', '_', app()->getLocale()) }}">
    @if($resolvedOgImage)
        <meta property="og:image" content="{{ $resolvedOgImage }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:type" content="image/png">
    @endif

    <meta name="twitter:card" content="{{ $seoTwitterCard }}">
    <meta name="twitter:title" content="{{ $seoTitle }}">
    <meta name="twitter:description" content="{{ $seoDescription }}">
    @if($resolvedOgImage)
        <meta name="twitter:image" content="{{ $resolvedOgImage }}">
    @endif
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Theme Color for Safari/Chrome - matches header glass effect -->
    <meta name="theme-color" content="#1a2332">
    <meta name="color-scheme" content="dark light">
    
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="WeatherNode">
    <link rel="apple-touch-icon" sizes="180x180" href="/images/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/images/favicon-16x16.png">
    <link rel="icon" type="image/x-icon" href="/images/favicon.ico">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    
    <!-- Page-specific modules that must register before Alpine.start() -->
    @stack('head_scripts')

    <!-- Compiled CSS & JS via Vite -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <!-- External Libraries -->
    {{-- Alpine is bundled via Vite (resources/js/app.js). Loading it again via CDN causes double init and extra CPU/GPU work. --}}

	    <script>
	        window.Meteo = {
	            activeLocale: @json($activeLocale),
	            activeUnits: @json($activeUnits),
	            jsLocale: @json($jsLocale),
	            stationTimezone: @json($stationTimezone ?? 'UTC'),
	            rainRateUnit: @json(\App\Models\Setting::getValue('display.rainrate_unit', '/h')),
	            temperatureDecimals: @json((int) \App\Models\Setting::getValue('display.temperature_decimals', 1)),
	            windDecimals: @json((int) \App\Models\Setting::getValue('display.wind_decimals', 1)),
	            rainDecimals: @json((int) \App\Models\Setting::getValue('display.rain_decimals', 1)),
	            pressureDecimals: @json((int) \App\Models\Setting::getValue('display.pressure_decimals', 1)),
	            apiKey: @json($publicApiKey),
	            iconsAnimatedBaseUrl: @json($weatherIconsBaseUrl ?? '/icons/weather'),
	            iconsStaticBaseUrl: '/icons/weather-static',
	            iconsBaseUrl: @json($weatherIconsBaseUrl ?? '/icons/weather'),
	        };
        window.Meteo.apiHeaders = function (extraHeaders) {
            const headers = Object.assign({ 'Accept': 'application/json' }, extraHeaders || {});
            if (window.Meteo.apiKey) {
                headers['X-API-Key'] = window.Meteo.apiKey;
            }
            return headers;
        };
        window.Meteo.appendApiKey = function (url) {
            if (!window.Meteo.apiKey) return url;
            const isAbsolute = url.startsWith('http://') || url.startsWith('https://');
            if (isAbsolute && !url.startsWith(window.location.origin)) {
                return url;
            }
            // For tile URLs with Leaflet template variables {z}/{x}/{y}, append API key without encoding
            // to preserve the template variables for Leaflet to process
            if (url.includes('{z}') || url.includes('{x}') || url.includes('{y}')) {
                const separator = url.includes('?') ? '&' : '?';
                return url + separator + 'api_key=' + encodeURIComponent(window.Meteo.apiKey);
            }
            const normalized = new URL(url, window.location.origin);
            if (!normalized.searchParams.has('api_key')) {
                normalized.searchParams.set('api_key', window.Meteo.apiKey);
            }
            return normalized.origin === window.location.origin
                ? normalized.pathname + normalized.search
                : normalized.toString();
        };
    </script>
    @stack('styles')

    {{-- Custom head code from admin (ads, analytics, tracking, etc.).
         SECURITY: this is rendered unescaped by design so admins can inject
         analytics/ad snippets. It executes on every page for every visitor, so the
         `integrations.head_code` setting is an admin-only trust boundary — treat
         write access to it as equivalent to full site script execution. It is the
         reason the CSP (see App\Http\Middleware\SecurityHeaders) allows inline
         scripts and is currently Report-Only. --}}
    @php
        $customHeadCode = \App\Models\Setting::getValue('integrations.head_code', '');
    @endphp
    @if(is_string($customHeadCode) && trim($customHeadCode) !== '')
        {!! $customHeadCode !!}
    @endif
</head>
<body class="has-weather-bg text-white min-h-screen font-sans {{ ($siteTheme ?? 'fx') === 'flat' ? 'theme-flat effects-disabled' : '' }}"
      data-side-rails="enabled"
      x-data="{ backgroundEffectsEnabled: localStorage.getItem('backgroundEffectsEnabled') !== 'false', toggleBackgroundEffects() { this.backgroundEffectsEnabled = !this.backgroundEffectsEnabled; localStorage.setItem('backgroundEffectsEnabled', this.backgroundEffectsEnabled); } }"
      :class="(@json($siteTheme ?? 'fx') !== 'flat') ? { 'effects-disabled': !backgroundEffectsEnabled } : {}">
    <!-- Site wrapper: clips weather effects overflow without affecting AdSense side rail ads
         which are injected by Google as direct children of <body> outside this wrapper. -->
    <div id="site-wrapper">

    <!-- Fixed background layer (never blocks body scroll) -->
    <div class="weather-bg"
         :class="(@json($siteTheme ?? 'fx') !== 'flat') ? (backgroundEffectsEnabled ? 'weather-bg--animated' : 'weather-bg--static') : 'weather-bg--static'"
         google-side-rail-overlap="true"
         aria-hidden="true"></div>

    <!-- Top Bar: Compact Header -->
    <header id="site-header" class="glass border-b border-white/10 sticky top-0 z-50 floating-header" google-side-rail-overlap="false">
        <div class="max-w-7xl mx-auto px-4 py-2">
            <!-- Mobile: Two rows -->
            <div class="flex flex-col gap-2 lg:hidden">
                <!-- Row 1: Logo and controls -->
                <div class="flex items-center justify-between">
                    <a href="{{ route('home') }}" class="flex items-center gap-3">
                        <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-cyan-400 rounded-lg flex items-center justify-center shadow-lg shadow-blue-500/30">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-lg font-bold">{{ \App\Models\Setting::stationName() }}</h1>
                            <p class="text-xs text-gray-400">{{ \App\Models\Setting::stationLocation() }}</p>
                        </div>
                    </a>
                    <div class="flex items-center gap-2">
                        @auth
                            @if(auth()->user()->is_admin)
                                @if(($siteTheme ?? 'fx') !== 'flat')
                                    <button @click="toggleBackgroundEffects()" :class="backgroundEffectsEnabled ? 'bg-emerald-600 hover:bg-emerald-500' : 'bg-gray-600 hover:bg-gray-500'" class="px-2 py-1 text-xs rounded transition-colors flex items-center gap-1" :title="backgroundEffectsEnabled ? '{{ __('Disable background effects') }}' : '{{ __('Enable background effects') }}'">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"></path></svg>
                                        <span class="relative">FX<span x-show="!backgroundEffectsEnabled" class="absolute inset-0 flex items-center justify-center"><span class="w-full h-0.5 bg-current rotate-45 absolute"></span></span></span>
                                    </button>
                                @endif
                                <a href="{{ route('admin.dashboard') }}" class="px-3 py-1 text-xs bg-blue-600 hover:bg-blue-500 rounded transition-colors">{{ __('Admin') }}</a>
                            @endif
                        @endauth
                        <div class="relative" x-data="{ openLang: false }">
                            <button type="button" class="px-2 py-1 text-xs bg-white/10 rounded hover:bg-white/20 transition-colors" @click="openLang = !openLang">
                                {{ $localeOptions[$activeLocale]['short'] ?? strtoupper($activeLocale) }}
                            </button>
                            <div x-cloak x-show="openLang" @click.outside="openLang = false" class="absolute right-0 mt-2 w-40 bg-weather-card border border-white/10 rounded-lg shadow-lg overflow-hidden text-xs z-50">
                                @foreach($localeOptions as $code => $meta)
                                    <a href="{{ localeUrl($code) }}" class="block px-3 py-2 hover:bg-white/10 {{ $activeLocale === $code ? 'text-blue-300' : 'text-gray-200' }}">{{ $meta['label'] }}</a>
                                @endforeach
                            </div>
                        </div>
                        <div class="relative" x-data="{ openUnits: false }">
                            <button type="button" class="px-2 py-1 text-xs bg-white/10 rounded hover:bg-white/20 transition-colors" @click="openUnits = !openUnits">
                                {{ $unitShort }}
                            </button>
                            <div x-cloak x-show="openUnits" @click.outside="openUnits = false" class="absolute right-0 mt-2 w-40 bg-weather-card border border-white/10 rounded-lg shadow-lg overflow-hidden text-xs z-50">
                                @foreach($unitOptions as $code => $meta)
                                    <a href="{{ request()->fullUrlWithQuery(['units' => $code]) }}" class="block px-3 py-2 hover:bg-white/10 {{ $activeUnits === $code ? 'text-blue-300' : 'text-gray-200' }}">{{ $meta['label'] }}</a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Row 2: Time/date (mobile) -->
                <div class="flex items-center justify-center gap-2 text-sm border-t border-white/5 pt-2">
                    <span class="live-indicator inline-block w-2 h-2 bg-green-500 rounded-full shadow-lg shadow-green-500/50"></span>
                    <span class="text-gray-300 font-display" id="currentTimeMobile">--:--:--</span>
                    <span class="text-gray-500">|</span>
                    <span class="text-gray-300" id="currentDateMobile">--</span>
                    <span class="text-gray-500 text-xs ml-1" id="currentTimeZoneLabelMobile"></span>
                </div>
            </div>
            <!-- Desktop: Single row -->
            <div class="hidden lg:flex items-center justify-between">
                <a href="{{ route('home') }}" class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-cyan-400 rounded-lg flex items-center justify-center shadow-lg shadow-blue-500/30">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold">{{ \App\Models\Setting::stationName() }}</h1>
                        <p class="text-xs text-gray-400">{{ \App\Models\Setting::stationLocation() }}</p>
                    </div>
                </a>
                <div class="flex items-center gap-2 text-sm">
                    <span class="live-indicator inline-block w-2 h-2 bg-green-500 rounded-full shadow-lg shadow-green-500/50"></span>
                    <span class="text-gray-300 font-display" id="currentTime">--:--:--</span>
                    <span class="text-gray-500">|</span>
                    <span class="text-gray-300" id="currentDate">--</span>
                    <span class="text-gray-500 text-xs ml-1" id="currentTimeZoneLabel"></span>
                </div>
                <div class="flex items-center gap-2">
                    @auth
                        @if(auth()->user()->is_admin)
                            @if(($siteTheme ?? 'fx') !== 'flat')
                                <button @click="toggleBackgroundEffects()" :class="backgroundEffectsEnabled ? 'bg-emerald-600 hover:bg-emerald-500' : 'bg-gray-600 hover:bg-gray-500'" class="px-2 py-1 text-xs rounded transition-colors flex items-center gap-1" :title="backgroundEffectsEnabled ? '{{ __('Disable background effects') }}' : '{{ __('Enable background effects') }}'">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"></path></svg>
                                    <span class="relative">FX<span x-show="!backgroundEffectsEnabled" class="absolute inset-0 flex items-center justify-center"><span class="w-full h-0.5 bg-current rotate-45 absolute"></span></span></span>
                                </button>
                            @endif
                            <a href="{{ route('admin.dashboard') }}" class="px-3 py-1 text-xs bg-blue-600 hover:bg-blue-500 rounded transition-colors">{{ __('Admin') }}</a>
                        @endif
                    @endauth
                    <div class="relative" x-data="{ openLang: false }">
                        <button type="button" class="px-2 py-1 text-xs bg-white/10 rounded hover:bg-white/20 transition-colors" @click="openLang = !openLang">
                            {{ $localeOptions[$activeLocale]['short'] ?? strtoupper($activeLocale) }}
                        </button>
                        <div x-cloak x-show="openLang" @click.outside="openLang = false" class="absolute right-0 mt-2 w-40 bg-weather-card border border-white/10 rounded-lg shadow-lg overflow-hidden text-xs">
                            @foreach($localeOptions as $code => $meta)
                                <a href="{{ localeUrl($code) }}" class="block px-3 py-2 hover:bg-white/10 {{ $activeLocale === $code ? 'text-blue-300' : 'text-gray-200' }}">{{ $meta['label'] }}</a>
                            @endforeach
                        </div>
                    </div>
                    <div class="relative" x-data="{ openUnits: false }">
                        <button type="button" class="px-2 py-1 text-xs bg-white/10 rounded hover:bg-white/20 transition-colors" @click="openUnits = !openUnits">
                            {{ $unitShort }}
                        </button>
                        <div x-cloak x-show="openUnits" @click.outside="openUnits = false" class="absolute right-0 mt-2 w-40 bg-weather-card border border-white/10 rounded-lg shadow-lg overflow-hidden text-xs">
                            @foreach($unitOptions as $code => $meta)
                                <a href="{{ request()->fullUrlWithQuery(['units' => $code]) }}" class="block px-3 py-2 hover:bg-white/10 {{ $activeUnits === $code ? 'text-blue-300' : 'text-gray-200' }}">{{ $meta['label'] }}</a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    @include('weather.partials.navigation')

    <main id="main-content" class="max-w-7xl mx-auto px-4 py-6 relative z-10 side-rail-safe">
        @yield('content')
    </main>

    @include('weather.partials.inline-ad-section')

    <!-- Footer -->
    @include('weather.partials.footer')

    @include('weather.partials.mobile-nav')

    <script>
        // Update clock (station timezone; DST via IANA + Intl)
        function updateClock() {
            const now = new Date();
            const locale = window.Meteo?.jsLocale || 'en-US';
            const tz = window.Meteo?.stationTimezone || 'UTC';
            const opts = { timeZone: tz };
            const time = now.toLocaleTimeString(locale, { ...opts, hour: '2-digit', minute: '2-digit', second: '2-digit' });
            const date = now.toLocaleDateString(locale, { ...opts, weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
            const parts = new Intl.DateTimeFormat(locale, { timeZone: tz, timeZoneName: 'short' }).formatToParts(now);
            const tzPart = parts.find(p => p.type === 'timeZoneName');
            const tzLabel = tzPart ? `(${tzPart.value})` : '';
            // Desktop
            const timeEl = document.getElementById('currentTime');
            const dateEl = document.getElementById('currentDate');
            const tzEl = document.getElementById('currentTimeZoneLabel');
            if (timeEl) timeEl.textContent = time;
            if (dateEl) dateEl.textContent = date;
            if (tzEl) tzEl.textContent = tzLabel;
            // Mobile
            const timeMobileEl = document.getElementById('currentTimeMobile');
            const dateMobileEl = document.getElementById('currentDateMobile');
            const tzMobileEl = document.getElementById('currentTimeZoneLabelMobile');
            if (timeMobileEl) timeMobileEl.textContent = time;
            if (dateMobileEl) dateMobileEl.textContent = date;
            if (tzMobileEl) tzMobileEl.textContent = tzLabel;
        }
        updateClock();
        setInterval(updateClock, 1000);
        
        // Floating header - hide on scroll down, show on scroll up (mobile only)
        (function() {
            const header = document.getElementById('site-header');
            if (!header) return;
            
            let lastScrollTop = 0;
            let ticking = false;
            const scrollThreshold = 5;
            
            // Dynamically adjust body padding based on header height
            function adjustBodyPadding() {
                if (window.innerWidth < 1024) {
                    const headerHeight = header.offsetHeight;
                    document.body.style.paddingTop = headerHeight + 'px';
                } else {
                    document.body.style.paddingTop = '';
                }
            }
            
            // Initial adjustment
            adjustBodyPadding();
            
            // Recalculate on resize
            window.addEventListener('resize', function() {
                adjustBodyPadding();
                if (window.innerWidth >= 1024) {
                    header.style.transform = '';
                }
            });
            
            // Also recalculate after fonts load (can change header height)
            if (document.fonts && document.fonts.ready) {
                document.fonts.ready.then(adjustBodyPadding);
            }
            
            function handleScroll() {
                if (window.innerWidth >= 1024) {
                    header.style.transform = '';
                    ticking = false;
                    return;
                }
                
                const scrollTop = window.pageYOffset || document.documentElement.scrollTop || 0;
                const scrollDelta = scrollTop - lastScrollTop;
                
                if (scrollDelta > scrollThreshold && scrollTop > 50) {
                    header.style.transform = 'translateY(-100%)';
                } else if (scrollDelta < -scrollThreshold) {
                    header.style.transform = 'translateY(0)';
                }
                if (scrollTop <= 10) {
                    header.style.transform = 'translateY(0)';
                }
                
                lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
                ticking = false;
            }
            
            window.addEventListener('scroll', function() {
                if (!ticking) {
                    window.requestAnimationFrame(handleScroll);
                    ticking = true;
                }
            }, { passive: true });
        })();
    </script>
    
    <!-- PWA Install Prompt (Mobile only, non-intrusive) -->
    <div id="pwa-install-prompt" class="fixed bottom-20 left-4 right-4 z-50 hidden" google-side-rail-overlap="true">
        <div class="glass rounded-2xl p-4 border border-white/20 shadow-xl max-w-md mx-auto">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 w-10 h-10 bg-weather-accent/20 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-weather-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-white">{{ __('Install App') }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">{{ __('Add to home screen for quick access') }}</p>
                </div>
                <button id="pwa-prompt-close" class="flex-shrink-0 p-1 text-gray-400 hover:text-white transition" aria-label="Close">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="flex gap-2 mt-3">
                <button id="pwa-prompt-install" class="flex-1 px-4 py-2 bg-weather-accent text-white text-sm font-medium rounded-lg hover:bg-blue-500 transition">
                    {{ __('Install') }}
                </button>
                <button id="pwa-prompt-later" class="px-4 py-2 text-gray-400 text-sm hover:text-white transition">
                    {{ __('Later') }}
                </button>
            </div>
        </div>
    </div>
    
    <script>
        // PWA Install Prompt - Non-intrusive, respects user preference
        (function() {
            const STORAGE_KEY = 'pwa_prompt_state';
            const REMIND_AFTER_DAYS = 30;
            const prompt = document.getElementById('pwa-install-prompt');
            
            if (!prompt) return;
            
            // Check if already installed as PWA
            const isStandalone = window.matchMedia('(display-mode: standalone)').matches 
                || window.navigator.standalone === true;
            if (isStandalone) return;
            
            // Only show on mobile
            const isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);
            if (!isMobile) return;

            // Keep the bottom slot free for AdSense mobile anchor ads.
            const hasAdSenseScript = Boolean(
                document.querySelector('script[src*="pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"]')
            );
            if (hasAdSenseScript) return;
            
            // Check user preference
            const state = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
            
            // Never show again if user chose that
            if (state.neverShow) return;
            
            // Check if we should wait before showing again
            if (state.dismissedAt) {
                const daysSinceDismiss = (Date.now() - state.dismissedAt) / (1000 * 60 * 60 * 24);
                if (daysSinceDismiss < REMIND_AFTER_DAYS) return;
            }
            
            // Don't show on first page load - wait a bit
            let deferredPrompt = null;
            
            // Capture the install prompt event (Chrome/Edge/Samsung)
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                deferredPrompt = e;
            });
            
            // Show prompt after 5 seconds of being on page
            setTimeout(() => {
                prompt.classList.remove('hidden');
                prompt.style.animation = 'slideUp 0.3s ease-out';
            }, 5000);
            
            // Close button - dismiss for 30 days
            document.getElementById('pwa-prompt-close')?.addEventListener('click', () => {
                hidePrompt();
                localStorage.setItem(STORAGE_KEY, JSON.stringify({ dismissedAt: Date.now() }));
            });
            
            // Later button - same as close
            document.getElementById('pwa-prompt-later')?.addEventListener('click', () => {
                hidePrompt();
                localStorage.setItem(STORAGE_KEY, JSON.stringify({ dismissedAt: Date.now() }));
            });
            
            // Install button
            document.getElementById('pwa-prompt-install')?.addEventListener('click', async () => {
                if (deferredPrompt) {
                    // Chrome/Edge - use native prompt
                    deferredPrompt.prompt();
                    const { outcome } = await deferredPrompt.userChoice;
                    if (outcome === 'accepted') {
                        localStorage.setItem(STORAGE_KEY, JSON.stringify({ neverShow: true }));
                    }
                    deferredPrompt = null;
                } else {
                    // iOS/Safari - show instructions
                    const isIOS = /iPhone|iPad|iPod/i.test(navigator.userAgent);
                    if (isIOS) {
                        alert('Tap the share button (□↑) and then "Add to Home Screen"');
                    } else {
                        alert('Use your browser menu to "Add to Home Screen" or "Install App"');
                    }
                }
                hidePrompt();
            });
            
            function hidePrompt() {
                prompt.style.animation = 'slideDown 0.2s ease-in forwards';
                setTimeout(() => prompt.classList.add('hidden'), 200);
            }
        })();
    </script>
    
    <style>
        @keyframes slideUp {
            from { transform: translateY(100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        @keyframes slideDown {
            from { transform: translateY(0); opacity: 1; }
            to { transform: translateY(100%); opacity: 0; }
        }
    </style>
    @stack('scripts')

    </div><!-- /#site-wrapper -->
</body>
</html>
