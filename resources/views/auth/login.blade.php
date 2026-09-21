<!DOCTYPE html>
<html data-public-theme="{{ $publicAppearance['palette'] ?? 'weathernode' }}" data-default-color-mode="{{ $publicAppearance['mode'] ?? 'dark' }}" data-color-mode="{{ ($publicAppearance['mode'] ?? 'dark') === 'light' ? 'light' : 'dark' }}" lang="{{ $jsLocale ?? app()->getLocale() }}" class="{{ ($publicAppearance['mode'] ?? 'dark') === 'light' ? '' : 'dark' }}">
<head>
    <x-public-theme-head />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Login') }} - WeatherNode</title>
    <meta name="description" content="{{ __('Log in to the WeatherNode admin panel') }}">
    
    @include('auth._dark-shell')
</head>
<body class="text-ui-fg min-h-screen font-sans {{ ($siteTheme ?? 'fx') === 'flat' ? 'theme-flat' : '' }}">
    <div class="weather-bg {{ ($siteTheme ?? 'fx') === 'flat' ? 'weather-bg--static' : 'weather-bg--animated' }}" aria-hidden="true"></div>

    <!-- Top Bar: Same Header as Dashboard -->
    <header class="glass border-b border-ui-line/10 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-2 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-gradient-to-br from-ui-accent to-ui-accent-end rounded-lg flex items-center justify-center shadow-lg shadow-blue-500/30">
                    <svg class="w-5 h-5 text-on-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-lg font-bold">WeatherNode</h1>
                    <p class="text-xs text-ui-muted">{{ \App\Models\Setting::stationName() }}</p>
                </div>
            </div>
            
            <div class="hidden md:flex items-center gap-2 text-sm">
                <span class="inline-block w-2 h-2 bg-green-500 text-on-accent rounded-full shadow-lg shadow-green-500/50"></span>
                <span class="text-ui-secondary font-display" id="currentTime">--:--:--</span>
                <span class="text-ui-subtle">|</span>
                <span class="text-ui-secondary" id="currentDate">--</span>
                <span class="text-ui-subtle text-xs ml-1" id="currentTimeZoneLabel"></span>
            </div>
            
            <div class="flex items-center gap-2">
                <x-public-theme-select />
                <a href="{{ route('home') }}" class="px-3 py-1 text-xs bg-ui-overlay/10 hover:bg-ui-overlay/20 rounded transition-colors">
                    ← {{ __('Home') }}
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 py-8">
        <div class="flex items-center justify-center min-h-[70vh]">
            <div class="w-full max-w-md">
                
                <!-- Login Card -->
                <div class="bg-weather-card rounded-2xl p-8 glow border border-ui-line/10">
                    <div class="text-center mb-6">
                        <div class="w-16 h-16 bg-gradient-to-br from-ui-accent to-ui-accent-end rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg shadow-blue-500/30">
                            <svg class="w-8 h-8 text-ui-fg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                        </div>
                        <h2 class="text-2xl font-bold">{{ __('Admin login') }}</h2>
                        <p class="text-ui-muted text-sm mt-1">{{ __('Log in to manage the dashboard') }}</p>
                    </div>

                    <!-- Session Status -->
                    @if (session('status'))
                        <div class="mb-6 p-4 bg-green-500/20 border border-green-500/30 rounded-xl text-data-green-400 text-sm">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if(($showInitialSetupLink ?? false) === true)
                        <div class="mb-6 p-4 bg-blue-500/20 border border-blue-500/30 rounded-xl text-data-blue-300 text-sm">
                            <p class="font-medium">{{ __('First-time setup detected') }}</p>
                            <p class="mt-1">
                                {{ __('No users exist yet.') }}
                                <a href="{{ route('setup.admin.create') }}" class="underline hover:text-ui-link">
                                    {{ __('Create your first admin account') }}
                                </a>.
                            </p>
                        </div>
                    @endif

                    <!-- Error Messages -->
                    @if ($errors->any())
                        <div class="mb-6 p-4 bg-red-500/20 border border-red-500/30 rounded-xl text-data-red-400 text-sm">
                            @foreach ($errors->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login') }}">
                        @csrf

                        <!-- Email Address -->
                        <div class="mb-5">
                            <label for="email" class="block text-sm font-medium text-ui-secondary mb-2">
                                <span class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path>
                                    </svg>
                                    {{ __('Email address') }}
                                </span>
                            </label>
                            <input 
                                id="email" 
                                type="email" 
                                name="email" 
                                value="{{ old('email') }}"
                                class="input-dark w-full px-4 py-3 rounded-xl text-ui-fg placeholder-ui-subtle"
                                required 
                                autofocus 
                                autocomplete="username"
                            >
                        </div>

                        <!-- Password -->
                        <div class="mb-5">
                            <label for="password" class="block text-sm font-medium text-ui-secondary mb-2">
                                <span class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                    </svg>
                                    {{ __('Password') }}
                                </span>
                            </label>
                            <input 
                                id="password" 
                                type="password" 
                                name="password"
                                class="input-dark w-full px-4 py-3 rounded-xl text-ui-fg placeholder-ui-subtle"
                                required 
                                autocomplete="current-password"
                            >
                        </div>

                        <!-- Remember Me -->
                        <div class="flex items-center justify-between mb-6">
                            <label for="remember_me" class="flex items-center cursor-pointer">
                                <input 
                                    id="remember_me" 
                                    type="checkbox" 
                                    name="remember"
                                    class="w-4 h-4 rounded bg-weather-dark border-ui-line/20 text-data-blue-500 focus:ring-blue-500 focus:ring-offset-0"
                                >
                                <span class="ml-2 text-sm text-ui-muted">{{ __('Remember me') }}</span>
                            </label>
                            
                            @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}" class="text-sm text-ui-link hover:text-data-blue-300 transition-colors">
                                    {{ __('Forgot password?') }}
                                </a>
                            @endif
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="btn-primary w-full py-3 px-4 rounded-xl font-semibold text-ui-fg flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                            </svg>
                            {{ __('Log in') }}
                        </button>
                    </form>
                </div>

            </div>
        </div>
    </main>

    <!-- Footer - Same as Dashboard -->
    <footer class="border-t border-ui-line/10 mt-8 py-6 relative z-10">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between items-center gap-4 text-sm text-ui-muted">
                <div class="flex items-center gap-4">
                    @php
                        $stationHardware = \App\Models\Setting::getValue('station.hardware', '');
                        $stationStartDate = \App\Models\Setting::getValue('station.start_date', '');
                        $stationStartYear = null;
                        if (is_string($stationStartDate) && $stationStartDate !== '') {
                            try {
                                $stationStartYear = \Carbon\Carbon::parse($stationStartDate)->year;
                            } catch (\Throwable) {
                                $stationStartYear = null;
                            }
                        }
                    @endphp
                    @if($stationHardware)
                    <span>{{ $stationHardware }}</span>
                    @endif
                    @if($stationHardware && $stationStartYear)
                    <span>•</span>
                    @endif
                    @if($stationStartYear)
                    <span>{{ __('Data since :year', ['year' => $stationStartYear]) }}</span>
                    @endif
                </div>
                <div class="flex items-center gap-4">
                    <a href="https://yr.no" class="hover:text-ui-fg transition-colors">Yr.no</a>
                    <a href="https://waqi.info" class="hover:text-ui-fg transition-colors">WAQI</a>
                    <a href="https://wunderground.com" class="hover:text-ui-fg transition-colors">WU</a>
                </div>
            </div>
        </div>
    </footer>

    <script>
        const stationTimezone = @json($stationTimezone ?? \App\Models\Setting::timezone());
        // Update clock (station timezone; DST via IANA + Intl)
        function updateClock() {
            const now = new Date();
            const locale = window.Meteo?.jsLocale || 'en-US';
            const tz = stationTimezone || 'UTC';
            const opts = { timeZone: tz };
            const timeStr = now.toLocaleTimeString(locale, { ...opts, hour: '2-digit', minute: '2-digit', second: '2-digit' });
            const dateStr = now.toLocaleDateString(locale, { ...opts, weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
            const parts = new Intl.DateTimeFormat(locale, { timeZone: tz, timeZoneName: 'short' }).formatToParts(now);
            const tzPart = parts.find(p => p.type === 'timeZoneName');
            const tzLabel = tzPart ? `(${tzPart.value})` : '';
            document.getElementById('currentTime').textContent = timeStr;
            document.getElementById('currentDate').textContent = dateStr;
            const tzEl = document.getElementById('currentTimeZoneLabel');
            if (tzEl) tzEl.textContent = tzLabel;
        }
        updateClock();
        setInterval(updateClock, 1000);
    </script>

</body>
</html>
