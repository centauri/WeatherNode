<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <!-- Theme Color for Safari/Chrome status bar -->
    <meta name="theme-color" content="#1f2937" media="(prefers-color-scheme: dark)">
    <meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
    <meta name="color-scheme" content="light dark">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">

    <title>{{ config('app.name', 'WeatherNode') }} - {{ __('Admin') }}</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/images/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/images/favicon-16x16.png">
    <link rel="icon" type="image/x-icon" href="/images/favicon.ico">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/admin.js'])

    <style>
        .sidebar-section[open] .sidebar-chevron {
            transform: rotate(180deg);
        }
        .sidebar-chevron {
            transition: transform 0.2s ease-in-out;
        }
        [x-cloak] { display: none !important; }
        
        /* Hide hamburger menu button on desktop */
        @media (min-width: 768px) {
            button[class*="md:hidden"] {
                display: none !important;
            }
            /* Also target by parent context */
            header button.md\:hidden {
                display: none !important;
            }
        }
        
        /* Sidebar: hidden on mobile by default, visible on desktop */
        @media (max-width: 767px) {
            aside.fixed {
                transform: translateX(-100%) !important;
            }
            aside.fixed.show-sidebar {
                transform: translateX(0) !important;
            }
        }
        /* On desktop, sidebar is static (not fixed) so no transform needed - always visible */
        @media (min-width: 768px) {
            aside.md\:static {
                position: static !important;
                transform: none !important;
                display: flex !important;
            }
        }
        
        /* Buy Me a Coffee button - image based */
        .bmc-link {
            align-items: center;
            flex-shrink: 0;
            transition: opacity 0.2s;
        }
        .bmc-link:hover {
            opacity: 0.8;
        }
        .bmc-link img {
            display: block;
        }
        
        /* Responsive visibility for BMC buttons */
        .bmc-mobile {
            display: inline-flex;
        }
        .bmc-desktop {
            display: none;
        }
        @media (min-width: 768px) {
            .bmc-mobile {
                display: none;
            }
            .bmc-desktop {
                display: inline-flex;
            }
        }
        
        /* Floating header on mobile */
        @media (max-width: 767px) {
            .mobile-header {
                position: fixed !important;
                top: 0;
                left: 0;
                right: 0;
                z-index: 40;
                transition: transform 0.25s ease-out;
            }
            /* Add padding to main content to account for fixed header */
            #main-content {
                padding-top: 64px !important;
            }
        }
    </style>

    @php
        // Get theme setting from database
        $themeSetting = \App\Models\Setting::where('key', 'display.theme')->value('value') ?? 'dark';
    @endphp

    <!-- Prevent flash of wrong theme on page load -->
    <script>
        (function() {
            const serverTheme = '{{ $themeSetting }}';
            const storedTheme = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

            // Priority: localStorage > server setting > system preference
            let shouldBeDark = false;

            if (storedTheme) {
                shouldBeDark = storedTheme === 'dark';
            } else if (serverTheme === 'user') {
                shouldBeDark = prefersDark;
            } else {
                shouldBeDark = serverTheme === 'dark';
            }

            if (shouldBeDark) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
</head>
<body class="font-sans antialiased bg-gray-100 dark:bg-gray-900" 
      x-data="{ 
          sidebarOpen: false,
          isMobile: window.innerWidth < 768,
          init() {
              // Update isMobile on resize and close sidebar on desktop
              const updateMobile = () => {
                  this.isMobile = window.innerWidth < 768;
                  if (!this.isMobile) {
                      this.sidebarOpen = false;
                  }
              };
              window.addEventListener('resize', updateMobile);
          }
      }">
    <!-- Mobile sidebar overlay - only visible on mobile -->
    <div x-show="sidebarOpen && isMobile" 
         x-cloak
         x-transition:enter="transition-opacity ease-linear duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-300"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="sidebarOpen = false"
         class="fixed inset-0 bg-gray-600 bg-opacity-75 z-40"></div>
    
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <aside :class="(sidebarOpen && isMobile) ? 'show-sidebar' : ''"
               class="fixed md:static inset-y-0 left-0 z-50 w-64 bg-slate-800 text-white flex-shrink-0 flex flex-col transition-transform duration-300 ease-in-out">
            <div class="p-4 border-b border-slate-700">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center space-x-2">
                    <svg class="w-8 h-8 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
                    </svg>
                    <span class="text-lg font-semibold">{{ config('app.name', 'WeatherNode') }}</span>
                </a>
                <p class="text-xs text-slate-400 mt-1">{{ __('Admin Panel') }}</p>
            </div>

            <nav class="flex-1 overflow-y-auto p-4 space-y-1 text-sm" 
                 x-on:click="if (isMobile && $event.target.closest('a')) sidebarOpen = false">
                <a href="{{ route('admin.dashboard') }}" 
                   class="flex items-center space-x-2 px-3 py-2 rounded-lg {{ request()->routeIs('admin.dashboard') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    <span>{{ __('Dashboard') }}</span>
                </a>

                <a href="{{ route('admin.visitors.index') }}" 
                   class="flex items-center space-x-2 px-3 py-2 rounded-lg {{ request()->routeIs('admin.visitors.index') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v8m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V7a2 2 0 012-2h2a2 2 0 012 2v10a2 2 0 01-2 2h-2a2 2 0 01-2-2"/>
                    </svg>
                    <span>{{ __('Visitor Analytics') }}</span>
                </a>

                <a href="{{ route('admin.settings.index') }}" 
                   class="flex items-center justify-between px-3 py-2 rounded-lg {{ request()->routeIs('admin.settings.index') && !request()->is('admin/settings/*') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                    <div class="flex items-center space-x-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <span>{{ __('All Settings') }}</span>
                    </div>
                    @php
                        $githubService = app(\App\Services\Update\GithubReleaseService::class);
                        $hasUpdate = $githubService->isUpdateAvailable();
                    @endphp
                    @if($hasUpdate)
                        <span class="px-2 py-0.5 text-xs bg-blue-500 text-white rounded-full">{{ __('Update') }}</span>
                    @endif
                </a>
                
                <a href="{{ route('admin.settings.updates') }}" 
                   class="flex items-center space-x-2 px-3 py-2 rounded-lg {{ request()->routeIs('admin.settings.updates') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    <span>{{ __('Updates') }}</span>
                    @if($hasUpdate)
                        <span class="ml-auto px-2 py-0.5 text-xs bg-blue-500 text-white rounded-full animate-pulse">{{ __('New') }}</span>
                    @endif
                </a>


                <!-- WEATHER STATION -->
                <details class="sidebar-section" {{
                    request()->is('admin/settings/station') ||
                    request()->is('admin/settings/livedata') ||
                    request()->is('admin/settings/history') ||
                    request()->is('admin/settings/sensors')
                    ? 'open' : ''
                }}>
                    <summary class="px-3 py-2 text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer flex items-center justify-between hover:text-slate-300">
                        {{ __('Weather Station') }}
                        <svg class="w-4 h-4 sidebar-chevron transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </summary>
                    <div class="mt-1 space-y-1">
                        <a href="{{ route('admin.settings.group', 'station') }}" 
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->is('admin/settings/station') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            </svg>
                            <span>{{ __('Station Info') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.group', 'livedata') }}" 
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->is('admin/settings/livedata') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            <span>{{ __('Live Data Source') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.group', 'history') }}" 
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->is('admin/settings/history') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/>
                            </svg>
                            <span>{{ __('Historical Data') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.group', 'sensors') }}" 
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->is('admin/settings/sensors') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/>
                            </svg>
                            <span>{{ __('Sensors') }}</span>
                        </a>
                    </div>
                </details>

                <!-- DATA SOURCES -->
                <details class="sidebar-section" {{ request()->is('admin/settings/ecowitt') || request()->is('admin/settings/wunderground') || request()->is('admin/settings/weatherflow') || request()->is('admin/settings/weatherlink') || request()->is('admin/settings/ambient') || request()->is('admin/settings/openweathermap') || request()->is('admin/settings/yrno') || request()->is('admin/settings/wxsim') || request()->is('admin/settings/environment_canada') || request()->is('admin/settings/airquality') || request()->is('admin/settings/pollen') || request()->is('admin/settings/aviation') || request()->is('admin/settings/tide') || request()->is('admin/settings/waves') || request()->is('admin/settings/rivers') || request()->is('admin/settings/opendata') ? 'open' : '' }}>
                    <summary class="px-3 py-2 text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer flex items-center justify-between hover:text-slate-300">
                        {{ __('Data Sources') }}
                        <svg class="w-4 h-4 sidebar-chevron transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </summary>
                    <div class="mt-1 space-y-1">
                        <a href="{{ route('admin.settings.group', 'ecowitt') }}" 
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->is('admin/settings/ecowitt') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/>
                            </svg>
                            <span>{{ __('Ecowitt') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.group', 'wunderground') }}" 
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->is('admin/settings/wunderground') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
                            </svg>
                            <span>{{ __('Weather Underground') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.group', 'weatherflow') }}" 
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->is('admin/settings/weatherflow') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12h9a3 3 0 100-6 3 3 0 00-3 3"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6h10a2 2 0 110 4h-1"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 18h7a2 2 0 110 4"/>
                            </svg>
                            <span>{{ __('WeatherFlow') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.group', 'openweathermap') }}" 
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->is('admin/settings/openweathermap') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg>
                            <span>{{ __('OpenWeatherMap') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.group', 'yrno') }}" 
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->is('admin/settings/yrno') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span>{{ __('Yr.no') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.group', 'wxsim') }}" 
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->is('admin/settings/wxsim') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                            </svg>
                            <span>WXSIM</span>
                        </a>
                        <a href="{{ route('admin.settings.group', 'environment_canada') }}" 
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->is('admin/settings/environment_canada') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span>Environment Canada</span>
                        </a>
                        <a href="{{ route('admin.settings.group', 'weatherlink') }}" 
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->is('admin/settings/weatherlink') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                            </svg>
                            <span>{{ __('Davis WeatherLink') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.group', 'ambient') }}" 
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->is('admin/settings/ambient') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.07 12.93a10 10 0 0113.86 0"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.11 15.97a6 6 0 017.78 0"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.15 19.01a2 2 0 012.7 0"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21h.01"/>
                            </svg>
                            <span>{{ __('Ambient Weather') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.group', 'airquality') }}"
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->is('admin/settings/airquality') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"/>
                            </svg>
                            <span>{{ __('Air Quality') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.group', 'pollen') }}"
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->is('admin/settings/pollen') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3C10.343 3 9 4.343 9 6s1.343 3 3 3 3-1.343 3-3-1.343-3-3-3zM6.343 8.343a1 1 0 00-1.414 1.414l1.414 1.414a1 1 0 001.414-1.414L6.343 8.343zM3 12a1 1 0 000 2h2a1 1 0 000-2H3zM17.657 8.343l-1.414 1.414a1 1 0 001.414 1.414l1.414-1.414a1 1 0 00-1.414-1.414zM19 12a1 1 0 000 2h2a1 1 0 000-2h-2zM12 18a1 1 0 000 2v2a1 1 0 000-2v-2zM6.343 15.657l-1.414 1.414a1 1 0 001.414 1.414l1.414-1.414a1 1 0 00-1.414-1.414zM15.657 15.657a1 1 0 00-1.414 1.414l1.414 1.414a1 1 0 001.414-1.414l-1.414-1.414z"/>
                            </svg>
                            <span>{{ __('Pollen Forecast') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.group', 'aviation') }}"
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->is('admin/settings/aviation') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                            </svg>
                            <span>{{ __('Aviation / METAR') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.group', 'tide') }}"
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->is('admin/settings/tide') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
                            </svg>
                            <span>{{ __('Tides') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.group', 'waves') }}"
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->is('admin/settings/waves') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
                            </svg>
                            <span>{{ __('Waves & Sea Temp') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.group', 'rivers') }}"
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->is('admin/settings/rivers') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/>
                            </svg>
                            <span>{{ __('River Levels') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.opendata') }}" 
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->is('admin/settings/opendata') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span>{{ __('Open Data Sources') }}</span>
                        </a>
                    </div>
                </details>

                <!-- FEATURES -->
                <details class="sidebar-section" {{ request()->is('admin/settings/forecast') || request()->is('admin/settings/alerts') || request()->is('admin/settings/lightning') || request()->is('admin/settings/webcam') || request()->is('admin/settings/radar') || request()->is('admin/settings/satellite') || request()->is('admin/settings/solar_forecast') || request()->is('admin/settings/thresholds') || request()->is('admin/settings/iss') ? 'open' : '' }}>
                    <summary class="px-3 py-2 text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer flex items-center justify-between hover:text-slate-300">
                        {{ __('Features') }}
                        <svg class="w-4 h-4 sidebar-chevron transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </summary>
                    <div class="mt-1 space-y-1">
                        <a href="{{ route('admin.settings.group', 'forecast') }}" 
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->is('admin/settings/forecast') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span>{{ __('Forecast') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.group', 'alerts') }}" 
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->is('admin/settings/alerts') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            <span>{{ __('Weather Alerts') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.group', 'lightning') }}" 
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->is('admin/settings/lightning') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            <span>{{ __('Lightning') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.group', 'webcam') }}" 
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->is('admin/settings/webcam') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <span>{{ __('Webcam') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.group', 'radar') }}" 
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->is('admin/settings/radar') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.636 18.364a9 9 0 010-12.728m12.728 0a9 9 0 010 12.728m-9.9-2.829a5 5 0 010-7.07m7.072 0a5 5 0 010 7.07M13 12a1 1 0 11-2 0 1 1 0 012 0z"/>
                            </svg>
                            <span>{{ __('Rain Radar') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.group', 'satellite') }}" 
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->is('admin/settings/satellite') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3"/>
                            </svg>
                            <span>{{ __('Satellite Imagery') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.group', 'solar_forecast') }}" 
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->is('admin/settings/solar_forecast') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg>
                            <span>{{ __('Solar Radiation Forecast') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.group', 'thresholds') }}" 
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->is('admin/settings/thresholds') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            <span>{{ __('Thresholds') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.group', 'iss') }}" 
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->is('admin/settings/iss') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span>{{ __('ISS / Space Stations') }}</span>
                        </a>
                    </div>
                </details>

                <!-- DISPLAY -->
                <details class="sidebar-section" {{ request()->is('admin/settings/display') || request()->is('admin/settings/navigation') || request()->routeIs('admin.settings.widgets') || request()->routeIs('admin.settings.charts') || request()->routeIs('admin.settings.effects') || request()->routeIs('admin.settings.appearance') || request()->routeIs('admin.settings.integrations') || request()->is('admin/settings/seo') || request()->is('admin/settings/og') || request()->is('admin/settings/contact') ? 'open' : '' }}>
                    <summary class="px-3 py-2 text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer flex items-center justify-between hover:text-slate-300">
                        {{ __('Display') }}
                        <svg class="w-4 h-4 sidebar-chevron transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </summary>
                    <div class="mt-1 space-y-1">
                        <a href="{{ route('admin.settings.group', 'display') }}" 
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->is('admin/settings/display') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            <span>{{ __('Display Settings') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.group', 'navigation') }}"
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->is('admin/settings/navigation') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                            <span>{{ __('Navigation') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.widgets') }}"
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->routeIs('admin.settings.widgets') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
                            </svg>
                            <span>{{ __('Dashboard Widgets') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.charts') }}"
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->routeIs('admin.settings.charts') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            <span>{{ __('History Charts') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.effects') }}" 
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->routeIs('admin.settings.effects') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
                            </svg>
                            <span>{{ __('Weather Effects') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.appearance') }}" 
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->routeIs('admin.settings.appearance') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/>
                            </svg>
                            <span>{{ __('Appearance') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.group', 'seo') }}"
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->is('admin/settings/seo') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <span>{{ __('SEO & Meta') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.group', 'og') }}"
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->is('admin/settings/og') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                            </svg>
                            <span>{{ __('Social Sharing Cards') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.group', 'contact') }}"
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->is('admin/settings/contact') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            <span>{{ __('Contact & Social') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.integrations') }}"
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->routeIs('admin.settings.integrations') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                            </svg>
                            <span>{{ __('Head Code & Integrations') }}</span>
                        </a>
                    </div>
                </details>

                <!-- SYSTEM -->
                <details class="sidebar-section" {{ request()->is('admin/settings/advanced') || request()->is('admin/settings/footer') || request()->is('admin/settings/scheduler') || request()->is('admin/settings/nlg') || request()->is('admin/settings/mail') || request()->is('admin/settings/notifications') || request()->routeIs('admin.users.*') || request()->routeIs('admin.api-keys.*') ? 'open' : '' }}>
                    <summary class="px-3 py-2 text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer flex items-center justify-between hover:text-slate-300">
                        {{ __('System') }}
                        <svg class="w-4 h-4 sidebar-chevron transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </summary>
                    <div class="mt-1 space-y-1">
                        <a href="{{ route('admin.settings.group', 'nlg') }}" 
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->is('admin/settings/nlg') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                            </svg>
                            <span>{{ __('NLG / Text Generation') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.group', 'advanced') }}" 
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->is('admin/settings/advanced') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                            </svg>
                            <span>{{ __('Advanced') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.group', 'footer') }}" 
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->is('admin/settings/footer') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                            <span>{{ __('Footer') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.group', 'scheduler') }}" 
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->is('admin/settings/scheduler') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2"/>
                            </svg>
                            <span>{{ __('Schedulers') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.mail') }}" 
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->is('admin/settings/mail') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            <span>{{ __('Mail') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.group', 'notifications') }}" 
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->is('admin/settings/notifications') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                            </svg>
                            <span>{{ __('Notifications') }}</span>
                        </a>
                        <a href="{{ route('admin.api-keys.index') }}"
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->routeIs('admin.api-keys.*') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7v10a4 4 0 004 4h2a4 4 0 004-4V7"/>
                            </svg>
                            <span>{{ __('API Keys') }}</span>
                        </a>
                        <a href="{{ route('admin.users.index') }}" 
                           class="flex items-center space-x-2 px-3 py-1.5 rounded-lg {{ request()->routeIs('admin.users.*') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                            </svg>
                            <span>{{ __('Users') }}</span>
                        </a>
                    </div>
                </details>
            </nav>

            <div class="p-4 border-t border-slate-700 space-y-1" 
                 x-on:click="if (isMobile && $event.target.closest('a')) sidebarOpen = false">
                <a href="{{ route('admin.help') }}" 
                   class="flex items-center space-x-2 px-3 py-2 rounded-lg {{ request()->routeIs('admin.help') ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-700' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>{{ __('Setup Guide') }}</span>
                </a>
                <a href="{{ route('home') }}" 
                   class="flex items-center space-x-2 px-3 py-2 rounded-lg text-slate-300 hover:bg-slate-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    <span>{{ __('Back to Site') }}</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col min-h-screen overflow-hidden">
            <!-- Top Bar -->
            <header id="mobile-header" class="bg-white dark:bg-gray-800 shadow-sm flex-shrink-0 mobile-header">
                <div class="px-2 md:px-6 py-3 md:py-4 flex items-center justify-between gap-2">
                    <div class="flex items-center space-x-3">
                        <!-- Mobile menu button -->
                        <button x-show="isMobile"
                                x-cloak
                                @click="sidebarOpen = !sidebarOpen" 
                                class="p-2 rounded-md text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path x-show="!sidebarOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                                <path x-show="sidebarOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                        <h1 class="text-lg md:text-xl font-semibold text-gray-800 dark:text-white">
                            @yield('title', __('Dashboard'))
                        </h1>
                    </div>

                    <div class="flex items-center space-x-1 md:space-x-4">
                        <!-- Buy Me a Coffee - Mobile (icon only) -->
                        <a href="https://www.buymeacoffee.com/centauriprime" target="_blank" rel="noopener noreferrer" class="bmc-link bmc-mobile">
                            <img src="https://cdn.buymeacoffee.com/buttons/bmc-new-btn-logo.svg" alt="Buy me a coffee" class="h-7">
                        </a>
                        <!-- Buy Me a Coffee - Desktop (full button) -->
                        <a href="https://www.buymeacoffee.com/centauriprime" target="_blank" rel="noopener noreferrer" class="bmc-link bmc-desktop">
                            <img src="https://img.buymeacoffee.com/button-api/?text=Buy me a coffee&emoji=&slug=centauriprime&button_colour=FFDD00&font_colour=000000&font_family=Cookie&outline_colour=000000&coffee_colour=ffffff" alt="Buy me a coffee" class="h-8">
                        </a>
                        
                        <!-- Theme Toggle -->
                        <div class="scale-90 md:scale-100">
                            <x-theme-toggle :serverTheme="$themeSetting" />
                        </div>

                        <!-- User Info -->
                        <span class="hidden lg:inline text-sm text-gray-600 dark:text-gray-300">
                            {{ auth()->user()->name }}
                        </span>

                        <!-- Logout -->
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="text-xs md:text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors px-1 md:px-0">
                                {{ __('Logout') }}
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main id="main-content" class="flex-1 overflow-y-auto p-4 md:p-6">
                @yield('content')
            </main>

            <!-- Footer -->
            <footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 px-6 py-4 flex-shrink-0">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    WeatherNode {{ \App\Services\VersionService::getAppVersion() }} &copy; {{ date('Y') }}
                </p>
            </footer>
        </div>
    </div>

    {{-- Page-specific scripts --}}
    @stack('scripts')
    
    <!-- Floating header scroll behavior (mobile only) -->
    <script>
        (function() {
            const header = document.getElementById('mobile-header');
            const mainContent = document.getElementById('main-content');
            
            if (!header) return;
            
            let lastScrollTop = 0;
            const scrollThreshold = 8;
            
            function getScrollTop() {
                // Try main content first, then window
                if (mainContent && mainContent.scrollTop > 0) {
                    return mainContent.scrollTop;
                }
                return window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop || 0;
            }
            
            function handleScroll() {
                // Only apply on mobile
                if (window.innerWidth >= 768) {
                    header.classList.remove('header-hidden', 'header-visible');
                    header.style.transform = '';
                    return;
                }
                
                const scrollTop = getScrollTop();
                const scrollDelta = scrollTop - lastScrollTop;
                
                // Scrolling down - hide header (only after scrolling a bit)
                if (scrollDelta > scrollThreshold && scrollTop > 60) {
                    header.style.transform = 'translateY(-100%)';
                }
                // Scrolling up - show header
                else if (scrollDelta < -scrollThreshold) {
                    header.style.transform = 'translateY(0)';
                }
                // At top - always show header
                if (scrollTop <= 10) {
                    header.style.transform = 'translateY(0)';
                }
                
                lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
            }
            
            // Listen to main content scroll
            if (mainContent) {
                mainContent.addEventListener('scroll', handleScroll, { passive: true });
            }
            
            // Also listen to window/document scroll
            window.addEventListener('scroll', handleScroll, { passive: true });
            document.addEventListener('scroll', handleScroll, { passive: true });
            
            // Reset on resize
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 768) {
                    header.style.transform = '';
                }
            });
        })();
    </script>
</body>
</html>
