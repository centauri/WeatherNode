{{-- The public shell shared by the pages shown before anyone is logged in:
     the login page and the first-run admin setup. Kept in one place so the
     first page a new owner ever sees looks like the app they installed. --}}
    @vite(['resources/css/app.css'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        .weather-bg {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            z-index: -1; pointer-events: none;
        }
        .weather-bg--animated {
            background: linear-gradient(-45deg, rgb(var(--wn-bg, 15 20 25)), rgb(var(--wn-gradient-mid, 26 39 68)), rgb(var(--wn-bg, 15 20 25)), rgb(var(--wn-gradient-end, 30 27 75)), rgb(var(--wn-bg, 15 20 25)));
            background-size: 400% 400%;
            animation: gradientShift 20s ease infinite;
        }
        .weather-bg--static {
            background: linear-gradient(-45deg, rgb(var(--wn-bg, 15 20 25)), rgb(var(--wn-gradient-mid, 26 39 68)), rgb(var(--wn-bg, 15 20 25)));
            background-size: 100% 100%;
        }
        .theme-flat .weather-bg--static {
            background: rgb(var(--wn-card, 26 35 50));
            background-image: linear-gradient(180deg, rgb(var(--wn-bg, 15 20 25)) 0%, rgb(var(--wn-card, 26 35 50)) 50%, rgb(var(--wn-bg, 21 29 40)) 100%);
        }
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            25% { background-position: 100% 50%; }
            50% { background-position: 100% 100%; }
            75% { background-position: 0% 100%; }
            100% { background-position: 0% 50%; }
        }
        
        .glass { 
            background: rgb(var(--wn-card, 26 35 50) / 0.75);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }
        
        .theme-flat .glass {
            backdrop-filter: none;
            -webkit-backdrop-filter: none;
            background: rgb(var(--wn-card, 26 35 50) / 0.98);
        }
        
        .glow { 
            box-shadow: 0 0 30px rgba(59, 130, 246, 0.2); 
        }
        
        .bg-weather-card {
            background: rgb(var(--wn-card, 26 35 50));
        }
        
        .input-dark {
            background: rgba(15, 20, 25, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.2s ease;
        }
        
        .input-dark:focus {
            border-color: rgba(59, 130, 246, 0.5);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            outline: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            transition: all 0.2s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 20px rgba(59, 130, 246, 0.4);
        }
    </style>
