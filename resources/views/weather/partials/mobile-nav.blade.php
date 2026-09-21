@php
    $menuFeatures = \App\Support\MenuFeatureMap::all();
    // Community Stations is always available, so the "More" menu always has at least one item.
    $hasMoreMenu = true;
@endphp

<!-- Mobile Bottom Nav -->
<nav id="mobile-bottom-nav" class="mobile-bottom-nav fixed bottom-0 left-0 right-0 glass border-t border-ui-line/10 lg:hidden z-50" x-data="{ moreOpen: false }" google-side-rail-overlap="true">
    <div class="flex justify-around py-2">
        <a href="{{ route('home') }}" class="flex flex-col items-center p-2 {{ request()->routeIs('home') ? 'text-ui-link' : 'text-ui-muted' }}">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            <span class="text-xs mt-1">{{ __('Home') }}</span>
        </a>

        @if($menuFeatures['forecast'] ?? true)
            <a href="{{ route('forecast') }}" class="flex flex-col items-center p-2 {{ request()->routeIs('forecast') ? 'text-ui-link' : 'text-ui-muted' }}">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/></svg>
                <span class="text-xs mt-1">{{ __('Forecast') }}</span>
            </a>
        @endif

        @if($menuFeatures['radar'] ?? true)
            <a href="{{ route('radar') }}" class="flex flex-col items-center p-2 {{ request()->routeIs('radar') ? 'text-ui-link' : 'text-ui-muted' }}">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                <span class="text-xs mt-1">{{ __('Radar') }}</span>
            </a>
        @endif

        @if($menuFeatures['history'] ?? true)
            <a href="{{ route('history') }}" class="flex flex-col items-center p-2 {{ request()->routeIs('history*') ? 'text-ui-link' : 'text-ui-muted' }}">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                <span class="text-xs mt-1">{{ __('History') }}</span>
            </a>
        @endif

        @if($hasMoreMenu)
            <div class="relative">
                <button @click="moreOpen = !moreOpen" class="flex flex-col items-center p-2 {{
                    (
                        (($menuFeatures['statistics'] ?? true) && request()->routeIs('statistics'))
                        || (($menuFeatures['satellite'] ?? true) && request()->routeIs('satellite'))
                        || (($menuFeatures['air_pollen'] ?? true) && (request()->routeIs('airquality') || request()->routeIs('pollen') || request()->routeIs('noise')))
                        || (($menuFeatures['astronomy'] ?? true) && request()->routeIs('astronomy'))
                        || (($menuFeatures['sky_water'] ?? true) && (request()->routeIs('aviation*') || request()->routeIs('water*')))
                        || (($menuFeatures['fire_weather'] ?? true) && request()->routeIs('fire-weather'))
                        || request()->routeIs('weather.community-stations')
                        || (($menuFeatures['earthquakes'] ?? true) && request()->routeIs('earthquakes'))
                        || (($menuFeatures['alerts'] ?? true) && request()->routeIs('alerts*'))
                    ) ? 'text-ui-link' : 'text-ui-muted'
                }}">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z"/></svg>
                    <span class="text-xs mt-1">{{ __('More') }}</span>
                </button>

                <!-- More Menu Popup -->
                <div x-cloak x-show="moreOpen" @click.outside="moreOpen = false"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-2"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100 translate-y-0"
                     x-transition:leave-end="opacity-0 translate-y-2"
                     class="absolute bottom-full right-0 mb-2 w-48 bg-weather-card border border-ui-line/10 rounded-lg shadow-xl overflow-hidden">

                    @if($menuFeatures['statistics'] ?? true)
                        <a href="{{ route('statistics') }}" class="flex items-center gap-3 px-4 py-3 hover:bg-ui-overlay/10 {{ request()->routeIs('statistics') ? 'text-ui-link bg-ui-overlay/5' : 'text-ui-body' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            <span>{{ __('Statistics') }}</span>
                        </a>
                    @endif

                    @if($menuFeatures['satellite'] ?? true)
                        <a href="{{ route('satellite') }}" class="flex items-center gap-3 px-4 py-3 hover:bg-ui-overlay/10 {{ request()->routeIs('satellite') ? 'text-ui-link bg-ui-overlay/5' : 'text-ui-body' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <span>{{ __('Satellite') }}</span>
                        </a>
                    @endif

                    @if($menuFeatures['air_pollen'] ?? true)
                        <a href="{{ route('airquality') }}" class="flex items-center gap-3 px-4 py-3 hover:bg-ui-overlay/10 {{ request()->routeIs('airquality') || request()->routeIs('pollen') || request()->routeIs('noise') ? 'text-ui-link bg-ui-overlay/5' : 'text-ui-body' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                            <span>{{ __('Air & Pollen') }}</span>
                        </a>
                    @endif

                    @if($menuFeatures['astronomy'] ?? true)
                        <a href="{{ route('astronomy') }}" class="flex items-center gap-3 px-4 py-3 hover:bg-ui-overlay/10 {{ request()->routeIs('astronomy') ? 'text-ui-link bg-ui-overlay/5' : 'text-ui-body' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                            <span>{{ __('Astronomy') }}</span>
                        </a>
                    @endif

                    @if($menuFeatures['sky_water'] ?? true)
                        <a href="{{ route('aviation') }}" class="flex items-center gap-3 px-4 py-3 hover:bg-ui-overlay/10 {{ request()->routeIs('aviation*') || request()->routeIs('water*') ? 'text-ui-link bg-ui-overlay/5' : 'text-ui-body' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                            <span>{{ __('Sky & Water') }}</span>
                        </a>
                    @endif

                    @if($menuFeatures['fire_weather'] ?? true)
                        <a href="{{ route('fire-weather') }}" class="flex items-center gap-3 px-4 py-3 hover:bg-ui-overlay/10 {{ request()->routeIs('fire-weather') ? 'text-ui-link bg-ui-overlay/5' : 'text-ui-body' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.879 16.121A3 3 0 1012.015 11L11 14H9c0 .768.293 1.536.879 2.121z"/></svg>
                            <span>{{ __('Fire Weather') }}</span>
                        </a>
                    @endif

                    <a href="{{ route('weather.community-stations') }}" class="flex items-center gap-3 px-4 py-3 hover:bg-ui-overlay/10 {{ request()->routeIs('weather.community-stations') ? 'text-ui-link bg-ui-overlay/5' : 'text-ui-body' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span>{{ __('Community') }}</span>
                    </a>

                    @if($menuFeatures['earthquakes'] ?? true)
                        <a href="{{ route('earthquakes') }}" class="flex items-center gap-3 px-4 py-3 hover:bg-ui-overlay/10 {{ request()->routeIs('earthquakes') ? 'text-ui-link bg-ui-overlay/5' : 'text-ui-body' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            <span>{{ __('Earthquakes') }}</span>
                        </a>
                    @endif

                    @if($menuFeatures['alerts'] ?? true)
                        @php
                            $_mNavRaw    = \Illuminate\Support\Facades\Cache::get('weather_alerts', []);
                            $_mNavInt    = \Illuminate\Support\Facades\Cache::get('local_warnings_' . app()->getLocale(), []);
                            $_mNavAll    = array_merge(is_array($_mNavRaw) ? $_mNavRaw : [], is_array($_mNavInt) ? $_mNavInt : []);
                            $_mNavMaxSev = !empty($_mNavAll) ? max(array_column($_mNavAll, 'severity') ?: [0]) : 0;
                            $_mNavDot    = match(true) {
                                $_mNavMaxSev >= 4 => '#BB2739',
                                $_mNavMaxSev >= 3 => '#F19E39',
                                $_mNavMaxSev >= 2 => '#FBEA55',
                                default          => null,
                            };
                        @endphp
                        <a href="{{ route('alerts') }}" class="flex items-center gap-3 px-4 py-3 hover:bg-ui-overlay/10 {{ request()->routeIs('alerts*') ? 'text-data-orange-400 bg-ui-overlay/5' : 'text-ui-body' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <span class="flex items-center gap-1.5">
                                {{ __('Alerts') }}
                                @if($_mNavDot)
                                    <span class="w-2 h-2 rounded-full" style="background: {{ $_mNavDot }}"></span>
                                @endif
                            </span>
                        </a>
                    @endif
                </div>
            </div>
        @endif
    </div>
</nav>
