@php
    $footerEnabled = \App\Models\Setting::getValue('footer.enabled', true);
    $showStationInfo = \App\Models\Setting::getValue('footer.show_station_info', true);
    $showCoordinates = \App\Models\Setting::getValue('footer.show_coordinates', true);
    $showSocial = \App\Models\Setting::getValue('footer.show_social', true);
    $showQuickLinks = \App\Models\Setting::getValue('footer.show_quick_links', true);
    $showLegal = \App\Models\Setting::getValue('footer.show_legal', true);
    $showSeoText = \App\Models\Setting::getValue('footer.show_seo_text', false);
    
    // Cookie settings link only makes sense when ads are enabled and this visitor is in a consent-required region.
    $adCode = trim((string) \App\Models\Setting::getValue('widgets.ad_code', ''));
    $enabledWidgetsRaw = \App\Models\Setting::getValue('widgets.enabled', []);
    if (is_string($enabledWidgetsRaw)) {
        $decodedWidgets = json_decode($enabledWidgetsRaw, true);
        $enabledWidgets = is_array($decodedWidgets) ? $decodedWidgets : [];
    } elseif (is_array($enabledWidgetsRaw)) {
        $enabledWidgets = $enabledWidgetsRaw;
    } else {
        $enabledWidgets = [];
    }
    $adsWidgetEnabled = in_array('ads', $enabledWidgets, true) && $adCode !== '';
    $adsConsentService = app(\App\Services\Ads\AdsConsentService::class);
    $adsConsentMode = $adsConsentService->normalizeConsentMode(
        (string) \App\Models\Setting::getValue('widgets.ads_consent_mode', \App\Services\Ads\AdsConsentService::MODE_AUTO)
    );
    $showCookieSettingsLink = $adsWidgetEnabled
        && $adsConsentService->requiresConsentForRequestWithMode(request(), $adsConsentMode);
    $cookieSettingsUrl = route('home', ['open_cookie_settings' => 1]);
    
    // Get custom links and ensure it's always an array
    $customLinksRaw = \App\Models\Setting::getValue('footer.custom_links', []);
    if (is_string($customLinksRaw)) {
        // If it's a JSON string, decode it
        $decoded = json_decode($customLinksRaw, true);
        $customLinks = is_array($decoded) ? $decoded : [];
    } elseif (is_array($customLinksRaw)) {
        $customLinks = $customLinksRaw;
    } else {
        $customLinks = [];
    }
    
    // Station info
    $stationHardware = \App\Models\Setting::getValue('station.hardware', '');
    $stationLocation = \App\Models\Setting::stationLocation();
    $stationLat = \App\Models\Setting::latitude();
    $stationLon = \App\Models\Setting::longitude();
    $stationStartDate = \App\Models\Setting::getValue('station.start_date', '');
    $stationStartYear = null;
    if (is_string($stationStartDate) && $stationStartDate !== '') {
        try {
            $stationStartYear = \Carbon\Carbon::parse($stationStartDate)->year;
        } catch (\Throwable) {
            $stationStartYear = null;
        }
    }
    
    // Social media links
    $socialLinks = [];
    $facebook = \App\Models\Setting::getValue('contact.facebook', '');
    $twitter = \App\Models\Setting::getValue('contact.twitter', '');
    $instagram = \App\Models\Setting::getValue('contact.instagram', '');
    $youtube = \App\Models\Setting::getValue('contact.youtube', '');
    $linkedin = \App\Models\Setting::getValue('contact.linkedin', '');
    
    if ($facebook) $socialLinks['facebook'] = $facebook;
    if ($twitter) $socialLinks['twitter'] = $twitter;
    if ($instagram) $socialLinks['instagram'] = $instagram;
    if ($youtube) $socialLinks['youtube'] = $youtube;
    if ($linkedin) $socialLinks['linkedin'] = $linkedin;
@endphp

@php
    // Check if any personal sections are enabled
    $hasPersonalContent = ($showStationInfo || ($showQuickLinks && !empty($customLinks)) || ($showSocial && !empty($socialLinks)) || $showLegal || $showSeoText);
    $isCompact = !$hasPersonalContent;
@endphp

@if($footerEnabled)
<footer class="border-t border-ui-line/10 mt-8 py-8 pb-24 lg:pb-8 relative z-10">
    <div class="max-w-7xl mx-auto px-4">
        @if($isCompact)
        <!-- Compact Footer (when personal sections disabled) -->
        <div class="space-y-4">
            <!-- Hardcoded Links - Always Visible -->
            <div class="flex flex-wrap items-center justify-center gap-4 text-sm text-ui-muted">
                <a href="https://weathernode.dev" target="_blank" rel="noopener noreferrer" class="hover:text-ui-fg transition-colors">
                    {{ __('WeatherNode') }}
                </a>
                <span class="text-ui-faint">•</span>
                <a href="https://github.com/centauri/WeatherNode" target="_blank" rel="noopener noreferrer" class="hover:text-ui-fg transition-colors">
                    {{ __('GitHub') }}
                </a>
                <span class="text-ui-faint">•</span>
                <a href="{{ route('legal.about') }}" class="hover:text-ui-fg transition-colors">
                    {{ __('About') }}
                </a>
                <span class="text-ui-faint">•</span>
                <a href="{{ route('legal.disclaimer') }}" class="hover:text-ui-fg transition-colors">
                    {{ __('Disclaimer') }}
                </a>
                <span class="text-ui-faint">•</span>
                <a href="{{ route('legal.license') }}" class="hover:text-ui-fg transition-colors">
                    {{ __('License') }}
                </a>
                <span class="text-ui-faint">•</span>
                <a href="{{ route('share') }}" class="hover:text-ui-fg transition-colors">
                    {{ __('Share & Embed') }}
                </a>
                @if($showCookieSettingsLink)
                <span class="text-ui-faint">•</span>
                <a href="{{ $cookieSettingsUrl }}"
                   onclick="if (window.location.pathname === '/' && window.dispatchEvent) { event.preventDefault(); window.dispatchEvent(new CustomEvent('meteo:open-cookie-settings')); return false; }"
                   class="hover:text-ui-fg transition-colors">
                    {{ __('Cookie settings') }}
                </a>
                @endif
            </div>
            
            <!-- Copyright -->
            <div class="text-center text-xs text-ui-subtle">
                <p>&copy; {{ date('Y') }} {{ \App\Models\Setting::stationName() }}. {{ __('Powered by') }} <a href="https://weathernode.dev" target="_blank" rel="noopener noreferrer" class="hover:text-ui-fg transition-colors">WeatherNode</a></p>
            </div>
        </div>
        @else
        <!-- Full Fat Footer (when personal sections enabled) -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            
            <!-- Station Information -->
            @if($showStationInfo)
            <div>
                <h3 class="text-sm font-semibold text-ui-fg mb-4">{{ __('Station Information') }}</h3>
                <ul class="space-y-2 text-sm text-ui-muted">
                    @if($stationHardware)
                    <li>
                        <span class="text-ui-subtle">{{ __('Hardware') }}:</span>
                        <span class="ml-2">{{ $stationHardware }}</span>
                    </li>
                    @endif
                    @if($stationLocation)
                    <li>
                        <span class="text-ui-subtle">{{ __('Location') }}:</span>
                        <span class="ml-2">{{ $stationLocation }}</span>
                    </li>
                    @endif
                    @if($showCoordinates && $stationLat && $stationLon)
                    <li>
                        <span class="text-ui-subtle">{{ __('Coordinates') }}:</span>
                        <span class="ml-2 font-mono text-xs">{{ number_format($stationLat, 6) }}, {{ number_format($stationLon, 6) }}</span>
                    </li>
                    @endif
                    @if($stationStartYear)
                    <li>
                        <span class="text-ui-subtle">{{ __('Data since') }}:</span>
                        <span class="ml-2">{{ $stationStartYear }}</span>
                    </li>
                    @endif
                </ul>
            </div>
            @endif

            <!-- Quick Links -->
            @if($showQuickLinks && !empty($customLinks) && is_array($customLinks))
            <div>
                <h3 class="text-sm font-semibold text-ui-fg mb-4">{{ __('Quick Links') }}</h3>
                <ul class="space-y-2">
                    @foreach($customLinks as $link)
                        @if(is_array($link) && !empty($link['label']) && !empty($link['url']))
                        <li>
                            <a href="{{ $link['url'] }}" class="text-sm text-ui-muted hover:text-ui-fg transition-colors">
                                @php
                                    // Check if label is a translation key (contains dot) or plain text
                                    $label = $link['label'];
                                    if (strpos($label, '.') !== false && strpos($label, 'http') === false) {
                                        // Likely a translation key
                                        echo __($label);
                                    } else {
                                        // Plain text
                                        echo e($label);
                                    }
                                @endphp
                            </a>
                        </li>
                        @endif
                    @endforeach
                </ul>
            </div>
            @endif

            <!-- Social Media -->
            @if($showSocial && !empty($socialLinks))
            <div>
                <h3 class="text-sm font-semibold text-ui-fg mb-4">{{ __('Follow Us') }}</h3>
                <ul class="space-y-2">
                    @if(isset($socialLinks['facebook']))
                    <li>
                        <a href="{{ $socialLinks['facebook'] }}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-2 text-sm text-ui-muted hover:text-ui-fg transition-colors">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                            </svg>
                            <span>{{ __('Facebook') }}</span>
                        </a>
                    </li>
                    @endif
                    @if(isset($socialLinks['twitter']))
                    <li>
                        <a href="{{ $socialLinks['twitter'] }}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-2 text-sm text-ui-muted hover:text-ui-fg transition-colors">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24h-6.658l-5.214-6.817-5.963 6.817H1.682l7.73-8.835L1.254 2.25H8.08l4.713 6.231 5.451-6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                            </svg>
                            <span>{{ __('X') }}</span>
                        </a>
                    </li>
                    @endif
                    @if(isset($socialLinks['instagram']))
                    <li>
                        <a href="{{ $socialLinks['instagram'] }}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-2 text-sm text-ui-muted hover:text-ui-fg transition-colors">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 0C8.74 0 8.333.015 7.053.072 5.775.132 4.905.333 4.14.63c-.789.306-1.459.717-2.126 1.384S.935 3.35.63 4.14C.333 4.905.131 5.775.072 7.053.012 8.333 0 8.74 0 12s.015 3.667.072 4.947c.06 1.277.261 2.148.558 2.913.306.788.717 1.459 1.384 2.126.667.666 1.336 1.079 2.126 1.384.766.296 1.636.499 2.913.558C8.333 23.988 8.74 24 12 24s3.667-.015 4.947-.072c1.277-.06 2.148-.262 2.913-.558.788-.306 1.459-.718 2.126-1.384.666-.667 1.079-1.335 1.384-2.126.296-.765.499-1.636.558-2.913.06-1.28.072-1.687.072-4.947s-.015-3.667-.072-4.947c-.06-1.277-.262-2.149-.558-2.913-.306-.789-.718-1.459-1.384-2.126C21.319 1.347 20.651.935 19.86.63c-.765-.297-1.636-.499-2.913-.558C15.667.012 15.26 0 12 0zm0 2.16c3.203 0 3.585.016 4.85.071 1.17.055 1.805.249 2.227.415.562.217.96.477 1.382.896.419.42.679.819.896 1.381.164.422.36 1.057.413 2.227.057 1.266.07 1.646.07 4.85s-.015 3.585-.074 4.85c-.061 1.17-.246 1.805-.413 2.227-.217.562-.477.96-.896 1.382-.42.419-.824.679-1.38.896-.42.164-1.065.36-2.235.413-1.274.057-1.649.07-4.859.07-3.211 0-3.586-.015-4.859-.074-1.171-.061-1.816-.246-2.236-.413-.569-.224-.96-.479-1.379-.896-.421-.419-.69-.824-.9-1.38-.165-.42-.359-1.065-.42-2.235-.045-1.26-.061-1.649-.061-4.844 0-3.196.016-3.586.061-4.861.061-1.17.255-1.814.42-2.234.21-.57.479-.96.9-1.381.419-.419.81-.689 1.379-.9.42-.166 1.051-.361 2.221-.421 1.275-.045 1.65-.06 4.859-.06l.045.03zm0 3.678c-3.405 0-6.162 2.76-6.162 6.162 0 3.405 2.76 6.162 6.162 6.162 3.405 0 6.162-2.76 6.162-6.162 0-3.405-2.76-6.162-6.162-6.162zM12 16c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4zm7.846-10.405c0 .795-.646 1.44-1.44 1.44-.795 0-1.44-.646-1.44-1.44 0-.794.646-1.439 1.44-1.439.793-.001 1.44.645 1.44 1.439z"/>
                            </svg>
                            <span>{{ __('Instagram') }}</span>
                        </a>
                    </li>
                    @endif
                    @if(isset($socialLinks['youtube']))
                    <li>
                        <a href="{{ $socialLinks['youtube'] }}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-2 text-sm text-ui-muted hover:text-ui-fg transition-colors">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                            </svg>
                            <span>{{ __('YouTube') }}</span>
                        </a>
                    </li>
                    @endif
                    @if(isset($socialLinks['linkedin']))
                    <li>
                        <a href="{{ $socialLinks['linkedin'] }}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-2 text-sm text-ui-muted hover:text-ui-fg transition-colors">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                            </svg>
                            <span>{{ __('LinkedIn') }}</span>
                        </a>
                    </li>
                    @endif
                </ul>
            </div>
            @endif

            <!-- Legal / About (Personal) -->
            @if($showLegal)
            <div>
                <h3 class="text-sm font-semibold text-ui-fg mb-4">{{ __('Legal') }}</h3>
                <ul class="space-y-2">
                    <li>
                        <a href="{{ route('legal.privacy') }}" class="text-sm text-ui-muted hover:text-ui-fg transition-colors">
                            {{ __('Privacy Policy') }}
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('legal.terms') }}" class="text-sm text-ui-muted hover:text-ui-fg transition-colors">
                            {{ __('Terms of Service') }}
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('legal.disclaimer') }}" class="text-sm text-ui-muted hover:text-ui-fg transition-colors">
                            {{ __('Disclaimer') }}
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('legal.about') }}" class="text-sm text-ui-muted hover:text-ui-fg transition-colors">
                            {{ __('About') }}
                        </a>
                    </li>
                    @if($showCookieSettingsLink)
                    <li>
                        <a href="{{ $cookieSettingsUrl }}"
                           onclick="if (window.location.pathname === '/' && window.dispatchEvent) { event.preventDefault(); window.dispatchEvent(new CustomEvent('meteo:open-cookie-settings')); return false; }"
                           class="text-sm text-ui-muted hover:text-ui-fg transition-colors">
                            {{ __('Cookie settings') }}
                        </a>
                    </li>
                    @endif
                </ul>
            </div>
            @endif

            <!-- WeatherNode & Project Links (Always Visible) -->
            <div>
                <h3 class="text-sm font-semibold text-ui-fg mb-4">{{ __('Project') }}</h3>
                <ul class="space-y-2">
                    <li>
                        <a href="{{ route('share') }}" class="text-sm text-ui-muted hover:text-ui-fg transition-colors">
                            {{ __('Share & Embed') }}
                        </a>
                    </li>
                    <li>
                        <a href="https://weathernode.dev" target="_blank" rel="noopener noreferrer" class="text-sm text-ui-muted hover:text-ui-fg transition-colors">
                            {{ __('WeatherNode') }}
                        </a>
                    </li>
                    <li>
                        <a href="https://github.com/centauri/WeatherNode" target="_blank" rel="noopener noreferrer" class="text-sm text-ui-muted hover:text-ui-fg transition-colors">
                            {{ __('GitHub Repository') }}
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('legal.license') }}" class="text-sm text-ui-muted hover:text-ui-fg transition-colors">
                            {{ __('License') }}
                        </a>
                    </li>
                </ul>
            </div>

        </div>

        @if($showSeoText)
        <!-- Optional SEO text for search engines (full-width below link columns, above copyright) -->
        <section class="mt-8 pt-6 border-t border-ui-line/10" aria-label="{{ __('Site Description') }}">
            <p class="text-sm text-ui-muted max-w-4xl">{{ __('Footer SEO text', ['location' => \App\Models\Setting::stationLocation() ?: \App\Models\Setting::stationName()]) }}</p>
        </section>
        @endif

        <!-- Bottom Bar -->
        <div class="mt-8 pt-6 border-t border-ui-line/10">
            <div class="flex flex-col md:flex-row justify-between items-center gap-4 text-xs text-ui-subtle">
                <div>
                    <p>&copy; {{ date('Y') }} {{ __('Local weather and forecast in') }} {{ \App\Models\Setting::stationLocation() }}. {{ __('All rights reserved.') }}</p>
                </div>
                <div class="flex items-center gap-4">
                    <span>{{ __('Powered by') }} <a href="https://weathernode.dev" target="_blank" rel="noopener noreferrer" class="hover:text-ui-fg transition-colors">WeatherNode</a></span>
                </div>
            </div>
        </div>
        @endif
    </div>
</footer>
@else
<!-- Minimal footer when footer disabled - but still show WeatherNode links -->
<footer class="border-t border-ui-line/10 mt-8 py-6 pb-24 lg:pb-6 relative z-10">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex flex-col md:flex-row justify-between items-center gap-4 text-sm text-ui-muted">
            <div class="flex flex-wrap items-center gap-4">
                <span>&copy; {{ date('Y') }} {{ __('Local weather and forecast in') }} {{ \App\Models\Setting::stationLocation() }}</span>
                <span class="text-ui-subtle">•</span>
                <a href="https://weathernode.dev" target="_blank" rel="noopener noreferrer" class="hover:text-ui-fg transition-colors">WeatherNode</a>
                <span class="text-ui-subtle">•</span>
                <a href="https://github.com/centauri/WeatherNode" target="_blank" rel="noopener noreferrer" class="hover:text-ui-fg transition-colors">GitHub</a>
                <span class="text-ui-subtle">•</span>
                <a href="{{ route('legal.about') }}" class="hover:text-ui-fg transition-colors">{{ __('About') }}</a>
                <span class="text-ui-subtle">•</span>
                <a href="{{ route('share') }}" class="hover:text-ui-fg transition-colors">{{ __('Share & Embed') }}</a>
                @if($showCookieSettingsLink)
                <span class="text-ui-faint">•</span>
                <a href="{{ $cookieSettingsUrl }}"
                   onclick="if (window.location.pathname === '/' && window.dispatchEvent) { event.preventDefault(); window.dispatchEvent(new CustomEvent('meteo:open-cookie-settings')); return false; }"
                   class="hover:text-ui-fg transition-colors">{{ __('Cookie settings') }}</a>
                @endif
            </div>
        </div>
    </div>
</footer>
@endif
