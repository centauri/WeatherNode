@php
    $legacyAdCode = trim((string) \App\Models\Setting::getValue('widgets.ad_code', ''));
    $legacyAdCompanyRaw = trim((string) \App\Models\Setting::getValue('widgets.ad_company', ''));
    $enabledWidgetsRaw = \App\Models\Setting::getValue('widgets.enabled', []);
    $pageAdEnabledRaw = \App\Models\Setting::getValue('widgets.page_ad_enabled', null);
    $pageAdCodeLegacyRaw = \App\Models\Setting::getValue('widgets.page_ad_code', null);
    $pageAdCodeDisplayRaw = \App\Models\Setting::getValue('widgets.page_ad_code_display', null);
    $pageAdCodeInFeedRaw = \App\Models\Setting::getValue('widgets.page_ad_code_in_feed', null);
    $pageAdCodeInArticleRaw = \App\Models\Setting::getValue('widgets.page_ad_code_in_article', null);
    $pageAdCompanyRaw = \App\Models\Setting::getValue('widgets.page_ad_company', null);
    $pageAdUnitTypeRaw = (string) \App\Models\Setting::getValue('widgets.page_ad_unit_type', 'display');

    if (is_string($enabledWidgetsRaw)) {
        $decodedWidgets = json_decode($enabledWidgetsRaw, true);
        $enabledWidgets = is_array($decodedWidgets) ? $decodedWidgets : [];
    } elseif (is_array($enabledWidgetsRaw)) {
        $enabledWidgets = $enabledWidgetsRaw;
    } else {
        $enabledWidgets = [];
    }

    $basePageAdCode = trim((string) (is_null($pageAdCodeLegacyRaw) ? $legacyAdCode : $pageAdCodeLegacyRaw));
    $legacyInlineEnabled = in_array('ads', $enabledWidgets, true) && $basePageAdCode !== '';
    $pageAdEnabled = is_null($pageAdEnabledRaw) ? $legacyInlineEnabled : (bool) $pageAdEnabledRaw;
    $pageAdUnitType = in_array($pageAdUnitTypeRaw, ['display', 'in_feed', 'in_article'], true) ? $pageAdUnitTypeRaw : 'display';
    $adCodeByType = [
        'display' => trim((string) (is_null($pageAdCodeDisplayRaw) ? $basePageAdCode : $pageAdCodeDisplayRaw)),
        'in_feed' => trim((string) (is_null($pageAdCodeInFeedRaw) ? '' : $pageAdCodeInFeedRaw)),
        'in_article' => trim((string) (is_null($pageAdCodeInArticleRaw) ? '' : $pageAdCodeInArticleRaw)),
    ];
    $adCode = $adCodeByType[$pageAdUnitType] ?? '';
    if ($adCode === '') {
        foreach (['display', 'in_feed', 'in_article'] as $fallbackType) {
            if (($adCodeByType[$fallbackType] ?? '') !== '') {
                $adCode = $adCodeByType[$fallbackType];
                break;
            }
        }
    }
    $adCompanyRaw = trim((string) (is_null($pageAdCompanyRaw) ? $legacyAdCompanyRaw : $pageAdCompanyRaw));
    $adCompany = $adCompanyRaw !== '' ? ucfirst(str_replace('_', ' ', $adCompanyRaw)) : '';
    $pageAdUnitTypeLabel = match ($pageAdUnitType) {
        'in_feed' => __('In-feed'),
        'in_article' => __('In-article'),
        default => __('Display'),
    };
    $adsEnabled = $pageAdEnabled && $adCode !== '';
@endphp

@if($adsEnabled)
<section id="inline-ad-section" class="max-w-7xl mx-auto px-4 pb-6 relative z-10">
    <div class="bg-weather-card rounded-2xl p-5 border border-ui-line/10">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold">📢 {{ __('Advertisement') }}</h3>
            @if($adCompany !== '')
                <span class="text-xs text-ui-muted">{{ $adCompany }} · {{ $pageAdUnitTypeLabel }}</span>
            @else
                <span class="text-xs text-ui-muted">{{ $pageAdUnitTypeLabel }}</span>
            @endif
        </div>
        <div id="inline-ad-slot" class="ad-container w-full min-h-[100px]"></div>
        <p id="inline-ad-hint" class="mt-3 text-xs text-ui-muted">{{ __('Advertisement loads when visible') }}</p>
        <p id="inline-ad-unfilled" class="mt-3 text-xs text-data-amber-300 hidden">{{ __('No ad available right now (ad network returned no fill).') }}</p>
        <p id="inline-ad-error" class="mt-3 text-xs text-ui-muted hidden">{{ __('Advertisement could not be loaded.') }}</p>
    </div>
</section>

<script>
    (function () {
        const slot = document.getElementById('inline-ad-slot');
        if (!slot) return;

        const adHtml = @json($adCode);
        if (!adHtml || !String(adHtml).trim()) return;

        const hint = document.getElementById('inline-ad-hint');
        const unfilled = document.getElementById('inline-ad-unfilled');
        const error = document.getElementById('inline-ad-error');

        const setState = (state) => {
            if (hint) hint.classList.toggle('hidden', state !== 'loading');
            if (unfilled) unfilled.classList.toggle('hidden', state !== 'unfilled');
            if (error) error.classList.toggle('hidden', state !== 'error');
        };

        const evaluateAdSenseState = () => {
            const adSenseSlots = Array.from(slot.querySelectorAll('ins.adsbygoogle'));
            if (adSenseSlots.length === 0) {
                return 'loaded';
            }

            const hasFilled = adSenseSlots.some((el) => el.getAttribute('data-ad-status') === 'filled');
            const hasUnfilled = adSenseSlots.some((el) => el.getAttribute('data-ad-status') === 'unfilled');
            const hasDone = adSenseSlots.some((el) => el.getAttribute('data-adsbygoogle-status') === 'done');

            if (hasFilled || hasDone) return 'loaded';
            if (hasUnfilled) return 'unfilled';
            return 'loading';
        };

        let mounted = false;
        const mountAd = () => {
            if (mounted) return;
            mounted = true;
            setState('loading');
            slot.innerHTML = adHtml;

            let hasAdSensePushScript = false;
            const scripts = Array.from(slot.querySelectorAll('script'));
            scripts.forEach((oldScript) => {
                const content = oldScript.textContent || '';
                if (content.includes('adsbygoogle') && content.includes('.push')) {
                    hasAdSensePushScript = true;
                }

                const replacement = document.createElement('script');
                Array.from(oldScript.attributes).forEach((attribute) => {
                    replacement.setAttribute(attribute.name, attribute.value);
                });
                replacement.textContent = content;
                oldScript.replaceWith(replacement);
            });

            const adSenseSlots = Array.from(slot.querySelectorAll('ins.adsbygoogle'));
            adSenseSlots.forEach((adSenseSlot) => {
                if (!adSenseSlot.style.display) adSenseSlot.style.display = 'block';
                if (!adSenseSlot.style.width) adSenseSlot.style.width = '100%';
            });

            if (adSenseSlots.length > 0 && !hasAdSensePushScript) {
                try {
                    adSenseSlots.forEach((adSenseSlot) => {
                        if (adSenseSlot.getAttribute('data-adsbygoogle-status')) return;
                        (window.adsbygoogle = window.adsbygoogle || []).push({});
                    });
                } catch (e) {
                    setState('error');
                    return;
                }
            }

            if (adSenseSlots.length === 0) {
                setState('loaded');
                return;
            }

            const statusDelaysMs = [2000, 5000, 9000];
            statusDelaysMs.forEach((delay, index) => {
                window.setTimeout(() => {
                    const state = evaluateAdSenseState();
                    setState(state);
                    if (index === statusDelaysMs.length - 1 && state === 'loading') {
                        setState('error');
                    }
                }, delay);
            });
        };

        if (!('IntersectionObserver' in window)) {
            mountAd();
            return;
        }

        const observer = new IntersectionObserver((entries) => {
            const visible = entries.some((entry) => entry.isIntersecting || entry.intersectionRatio > 0);
            if (!visible) return;
            observer.disconnect();
            mountAd();
        }, { rootMargin: '200px 0px', threshold: 0.01 });

        observer.observe(slot);
    })();
</script>
@endif
