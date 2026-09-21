<!DOCTYPE html>
<html data-public-theme="{{ $publicAppearance['palette'] ?? 'weathernode' }}" data-default-color-mode="{{ $publicAppearance['mode'] ?? 'dark' }}" data-color-mode="{{ ($publicAppearance['mode'] ?? 'dark') === 'light' ? 'light' : 'dark' }}" lang="{{ $jsLocale ?? app()->getLocale() }}" class="{{ ($publicAppearance['mode'] ?? 'dark') === 'light' ? '' : 'dark' }}">
<head>
    <x-public-theme-head />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('First-run setup') }} - WeatherNode</title>
    <meta name="robots" content="noindex, nofollow">

    @include('auth._dark-shell')
</head>
<body class="text-ui-fg min-h-screen font-sans {{ ($siteTheme ?? 'fx') === 'flat' ? 'theme-flat' : '' }}">
    <div class="weather-bg {{ ($siteTheme ?? 'fx') === 'flat' ? 'weather-bg--static' : 'weather-bg--animated' }}" aria-hidden="true"></div>

    <main class="max-w-7xl mx-auto px-4 py-8">
        <div class="flex items-center justify-center min-h-[85vh]">
            <div class="w-full max-w-md">
                <div class="bg-weather-card rounded-2xl p-8 glow border border-ui-line/10">
                    <div class="text-center mb-6">
                        <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-cyan-400 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg shadow-blue-500/30">
                            <svg class="w-8 h-8 text-ui-fg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
                            </svg>
                        </div>
                        <h1 class="text-2xl font-bold">{{ __('Welcome to WeatherNode') }}</h1>
                        <p class="text-ui-muted text-sm mt-1">{{ __('First-run setup: create the initial administrator account.') }}</p>
                    </div>

                    @if ($errors->any())
                        <div class="mb-6 p-4 bg-red-500/20 border border-red-500/30 rounded-xl text-data-red-400 text-sm">
                            @foreach ($errors->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('setup.admin.store') }}">
                        @csrf

                        <div class="mb-5">
                            <label for="name" class="block text-sm font-medium text-ui-secondary mb-2">{{ __('Name') }}</label>
                            <input id="name" type="text" name="name" value="{{ old('name', 'Administrator') }}"
                                   class="input-dark w-full px-4 py-3 rounded-xl text-ui-fg placeholder-ui-subtle"
                                   required autofocus autocomplete="name">
                        </div>

                        <div class="mb-5">
                            <label for="email" class="block text-sm font-medium text-ui-secondary mb-2">{{ __('Email address') }}</label>
                            <input id="email" type="email" name="email" value="{{ old('email') }}"
                                   class="input-dark w-full px-4 py-3 rounded-xl text-ui-fg placeholder-ui-subtle"
                                   required autocomplete="username">
                        </div>

                        <div class="mb-5">
                            <label for="password" class="block text-sm font-medium text-ui-secondary mb-2">{{ __('Password') }}</label>
                            <input id="password" type="password" name="password"
                                   class="input-dark w-full px-4 py-3 rounded-xl text-ui-fg placeholder-ui-subtle"
                                   required autocomplete="new-password">
                        </div>

                        <div class="mb-6">
                            <label for="password_confirmation" class="block text-sm font-medium text-ui-secondary mb-2">{{ __('Confirm Password') }}</label>
                            <input id="password_confirmation" type="password" name="password_confirmation"
                                   class="input-dark w-full px-4 py-3 rounded-xl text-ui-fg placeholder-ui-subtle"
                                   required autocomplete="new-password">
                        </div>

                        <button type="submit" class="btn-primary w-full py-3 rounded-xl font-semibold text-ui-fg">
                            {{ __('Create admin') }}
                        </button>
                    </form>

                    <p class="text-center text-sm text-ui-subtle mt-6">
                        <a href="{{ route('login') }}" class="hover:text-ui-secondary transition-colors">{{ __('Back to login') }}</a>
                    </p>
                </div>

                <p class="text-center text-xs text-ui-subtle mt-6">
                    {{ __('This page is only available while no account exists.') }}
                </p>
            </div>
        </div>
    </main>
</body>
</html>
