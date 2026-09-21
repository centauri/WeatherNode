<!DOCTYPE html>
<html @if(isset($publicAppearance['custom'])) data-custom-theme @endif data-public-theme="{{ $publicAppearance['palette'] ?? 'weathernode' }}" data-default-color-mode="{{ $publicAppearance['mode'] ?? 'dark' }}" data-color-mode="{{ ($publicAppearance['mode'] ?? 'dark') === 'light' ? 'light' : 'dark' }}" lang="en">
<head>
    <x-public-theme-head />
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AdSense Test</title>
    <style>
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #0b1220;
            color: #e5e7eb;
        }
        .wrap {
            max-width: 980px;
            margin: 32px auto;
            padding: 0 16px 32px;
        }
        .card {
            background: #111a2b;
            border: 1px solid #25324b;
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 16px;
        }
        .title {
            font-size: 22px;
            font-weight: 700;
            margin: 0 0 10px;
        }
        .muted {
            color: #9ca3af;
            font-size: 14px;
            margin: 0 0 8px;
        }
        #ads-test-slot {
            min-height: 140px;
            width: 100%;
            background: #0b1322;
            border: 1px dashed #334155;
            border-radius: 10px;
            padding: 8px;
            box-sizing: border-box;
        }
        pre {
            margin: 0;
            white-space: pre-wrap;
            word-break: break-word;
            background: #0b1322;
            border: 1px solid #25324b;
            border-radius: 10px;
            padding: 12px;
            color: #cbd5e1;
            font-size: 12px;
            line-height: 1.45;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1 class="title">AdSense Isolated Test</h1>
            <p class="muted">Ad company: {{ $adCompany ?: 'not set' }}</p>
            <p class="muted">This page mounts the exact stored ad snippet and prints runtime status.</p>
        </div>

        <div class="card">
            <p class="muted">Ad slot</p>
            <div id="ads-test-slot"></div>
        </div>

        <div class="card">
            <p class="muted">Diagnostics (updates after mount)</p>
            <pre id="ads-test-debug">Initializing...</pre>
        </div>
    </div>

    <script>
        (function () {
            const adCodeHtml = @json($adCode);
            const slot = document.getElementById('ads-test-slot');
            const debugEl = document.getElementById('ads-test-debug');
            const query = new URLSearchParams(window.location.search);
            const host = window.location.hostname || '';
            const isLocalHost = host === 'localhost' || host === '127.0.0.1' || host === '::1';
            const adTestOverride = (query.get('adtest') || '').toLowerCase();
            const forceAdTest = adTestOverride === 'on'
                ? true
                : adTestOverride === 'off'
                    ? false
                    : isLocalHost;

            function collectStatus() {
                const adSenseSlots = Array.from(slot.querySelectorAll('ins.adsbygoogle'));
                return {
                    adCodeLength: adCodeHtml ? adCodeHtml.length : 0,
                    forceAdTest: forceAdTest,
                    host: host,
                    slotChildren: slot.childElementCount,
                    adSenseScriptPresent: Boolean(
                        document.querySelector('script[src*="pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"]')
                    ),
                    adsByGoogleGlobalType: typeof window.adsbygoogle,
                    adSenseSlots: adSenseSlots.map((el, index) => ({
                        index,
                        status: el.getAttribute('data-adsbygoogle-status') || null,
                        adStatus: el.getAttribute('data-ad-status') || null,
                        adTest: el.getAttribute('data-adtest') || null,
                        width: el.clientWidth,
                        height: el.clientHeight,
                    })),
                };
            }

            function renderStatus() {
                const status = collectStatus();
                debugEl.textContent = JSON.stringify(status, null, 2);
                console.log('[ads-test] status', status);
            }

            if (!adCodeHtml || !adCodeHtml.trim()) {
                debugEl.textContent = JSON.stringify({ error: 'No ad code configured in widgets.ad_code' }, null, 2);
                return;
            }

            slot.innerHTML = adCodeHtml;

            const scripts = Array.from(slot.querySelectorAll('script'));
            let hasAdSenseBootstrapScript = false;
            let hasAdSensePushScript = false;

            scripts.forEach((oldScript) => {
                const src = (oldScript.getAttribute('src') || '').toLowerCase();
                if (src.includes('pagead2.googlesyndication.com/pagead/js/adsbygoogle.js')) {
                    hasAdSenseBootstrapScript = true;
                }

                const scriptContent = oldScript.textContent || '';
                if (scriptContent.includes('adsbygoogle') && scriptContent.includes('.push')) {
                    hasAdSensePushScript = true;
                }

                const replacement = document.createElement('script');
                Array.from(oldScript.attributes).forEach((attribute) => {
                    replacement.setAttribute(attribute.name, attribute.value);
                });
                replacement.textContent = oldScript.textContent || '';
                oldScript.replaceWith(replacement);
            });

            const adSenseSlots = Array.from(slot.querySelectorAll('ins.adsbygoogle'));
            adSenseSlots.forEach((adSenseSlot) => {
                if (!adSenseSlot.style.display) {
                    adSenseSlot.style.display = 'block';
                }
                if (!adSenseSlot.style.width) {
                    adSenseSlot.style.width = '100%';
                }
                if (forceAdTest) {
                    adSenseSlot.setAttribute('data-adtest', 'on');
                }
            });

            if (hasAdSenseBootstrapScript && !hasAdSensePushScript && adSenseSlots.length > 0) {
                try {
                    adSenseSlots.forEach((adSenseSlot) => {
                        if (adSenseSlot.getAttribute('data-adsbygoogle-status')) {
                            return;
                        }
                        (window.adsbygoogle = window.adsbygoogle || []).push({});
                    });
                } catch (error) {
                    console.warn('[ads-test] fallback AdSense push failed', error);
                }
            }

            renderStatus();
            window.setTimeout(renderStatus, 2000);
            window.setTimeout(renderStatus, 5000);
            window.setTimeout(renderStatus, 9000);
        })();
    </script>
</body>
</html>
