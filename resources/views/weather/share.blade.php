@extends('weather.layout')

@section('title', __('Share & Embed') . ' — ' . \App\Models\Setting::stationName())
@section('meta_description', 'Share ' . \App\Models\Setting::stationName() . ' on social media or embed a live weather card on your website.')
@section('og_image', \App\Models\Setting::getValue('og.enabled', false) ? route('og.generic', ['page' => 'share']) : '')

@section('content')
@php
    use App\Models\Setting;
    $stationName = Setting::stationName();
    $stationLoc  = Setting::stationLocation() ?: $stationName;
    $siteUrl     = rtrim(url('/'), '/');
    $ogEnabled   = (bool) Setting::getValue('og.enabled', false);
    $menuFeatures = \App\Support\MenuFeatureMap::all();

    // Shareable pages
    $pages = [
        [
            'label' => __('Live Conditions'),
            'desc'  => __('Current temperature, humidity, wind and pressure'),
            'url'   => $siteUrl . '/',
            'og'    => $ogEnabled ? route('og.home') : null,
        ],
    ];

    if ($menuFeatures['forecast'] ?? true) {
        $pages[] = [
            'label' => __('Forecast'),
            'desc'  => __('3-day weather forecast'),
            'url'   => route('forecast'),
            'og'    => $ogEnabled ? route('og.forecast') : null,
        ];
    }

    if ($menuFeatures['fire_weather'] ?? true) {
        $pages[] = [
            'label' => __('Fire Weather'),
            'desc'  => __('Angström Index and drought indicator'),
            'url'   => route('fire-weather'),
            'og'    => $ogEnabled ? route('og.fire-weather') : null,
        ];
    }

    if ($menuFeatures['statistics'] ?? true) {
        $pages[] = [
            'label' => __('Statistics'),
            'desc'  => __('Annual weather statistics and records'),
            'url'   => route('statistics'),
            'og'    => $ogEnabled ? route('og.statistics', ['year' => date('Y')]) : null,
        ];
    }

    if ($menuFeatures['air_pollen'] ?? true) {
        $pages[] = [
            'label' => __('Air Quality'),
            'desc'  => __('PM2.5, PM10 and AQI'),
            'url'   => route('airquality'),
            'og'    => $ogEnabled ? route('og.air-quality') : null,
        ];
    }

    if ($menuFeatures['astronomy'] ?? true) {
        $pages[] = [
            'label' => __('Astronomy'),
            'desc'  => __('Sunrise, sunset and moon phase'),
            'url'   => route('astronomy'),
            'og'    => $ogEnabled ? route('og.astronomy') : null,
        ];
    }

    // Add aviation entry when METAR is configured
    $primaryIcao  = Setting::getValue('metar.primary_icao', null);
    $metarEnabled = (bool) Setting::getValue('metar.enabled', false);
    if (($menuFeatures['sky_water'] ?? true) && $metarEnabled && $primaryIcao) {
        $icaoUpper = strtoupper($primaryIcao);
        $pages[] = [
            'label' => 'Aviation — ' . $icaoUpper,
            'desc'  => __('METAR weather for :icao', ['icao' => $icaoUpper]),
            'url'   => route('aviation', ['icao' => strtolower($primaryIcao)]),
            'og'    => $ogEnabled ? route('og.aviation', ['icao' => strtolower($primaryIcao)]) : null,
        ];
    }
@endphp

<div class="max-w-4xl mx-auto px-4 py-8">

    {{-- Page header --}}
    <div class="mb-10">
        <div class="flex items-center gap-3 mb-2">
            <svg class="w-7 h-7 text-data-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
            </svg>
            <h1 class="text-3xl font-bold text-ui-fg">{{ __('Share & Embed') }}</h1>
        </div>
        <p class="text-ui-muted max-w-2xl">
            {{ __('Share :name with others or embed a live weather card on your own website.', ['name' => $stationName]) }}
        </p>
    </div>

    <div class="space-y-12">

        {{-- ─── 1. Share the station ───────────────────────────────────────────── --}}
        <section>
            <h2 class="text-xl font-semibold text-ui-fg mb-1">{{ __('Share this station') }}</h2>
            <p class="text-sm text-ui-muted mb-6">{{ __('Post the weather station link on social media or copy it to share anywhere.') }}</p>

            @php
                $mainUrl      = $siteUrl . '/';
                $mainText     = __(':name — live weather in :loc', ['name' => $stationName, 'loc' => $stationLoc]);
                $encUrl       = rawurlencode($mainUrl);
                $encText      = rawurlencode($mainText);
            @endphp

            <div class="flex flex-wrap gap-3">
                {{-- WhatsApp --}}
                <a href="https://wa.me/?text={{ rawurlencode($mainText . ' ' . $mainUrl) }}"
                   target="_blank" rel="noopener noreferrer"
                   class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-green-600/15 text-data-green-400 hover:bg-green-600/25 border border-green-600/30 transition-colors text-sm font-medium">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                    </svg>
                    WhatsApp
                </a>

                {{-- X / Twitter --}}
                <a href="https://twitter.com/intent/tweet?url={{ $encUrl }}&text={{ $encText }}"
                   target="_blank" rel="noopener noreferrer"
                   class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-ui-soft/50 text-ui-body hover:bg-ui-soft border border-ui-border/50 transition-colors text-sm font-medium">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24h-6.658l-5.214-6.817-5.963 6.817H1.682l7.73-8.835L1.254 2.25H8.08l4.713 6.231 5.451-6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                    </svg>
                    X / Twitter
                </a>

                {{-- Facebook --}}
                <a href="https://www.facebook.com/sharer/sharer.php?u={{ $encUrl }}"
                   target="_blank" rel="noopener noreferrer"
                   class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-blue-600/15 text-data-blue-400 hover:bg-blue-600/25 border border-blue-600/30 transition-colors text-sm font-medium">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                    </svg>
                    Facebook
                </a>

                {{-- Telegram --}}
                <a href="https://t.me/share/url?url={{ $encUrl }}&text={{ $encText }}"
                   target="_blank" rel="noopener noreferrer"
                   class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-sky-600/15 text-data-sky-400 hover:bg-sky-600/25 border border-sky-600/30 transition-colors text-sm font-medium">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                    </svg>
                    Telegram
                </a>

                {{-- Copy link --}}
                <button id="copy-main-btn"
                        onclick="copyLink(this, '{{ addslashes($mainUrl) }}')"
                        class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-ui-soft/50 text-ui-secondary hover:bg-ui-soft border border-ui-border/50 transition-colors text-sm font-medium cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                    {{ __('Copy link') }}
                </button>
            </div>
        </section>

        {{-- ─── 2. Share a specific page ───────────────────────────────────────── --}}
        <section>
            <h2 class="text-xl font-semibold text-ui-fg mb-1">{{ __('Share a specific page') }}</h2>
            <p class="text-sm text-ui-muted mb-4">{{ __('Each page has its own shareable link.') }}</p>

            <div class="space-y-2">
                @foreach($pages as $page)
                @php
                    $pUrl      = $page['url'];
                    $pEncUrl   = rawurlencode($pUrl);
                    $pText     = $page['label'] . ' — ' . $stationName;
                    $pEncText  = rawurlencode($pText);
                @endphp
                <div class="flex flex-col sm:flex-row sm:items-center gap-3 bg-ui-raised/40 rounded-xl border border-ui-divider/50 px-4 py-3">
                    <div class="flex-1 min-w-0">
                        <span class="text-sm font-medium text-ui-fg">{{ $page['label'] }}</span>
                        <span class="text-xs text-ui-subtle ml-2">{{ $page['desc'] }}</span>
                    </div>
                    {{-- Compact icon-only share buttons --}}
                    <div class="flex items-center gap-1 flex-shrink-0">
                        <a href="https://wa.me/?text={{ rawurlencode($pText . ' ' . $pUrl) }}"
                           target="_blank" rel="noopener noreferrer"
                           class="p-2 rounded-lg text-data-green-400 hover:bg-green-900/30 transition-colors" title="WhatsApp">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        </a>
                        <a href="https://twitter.com/intent/tweet?url={{ $pEncUrl }}&text={{ $pEncText }}"
                           target="_blank" rel="noopener noreferrer"
                           class="p-2 rounded-lg text-ui-secondary hover:bg-ui-soft transition-colors" title="X / Twitter">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24h-6.658l-5.214-6.817-5.963 6.817H1.682l7.73-8.835L1.254 2.25H8.08l4.713 6.231 5.451-6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                        </a>
                        <a href="https://www.facebook.com/sharer/sharer.php?u={{ $pEncUrl }}"
                           target="_blank" rel="noopener noreferrer"
                           class="p-2 rounded-lg text-data-blue-400 hover:bg-blue-900/30 transition-colors" title="Facebook">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                        </a>
                        <a href="https://t.me/share/url?url={{ $pEncUrl }}&text={{ $pEncText }}"
                           target="_blank" rel="noopener noreferrer"
                           class="p-2 rounded-lg text-data-sky-400 hover:bg-sky-900/30 transition-colors" title="Telegram">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
                        </a>
                        <button onclick="copyLink(this, '{{ addslashes($pUrl) }}')"
                                class="p-2 rounded-lg text-ui-muted hover:bg-ui-soft transition-colors cursor-pointer" title="{{ __('Copy link') }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                        </button>
                    </div>
                </div>
                @endforeach
            </div>
        </section>

        {{-- ─── 3. Live weather widget (iframe) ───────────────────────────────────── --}}
        <section>
            <h2 class="text-xl font-semibold text-ui-fg mb-1">{{ __('Live weather widget') }}</h2>
            <p class="text-sm text-ui-muted mb-6">
                {{ __('Embed a live, self-updating weather card on any website. The widget refreshes automatically every 2 minutes.') }}
            </p>

            @php
                $widgetUrl  = route('widget');
                $widgetCode = '<iframe' . "\n"
                    . '  src="' . $widgetUrl . '"' . "\n"
                    . '  width="280" height="200"' . "\n"
                    . '  frameborder="0"' . "\n"
                    . '  style="border:none;border-radius:16px;overflow:hidden;display:block"' . "\n"
                    . '  loading="lazy"' . "\n"
                    . '  title="' . e($stationName . ' — ' . __('Live weather')) . '"' . "\n"
                    . '></iframe>';
            @endphp

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
                {{-- Live preview --}}
                <div>
                    <p class="text-xs text-ui-subtle mb-2 uppercase tracking-wide">{{ __('Preview') }}</p>
                    <iframe src="{{ $widgetUrl }}"
                            width="280" height="200"
                            frameborder="0"
                            style="border:none;border-radius:16px;overflow:hidden;display:block"
                            loading="lazy"
                            title="{{ $stationName }}"></iframe>
                </div>

                {{-- Embed code --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs text-ui-subtle uppercase tracking-wide">{{ __('Embed code') }}</p>
                        <button onclick="copyEmbed(this)" data-code="{{ e($widgetCode) }}"
                                class="copy-btn text-xs px-3 py-1.5 rounded-lg bg-blue-600/20 text-data-blue-400 hover:bg-blue-600/35 border border-blue-600/30 transition-colors cursor-pointer">
                            {{ __('Copy code') }}
                        </button>
                    </div>
                    <pre class="text-xs text-ui-muted bg-ui-deep/60 rounded-xl p-4 overflow-x-auto leading-relaxed whitespace-pre-wrap break-all border border-ui-divider/50"><code>{{ $widgetCode }}</code></pre>
                    <p class="text-xs text-ui-faint mt-2">
                        {{ __('Adjust width and height to fit your layout.') }}
                        {{ __('The widget adapts to any reasonable size.') }}
                    </p>
                </div>
            </div>
        </section>

        {{-- ─── 4. Embed a weather card ──────────────────────────────────────────── --}}
        @if($ogEnabled)
        <section>
            <h2 class="text-xl font-semibold text-ui-fg mb-1">{{ __('Embed a weather card') }}</h2>
            <p class="text-sm text-ui-muted mb-6">
                {{ __('Copy a snippet and paste it into your website to show a live weather card that updates automatically.') }}
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                @foreach($pages as $page)
                @if($page['og'])
                @php
                    $embedHtml =
                        '<a href="' . $page['url'] . '" target="_blank" rel="noopener noreferrer">' . "\n" .
                        '  <img src="' . $page['og'] . '"' . "\n" .
                        '       alt="' . e($page['label'] . ' — ' . $stationName) . '"' . "\n" .
                        '       width="600" height="315"' . "\n" .
                        '       style="border-radius:8px;max-width:100%;display:block">' . "\n" .
                        '</a>';
                @endphp
                <div class="bg-ui-raised/50 rounded-xl border border-ui-divider overflow-hidden">
                    <a href="{{ $page['url'] }}" target="_blank" rel="noopener noreferrer">
                        <img src="{{ $page['og'] }}" alt="{{ $page['label'] }}"
                             class="w-full" loading="lazy" style="aspect-ratio:1200/630;object-fit:cover">
                    </a>
                    <div class="p-4">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-sm font-medium text-ui-fg">{{ $page['label'] }}</span>
                            <button onclick="copyEmbed(this)"
                                    data-code="{{ e($embedHtml) }}"
                                    class="copy-btn text-xs px-3 py-1.5 rounded-lg bg-blue-600/20 text-data-blue-400 hover:bg-blue-600/35 border border-blue-600/30 transition-colors cursor-pointer">
                                {{ __('Copy code') }}
                            </button>
                        </div>
                        <pre class="text-xs text-ui-muted bg-ui-deep/60 rounded-lg p-3 overflow-x-auto leading-relaxed whitespace-pre-wrap break-all"><code>{{ $embedHtml }}</code></pre>
                    </div>
                </div>
                @endif
                @endforeach
            </div>
        </section>

        @else

        <section>
            <div class="rounded-xl border border-blue-700/40 bg-blue-900/10 p-6">
                <h2 class="text-base font-semibold text-ui-fg mb-2">{{ __('Embed weather cards on your site') }}</h2>
                <p class="text-sm text-ui-secondary mb-4">
                    {{ __('When Social Sharing Cards are enabled, each page on this station gets a live preview image that updates automatically. Copy the embed snippet and paste it into any website.') }}
                </p>
                <div class="flex items-center gap-2 text-sm text-ui-muted">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    {{ __('An administrator can enable this in Settings → Social Sharing Cards.') }}
                </div>
            </div>
        </section>

        @endif

    </div>
</div>

<script>
function copyLink(btn, url) {
    navigator.clipboard.writeText(url).then(function () {
        const svg = btn.querySelector('svg');
        if (svg) {
            svg.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>';
            btn.classList.add('text-data-green-400');
            btn.classList.remove('text-ui-muted');
            setTimeout(function () {
                svg.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>';
                btn.classList.remove('text-data-green-400');
                btn.classList.add('text-ui-muted');
            }, 2000);
        }
    });
}

function copyEmbed(btn) {
    const code = btn.dataset.code;
    navigator.clipboard.writeText(code).then(function () {
        const orig = btn.textContent.trim();
        btn.textContent = '✓ {{ __('Copied') }}!';
        btn.classList.add('text-data-green-400', 'bg-green-600/20', 'border-green-600/30');
        btn.classList.remove('text-data-blue-400', 'bg-blue-600/20', 'border-blue-600/30');
        setTimeout(function () {
            btn.textContent = orig;
            btn.classList.remove('text-data-green-400', 'bg-green-600/20', 'border-green-600/30');
            btn.classList.add('text-data-blue-400', 'bg-blue-600/20', 'border-blue-600/30');
        }, 2500);
    });
}
</script>
@endsection
