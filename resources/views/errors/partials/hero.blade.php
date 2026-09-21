@php
    /** @var string|int $code */
    /** @var string $headline */
    /** @var string $message */
    /** @var string $scene */

    $code = (string) ($code ?? 'Error');
    $headline = (string) ($headline ?? __('Something went sideways'));
    $message = (string) ($message ?? __('We’re on it. In the meantime, you can head back to safe skies.'));
    $scene = (string) ($scene ?? 'wind');
    $canGoBack = url()->previous() && url()->previous() !== url()->current();
@endphp

@push('styles')
    <style>
        .error-sky {
            position: relative;
            overflow: hidden;
            border-radius: 1.25rem;
            background:
                radial-gradient(1200px 600px at 20% 10%, rgba(56, 189, 248, 0.20), transparent 55%),
                radial-gradient(900px 500px at 85% 30%, rgba(99, 102, 241, 0.20), transparent 60%),
                linear-gradient(180deg, rgb(var(--wn-card) / 0.85), rgb(var(--wn-bg) / 0.92));
            border: 1px solid rgb(var(--wn-overlay) / 0.10);
        }

        .error-grain::before {
            content: "";
            position: absolute;
            inset: 0;
            pointer-events: none;
            background-image:
                radial-gradient(rgb(var(--wn-overlay) / 0.08) 1px, transparent 1px),
                radial-gradient(rgb(var(--wn-overlay) / 0.04) 1px, transparent 1px);
            background-size: 28px 28px, 18px 18px;
            background-position: 0 0, 11px 7px;
            opacity: 0.35;
            mix-blend-mode: overlay;
        }

        .error-code {
            font-variant-numeric: tabular-nums;
            letter-spacing: -0.06em;
            text-shadow: 0 10px 30px rgba(0,0,0,0.35);
        }

        .error-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            padding: .75rem 1rem;
            border-radius: .9rem;
            border: 1px solid rgb(var(--wn-overlay) / 0.12);
            background: rgb(var(--wn-overlay) / 0.06);
            transition: transform .15s ease, background .15s ease, border-color .15s ease;
            backdrop-filter: blur(8px);
        }

        .error-btn:hover {
            transform: translateY(-1px);
            background: rgb(var(--wn-overlay) / 0.10);
            border-color: rgb(var(--wn-overlay) / 0.18);
        }

        .error-btn-primary {
            color: white;
            background: rgb(var(--wn-accent-strong));
            border-color: rgba(59, 130, 246, 0.35);
            box-shadow: 0 14px 40px rgba(59, 130, 246, 0.18);
        }

        .error-btn-primary:hover {
            background: rgb(var(--wn-accent-strong));
            border-color: rgba(34, 211, 238, 0.42);
        }

        /* Scene shared */
        .scene {
            position: relative;
            width: min(520px, 100%);
            height: 260px;
            margin-left: auto;
            margin-right: auto;
        }

        .scene * { box-sizing: border-box; }

        /* Wind: page blown away */
        @keyframes gust {
            0%   { transform: translateX(-10px); opacity: 0.15; }
            40%  { transform: translateX(0); opacity: 0.55; }
            100% { transform: translateX(26px); opacity: 0.15; }
        }
        @keyframes fly {
            0%   { transform: translate(0, 22px) rotate(-6deg); }
            35%  { transform: translate(58px, -6px) rotate(10deg); }
            65%  { transform: translate(122px, 18px) rotate(-12deg); }
            100% { transform: translate(220px, -10px) rotate(18deg); }
        }
        .wind-line {
            position: absolute;
            height: 2px;
            border-radius: 999px;
            background: linear-gradient(90deg, transparent, rgb(var(--wn-overlay) / 0.38), transparent);
            filter: blur(.1px);
            animation: gust 1.35s ease-in-out infinite;
        }
        .paper {
            position: absolute;
            left: 28px;
            top: 72px;
            width: 128px;
            height: 164px;
            border-radius: 16px;
            background: linear-gradient(180deg, rgb(var(--wn-overlay) / 0.92), rgb(var(--wn-overlay) / 0.78));
            box-shadow: 0 22px 60px rgba(0,0,0,0.35);
            border: 1px solid rgb(var(--wn-overlay) / 0.30);
            transform-origin: 30% 60%;
            animation: fly 2.6s ease-in-out infinite alternate;
        }
        .paper::before {
            content: "";
            position: absolute;
            inset: 14px 14px auto 14px;
            height: 8px;
            border-radius: 999px;
            background: rgba(2, 6, 23, 0.10);
            box-shadow:
                0 18px 0 rgba(2, 6, 23, 0.10),
                0 36px 0 rgba(2, 6, 23, 0.10),
                0 54px 0 rgba(2, 6, 23, 0.10);
        }
        .paper::after {
            content: "";
            position: absolute;
            right: -6px;
            top: 18px;
            width: 26px;
            height: 26px;
            background: linear-gradient(135deg, rgb(var(--wn-overlay) / 0.92), rgb(var(--wn-overlay) / 0.65));
            border-left: 1px solid rgba(2,6,23,0.08);
            border-bottom: 1px solid rgba(2,6,23,0.08);
            transform: rotate(45deg);
            border-radius: 6px;
        }

        /* Flood: waves + floating sign */
        @keyframes wave {
            0% { transform: translateX(-6%) }
            100% { transform: translateX(6%) }
        }
        @keyframes bob {
            0% { transform: translateY(0) rotate(-2deg) }
            50% { transform: translateY(-8px) rotate(2deg) }
            100% { transform: translateY(0) rotate(-2deg) }
        }
        .water {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 56%;
            border-radius: 22px;
            background:
                radial-gradient(120px 80px at 15% 25%, rgb(var(--wn-overlay) / 0.18), transparent 60%),
                radial-gradient(140px 90px at 80% 30%, rgb(var(--wn-overlay) / 0.12), transparent 60%),
                linear-gradient(180deg, rgba(34, 211, 238, 0.38), rgba(59, 130, 246, 0.22));
            border: 1px solid rgb(var(--wn-overlay) / 0.12);
            overflow: hidden;
        }
        .water::before,
        .water::after {
            content: "";
            position: absolute;
            left: -20%;
            right: -20%;
            top: -22px;
            height: 60px;
            background:
                radial-gradient(40px 14px at 10% 70%, rgb(var(--wn-overlay) / 0.22), transparent 60%),
                radial-gradient(50px 16px at 30% 60%, rgb(var(--wn-overlay) / 0.16), transparent 60%),
                radial-gradient(45px 16px at 55% 75%, rgb(var(--wn-overlay) / 0.18), transparent 60%),
                radial-gradient(55px 18px at 75% 65%, rgb(var(--wn-overlay) / 0.16), transparent 60%),
                radial-gradient(40px 14px at 92% 72%, rgb(var(--wn-overlay) / 0.18), transparent 60%);
            filter: blur(.2px);
            animation: wave 2.8s ease-in-out infinite alternate;
            opacity: .85;
        }
        .water::after {
            top: -30px;
            opacity: .55;
            animation-duration: 3.6s;
        }
        .float-sign {
            position: absolute;
            left: 18%;
            top: 62px;
            width: 170px;
            height: 110px;
            border-radius: 18px;
            background: rgba(2, 6, 23, 0.45);
            border: 1px solid rgb(var(--wn-overlay) / 0.14);
            box-shadow: 0 22px 60px rgba(0,0,0,0.35);
            display: grid;
            place-items: center;
            animation: bob 2.9s ease-in-out infinite;
        }
        .float-sign span {
            font-weight: 800;
            letter-spacing: -0.04em;
            color: rgb(var(--wn-overlay) / 0.92);
            font-size: 42px;
        }

        /* Lightning: storm + server */
        @keyframes flash {
            0%, 72%, 100% { opacity: .0; }
            74% { opacity: .9; }
            78% { opacity: .15; }
            82% { opacity: .8; }
            86% { opacity: .0; }
        }
        .server {
            position: absolute;
            right: 10%;
            bottom: 16%;
            width: 150px;
            height: 170px;
            border-radius: 18px;
            background: linear-gradient(180deg, rgb(var(--wn-overlay) / 0.10), rgb(var(--wn-overlay) / 0.05));
            border: 1px solid rgb(var(--wn-overlay) / 0.12);
            box-shadow: 0 18px 60px rgba(0,0,0,0.35);
        }
        .server::before {
            content: "";
            position: absolute;
            inset: 16px 14px;
            border-radius: 14px;
            background:
                radial-gradient(6px 6px at 12% 12%, rgba(34, 211, 238, 0.9), transparent 65%),
                radial-gradient(6px 6px at 12% 36%, rgba(34, 211, 238, 0.55), transparent 65%),
                radial-gradient(6px 6px at 12% 60%, rgba(34, 211, 238, 0.25), transparent 65%),
                linear-gradient(180deg, rgba(2,6,23,0.55), rgba(2,6,23,0.35));
            border: 1px solid rgb(var(--wn-overlay) / 0.08);
        }
        .bolt {
            position: absolute;
            left: 18%;
            top: 14%;
            width: 0;
            height: 0;
            border-left: 24px solid transparent;
            border-right: 10px solid transparent;
            border-top: 74px solid rgba(250, 204, 21, 0.95);
            transform: rotate(18deg);
            filter: drop-shadow(0 12px 30px rgba(250, 204, 21, 0.22));
            animation: flash 3.4s ease-in-out infinite;
        }
        .bolt::after {
            content: "";
            position: absolute;
            left: -18px;
            top: -8px;
            width: 0;
            height: 0;
            border-left: 10px solid transparent;
            border-right: 24px solid transparent;
            border-top: 72px solid rgba(250, 204, 21, 0.95);
            transform: translateY(34px) translateX(10px) rotate(-14deg);
        }

        @media (prefers-reduced-motion: reduce) {
            .wind-line, .paper, .water::before, .water::after, .float-sign, .bolt {
                animation: none !important;
            }
        }
    </style>
@endpush

<section class="error-sky error-grain">
    <div class="relative p-6 sm:p-10">
        <div class="grid lg:grid-cols-2 gap-8 items-center">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full border border-ui-line/10 bg-ui-overlay/5 text-xs text-ui-body">
                    <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 shadow shadow-emerald-400/30"></span>
                    <span>{{ __('Weather report') }}</span>
                    <span class="text-ui-muted">—</span>
                    <span class="text-ui-secondary">{{ __('error') }} {{ $code }}</span>
                </div>

                <div class="mt-4">
                    <div class="error-code text-6xl sm:text-7xl font-extrabold text-ui-fg/95">{{ $code }}</div>
                    <h2 class="mt-3 text-2xl sm:text-3xl font-bold text-ui-fg">{{ $headline }}</h2>
                    <p class="mt-3 text-ui-body/90 leading-relaxed max-w-prose">{{ $message }}</p>
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="/" class="error-btn error-btn-primary">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0h6"/>
                        </svg>
                        <span class="font-semibold">{{ __('Back to dashboard') }}</span>
                    </a>

                    @if($canGoBack)
                        <button type="button" class="error-btn" onclick="history.back()">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                            </svg>
                            <span class="font-semibold">{{ __('Go back') }}</span>
                        </button>
                    @endif

                    <a href="/radar" class="error-btn">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21a9 9 0 009-9 9 9 0 10-18 0 9 9 0 009 9z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 12l3 3"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 12V7"/>
                        </svg>
                        <span class="font-semibold">{{ __('Open radar') }}</span>
                    </a>
                </div>

                <p class="mt-5 text-xs text-ui-secondary/80">
                    {{ __('Tip: If this keeps happening, the atmosphere might be fine — but our server pressure isn’t.') }}
                </p>
            </div>

            <div class="relative">
                <div class="scene">
                    @if($scene === 'flood')
                        <div class="float-sign"><span>{{ $code }}</span></div>
                        <div class="water"></div>
                    @elseif($scene === 'lightning')
                        <div class="bolt" aria-hidden="true"></div>
                        <div class="server" aria-hidden="true"></div>
                        <div class="wind-line" style="left: 10%; top: 42px; width: 64%; animation-delay: .15s;"></div>
                        <div class="wind-line" style="left: 6%; top: 92px; width: 58%; animation-delay: .35s;"></div>
                        <div class="wind-line" style="left: 12%; top: 146px; width: 62%; animation-delay: .05s;"></div>
                    @else
                        <div class="wind-line" style="left: 6%; top: 66px; width: 68%; animation-delay: .10s;"></div>
                        <div class="wind-line" style="left: 8%; top: 116px; width: 58%; animation-delay: .28s;"></div>
                        <div class="wind-line" style="left: 4%; top: 160px; width: 72%; animation-delay: .04s;"></div>
                        <div class="paper" aria-hidden="true"></div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
