<!DOCTYPE html>
<html data-public-theme="{{ $publicAppearance['palette'] ?? 'weathernode' }}" data-default-color-mode="{{ $publicAppearance['mode'] ?? 'dark' }}" data-color-mode="{{ ($publicAppearance['mode'] ?? 'dark') === 'light' ? 'light' : 'dark' }}" lang="{{ app()->getLocale() }}" class="{{ ($publicAppearance['mode'] ?? 'dark') === 'light' ? '' : 'dark' }}">
<head>
    <x-public-theme-head />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Error') — {{ config('app.name', 'WeatherNode') }}</title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#1a2332">
    <meta name="color-scheme" content="dark light">
    
    <link rel="icon" type="image/png" sizes="32x32" href="/images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/images/favicon-16x16.png">
    <link rel="icon" type="image/x-icon" href="/images/favicon.ico">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    @if(is_file(public_path('build/manifest.json')))
        @vite(['resources/css/app.css'])
    @endif

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        
        :root {
            --bg-base: rgb(var(--wn-bg, 15 20 25));
            --bg-card: rgb(var(--wn-card) / 0.9);
            --text-primary: rgb(var(--wn-fg));
            --text-secondary: rgb(var(--wn-muted));
            --accent: rgb(var(--wn-link));
            --accent-glow: rgb(var(--wn-accent) / 0.25);
        }
        
        html { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
        
        body {
            min-height: 100vh;
            background:
                radial-gradient(ellipse 120% 80% at 50% -20%, rgba(59, 130, 246, 0.12), transparent 50%),
                radial-gradient(ellipse 100% 60% at 80% 100%, rgba(34, 211, 238, 0.08), transparent 50%),
                var(--bg-base);
            color: var(--text-primary);
            display: flex;
            flex-direction: column;
        }
        
        .container {
            width: 100%;
            max-width: 72rem;
            margin: 0 auto;
            padding: 0 1rem;
        }
        
        /* Header */
        .header {
            padding: 1rem 0;
            border-bottom: 1px solid rgb(var(--wn-line) / 0.1);
            background: rgb(var(--wn-card) / 0.75);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }
        
        .theme-flat .header {
            backdrop-filter: none;
            -webkit-backdrop-filter: none;
            background: rgb(var(--wn-card) / 0.98);
        }
        
        .theme-flat .error-btn {
            backdrop-filter: none;
            -webkit-backdrop-filter: none;
            background: rgba(255, 255, 255, 0.08);
        }
        
        .header-inner {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .logo {
            width: 2rem;
            height: 2rem;
            background: linear-gradient(135deg, rgb(var(--wn-accent)), rgb(var(--wn-accent-end)));
            border-radius: 0.5rem;
            display: grid;
            place-items: center;
            box-shadow: 0 4px 12px var(--accent-glow);
        }
        
        .logo svg { width: 1.25rem; height: 1.25rem; color: white; }
        
        .site-name {
            font-size: 1.125rem;
            font-weight: 700;
        }
        
        /* Main */
        main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        
        /* Footer */
        .footer {
            padding: 1.25rem 0;
            border-top: 1px solid rgb(var(--wn-line) / 0.1);
            text-align: center;
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        
        .footer a {
            color: var(--text-primary);
            text-decoration: none;
            transition: color 0.15s;
        }
        
        .footer a:hover { color: var(--accent); }
    </style>
    
    @stack('styles')
</head>
<body class="{{ ($siteTheme ?? 'fx') === 'flat' ? 'theme-flat' : '' }}">
    <header class="header">
        <div class="container">
            <a href="/" class="header-inner" style="text-decoration:none;color:inherit;">
                <div class="logo">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
                    </svg>
                </div>
                <span class="site-name">{{ config('app.name', 'WeatherNode') }}</span>
            </a>
        </div>
    </header>

    <main>
        <div class="container">
            @yield('content')
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <a href="/">{{ __('Back to weather dashboard') }}</a>
        </div>
    </footer>
</body>
</html>
