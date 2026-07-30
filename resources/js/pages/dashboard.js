const cfg = window.__METEO_DASHBOARD_CONFIG__ || {};
const t = (key) => cfg.i18n?.[key] ?? key;
const locale = window.Meteo?.jsLocale || 'nl-NL';
const hybridSsrEnabled = window.__METEO_DASHBOARD_HYBRID__ === true;
const initialPayload = (window.__METEO_DASHBOARD_INITIAL__ && typeof window.__METEO_DASHBOARD_INITIAL__ === 'object')
    ? window.__METEO_DASHBOARD_INITIAL__
    : null;

function weatherDashboard() {
    return {
                canUseDebugOverrides: Boolean(cfg.canUseDebugOverrides),
	                debugPressure: null,
	                isFlatTheme: document.body.classList.contains('theme-flat'),
	                current: null,
	                forecast: [],
	                hourlyForecast: [],
                forecastView: 'daily5',
                sun: null,
                moon: null,
                aurora: null,
                astronomicalEvents: [],
                airQuality: null,
                luftdaten: null,
                luftdatenNoise: null,
                pollenData: null,
                tideData: null,
                waterWaves: null,
                metar: null,
                earthquakes: [],
                alerts: [],
                weatherToasts: [],
                _seenAlertTypes: null,
                pressureHistory: [],
                windHistory: [],
                windCardFlipped: false,
                pressureChartHoveredIndex: null,
                pressureChartTooltipX: 0,
                pressureChartTooltipY: 0,
                extraSensors: null,
                extraSensorLabels: { temps: {}, soil: {}, pm25: {}, leak: {}, battery: {} },
                lightning: null,
                batteryStatus: {},
                enabledWidgets: cfg.enabledWidgets || [],
                widgetOrder: [],
                gridCols: 3,
                editMode: false,
                sortableInstances: [],
                today: null,
                station: {
                    name: cfg.stationName,
                    location: cfg.stationLocation,
                },
                todayHigh: null,
                todayLow: null,
	                currentTime: '--:--:--',
	                currentDate: '--',
	                currentTimeZoneLabel: '',
                units: window.Meteo?.activeUnits || 'metric',
                nowTs: Date.now(),
                tempChartShowNowLine: Boolean(cfg.tempChartShowNowLine),
                tempChartShowObserved: Boolean(cfg.tempChartShowObserved),
                observedTempHistory: [],
                editLabel: t('Edit') || 'Edit',
                doneLabel: t('Done') || 'Done',
                updatedLabel: t('Updated') || 'Updated',
                refreshLabel: t('Refresh') || 'Refresh',
                refreshingLabel: t('Refreshing...') || 'Refreshing...',
                notUpdatedYetLabel: t('Not updated yet') || 'Not updated yet',
                metarTokens: {
                    intensity: {
                        light: t('Light'),
                        heavy: t('Heavy'),
                    },
                    descriptor: {
                        TS: t('Thunderstorm'),
                        SH: t('Showers'),
                        FZ: t('Freezing'),
                        BL: t('Blowing'),
                        DR: t('Low drifting'),
                        MI: t('Shallow'),
                        PR: t('Partial'),
                        BC: t('Patches'),
                    },
                    phenomena: {
                        RA: t('Rain'),
                        SN: t('Snow'),
                        DZ: t('Drizzle'),
                        SG: t('Snow grains'),
                        IC: t('Ice crystals'),
                        PL: t('Ice pellets'),
                        GR: t('Hail'),
                        GS: t('Small hail'),
                        UP: t('Unknown precipitation'),
                        BR: t('Mist'),
                        FG: t('Fog'),
                        HZ: t('Haze'),
                        FU: t('Smoke'),
                        DU: t('Dust'),
                        SA: t('Sand'),
                        VA: t('Volcanic ash'),
                        PO: t('Dust/sand whirls'),
                        SQ: t('Squalls'),
                        FC: t('Funnel cloud'),
                        SS: t('Sandstorm'),
                        DS: t('Duststorm'),
                        PY: t('Spray'),
                    },
                    with: t('with'),
                    and: t('and'),
                    vicinity: t('In the vicinity'),
                },
                moonPhaseLabels: {
                    'New Moon': t('New Moon'),
                    'Waxing Crescent': t('Waxing Crescent'),
                    'First Quarter': t('First Quarter'),
                    'Waxing Gibbous': t('Waxing Gibbous'),
                    'Full Moon': t('Full Moon'),
                    'Waning Gibbous': t('Waning Gibbous'),
                    'Last Quarter': t('Last Quarter'),
                    'Waning Crescent': t('Waning Crescent'),
                },
                eventLabels: {
                    // Moon phases
                    'New Moon': t('New Moon'),
                    'First Quarter': t('First Quarter'),
                    'Full Moon': t('Full Moon'),
                    'Last Quarter': t('Last Quarter'),
                    'Supermoon': t('Supermoon'),
                    'Blue Moon': t('Blue Moon'),
                    // Seasons
                    'Spring Equinox': t('Spring Equinox'),
                    'Summer Solstice': t('Summer Solstice'),
                    'Autumn Equinox': t('Autumn Equinox'),
                    'Winter Solstice': t('Winter Solstice'),
                    // Eclipses
                    'Total Solar Eclipse': t('Total Solar Eclipse'),
                    'Partial Solar Eclipse': t('Partial Solar Eclipse'),
                    'Annular Solar Eclipse': t('Annular Solar Eclipse'),
                    'Hybrid Solar Eclipse': t('Hybrid Solar Eclipse'),
                    'Total Lunar Eclipse': t('Total Lunar Eclipse'),
                    'Partial Lunar Eclipse': t('Partial Lunar Eclipse'),
                    'Penumbral Lunar Eclipse': t('Penumbral Lunar Eclipse'),
                    // Meteor showers
                    'Quadrantids peak': t('Quadrantids peak'),
                    'Lyrids peak': t('Lyrids peak'),
                    'Eta Aquariids peak': t('Eta Aquariids peak'),
                    'Delta Aquariids peak': t('Delta Aquariids peak'),
                    'Perseids peak': t('Perseids peak'),
                    'Draconids peak': t('Draconids peak'),
                    'Orionids peak': t('Orionids peak'),
                    'Taurids peak': t('Taurids peak'),
                    'Leonids peak': t('Leonids peak'),
                    'Geminids peak': t('Geminids peak'),
                    'Ursids peak': t('Ursids peak'),
                    // Planetary events
                    'Mercury at greatest elongation': t('Mercury at greatest elongation'),
                    'Venus at greatest elongation': t('Venus at greatest elongation'),
                    'Venus at greatest brilliancy': t('Venus at greatest brilliancy'),
                    'Mars at opposition': t('Mars at opposition'),
                    'Jupiter at opposition': t('Jupiter at opposition'),
                    'Saturn at opposition': t('Saturn at opposition'),
                    'Uranus at opposition': t('Uranus at opposition'),
                    'Neptune at opposition': t('Neptune at opposition'),
                    'Mars-Saturn conjunction': t('Mars-Saturn conjunction'),
                    'Jupiter-Mercury conjunction': t('Jupiter-Mercury conjunction'),
                    'Venus-Saturn conjunction': t('Venus-Saturn conjunction'),
                    'Venus-Jupiter conjunction': t('Venus-Jupiter conjunction'),
                    'Venus-Mars conjunction': t('Venus-Mars conjunction'),
                    'Venus-Neptune conjunction': t('Venus-Neptune conjunction'),
                    'Saturn-Neptune conjunction': t('Saturn-Neptune conjunction'),
                    'Transit of Mercury': t('Transit of Mercury'),
                    'Saturn-Neptune conjunction hint': t('Saturn-Neptune conjunction hint'),
                    'Jupiter-Saturn great conjunction': t('Jupiter-Saturn great conjunction'),
                    // Earth orbital
                    'Earth at Perihelion': t('Earth at Perihelion'),
                    'Earth at Aphelion': t('Earth at Aphelion'),
                    // Zodiacal light
                    'Zodiacal Light (evening)': t('Zodiacal Light (evening)'),
                    'Zodiacal Light (morning)': t('Zodiacal Light (morning)'),
                    // Planetary parades / alignments
                    'Seven-planet parade': t('Seven-planet parade'),
                    'Six-planet alignment (morning)': t('Six-planet alignment (morning)'),
                    'Six-planet alignment (evening)': t('Six-planet alignment (evening)'),
                    'Seven-planet parade hint': t('Seven-planet parade hint'),
                    'Six-planet alignment morning hint': t('Six-planet alignment morning hint'),
                    'Six-planet alignment evening hint': t('Six-planet alignment evening hint'),
                    // Event explanation hints (all event types)
                    'Annular Solar Eclipse hint': t('Annular Solar Eclipse hint'),
                    'Autumn Equinox hint': t('Autumn Equinox hint'),
                    'Blue Moon hint': t('Blue Moon hint'),
                    'Comet hint': t('Comet hint'),
                    'Earth at Aphelion hint': t('Earth at Aphelion hint'),
                    'Earth at Perihelion hint': t('Earth at Perihelion hint'),
                    'First Quarter hint': t('First Quarter hint'),
                    'Full Moon hint': t('Full Moon hint'),
                    'Hybrid Solar Eclipse hint': t('Hybrid Solar Eclipse hint'),
                    'Jupiter at opposition hint': t('Jupiter at opposition hint'),
                    'Jupiter-Saturn great conjunction hint': t('Jupiter-Saturn great conjunction hint'),
                    'Last Quarter hint': t('Last Quarter hint'),
                    'Mars at opposition hint': t('Mars at opposition hint'),
                    'Mercury at greatest elongation hint': t('Mercury at greatest elongation hint'),
                    'Meteor shower peak hint': t('Meteor shower peak hint'),
                    'Neptune at opposition hint': t('Neptune at opposition hint'),
                    'New Moon hint': t('New Moon hint'),
                    'Partial Lunar Eclipse hint': t('Partial Lunar Eclipse hint'),
                    'Partial Solar Eclipse hint': t('Partial Solar Eclipse hint'),
                    'Penumbral Lunar Eclipse hint': t('Penumbral Lunar Eclipse hint'),
                    'Planetary conjunction hint': t('Planetary conjunction hint'),
                    'Saturn at opposition hint': t('Saturn at opposition hint'),
                    'Spring Equinox hint': t('Spring Equinox hint'),
                    'Summer Solstice hint': t('Summer Solstice hint'),
                    'Supermoon hint': t('Supermoon hint'),
                    'Total Lunar Eclipse hint': t('Total Lunar Eclipse hint'),
                    'Total Solar Eclipse hint': t('Total Solar Eclipse hint'),
                    'Transit of Mercury hint': t('Transit of Mercury hint'),
                    'Uranus at opposition hint': t('Uranus at opposition hint'),
                    'Venus at greatest brilliancy hint': t('Venus at greatest brilliancy hint'),
                    'Venus at greatest elongation hint': t('Venus at greatest elongation hint'),
                    'Winter Solstice hint': t('Winter Solstice hint'),
                    'Zodiacal Light (evening) hint': t('Zodiacal Light (evening) hint'),
                    'Zodiacal Light (morning) hint': t('Zodiacal Light (morning) hint'),
                },
                eventTypeLabels: {
                    'moon': t('Moon'),
                    'seasonal': t('Season'),
                    'eclipse': t('Eclipse'),
                    'meteor': t('Meteor'),
                    'planet': t('Planet'),
                    'earth': t('Earth'),
                    'comet': t('Comet'),
                    'special': t('Special'),
                    'transit': t('Transit'),
                },
                translations: {
                    minutesAgo: t('minutes ago'),
                    loading: t('Loading...'),
                    good: t('Good'),
                    noActivity: t('No activity'),
                    strikes: t('strikes'),
                    unknown: t('Unknown'),
                    unlikely: t('Unlikely'),
                    moderate: t('Moderate'),
                    inRegion: t('in region'),
                    chance: t('Chance'),
                    update: t('Update'),
                    active: t('active'),
                    weather: t('Weather'),
                    warningTypes: {
                        'wind': t('Wind'),
                        'snow-ice': t('Snow ice'),
                        'thunderstorm': t('Thunderstorm'),
                        'fog': t('Fog'),
                        'high-temperature': t('High temperature'),
                        'low-temperature': t('Low temperature'),
                        'coastal-event': t('Coastal event'),
                        'forest-fire': t('Forest fire'),
                        'avalanches': t('Avalanches'),
                        'rain': t('Rain'),
                        'flooding': t('Flooding'),
                        'rain-flood': t('Rain-flood'),
                    },
                    flightCategory: t('Flight category'),
                    advertisement: t('Advertisement'),
                    adCompany: cfg.adCompany,
                    pollenRisk: {
                        'None':      t('None'),
                        'Low':       t('Low'),
                        'Moderate':  t('Moderate'),
                        'High':      t('High'),
                        'Very High': t('Very High'),
                    },
                    pollenTypes: {
                        grass: t('Grass'),
                        tree:  t('Tree'),
                        weed:  t('Weed'),
                    },
                    aqiLevels: {
                        // US EPA levels
                        'Good': t('Good'),
                        'Moderate': t('Moderate'),
                        'Unhealthy for Sensitive Groups': t('Unhealthy for Sensitive Groups'),
                        'Unhealthy for Sensitive': t('Unhealthy for Sensitive Groups'),
                        'Unhealthy': t('Unhealthy'),
                        'Very Unhealthy': t('Very Unhealthy'),
                        'Hazardous': t('Hazardous'),
                        // EEA (European) levels
                        'Fair': t('Fair'),
                        'Poor': t('Poor'),
                        'Very Poor': t('Very Poor'),
                        'Extremely Poor': t('Extremely Poor'),
                        // UK DAQI levels
                        'Low': t('Low'),
                        'High': t('High'),
                        'Very High': t('Very High'),
                    },
                },
                defaultMetarIcao: cfg.defaultMetarIcao,
                hasAdCode: Boolean(cfg.hasAdCode),
                adsConsentMode: cfg.adsConsentMode,
                adsConsentRequired: Boolean(cfg.adsConsentRequired),
                adsConsentCountryCode: cfg.adsConsentCountryCode,
                adsConsentStorageKey: 'meteouitgeest_ads_consent_v1',
                adsConsentMaxAgeDays: 180,
                adsConsentStatus: null,
                adCodeHtml: cfg.adCodeHtml || '',
                adMounted: false,
                adFillState: 'idle',
                adSlotCollapsed: false,
                adForceTestMode: false,
                adRuntimeStatuses: [],
                showCookieBanner: false,
                showCookieSettingsModal: false,
                cookieSettingsAdsAllowed: false,
                cookieBannerCopy: {
                    title: t('Cookies for ads'),
                    description: t('We use cookies to show ads and measure ad performance. You can accept or reject.'),
                    accept: t('Accept'),
                    reject: t('Reject'),
                    settings: t('Settings'),
                },
                get canRenderAds() {
                    if (!this.hasAdCode || !this.isWidgetEnabled('ads')) {
                        return false;
                    }

                    if (!this.adsConsentRequired) {
                        return true;
                    }

                    return this.adsConsentStatus === 'accepted';
                },
                get showAdsConsentPlaceholder() {
                    return this.hasAdCode
                        && this.isWidgetEnabled('ads')
                        && this.adsConsentRequired
                        && this.adsConsentStatus !== 'accepted';
                },
                readStoredAdsConsent() {
                    try {
                        const raw = localStorage.getItem(this.adsConsentStorageKey);
                        if (!raw) return null;

                        const parsed = JSON.parse(raw);
                        if (!parsed || typeof parsed !== 'object') return null;

                        const status = parsed.status;
                        const savedAt = Number(parsed.saved_at);
                        const maxAgeMs = this.adsConsentMaxAgeDays * 24 * 60 * 60 * 1000;
                        if (!['accepted', 'rejected'].includes(status)) {
                            localStorage.removeItem(this.adsConsentStorageKey);
                            return null;
                        }
                        if (!Number.isFinite(savedAt) || savedAt <= 0 || (Date.now() - savedAt) > maxAgeMs) {
                            localStorage.removeItem(this.adsConsentStorageKey);
                            return null;
                        }

                        return status;
                    } catch (error) {
                        return null;
                    }
                },
                persistAdsConsent(status) {
                    try {
                        if (!status) {
                            localStorage.removeItem(this.adsConsentStorageKey);
                            return;
                        }

                        localStorage.setItem(this.adsConsentStorageKey, JSON.stringify({
                            status,
                            saved_at: Date.now(),
                            country: this.adsConsentCountryCode || null,
                            version: 1,
                        }));
                    } catch (error) {
                        // Ignore storage errors (private mode / blocked storage).
                    }
                },
                refreshAdsConsentState() {
                    if (!this.adsConsentRequired) {
                        this.showCookieBanner = false;
                        this.showCookieSettingsModal = false;
                        return;
                    }

                    this.showCookieBanner = this.adsConsentStatus !== 'accepted' && this.adsConsentStatus !== 'rejected';
                },
                setAdsConsent(status) {
                    this.adsConsentStatus = status;
                    this.cookieSettingsAdsAllowed = status === 'accepted';
                    this.persistAdsConsent(status);
                    this.refreshAdsConsentState();
                    this.scheduleLazyWidgetInitialization();
                },
                acceptAdsConsent() {
                    this.setAdsConsent('accepted');
                    this.showCookieSettingsModal = false;
                },
                rejectAdsConsent() {
                    this.setAdsConsent('rejected');
                    this.showCookieSettingsModal = false;
                },
                openCookieSettings() {
                    if (!this.adsConsentRequired) {
                        return;
                    }

                    this.cookieSettingsAdsAllowed = this.adsConsentStatus === 'accepted';
                    this.showCookieSettingsModal = true;
                    this.showCookieBanner = false;
                },
                closeCookieSettings() {
                    this.showCookieSettingsModal = false;
                    this.refreshAdsConsentState();
                },
                saveCookieSettings() {
                    if (this.cookieSettingsAdsAllowed) {
                        this.acceptAdsConsent();
                    } else {
                        this.rejectAdsConsent();
                    }
                },
                consumeCookieSettingsQueryParam() {
                    try {
                        const url = new URL(window.location.href);
                        if (url.searchParams.get('open_cookie_settings') !== '1') {
                            return;
                        }

                        if (this.adsConsentRequired) {
                            this.openCookieSettings();
                        }

                        url.searchParams.delete('open_cookie_settings');
                        const nextUrl = `${url.pathname}${url.search}${url.hash}`;
                        window.history.replaceState({}, document.title, nextUrl);
                    } catch (error) {
                        // Ignore URL parsing errors.
                    }
                },
                registerAdsConsentListener() {
                    if (this._adsConsentListener) {
                        return;
                    }

                    this._adsConsentListener = () => {
                        if (!this.adsConsentRequired) {
                            return;
                        }
                        this.openCookieSettings();
                    };

                    window.addEventListener('meteo:open-cookie-settings', this._adsConsentListener);
                },
                initAdsConsent() {
                    if (!this.adsConsentRequired) {
                        this.adsConsentStatus = 'accepted';
                        this.showCookieBanner = false;
                        this.showCookieSettingsModal = false;
                        return;
                    }

                    this.adsConsentStatus = this.readStoredAdsConsent();
                    this.cookieSettingsAdsAllowed = this.adsConsentStatus === 'accepted';
                    this.refreshAdsConsentState();
                    this.consumeCookieSettingsQueryParam();
                },
                scheduleLazyWidgetInitialization() {
                    this.$nextTick(() => {
                        this.initLazyImageObserver();
                        this.initRadarWidgetObserver();
                        this.initAdObserver();
                    });
                },
                initAdObserver() {
                    if (this.adMounted || !this.canRenderAds) {
                        this.disconnectAdObserver();
                        return;
                    }

                    const slot = document.getElementById('dashboard-ad-slot');
                    if (!slot) return;

                    if (!('IntersectionObserver' in window)) {
                        this.mountAdCode();
                        return;
                    }

                    if (this._adObserver) {
                        this._adObserver.disconnect();
                    }

                    this._adObserver = new IntersectionObserver((entries) => {
                        const visible = entries.some((entry) => entry.isIntersecting || entry.intersectionRatio > 0);
                        if (!visible) return;
                        this.mountAdCode();
                    }, {
                        rootMargin: '200px 0px',
                        threshold: 0.01,
                    });

                    this._adObserver.observe(slot);
                },
                disconnectAdObserver() {
                    if (this._adObserver) {
                        this._adObserver.disconnect();
                        this._adObserver = null;
                    }
                },
                isLocalHostForAds() {
                    const host = window.location.hostname || '';
                    return host === 'localhost' || host === '127.0.0.1' || host === '::1';
                },
                updateAdRuntimeStatus(adSenseSlots) {
                    if (!Array.isArray(adSenseSlots) || adSenseSlots.length === 0) {
                        if (this.adFillState === 'unfilled') {
                            return;
                        }
                        if (this.adMounted) {
                            this.adFillState = 'error';
                        }
                        return;
                    }

                    const statuses = adSenseSlots.map((adSenseSlot, index) => ({
                        index,
                        adsByGoogleStatus: adSenseSlot.getAttribute('data-adsbygoogle-status') || null,
                        adStatus: adSenseSlot.getAttribute('data-ad-status') || null,
                        adTest: adSenseSlot.getAttribute('data-adtest') || null,
                    }));
                    this.adRuntimeStatuses = statuses;

                    const hasFilled = statuses.some((entry) => entry.adStatus === 'filled');
                    const hasUnfilled = statuses.some((entry) => entry.adStatus === 'unfilled');
                    const hasDone = statuses.some((entry) => entry.adsByGoogleStatus === 'done');

                    if (hasFilled) {
                        this.adFillState = 'filled';
                        this.adSlotCollapsed = false;
                        return;
                    }

                    if (hasUnfilled) {
                        this.adFillState = 'unfilled';
                        // Don't clear innerHTML here — AdSense may still be loading
                        // on slower connections. The final status check in mountAdCode
                        // handles cleanup after all retries are exhausted.
                        return;
                    }

                    if (hasDone) {
                        this.adFillState = 'done';
                        this.adSlotCollapsed = false;
                        return;
                    }

                    this.adFillState = 'loading';
                },
                mountAdCode() {
                    if (this.adMounted || !this.canRenderAds) {
                        return;
                    }

                    const slot = document.getElementById('dashboard-ad-slot');
                    if (!slot || !this.adCodeHtml) return;

                    this.adFillState = 'loading';
                    this.adSlotCollapsed = false;
                    this.adRuntimeStatuses = [];
                    this.adForceTestMode = this.isLocalHostForAds();
                    slot.innerHTML = this.adCodeHtml;

                    // Recreate script tags so browser executes ad network bootstrap code.
                    const scripts = Array.from(slot.querySelectorAll('script'));
                    let hasAdSenseBootstrapScript = false;
                    let hasAdSensePushScript = false;
                    scripts.forEach((oldScript) => {
                        const scriptSrc = (oldScript.getAttribute('src') || '').toLowerCase();
                        if (scriptSrc.includes('pagead2.googlesyndication.com/pagead/js/adsbygoogle.js')) {
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
                        if (this.adForceTestMode) {
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
                            console.warn('AdSense slot initialization failed:', error);
                        }
                    }

                    // Surface practical diagnostics for AdSense runtime state.
                    // Use generous delays — AdSense can take 5-10s on slow connections.
                    if (adSenseSlots.length > 0) {
                        const statusCheckDelaysMs = [2000, 5000, 10000];
                        statusCheckDelaysMs.forEach((delay, index) => {
                            window.setTimeout(() => {
                                const latestSlots = Array.from(slot.querySelectorAll('ins.adsbygoogle'));
                                this.updateAdRuntimeStatus(latestSlots);

                                if (index !== statusCheckDelaysMs.length - 1) {
                                    return;
                                }

                                // Final check — only now collapse if truly unfilled.
                                if (this.adFillState === 'unfilled') {
                                    this.adSlotCollapsed = true;
                                    console.warn('AdSense rendered but returned unfilled ad inventory for this request.', this.adRuntimeStatuses);
                                } else {
                                    console.log('AdSense slot status:', this.adRuntimeStatuses);
                                }
                            }, delay);
                        });
                    } else {
                        this.adFillState = 'error';
                    }

                    if (!hasAdSenseBootstrapScript && adSenseSlots.length > 0) {
                        window.setTimeout(() => {
                            if (this.adRuntimeStatuses.length === 0 && this.adFillState === 'loading') {
                                this.adFillState = 'error';
                            }
                        }, 5000);
                    }

                    this.adMounted = true;
                    this.disconnectAdObserver();
                },
                getAdsDebugInfo() {
                    const slot = document.getElementById('dashboard-ad-slot');
                    const adSenseSlots = slot ? Array.from(slot.querySelectorAll('ins.adsbygoogle')) : [];

                    return {
                        hasAdCode: this.hasAdCode,
                        canRenderAds: this.canRenderAds,
                        adMounted: this.adMounted,
                        adFillState: this.adFillState,
                        adSlotCollapsed: this.adSlotCollapsed,
                        adForceTestMode: this.adForceTestMode,
                        adCodeLength: this.adCodeHtml ? this.adCodeHtml.length : 0,
                        slotFound: Boolean(slot),
                        slotChildCount: slot ? slot.childElementCount : 0,
                        adSenseScriptLoaded: Boolean(
                            document.querySelector('script[src*="pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"]')
                        ),
                        adSenseSlotCount: adSenseSlots.length,
                        adRuntimeStatuses: this.adRuntimeStatuses,
                        adSenseSlots: adSenseSlots.map((adSenseSlot, index) => ({
                            index,
                            adsByGoogleStatus: adSenseSlot.getAttribute('data-adsbygoogle-status') || null,
                            adStatus: adSenseSlot.getAttribute('data-ad-status') || null,
                            adTest: adSenseSlot.getAttribute('data-adtest') || null,
                            width: adSenseSlot.clientWidth,
                            height: adSenseSlot.clientHeight,
                        })),
                    };
                },

                tempUnit() {
                    return this.units === 'imperial' ? '°F' : '°C';
                },
                windUnit() {
                    if (this.units === 'scandinavia') return 'm/s';
                    if (this.units === 'imperial' || this.units === 'uk') return 'mph';
                    return 'km/h';
                },
                pressureUnit() {
                    return this.units === 'imperial' ? 'inHg' : 'hPa';
                },
                rainUnit() {
                    return this.units === 'imperial' ? 'in' : 'mm';
                },
                rainRateSuffix() {
                    return (window.Meteo?.rainRateUnit === '/min') ? '/min' : '/h';
                },
                normalizeDecimals(value, fallback = 1) {
                    const n = Number(value);
                    if (!Number.isFinite(n)) return fallback;
                    return Math.max(0, Math.min(4, Math.trunc(n)));
                },
                temperatureDecimals() {
                    return this.normalizeDecimals(window.Meteo?.temperatureDecimals, 1);
                },
                windDecimals() {
                    return this.normalizeDecimals(window.Meteo?.windDecimals, 1);
                },
                rainDecimals() {
                    return this.normalizeDecimals(window.Meteo?.rainDecimals, 1);
                },
                pressureDecimals() {
                    return this.normalizeDecimals(window.Meteo?.pressureDecimals, 1);
                },
                distanceUnit() {
                    return (this.units === 'imperial' || this.units === 'uk') ? 'mi' : 'km';
                },
                formatTempValue(value, decimals = null) {
                    if (value === null || value === undefined) return '--';
                    const temp = this.units === 'imperial' ? (value * 9 / 5 + 32) : value;
                    const useDecimals = decimals === null ? this.temperatureDecimals() : this.normalizeDecimals(decimals, this.temperatureDecimals());
                    return temp.toFixed(useDecimals);
                },
                formatTemp(value, decimals = null) {
                    const formatted = this.formatTempValue(value, decimals);
                    return formatted === '--' ? '--' : `${formatted}${this.tempUnit()}`;
                },
                formatWindValue(value, decimals = null) {
                    if (value === null || value === undefined) return '--';
                    let speed = value;
                    if (this.units === 'imperial' || this.units === 'uk') {
                        speed = value * 0.6213711922;
                    } else if (this.units === 'scandinavia') {
                        speed = value / 3.6;
                    }
                    const useDecimals = decimals === null ? this.windDecimals() : this.normalizeDecimals(decimals, this.windDecimals());
                    return speed.toFixed(useDecimals);
                },
                formatWind(value, decimals = null) {
                    const formatted = this.formatWindValue(value, decimals);
                    return formatted === '--' ? '--' : `${formatted} ${this.windUnit()}`;
                },
                formatPressureValue(value, decimals = null) {
                    if (value === null || value === undefined) return '--';
                    const pressure = this.units === 'imperial' ? (value * 0.02953) : value;
                    const useDecimals = decimals === null ? this.pressureDecimals() : this.normalizeDecimals(decimals, this.pressureDecimals());
                    return pressure.toFixed(useDecimals);
                },
                formatPressure(value, decimals = null) {
                    const formatted = this.formatPressureValue(value, decimals);
                    return formatted === '--' ? '--' : `${formatted} ${this.pressureUnit()}`;
                },
                get pressureChartNormalized() {
                    const history = this.pressureHistory || [];
                    return history
                        .map(d => d?.pressure ?? d?.pressure_rel ?? d?.pressure_abs)
                        .map((v) => {
                            if (typeof v === 'string') {
                                return v.replace(',', '.');
                            }
                            return v;
                        })
                        .filter(v => Number.isFinite(Number(v)))
                        .map(v => Number(v));
                },
                get hasPressureChartData() {
                    return this.pressureChartNormalized.length > 0;
                },
                get pressureChartData() {
                    const history = this.pressureHistory || [];
                    const pressures = this.pressureChartNormalized;
                    if (!pressures.length) {
                        return { bars: [], pressures: [], min: null, max: null, times: [], range: null };
                    }
                    const min = Math.min(...pressures);
                    const max = Math.max(...pressures);
                    const scaleRange = (max - min) || 1;
                    const bars = pressures.map(p => 20 + ((p - min) / scaleRange) * 60);
                    return {
                        bars,
                        pressures,
                        times: history.map(d => d.time),
                        min,
                        max,
                        range: max - min
                    };
                },
                pressureChartFormatTime(timeStr) {
                    if (!timeStr) return '';
                    const tz = window.Meteo?.stationTimezone || 'UTC';
                    const date = new Date(timeStr);
                    return date.toLocaleTimeString(locale, { timeZone: tz, hour: '2-digit', minute: '2-digit' });
                },
                pressureChartFormatRelativeTime(timeStr) {
                    if (!timeStr) return '';
                    const date = new Date(timeStr);
                    const now = new Date();
                    const diffMs = now - date;
                    const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
                    const diffMinutes = Math.floor(diffMs / (1000 * 60)) % 60;
                    if (diffHours < 0) return diffHours === -1 ? 'in 1h' : `in ${-diffHours}h`;
                    if (diffHours === 0) return diffMinutes === 0 ? 'Now' : `${diffMinutes}m ago`;
                    if (diffHours === 1) return '1h ago';
                    return `${diffHours}h ago`;
                },
                pressureChartGetBarColor(idx, data) {
                    if (idx === data.bars.length - 1) return 'rgba(59, 130, 246, 0.7)';
                    const currentP = data.pressures?.[idx];
                    const nextP = data.pressures?.[idx + 1];
                    if (currentP == null || nextP == null) return 'rgba(107, 114, 128, 0.4)';
                    if (nextP < currentP) return 'rgba(6, 182, 212, 0.7)';
                    if (nextP > currentP) return 'rgba(248, 113, 113, 0.7)';
                    return 'rgba(34, 197, 94, 0.7)';
                },
                pressureChartHandleMouseEnter(event, idx) {
                    this.pressureChartHoveredIndex = idx;
                    const rect = event.currentTarget.getBoundingClientRect();
                    const container = event.currentTarget.closest('.relative').getBoundingClientRect();
                    this.pressureChartTooltipX = rect.left - container.left + rect.width / 2;
                    this.pressureChartTooltipY = rect.top - container.top - 5;
                },
                pressureChartHandleMouseLeave() {
                    this.pressureChartHoveredIndex = null;
                },
                get pressureChartLinePath() {
                    const data = this.pressureChartData;
                    if (!data.bars.length || data.bars.length < 2) return '';
                    const barWidth = 100 / data.bars.length;
                    let path = `M ${barWidth / 2} ${100 - data.bars[0]}`;
                    for (let i = 1; i < data.bars.length; i++) {
                        const x = (i * barWidth) + (barWidth / 2);
                        const y = 100 - data.bars[i];
                        path += ` L ${x} ${y}`;
                    }
                    return path;
                },
                get pressureChartTimeLabels() {
                    const data = this.pressureChartData;
                    if (!data.times.length) return [];
                    const total = data.times.length;
                    const labels = [];
                    if (total > 0) labels.push({ idx: 0, time: this.pressureChartFormatRelativeTime(data.times[0]) });
                    if (total > 1) labels.push({ idx: Math.floor(total / 2), time: this.pressureChartFormatRelativeTime(data.times[Math.floor(total / 2)]) });
                    if (total > 2) labels.push({ idx: total - 1, time: this.pressureChartFormatRelativeTime(data.times[total - 1]) });
                    return labels;
                },
                // ── Wind Rose Data ──────────────────────────────
                get windRoseData() {
                    const history = this.windHistory || [];
                    const directions16 = ['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSW','SW','WSW','W','WNW','NW','NNW'];
                    // Convert display thresholds to active unit for legend labels
                    const cv = (v) => {
                        if (this.units === 'imperial' || this.units === 'uk') return Math.round(v * 0.6213711922);
                        if (this.units === 'scandinavia') return Math.round(v / 3.6);
                        return v;
                    };
                    const speedRanges = [
                        { max: 5,        color: 'rgba(74,222,128,0.8)',  label: `0-${cv(5)}` },
                        { max: 15,       color: 'rgba(96,165,250,0.8)',  label: `${cv(5)}-${cv(15)}` },
                        { max: 30,       color: 'rgba(250,204,21,0.8)',  label: `${cv(15)}-${cv(30)}` },
                        { max: 50,       color: 'rgba(251,146,60,0.8)',  label: `${cv(30)}-${cv(50)}` },
                        { max: Infinity, color: 'rgba(248,113,113,0.8)', label: `${cv(50)}+` },
                    ];
                    const bins = directions16.map(() => speedRanges.map(() => 0));
                    let total = 0;
                    let calm = 0;

                    for (const entry of history) {
                        const dir = Number(entry.direction);
                        const spd = Number(entry.speed);
                        if (!Number.isFinite(dir) || !Number.isFinite(spd)) continue;
                        total++;
                        if (spd < 1) { calm++; continue; }
                        const binIdx = Math.round(((dir % 360 + 360) % 360) / 22.5) % 16;
                        for (let s = 0; s < speedRanges.length; s++) {
                            if (spd <= speedRanges[s].max) { bins[binIdx][s]++; break; }
                        }
                    }

                    if (total === 0) return { petals: [], calmPct: 0, maxPct: 0, speedRanges, total: 0 };

                    const calmPct = Math.round((calm / total) * 100);
                    let maxPct = 0;
                    const petals = directions16.map((label, i) => {
                        const angle = i * 22.5;
                        const segments = [];
                        let cumPct = 0;
                        for (let s = 0; s < speedRanges.length; s++) {
                            const pct = (bins[i][s] / total) * 100;
                            if (pct > 0) {
                                segments.push({ pct, cumPct, color: speedRanges[s].color });
                            }
                            cumPct += pct;
                        }
                        if (cumPct > maxPct) maxPct = cumPct;
                        return { label, angle, totalPct: cumPct, segments };
                    });

                    // Build SVG markup — annular sector (pie slice) paths
                    const cx = 110, cy = 110, maxR = 62, baseR = 14;
                    const toRad = d => d * Math.PI / 180;
                    const sectorHalf = 10; // half-width of each sector in degrees (22.5/2 = 11.25, minus 1.25 gap)
                    const sectorPath = (angleDeg, innerR, outerR) => {
                        // angleDeg: center angle (0=N, clockwise). Convert to math angle (0=right, CCW)
                        const a1 = toRad(angleDeg - sectorHalf - 90);
                        const a2 = toRad(angleDeg + sectorHalf - 90);
                        // Inner arc start/end
                        const ix1 = cx + innerR * Math.cos(a1), iy1 = cy + innerR * Math.sin(a1);
                        const ix2 = cx + innerR * Math.cos(a2), iy2 = cy + innerR * Math.sin(a2);
                        // Outer arc start/end
                        const ox1 = cx + outerR * Math.cos(a1), oy1 = cy + outerR * Math.sin(a1);
                        const ox2 = cx + outerR * Math.cos(a2), oy2 = cy + outerR * Math.sin(a2);
                        // Path: move to inner-start, line to outer-start, arc to outer-end, line to inner-end, arc back
                        return `M${ix1.toFixed(1)},${iy1.toFixed(1)} L${ox1.toFixed(1)},${oy1.toFixed(1)} A${outerR},${outerR} 0 0,1 ${ox2.toFixed(1)},${oy2.toFixed(1)} L${ix2.toFixed(1)},${iy2.toFixed(1)} A${innerR},${innerR} 0 0,0 ${ix1.toFixed(1)},${iy1.toFixed(1)}Z`;
                    };
                    const mp = maxPct || 1;

                    // Build complete SVG markup string (x-for/template doesn't work inside SVG)
                    const svgParts = [];
                    // Concentric scale circles
                    [17.5, 35, 52.5].forEach(r => svgParts.push(`<circle cx="${cx}" cy="${cy}" r="${r}" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="0.5"/>`));
                    svgParts.push(`<circle cx="${cx}" cy="${cy}" r="70" fill="none" stroke="rgba(255,255,255,0.12)" stroke-width="0.5"/>`);
                    // Axis lines
                    for (let i = 0; i < 16; i++) {
                        const rad = toRad(i * 22.5);
                        svgParts.push(`<line x1="${cx}" y1="${cy}" x2="${(cx + 75 * Math.sin(rad)).toFixed(1)}" y2="${(cy - 75 * Math.cos(rad)).toFixed(1)}" stroke="rgba(255,255,255,0.05)" stroke-width="0.5"/>`);
                    }
                    // Petal segments (annular sectors)
                    for (const petal of petals) {
                        for (const seg of petal.segments) {
                            const iR = baseR + (seg.cumPct / mp) * maxR;
                            const oR = baseR + ((seg.cumPct + seg.pct) / mp) * maxR;
                            const d = sectorPath(petal.angle, iR, oR);
                            svgParts.push(`<path d="${d}" fill="${seg.color}" stroke="rgba(0,0,0,0.2)" stroke-width="0.5"/>`);
                        }
                    }
                    // Direction labels
                    const labels = [
                        [cx, 22, 'white', 10, 'bold', 'N'], [cx+88, cy+4, 'white', 10, 'bold', 'E'],
                        [cx, cy+96, 'white', 10, 'bold', 'S'], [cx-88, cy+4, 'white', 10, 'bold', 'W'],
                        [cx+45, cy-76, 'rgba(255,255,255,0.5)', 7, 'normal', 'NE'],
                        [cx+80, cy-40, 'rgba(255,255,255,0.5)', 7, 'normal', 'ENE'],
                        [cx+80, cy+48, 'rgba(255,255,255,0.5)', 7, 'normal', 'ESE'],
                        [cx+45, cy+84, 'rgba(255,255,255,0.5)', 7, 'normal', 'SE'],
                        [cx-45, cy+84, 'rgba(255,255,255,0.5)', 7, 'normal', 'SW'],
                        [cx-80, cy+48, 'rgba(255,255,255,0.5)', 7, 'normal', 'WSW'],
                        [cx-80, cy-40, 'rgba(255,255,255,0.5)', 7, 'normal', 'WNW'],
                        [cx-45, cy-76, 'rgba(255,255,255,0.5)', 7, 'normal', 'NW'],
                    ];
                    for (const [lx, ly, fill, fs, fw, txt] of labels) {
                        svgParts.push(`<text x="${lx}" y="${ly}" text-anchor="middle" fill="${fill}" font-size="${fs}" font-weight="${fw}">${txt}</text>`);
                    }
                    // Center calm
                    svgParts.push(`<circle cx="${cx}" cy="${cy}" r="11" fill="rgba(15,23,42,0.8)" stroke="rgba(255,255,255,0.15)" stroke-width="0.5"/>`);
                    svgParts.push(`<text x="${cx}" y="${cy-2}" text-anchor="middle" fill="rgba(255,255,255,0.6)" font-size="5.5">Calm</text>`);
                    svgParts.push(`<text x="${cx}" y="${cy+6}" text-anchor="middle" fill="white" font-size="7" font-weight="bold">${calmPct}%</text>`);

                    const svgMarkup = `<svg viewBox="0 0 220 220" class="w-full mx-auto" style="max-width:280px;">${svgParts.join('')}</svg>`;

                    return { svgMarkup, calmPct, maxPct: mp, speedRanges, total };
                },

                formatRainValue(value, decimals = null) {
                    if (value === null || value === undefined) return '--';
                    const rain = this.units === 'imperial' ? (value * 0.0393700787) : value;
                    const useDecimals = decimals === null ? this.rainDecimals() : this.normalizeDecimals(decimals, this.rainDecimals());
                    return rain.toFixed(useDecimals);
                },
                formatRain(value, decimals = null) {
                    const formatted = this.formatRainValue(value, decimals);
                    return formatted === '--' ? '--' : `${formatted} ${this.rainUnit()}`;
                },
                formatRainRateValue(value, decimals = null) {
                    if (value === null || value === undefined) return '--';
                    let rate = this.units === 'imperial' ? (value * 0.0393700787) : value;
                    if (this.rainRateSuffix() === '/min') {
                        rate = rate / 60;
                    }
                    const useDecimals = decimals === null ? this.rainDecimals() : this.normalizeDecimals(decimals, this.rainDecimals());
                    return rate.toFixed(useDecimals);
                },
                formatRainRate(value, decimals = null) {
                    const formatted = this.formatRainRateValue(value, decimals);
                    return formatted === '--' ? '--' : `${formatted} ${this.rainUnit()}${this.rainRateSuffix()}`;
                },
                formatLastRainAt(timestamp) {
                    if (!timestamp) return '--';
                    const tz = window.Meteo?.stationTimezone || 'UTC';
                    const date = new Date(timestamp);
                    if (Number.isNaN(date.getTime())) return '--';
                    return date.toLocaleString(locale, {
                        timeZone: tz,
                        year: 'numeric',
                        month: 'short',
                        day: '2-digit',
                        hour: '2-digit',
                        minute: '2-digit',
                    });
                },
                formatDistanceValue(value, decimals = 1) {
                    if (value === null || value === undefined) return '--';
                    const distance = (this.units === 'imperial' || this.units === 'uk')
                        ? (value * 0.621371)
                        : value;
                    return distance.toFixed(decimals);
                },
                formatDistance(value, decimals = 1) {
                    const formatted = this.formatDistanceValue(value, decimals);
                    return formatted === '--' ? '--' : `${formatted} ${this.distanceUnit()}`;
                },
                magnitudeColorClass(mag) {
                    if (mag == null) return 'bg-gray-500/20 text-gray-300';
                    if (mag < 2) return 'bg-gray-500/20 text-gray-300';
                    if (mag < 3) return 'bg-blue-500/20 text-blue-400';
                    if (mag < 4) return 'bg-cyan-500/20 text-cyan-400';
                    if (mag < 5) return 'bg-yellow-500/20 text-yellow-400';
                    if (mag < 6) return 'bg-orange-500/20 text-orange-400';
                    if (mag < 7) return 'bg-red-500/20 text-red-400';
                    return 'bg-purple-500/20 text-purple-400';
                },
                formatMetarConditions(conditions) {
                    if (!conditions || conditions.length === 0) return '--';
                    const translated = conditions
                        .map((condition) => this.translateMetarCondition(condition))
                        .filter(Boolean);
                    return translated.length ? translated.join(', ') : '--';
                },
                formatMetarClouds(clouds) {
                    if (!clouds || clouds.length === 0) return '--';
                    // Translate based on the code (METAR), not the English text from API.
                    const cloudCodes = {
                        'SKC': t('Clear'),
                        'CLR': t('Clear'),
                        'NSC': t('Clear'),
                        'NCD': t('Clear'),
                        // CAVOK is a specific aviation concept (ceiling/visibility OK), not just "clear skies".
                        'CAVOK': t('CAVOK'),
                        'FEW': t('Few clouds'),
                        'SCT': t('Scattered clouds'),
                        'BKN': t('Broken clouds'),
                        'OVC': t('Overcast'),
                        'VV': t('Vertical visibility'),
                    };

                    const useFeet = (this.units === 'imperial' || this.units === 'uk');
                    const formatBase = (layer) => {
                        const base = useFeet ? layer?.base_feet : layer?.base_meters;
                        if (base === null || base === undefined) return '';
                        const n = Number(base);
                        if (!Number.isFinite(n) || n <= 0) return '';
                        if (useFeet) {
                            // Aviation convention: cloud bases are typically expressed in ~100 ft steps.
                            const rounded = Math.round(n / 100) * 100;
                            return ` ${rounded} ft`;
                        }
                        const rounded = Math.round(n);
                        return ` ${rounded} m`;
                    };

                    const parts = clouds
                        .map((layer) => {
                            const code = (layer?.code || '').toUpperCase();
                            const label = cloudCodes[code] || layer?.text || code || '';
                            if (!label) return '';
                            return `${label}${formatBase(layer)}`.trim();
                        })
                        .filter(Boolean);

                    return parts.length ? parts.join(', ') : '--';
                },
                translateMoonPhase(name) {
                    if (!name) return '';
                    return this.moonPhaseLabels[name] || name;
                },
                translateEvent(name) {
                    if (!name) return '';
                    return this.eventLabels[name] || this.moonPhaseLabels[name] || name;
                },
                translateEventType(type) {
                    if (!type) return '';
                    return this.eventTypeLabels[type] || type;
                },
                translateMetarCondition(condition) {
                    if (!condition) return '';
                    const fallback = condition.text || condition.code || '';
                    const rawCode = (condition.code || '').toUpperCase();
                    if (!rawCode) return fallback;

                    let working = rawCode;
                    let intensity = '';
                    if (working.startsWith('-')) {
                        intensity = this.metarTokens.intensity.light;
                        working = working.slice(1);
                    } else if (working.startsWith('+')) {
                        intensity = this.metarTokens.intensity.heavy;
                        working = working.slice(1);
                    }

                    let inVicinity = false;
                    if (working.startsWith('VC')) {
                        inVicinity = true;
                        working = working.slice(2);
                    }

                    const descriptors = [];
                    const descriptorTokens = ['MI', 'PR', 'BC', 'DR', 'BL', 'SH', 'TS', 'FZ'];
                    while (working.length >= 2) {
                        const token = working.slice(0, 2);
                        if (!descriptorTokens.includes(token)) break;
                        descriptors.push(token);
                        working = working.slice(2);
                    }

                    const phenomena = [];
                    for (let i = 0; i < working.length; i += 2) {
                        const token = working.slice(i, i + 2);
                        if (token.length === 2) phenomena.push(token);
                    }

                    if (!descriptors.length && !phenomena.length) return fallback;

                    const phenomenaText = phenomena.map((code) => this.metarTokens.phenomena[code]).filter(Boolean);
                    if (phenomena.length && phenomenaText.length !== phenomena.length) return fallback;

                    const descriptorSet = new Set(descriptors);
                    let phrase = '';
                    const joiner = ` ${this.metarTokens.and} `;

                    if (descriptorSet.has('TS')) {
                        phrase = this.metarTokens.descriptor.TS;
                        if (phenomenaText.length) {
                            phrase += ` ${this.metarTokens.with} ${phenomenaText.join(joiner)}`;
                        }
                    } else if (descriptorSet.has('SH')) {
                        if (phenomenaText.length) {
                            phrase = `${phenomenaText.join(joiner)} ${this.metarTokens.descriptor.SH}`;
                        } else {
                            phrase = this.metarTokens.descriptor.SH;
                        }
                    } else if (descriptorSet.has('FZ')) {
                        phrase = `${this.metarTokens.descriptor.FZ}${phenomenaText.length ? ` ${phenomenaText.join(joiner)}` : ''}`;
                    } else {
                        const descriptorText = descriptors.map((code) => this.metarTokens.descriptor[code]).filter(Boolean);
                        if (descriptorText.length !== descriptors.length) return fallback;
                        phrase = [...descriptorText, phenomenaText.join(joiner)].filter(Boolean).join(' ');
                    }

                    if (intensity) {
                        phrase = `${intensity} ${phrase}`;
                    }

                    if (inVicinity && phrase) {
                        phrase = `${this.metarTokens.vicinity}: ${phrase}`;
                    }

                    return phrase || fallback;
                },
                
                // Weather condition flags (used for effect visibility; set in updateWeatherConditions)
                isRaining: false,
                isSnowing: false,
                isWindy: false,
                isSunny: false,
                isFoggy: false,   // true when test effect = fog, or real: humidity >= 98% and not raining
                isThunderstorm: false,
                
                // Effect settings from API
                effectsEnabled: true,
                // Background effects toggle (persisted to localStorage for user preference)
                backgroundEffectsEnabled: localStorage.getItem('backgroundEffectsEnabled') !== 'false',
                effects: {
                    rain: { enabled: true, intensity: 50, splash_on_cards: true, show_forecast: true, forecast_threshold_type: 'absolute', forecast_threshold_value: 0.5 },
                    snow: { enabled: true, intensity: 50 },
                    wind: { enabled: true, intensity: 50 },
                    lightning: { enabled: true },
                    sun: { enabled: true },
                    clouds: { enabled: true },
                    fog: { enabled: true },
                    test_mode: false,
                    test_effect: 'rain',
                },
                
                rainDrops: [],
                windParticles: [],

                // Change detection
                previousData: {
                    currentRecordedAt: null,
                    forecastHash: null,
                    airQualityHash: null,
                    astronomyHash: null
                },
                changedFields: new Set(),
                lastUpdateTime: null,
                lastDataTime: null,
                isRefreshing: false,
                dataStaleThreshold: 600000, // 10 minutes in ms
                staleBannerGraceMs: 90000, // suppress stale warning briefly on first load/hard refresh
                staleBannerGraceUntil: Date.now() + 90000,
                lastUpdateText: t('Not updated yet') || 'Not updated yet', // Reactive property
                dataIsStale: false, // Reactive property for stale detection
                ssrFallbackVisible: hybridSsrEnabled && !!(initialPayload && initialPayload.success),

                // Per-card update tracking
                cardUpdates: {
                    current: null,
                    forecast: null,
                    airQuality: null,
                    astronomy: null,
                },
                // Reactive text for each card
                cardUpdateTexts: {
                    current: null,
                    forecast: null,
                    airQuality: null,
                    astronomy: null,
                },

                // Health status from backend (populated by health check command)
                healthStatus: {
                    sensor: { is_stale: false },
                    forecast: { is_stale: false },
                    astronomy: { is_stale: false },
                    aurora: { is_stale: false },
                    airquality: { is_stale: false },
                    metar: { is_stale: false },
                },

                // Page visibility
                isPageVisible: !document.hidden, // Initialize from document state
                pausedAt: null,
                isBrowserOnline: navigator.onLine !== false,

                // Store all interval IDs for cleanup
                _intervals: [],
                _initialized: false,
                _adsConsentListener: null,
                _adObserver: null,
                _lazyImageObserver: null,
                _sortableImportPromise: null,
                _sortableCtor: null,
                _leafletLoadPromise: null,
                _radarObserver: null,
                _radarMap: null,
                _radarLayer: null,
                _radarFrames: [],
                _radarCurrentFrameIndex: 0,
                _radarAnimationInterval: null,
                _radarRefreshInterval: null,
                _radarHost: '',
                _radarLastGenerated: null,
                _radarVisibilityListener: null,
                _radarMapInitialized: false,
                _radarPendingLayer: null,
                _radarSwapToken: 0,
                radarFrameTimeLabel: '',
                radarZoomLevel: null,
                _radarZoomListener: null,
                _visibilityListener: null,
                _onlineListener: null,
                _offlineListener: null,
                _isFetchingData: false,

	                async init() {
	                    // Prevent double initialization (Safari can trigger this)
	                    if (this._initialized) {
	                        console.log('⚠️ Dashboard already initialized, skipping');
	                        return;
	                    }
	                    this._initialized = true;
	                    window.__meteouitgeestAdsDebug = () => this.getAdsDebugInfo();
	                    
	                    console.log('🚀 Initializing dashboard...');
	                    
	                    // Clear any existing intervals first
	                    this.cleanup();
                        this.staleBannerGraceUntil = Date.now() + this.staleBannerGraceMs;
                        this.registerAdsConsentListener();
                        this.initAdsConsent();
	                    
	                    this.updateClock();
	                    const clockIntervalMs = 1000;
	                    this._intervals.push(setInterval(() => {
	                        if (this.isPageVisible) {
	                            this.updateClock();
	                        }
	                    }, clockIntervalMs));
	                    
		                    // Keep a reactive "now" for chart overlays.
		                    // Only needed when the hourly temperature chart widget is enabled.
		                    if (this.isWidgetEnabled('hourly')) {
		                        this._intervals.push(setInterval(() => {
		                            if (this.isPageVisible) {
		                                this.nowTs = Date.now();
		                            }
		                        }, 60000));
		                    }

	                    // Update status text every second
	                    const statusIntervalMs = this.isFlatTheme ? 10000 : 1000;
	                    this._intervals.push(setInterval(() => {
	                        if (!this.isPageVisible) {
	                            return;
	                        }
	                        this.lastUpdateText = this.getLastUpdateText();
	                        const wasStale = this.dataIsStale;
	                        this.dataIsStale = this.isDataStale();
	                        if (wasStale !== this.dataIsStale) {
	                            console.log(this.dataIsStale ? '⚠️ Data is now STALE' : '✅ Data is now FRESH');
	                        }
	                    }, statusIntervalMs));

                    // Initialize visibility tracking
                    this.initVisibilityTracking();
                    this.initConnectivityTracking();

                    const hasInitialPayload = hybridSsrEnabled && initialPayload && initialPayload.success;
                    if (hasInitialPayload) {
                        this.applyPayload(initialPayload, { initial: true, silent: true });
                        window.requestAnimationFrame(() => {
                            window.requestAnimationFrame(() => {
                                this.ssrFallbackVisible = false;
                            });
                        });
                        if (this.isWidgetEnabled('hourly') && this.tempChartShowObserved) {
                            await this.fetchObservedTempHistory();
                        }
                        // Hybrid mode: refresh immediately after SSR hydration to keep data fresh.
                        if (this.isPageVisible) {
                            this.fetchData({ force: true, silent: true });
                        }
                    } else {
                        // Initial data fetch - use try/catch to prevent crashes
                        try {
	                            await this.fetchData({ force: true });
                                if (!this.current && (!Array.isArray(this.forecast) || this.forecast.length === 0)) {
                                    // Safari can report hidden state during first paint; do one quick forced retry.
                                    setTimeout(() => this.fetchData({ force: true, silent: true }), 1200);
                                }
	                            if (this.isWidgetEnabled('hourly') && this.tempChartShowObserved) {
	                                await this.fetchObservedTempHistory();
	                            }
                        } catch (err) {
                            console.error('❌ Initial data fetch failed:', err);
                            setTimeout(() => this.fetchData({ force: true, silent: true }), 1500);
                        }
                    }

                    // Smart polling - only when page is visible
                    this._intervals.push(setInterval(() => {
                        if (this.isPageVisible) {
                            this.fetchData({ silent: true });
                        }
                    }, 60000)); // 60 seconds

                    // Observed temperature history changes slowly; refresh less frequently.
	                    if (this.isWidgetEnabled('hourly') && this.tempChartShowObserved) {
	                        this._intervals.push(setInterval(() => {
	                            if (this.isPageVisible) {
	                                this.fetchObservedTempHistory();
	                            }
	                        }, 600000)); // 10 minutes
	                    }

                    // Auto-refresh webcam and radar images
                    this.initImageRefresh();
                    this.scheduleLazyWidgetInitialization();

	                    // Start weather effects after a short delay to let the DOM settle
	                    // Using setTimeout for reliable cross-browser support
	                    setTimeout(() => {
	                        console.log('🌧️ Starting weather effects check...', {
	                            effectsEnabled: this.effectsEnabled,
	                            backgroundEffectsEnabled: this.backgroundEffectsEnabled,
	                            testMode: this.effects.test_mode,
	                            testEffect: this.effects.test_effect,
	                            isRaining: this.isRaining,
	                            isSnowing: this.isSnowing,
	                            isWindy: this.isWindy
	                        });

	                        this.applyBackgroundEffectsState();
	                    }, 300);

	                    // React to toggles/admin settings without requiring a refresh.
	                    this.$watch('backgroundEffectsEnabled', () => this.applyBackgroundEffectsState());
	                    this.$watch('effectsEnabled', () => this.applyBackgroundEffectsState());
	                },

                // Cleanup function for proper memory management
                cleanup() {
                    // Clear main intervals
                    if (this._intervals) {
                        this._intervals.forEach(id => clearInterval(id));
                        this._intervals = [];
                    }
                    if (this._adsConsentListener) {
                        window.removeEventListener('meteo:open-cookie-settings', this._adsConsentListener);
                        this._adsConsentListener = null;
                    }
                    if (this._visibilityListener) {
                        document.removeEventListener('visibilitychange', this._visibilityListener);
                        this._visibilityListener = null;
                    }
                    if (this._onlineListener) {
                        window.removeEventListener('online', this._onlineListener);
                        this._onlineListener = null;
                    }
                    if (this._offlineListener) {
                        window.removeEventListener('offline', this._offlineListener);
                        this._offlineListener = null;
                    }
                    this.disconnectAdObserver();
                    this.disconnectLazyImageObserver();
                    this.cleanupRadarMap();
                    // Clear weather effect intervals
                    this.stopWeatherEffects();
                },

                disconnectLazyImageObserver() {
                    if (this._lazyImageObserver) {
                        this._lazyImageObserver.disconnect();
                        this._lazyImageObserver = null;
                    }
                },

                initLazyImageObserver() {
                    const lazyImages = Array.from(document.querySelectorAll('img[data-lazy-src]'));
                    if (!lazyImages.length) {
                        this.disconnectLazyImageObserver();
                        return;
                    }

                    if (!('IntersectionObserver' in window)) {
                        lazyImages.forEach((img) => this.activateLazyImage(img));
                        return;
                    }

                    if (this._lazyImageObserver) {
                        this._lazyImageObserver.disconnect();
                    }

                    this._lazyImageObserver = new IntersectionObserver((entries, observer) => {
                        entries.forEach((entry) => {
                            if (!entry.isIntersecting) return;
                            this.activateLazyImage(entry.target);
                            observer.unobserve(entry.target);
                        });
                    }, {
                        rootMargin: '250px 0px',
                        threshold: 0.01,
                    });

                    lazyImages.forEach((img) => this._lazyImageObserver.observe(img));
                },

                activateLazyImage(imgElement) {
                    if (!imgElement) return;
                    const lazySrc = imgElement.dataset.lazySrc;
                    if (!lazySrc) return;

                    const separator = lazySrc.includes('?') ? '&' : '?';
                    const nextSrc = `${lazySrc}${separator}t=${Date.now()}`;
                    if (imgElement.src === nextSrc) return;

                    imgElement.src = nextSrc;
                    imgElement.dataset.lazyLoaded = '1';
                },

                initImageRefresh() {
                    this.initLazyImageObserver();

                    // Refresh webcam at configured interval (only when in image mode, not livestream)
                    const webcamImg = document.getElementById('webcam-image');
                    const webcamRefreshInterval = Number(cfg.webcamRefreshInterval || 60) * 1000;
                    if (webcamImg) {
                        this._intervals.push(setInterval(() => {
                            if (this.isPageVisible && webcamImg && this.isWebcamInImageMode(webcamImg)) {
                                this.refreshImage(webcamImg);
                            }
                        }, webcamRefreshInterval));
                    }

                    // Refresh radar every 60 seconds (GIFs update less frequently)
                    const radarImg = document.getElementById('radar-image');
                    if (radarImg) {
                        this._intervals.push(setInterval(() => {
                            // Skip when hidden (e.g. radar widget disabled via x-show) to avoid background decoding/refresh work.
                            if (this.isPageVisible && radarImg && radarImg.offsetParent) {
                                this.refreshImage(radarImg);
                            }
                        }, 60000)); // 60 seconds
                    }
                },

                isWebcamInImageMode(webcamImg) {
                    // Only refresh when displayMode is exactly 'image', not 'stream' or 'both'
                    if (!webcamImg) {
                        return false;
                    }
                    
                    // Check if the image element itself is visible
                    if (!webcamImg.offsetParent) {
                        return false;
                    }
                    
                    // Check computed style to ensure it's not hidden
                    const style = window.getComputedStyle(webcamImg);
                    if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') {
                        return false;
                    }
                    
                    // Check parent div visibility (the div with x-show directive)
                    const parentDiv = webcamImg.parentElement;
                    if (parentDiv) {
                        const parentStyle = window.getComputedStyle(parentDiv);
                        if (parentStyle.display === 'none' || parentStyle.visibility === 'hidden') {
                            return false;
                        }
                    }
                    
                    // Check if there's a visible stream iframe or video in the same container
                    // This indicates we're in stream mode and shouldn't refresh
                    const container = webcamImg.closest('.aspect-video');
                    if (container) {
                        const streamIframes = container.querySelectorAll('iframe');
                        const streamVideos = container.querySelectorAll('video');
                        
                        for (let iframe of streamIframes) {
                            const iframeStyle = window.getComputedStyle(iframe);
                            if (iframeStyle.display !== 'none' && iframeStyle.visibility !== 'hidden' && iframe.offsetParent) {
                                return false; // Stream iframe is visible, don't refresh
                            }
                        }
                        for (let video of streamVideos) {
                            const videoStyle = window.getComputedStyle(video);
                            if (videoStyle.display !== 'none' && videoStyle.visibility !== 'hidden' && video.offsetParent) {
                                return false; // Stream video is visible, don't refresh
                            }
                        }
                    }
                    
                    // Access Alpine.js component's displayMode to check if it's image-only mode
                    // Find the Alpine component that contains this image
                    let alpineParent = webcamImg.closest('[x-data]');
                    if (alpineParent) {
                        // Try multiple ways to access Alpine data
                        let alpineData = null;
                        if (alpineParent._x_dataStack && alpineParent._x_dataStack[0]) {
                            alpineData = alpineParent._x_dataStack[0];
                        } else if (alpineParent.__x && alpineParent.__x.$data) {
                            alpineData = alpineParent.__x.$data;
                        } else if (window.Alpine && alpineParent._x_dataStack) {
                            alpineData = alpineParent._x_dataStack[0];
                        }
                        
                        if (alpineData && alpineData.displayMode !== undefined) {
                            // Only refresh when displayMode is exactly 'image', not 'stream' or 'both'
                            return alpineData.displayMode === 'image';
                        }
                    }
                    
                    // Fallback: if we can't access Alpine data, be conservative and don't refresh
                    // This prevents unwanted refreshes during livestream
                    return false;
                },

                refreshImage(imgElement) {
                    if (!imgElement) return;

                    const lazySrc = imgElement.dataset.lazySrc;
                    if (lazySrc && (!imgElement.src || imgElement.src.startsWith('data:'))) {
                        this.activateLazyImage(imgElement);
                        return;
                    }

                    const currentSrc = imgElement.src;
                    let newSrc = currentSrc;
                    try {
                        const url = new URL(currentSrc);

                        // Remove existing timestamp parameter if present
                        url.searchParams.delete('t');
                        url.searchParams.delete('_t');
                        url.searchParams.delete('cache');

                        // Add new timestamp to force refresh
                        url.searchParams.set('t', Date.now());
                        newSrc = url.toString();
                    } catch (error) {
                        const base = lazySrc || currentSrc;
                        const separator = base.includes('?') ? '&' : '?';
                        newSrc = `${base}${separator}t=${Date.now()}`;
                    }

                    // Only update if URL actually changed (avoid unnecessary reloads)
                    if (newSrc !== currentSrc) {
                        // Use a small delay to prevent flicker
                        imgElement.style.opacity = '0.8';
                        imgElement.onload = () => {
                            imgElement.style.opacity = '1';
                        };
                        imgElement.onerror = () => {
                            imgElement.style.opacity = '1';
                        };
                        imgElement.src = newSrc;
                    }
                },

                async loadLeafletAssets() {
                    if (window.L) {
                        return window.L;
                    }

                    if (!this._leafletLoadPromise) {
                        this._leafletLoadPromise = new Promise((resolve, reject) => {
                            let resolved = false;

                            const finish = () => {
                                if (resolved) return;
                                if (!window.L) return;
                                resolved = true;
                                resolve(window.L);
                            };

                            const fail = () => {
                                if (resolved) return;
                                resolved = true;
                                reject(new Error('Failed to load Leaflet assets'));
                            };

                            const cssId = 'dashboard-leaflet-css';
                            if (!document.getElementById(cssId)) {
                                const link = document.createElement('link');
                                link.id = cssId;
                                link.rel = 'stylesheet';
                                link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                                link.crossOrigin = '';
                                document.head.appendChild(link);
                            }

                            const scriptId = 'dashboard-leaflet-js';
                            let script = document.getElementById(scriptId);
                            if (!script) {
                                script = document.createElement('script');
                                script.id = scriptId;
                                script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                                script.async = true;
                                script.defer = true;
                                document.head.appendChild(script);
                            }

                            script.addEventListener('load', finish, { once: true });
                            script.addEventListener('error', fail, { once: true });

                            if (window.L) {
                                finish();
                            }
                        }).catch((error) => {
                            this._leafletLoadPromise = null;
                            throw error;
                        });
                    }

                    return this._leafletLoadPromise;
                },

                initRadarWidgetObserver() {
                    if (!cfg.radarRainviewerApiEnabled) return;

                    const radarMapWidget = document.getElementById('radar-map-widget');
                    if (!radarMapWidget || this._radarMapInitialized) return;

                    if (!('IntersectionObserver' in window)) {
                        this.initializeRadarMap();
                        return;
                    }

                    if (this._radarObserver) {
                        this._radarObserver.disconnect();
                    }

                    this._radarObserver = new IntersectionObserver((entries) => {
                        const visible = entries.some((entry) => entry.isIntersecting || entry.intersectionRatio > 0);
                        if (!visible) return;

                        this._radarObserver?.disconnect();
                        this._radarObserver = null;
                        this.initializeRadarMap();
                    }, {
                        rootMargin: '250px 0px',
                        threshold: 0.01,
                    });

                    this._radarObserver.observe(radarMapWidget);
                },

                async initializeRadarMap() {
                    if (this._radarMapInitialized || !cfg.radarRainviewerApiEnabled) return;

                    const radarMapWidget = document.getElementById('radar-map-widget');
                    if (!radarMapWidget) return;

                    let L;
                    try {
                        L = await this.loadLeafletAssets();
                    } catch (error) {
                        console.error('Failed to initialize RainViewer map:', error);
                        return;
                    }

                    const stationLat = Number(cfg.stationLat);
                    const stationLon = Number(cfg.stationLon);
                    if (!Number.isFinite(stationLat) || !Number.isFinite(stationLon)) {
                        return;
                    }

                    const configuredZoom = Number(cfg.rainviewerZoom || 7);
                    const clampedZoom = Math.min(Math.max(configuredZoom || 7, 0), 7);
                    const useProxy = Boolean(cfg.radarUseProxy);

                    this._radarMap = L.map(radarMapWidget, {
                        center: [stationLat, stationLon],
                        zoom: clampedZoom,
                        zoomControl: false,
                        attributionControl: false,
                        maxZoom: 7,
                        minZoom: 0,
                    });

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; OpenStreetMap contributors',
                        minZoom: 0,
                        maxZoom: 7,
                    }).addTo(this._radarMap);

                    const stationIcon = L.divIcon({
                        className: 'station-marker',
                        html: '<div style="width: 12px; height: 12px; background: #10b981; border: 2px solid white; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>',
                        iconSize: [12, 12],
                        iconAnchor: [6, 6],
                    });

                    L.marker([stationLat, stationLon], { icon: stationIcon }).addTo(this._radarMap);

                    this._radarMapInitialized = true;
                    this._radarFrames = [];
                    this._radarCurrentFrameIndex = 0;
                    this._radarHost = '';
                    this._radarLastGenerated = null;
                    this.radarZoomLevel = this._radarMap.getZoom();
                    this._radarZoomListener = () => {
                        if (!this._radarMap) return;
                        this.radarZoomLevel = this._radarMap.getZoom();
                    };
                    this._radarMap.on('zoomend', this._radarZoomListener);

                    await this.loadRadarFrames();
                    this.startRadarAnimation();

                    this._radarRefreshInterval = setInterval(() => {
                        if (document.hidden || !radarMapWidget.offsetParent) return;
                        this.loadRadarFrames();
                    }, 300000);

                    this._radarVisibilityListener = () => {
                        if (document.hidden) {
                            this.stopRadarAnimation();
                            return;
                        }
                        if (this._radarFrames.length > 0) {
                            this.loadRadarFrames().finally(() => this.startRadarAnimation());
                        }
                    };
                    document.addEventListener('visibilitychange', this._radarVisibilityListener);

                    if (useProxy && this._radarHost && !/^https?:\/\//i.test(this._radarHost)) {
                        this._radarHost = this._radarHost.replace(/\/$/, '');
                    }
                },

                async loadRadarFrames() {
                    if (!this._radarMapInitialized) return;

                    const useProxy = Boolean(cfg.radarUseProxy);
                    const includeFutureFrames = Boolean(cfg.radarWidgetFutureFramesEnabled);

                    try {
                        const apiUrl = useProxy ? '/api/radar/frames' : '/api/weather/radar';
                        const response = await fetch(apiUrl, {
                            headers: this.getApiHeaders(),
                        });
                        const data = await response.json();

                        const frames = data?.data?.radar?.past;
                        if (!data?.success || !Array.isArray(frames) || frames.length === 0) {
                            return;
                        }

                        const generated = data?.data?.generated || data?.generated || null;
                        const baseSignature = generated
                            || `${frames.length}:${String(frames[frames.length - 1]?.path || '')}`;

                        const futureFramesPayload = includeFutureFrames
                            ? await this.loadRadarFutureFrames()
                            : { provider: 'none', frames: [] };
                        const futureFrames = Array.isArray(futureFramesPayload?.frames)
                            ? futureFramesPayload.frames
                            : [];

                        const latestRainviewerTs = this.getRadarFrameUnix(frames[frames.length - 1]);
                        const filteredFutureFrames = futureFrames.filter((frame) => {
                            const frameTs = this.getRadarFrameUnix(frame);
                            if (!Number.isFinite(frameTs)) return false;
                            if (!Number.isFinite(latestRainviewerTs)) return true;
                            return frameTs > latestRainviewerTs;
                        });

                        const futureProvider = String(futureFramesPayload?.provider || 'none');
                        const futureSignature = filteredFutureFrames.length > 0
                            ? `|future:${futureProvider}:${filteredFutureFrames.length}:${this.getRadarFrameUnix(filteredFutureFrames[0])}:${this.getRadarFrameUnix(filteredFutureFrames[filteredFutureFrames.length - 1])}`
                            : `|future:${futureProvider}:0`;
                        const frameSignature = `${baseSignature}${futureSignature}`;
                        if (this._radarLastGenerated && frameSignature === this._radarLastGenerated) {
                            return;
                        }
                        this._radarLastGenerated = frameSignature;

                        const taggedRainviewerFrames = frames.map((frame) => ({
                            ...frame,
                            source: 'rainviewer',
                        }));

                        this._radarFrames = filteredFutureFrames.length > 0
                            ? [...taggedRainviewerFrames, ...filteredFutureFrames]
                            : taggedRainviewerFrames;
                        this._radarHost = useProxy
                            ? (data?.data?.host || '/api/radar/tile')
                            : (data?.data?.host || 'https://tilecache.rainviewer.com');

                        this._radarCurrentFrameIndex = 0;
                        this.showRadarFrame(0);
                    } catch (error) {
                        console.error('Failed to load radar data:', error);
                    }
                },

                async loadRadarFutureFrames() {
                    try {
                        const response = await fetch('/api/weather/radar-future-frames', {
                            headers: this.getApiHeaders(),
                        });
                        const data = await response.json();

                        const provider = String(data?.data?.provider || 'none');
                        const frames = data?.data?.frames;
                        if (!response.ok || !data?.success || !Array.isArray(frames)) {
                            const message = data?.message || `HTTP ${response.status}`;
                            console.warn('Future radar frames unavailable:', {
                                provider,
                                message,
                            });
                            return { provider, frames: [] };
                        }

                        const normalizedFrames = frames
                            .map((frame) => {
                                if (!frame || typeof frame !== 'object') return null;

                                const kind = String(frame.kind || 'image_overlay');
                                if (kind === 'image_overlay') {
                                    const imageUrl = String(frame.url || frame.imageUrl || '');
                                    if (!imageUrl) return null;

                                    return {
                                        source: 'future_provider',
                                        provider: String(frame.provider || provider || ''),
                                        kind,
                                        time: frame.time ?? frame.timestamp ?? null,
                                        timestamp: frame.timestamp ?? null,
                                        imageUrl,
                                        proxyUrl: frame.proxy_url || frame.proxyUrl || null,
                                        bounds: Array.isArray(frame.bounds) ? frame.bounds : null,
                                        attribution: frame.attribution || null,
                                        opacity: frame.opacity,
                                    };
                                }

                                if (kind === 'tile_layer') {
                                    const tileUrlTemplate = String(frame.url || frame.tile_url_template || frame.tileUrlTemplate || '');
                                    if (!tileUrlTemplate) return null;

                                    return {
                                        source: 'future_provider',
                                        provider: String(frame.provider || provider || ''),
                                        kind,
                                        time: frame.time ?? frame.timestamp ?? null,
                                        timestamp: frame.timestamp ?? null,
                                        tileUrlTemplate,
                                        attribution: frame.attribution || null,
                                        opacity: frame.opacity,
                                        minZoom: Number(frame.min_zoom ?? frame.minZoom ?? 0),
                                        maxZoom: Number(frame.max_zoom ?? frame.maxZoom ?? 7),
                                    };
                                }

                                return null;
                            })
                            .filter(Boolean)
                            .sort((a, b) => {
                                const aTs = this.getRadarFrameUnix(a) || 0;
                                const bTs = this.getRadarFrameUnix(b) || 0;
                                return aTs - bTs;
                            });

                        return {
                            provider,
                            frames: normalizedFrames,
                        };
                    } catch (error) {
                        console.warn('Failed to load future radar frames for dashboard widget:', error);
                        return { provider: 'none', frames: [] };
                    }
                },

                normalizeRadarBounds(bounds, fallback = [[50.75, 3.2], [53.7, 7.2]]) {
                    if (!Array.isArray(bounds) || bounds.length !== 2) {
                        return fallback;
                    }

                    const sw = bounds[0];
                    const ne = bounds[1];
                    if (!Array.isArray(sw) || !Array.isArray(ne) || sw.length !== 2 || ne.length !== 2) {
                        return fallback;
                    }

                    const swLat = Number(sw[0]);
                    const swLon = Number(sw[1]);
                    const neLat = Number(ne[0]);
                    const neLon = Number(ne[1]);
                    if (![swLat, swLon, neLat, neLon].every(Number.isFinite)) {
                        return fallback;
                    }

                    return [[swLat, swLon], [neLat, neLon]];
                },

                getRadarFrameUnix(frame) {
                    const direct = Number(frame?.time ?? frame?.timestamp ?? null);
                    if (Number.isFinite(direct) && direct > 0) {
                        return direct > 1e12 ? Math.floor(direct / 1000) : direct;
                    }

                    const isoSource = frame?.time ?? frame?.timestamp ?? null;
                    if (typeof isoSource === 'string' && isoSource.length > 0) {
                        const parsedMs = Date.parse(isoSource);
                        if (Number.isFinite(parsedMs) && parsedMs > 0) {
                            return Math.floor(parsedMs / 1000);
                        }
                    }

                    const path = String(frame?.path || '');
                    const match = path.match(/\/(\d+)(?:\/)?$/);
                    if (match) {
                        const parsed = Number(match[1]);
                        if (Number.isFinite(parsed) && parsed > 0) {
                            return parsed > 1e12 ? Math.floor(parsed / 1000) : parsed;
                        }
                    }

                    // RainViewer paths can include extra suffixes; grab the most likely unix timestamp anywhere in path.
                    const unixInPath = path.match(/(?:^|\/)(\d{10,13})(?:\/|$)/);
                    if (!unixInPath) return null;

                    const parsed = Number(unixInPath[1]);
                    if (!Number.isFinite(parsed) || parsed <= 0) return null;
                    return parsed > 1e12 ? Math.floor(parsed / 1000) : parsed;
                },

                formatRadarFrameTimeLabel(frame) {
                    const unixTs = this.getRadarFrameUnix(frame);
                    if (!Number.isFinite(unixTs)) return '';

                    const tz = window.Meteo?.stationTimezone || 'UTC';
                    const date = new Date(unixTs * 1000);
                    const timeLabel = date.toLocaleTimeString(locale, {
                        timeZone: tz,
                        hour: '2-digit',
                        minute: '2-digit',
                    });

                    const nowDate = new Date().toLocaleDateString('en-CA', { timeZone: tz });
                    const frameDate = date.toLocaleDateString('en-CA', { timeZone: tz });
                    if (nowDate !== frameDate) {
                        const shortDate = date.toLocaleDateString(locale, {
                            timeZone: tz,
                            day: '2-digit',
                            month: 'short',
                        });
                        return `${shortDate} ${timeLabel}`;
                    }

                    return timeLabel;
                },

                showRadarFrame(index) {
                    if (!this._radarMap || !this._radarFrames.length || !window.L) {
                        return;
                    }

                    const useProxy = Boolean(cfg.radarUseProxy);
                    const frameIndex = index % this._radarFrames.length;
                    const frame = this._radarFrames[frameIndex];
                    const isFutureProviderFrame = frame?.source === 'future_provider';
                    if (!isFutureProviderFrame && !frame?.path) return;

                    this.radarFrameTimeLabel = this.formatRadarFrameTimeLabel(frame);

                    if (this._radarPendingLayer) {
                        this._radarPendingLayer.off();
                        if (this._radarMap.hasLayer(this._radarPendingLayer)) {
                            this._radarMap.removeLayer(this._radarPendingLayer);
                        }
                        this._radarPendingLayer = null;
                    }

                    if (isFutureProviderFrame && frame?.kind === 'image_overlay' && typeof frame?.imageUrl === 'string') {
                        const previousLayer = this._radarLayer;
                        const bounds = this.normalizeRadarBounds(frame.bounds);
                        const rawImageUrl = (typeof frame.proxyUrl === 'string' && frame.proxyUrl)
                            ? frame.proxyUrl
                            : frame.imageUrl;
                        const isAbsoluteImage = /^https?:\/\//i.test(rawImageUrl);
                        const imageUrl = (!isAbsoluteImage && typeof window.Meteo?.appendApiKey === 'function')
                            ? window.Meteo.appendApiKey(rawImageUrl)
                            : rawImageUrl;
                        const layerOpacity = Number.isFinite(Number(frame.opacity)) ? Number(frame.opacity) : 0.7;
                        const useCrossOrigin = !isAbsoluteImage || rawImageUrl.startsWith(window.location.origin);
                        const nextLayer = window.L.imageOverlay(imageUrl, bounds, {
                            opacity: layerOpacity,
                            attribution: String(frame.attribution || 'Future radar'),
                            crossOrigin: useCrossOrigin,
                        });

                        if (previousLayer && this._radarMap.hasLayer(previousLayer)) {
                            this._radarMap.removeLayer(previousLayer);
                        }

                        nextLayer.addTo(this._radarMap);
                        this._radarLayer = nextLayer;
                        return;
                    }

                    if (isFutureProviderFrame && frame?.kind === 'tile_layer' && typeof frame?.tileUrlTemplate === 'string') {
                        const previousLayer = this._radarLayer;
                        const layerOpacity = Number.isFinite(Number(frame.opacity)) ? Number(frame.opacity) : 0.7;
                        const minZoom = Number.isFinite(Number(frame.minZoom)) ? Number(frame.minZoom) : 0;
                        const maxZoom = Number.isFinite(Number(frame.maxZoom)) ? Number(frame.maxZoom) : 7;
                        const nextLayer = window.L.tileLayer(frame.tileUrlTemplate, {
                            opacity: layerOpacity,
                            attribution: String(frame.attribution || 'Future radar'),
                            minZoom,
                            maxZoom,
                            tms: false,
                            crossOrigin: true,
                        });

                        if (previousLayer && this._radarMap.hasLayer(previousLayer)) {
                            this._radarMap.removeLayer(previousLayer);
                        }

                        nextLayer.addTo(this._radarMap);
                        this._radarLayer = nextLayer;
                        return;
                    }

                    const rawTileUrl = `${this._radarHost}${frame.path}/512/{z}/{x}/{y}/1/1_0.png`;
                    const isAbsolute = /^https?:\/\//i.test(rawTileUrl);
                    const tileUrl = (useProxy && !isAbsolute && typeof window.Meteo?.appendApiKey === 'function')
                        ? window.Meteo.appendApiKey(rawTileUrl)
                        : rawTileUrl;

                    const previousLayer = this._radarLayer;
                    const nextLayer = window.L.tileLayer(tileUrl, {
                        opacity: 0.7,
                        attribution: 'RainViewer',
                        minZoom: 0,
                        maxZoom: 7,
                        tms: false,
                        crossOrigin: true,
                    });

                    this._radarPendingLayer = nextLayer;
                    const swapToken = ++this._radarSwapToken;
                    let tileLoadCount = 0;
                    let tileErrorCount = 0;
                    let completed = false;

                    const finalizeSwap = (preferNewLayer) => {
                        if (completed || swapToken !== this._radarSwapToken) {
                            return;
                        }
                        completed = true;
                        clearTimeout(timeoutId);

                        nextLayer.off('tileload', onTileLoad);
                        nextLayer.off('tileerror', onTileError);
                        nextLayer.off('load', onLayerLoad);

                        const canUseNewLayer = preferNewLayer || !previousLayer;

                        if (canUseNewLayer) {
                            if (previousLayer && this._radarMap.hasLayer(previousLayer)) {
                                this._radarMap.removeLayer(previousLayer);
                            }
                            this._radarLayer = nextLayer;
                        } else {
                            if (this._radarMap.hasLayer(nextLayer)) {
                                this._radarMap.removeLayer(nextLayer);
                            }
                            this._radarLayer = previousLayer;
                        }

                        if (this._radarPendingLayer === nextLayer) {
                            this._radarPendingLayer = null;
                        }
                    };

                    const onTileLoad = () => {
                        tileLoadCount += 1;
                    };

                    const onTileError = () => {
                        tileErrorCount += 1;
                    };

                    const onLayerLoad = () => {
                        finalizeSwap(tileLoadCount > 0);
                    };

                    nextLayer.on('tileload', onTileLoad);
                    nextLayer.on('tileerror', onTileError);
                    nextLayer.on('load', onLayerLoad);

                    const timeoutId = setTimeout(() => {
                        // Prefer new layer if any tile loaded; otherwise keep previous so radar stays visible at new zoom
                        const looksUsable = tileLoadCount > 0 && tileLoadCount >= tileErrorCount;
                        finalizeSwap(looksUsable);
                    }, 6500);

                    nextLayer.addTo(this._radarMap);
                },

                startRadarAnimation() {
                    this.stopRadarAnimation();
                    this.showRadarFrame(this._radarCurrentFrameIndex);

                    if (document.body.classList.contains('theme-flat') || this._radarFrames.length <= 1) {
                        return;
                    }

                    const frameDelay = Math.max(250, Number(cfg.rainviewerFrameDelay || 1000));
                    this._radarAnimationInterval = setInterval(() => {
                        if (!this._radarFrames.length) return;
                        this._radarCurrentFrameIndex = (this._radarCurrentFrameIndex + 1) % this._radarFrames.length;
                        this.showRadarFrame(this._radarCurrentFrameIndex);
                    }, frameDelay);
                },

                stopRadarAnimation() {
                    if (this._radarAnimationInterval) {
                        clearInterval(this._radarAnimationInterval);
                        this._radarAnimationInterval = null;
                    }
                },

                radarCanZoomIn() {
                    if (!this._radarMap) return false;
                    return this._radarMap.getZoom() < this._radarMap.getMaxZoom();
                },

                radarCanZoomOut() {
                    if (!this._radarMap) return false;
                    return this._radarMap.getZoom() > this._radarMap.getMinZoom();
                },

                radarZoomIn() {
                    if (!this._radarMap || !this.radarCanZoomIn()) return;
                    this._radarMap.zoomIn();
                },

                radarZoomOut() {
                    if (!this._radarMap || !this.radarCanZoomOut()) return;
                    this._radarMap.zoomOut();
                },

                cleanupRadarMap() {
                    if (this._radarObserver) {
                        this._radarObserver.disconnect();
                        this._radarObserver = null;
                    }

                    this.stopRadarAnimation();

                    if (this._radarRefreshInterval) {
                        clearInterval(this._radarRefreshInterval);
                        this._radarRefreshInterval = null;
                    }

                    if (this._radarVisibilityListener) {
                        document.removeEventListener('visibilitychange', this._radarVisibilityListener);
                        this._radarVisibilityListener = null;
                    }

                    if (this._radarMap && this._radarZoomListener) {
                        this._radarMap.off('zoomend', this._radarZoomListener);
                        this._radarZoomListener = null;
                    }

                    if (this._radarMap) {
                        this._radarMap.remove();
                        this._radarMap = null;
                    }

                    if (this._radarPendingLayer) {
                        this._radarPendingLayer.off();
                        this._radarPendingLayer = null;
                    }

                    this._radarSwapToken += 1;
                    this._radarLayer = null;
                    this._radarFrames = [];
                    this._radarCurrentFrameIndex = 0;
                    this._radarHost = '';
                    this._radarLastGenerated = null;
                    this._radarMapInitialized = false;
                    this.radarFrameTimeLabel = '';
                    this.radarZoomLevel = null;
                },

                initVisibilityTracking() {
                    console.log('Visibility tracking initialized. Current state:', !document.hidden ? 'visible' : 'hidden');

                    if (this._visibilityListener) {
                        document.removeEventListener('visibilitychange', this._visibilityListener);
                    }

                    this._visibilityListener = () => {
                        this.isPageVisible = !document.hidden;
                        console.log('Visibility changed. Page is now:', this.isPageVisible ? 'visible' : 'hidden');

                        if (this.isPageVisible && this.pausedAt) {
                            const pausedDuration = Date.now() - this.pausedAt;
                            console.log(`✅ Page visible again after ${Math.round(pausedDuration/1000)}s`);

                            // Refresh quickly after tab restore so stale/offline state clears promptly.
                            if (pausedDuration > 15000 || !this.lastUpdateTime || this.dataIsStale) {
                                console.log('⏰ Fetching immediately after visibility resume');
                                this.fetchData({ force: true, silent: true });
                            }
                            this.pausedAt = null;
                        } else if (!this.isPageVisible) {
                            this.pausedAt = Date.now();
                            console.log('⏸️ Page hidden - polling will be skipped');
                        }
                    };

                    document.addEventListener('visibilitychange', this._visibilityListener);
                },

                initConnectivityTracking() {
                    this.isBrowserOnline = navigator.onLine !== false;

                    if (this._onlineListener) {
                        window.removeEventListener('online', this._onlineListener);
                    }
                    if (this._offlineListener) {
                        window.removeEventListener('offline', this._offlineListener);
                    }

                    this._onlineListener = () => {
                        this.isBrowserOnline = true;
                        console.log('✅ Network back online');
                        if (this.isPageVisible) {
                            this.fetchData({ force: true, silent: true });
                        }
                    };

                    this._offlineListener = () => {
                        this.isBrowserOnline = false;
                        console.warn('⚠️ Network offline');
                    };

                    window.addEventListener('online', this._onlineListener);
                    window.addEventListener('offline', this._offlineListener);
                },
                
                formatHour(timeStr) {
                    if (!timeStr) return '--:--';
                    const tz = window.Meteo?.stationTimezone || 'UTC';
                    const date = new Date(timeStr);
                    return date.toLocaleTimeString(locale, { timeZone: tz, hour: '2-digit', minute: '2-digit' });
                },
                
                nextRainInfo() {
                    const threshold = 0.1; // mm — skip trace amounts
                    for (let i = 0; i < Math.min(this.hourlyForecast.length, 24); i++) {
                        const h = this.hourlyForecast[i];
                        if ((h.precipitation_1h || 0) >= threshold) {
                            return { time: this.formatHour(h.time), amount: h.precipitation_1h, isNow: i === 0 };
                        }
                    }
                    return null;
                },

                nextRainLabel() {
                    const info = this.nextRainInfo();
                    if (!info) return t('Dry 24h');
                    return info.isNow ? t('Now') : info.time;
                },

                alertBannerText() {
                    if (!this.alerts?.length) return '';
                    const count  = this.alerts.length;
                    const types  = [...new Set(
                        this.alerts.slice(0, 3)
                            .map(a => a.warning_type_label || a.warning_type)
                            .filter(Boolean)
                    )];
                    const label  = t(count === 1 ? 'active warning' : 'active warnings');
                    return count + ' ' + label + (types.length ? ': ' + types.join(', ') : '');
                },

                // ── Weather alert toasts ──────────────────────────────────────────────

                _alertIcon(type) {
                    return { lightning:'⚡', frost:'🌡️', uv:'☀️', 'air-quality':'💨',
                             pollen:'🌿', waves:'🌊', fire:'🔥', flood:'🏞️',
                             slippery:'🌡️', 'heavy-rain':'🌧️', 'extreme-wind':'🌬️',
                             'extreme-heat':'🔥', 'extreme-cold':'🥶' }[type] || '🚨';
                },

                addWeatherToast(type, title, message, color, link) {
                    if (!this._seenAlertTypes) this._seenAlertTypes = new Set();
                    if (this._seenAlertTypes.has(type)) return;
                    this._seenAlertTypes.add(type);
                    // Cap at 2 visible — drop oldest silently if full
                    if (this.weatherToasts.length >= 2) this.weatherToasts.shift();
                    const id = Date.now() + Math.random();
                    this.weatherToasts.push({ id, icon: this._alertIcon(type), title, message, color, link });
                    setTimeout(() => this.dismissWeatherToast(id), 12000);
                },

                dismissWeatherToast(id) {
                    this.weatherToasts = this.weatherToasts.filter(t => t.id !== id);
                },

                checkWeatherToasts() {
                    if (!this._seenAlertTypes) this._seenAlertTypes = new Set();

                    // Part A — backend alerts via this.alerts (severity >= 3 only)
                    const currentTypes = new Set((this.alerts || []).map(a => a.warning_type));
                    const frontendTypes = new Set(['slippery','heavy-rain','extreme-wind','extreme-heat','extreme-cold']);
                    for (const type of [...this._seenAlertTypes]) {
                        if (!frontendTypes.has(type) && !currentTypes.has(type)) {
                            this._seenAlertTypes.delete(type);
                        }
                    }
                    for (const alert of (this.alerts || [])) {
                        if ((alert.severity ?? 0) >= 3) {
                            this.addWeatherToast(
                                alert.warning_type,
                                alert.title,
                                (alert.description || '').substring(0, 80),
                                alert.severity_color || '#F19E39',
                                alert.link || '/alerts'
                            );
                        }
                    }

                    // Part B — real-time conditions using existing FX booleans
                    // Visual effects (rain, snow, wind, fog, lightning FX) are untouched —
                    // toasts observe the same computed booleans at stricter thresholds only.
                    const c = this.current;
                    if (!c) return;

                    const checkCurrent = (type, condition, title, message) => {
                        if (condition) {
                            this.addWeatherToast(type, title, message, '#F19E39', '/alerts');
                        } else {
                            this._seenAlertTypes.delete(type); // clears when condition ends so it can re-trigger
                        }
                    };

                    // isSnowing = temp < 2°C AND precipitation — already computed by snow FX system
                    checkCurrent('slippery',     this.isSnowing,                              t('Frost risk'),       t('Roads may be slippery'));
                    // isRaining = rain_rate > 0 — FX triggers on any rain; toast only at heavy (≥ 10 mm/h)
                    checkCurrent('heavy-rain',   this.isRaining && (c.rain_rate ?? 0) >= 10,  t('Heavy rain'),       this.formatRain(c.rain_rate) + '/h');
                    // isWindy = gust > 40 km/h — FX triggers at moderate wind; toast only at storm-force (≥ 89 km/h)
                    checkCurrent('extreme-wind', this.isWindy   && (c.wind_gust ?? 0) >= 89,  t('Storm-force wind'), this.formatWind(c.wind_gust));
                    // No FX boolean for temperature extremes — check raw value directly
                    checkCurrent('extreme-heat', (c.temperature ?? 0) >= 35,                  t('Extreme heat'),     this.formatTemp(c.temperature));
                    checkCurrent('extreme-cold', (c.temperature ?? 0) <= -10,                 t('Extreme cold'),     this.formatTemp(c.temperature));
                },

                tempAdvisory() {
                    const today = this.forecast?.[0];
                    if (!today) return null;
                    const high = today.temp_high ?? null;
                    const low  = today.temp_low  ?? null;
                    // Heat warnings (daytime high — priority)
                    if (high !== null) {
                        if (high >= 35) return { label: t('Extreme heat'), color: 'text-red-500' };
                        if (high >= 30) return { label: t('Tropical'),     color: 'text-orange-500' };
                        if (high >= 25) return { label: t('Warm'),         color: 'text-amber-400' };
                        if (high >= 20) return { label: t('Pleasant'),     color: 'text-green-400' };
                        if (high >= 15) return { label: t('Mild'),         color: 'text-teal-400' };
                        if (high >= 10) return { label: t('Chilly'),       color: 'text-sky-400' };
                        if (high >= 5)  return { label: t('Cold'),         color: 'text-blue-400' };
                    }
                    // Frost risk (when high < 5 or no high data)
                    if (low !== null) {
                        if (low <= -5) return { label: t('Hard frost'), color: 'text-indigo-400' };
                        if (low <=  0) return { label: t('Frost'),      color: 'text-blue-300' };
                        if (low <=  2) return { label: t('Frost risk'), color: 'text-cyan-400' };
                        return { label: t('Cold'), color: 'text-blue-400' };
                    }
                    return null;
                },

                // #4 — Compared to yesterday: compares today's high vs yesterday's high
                tempVsYesterday() {
                    const yHigh = window.Meteo?.yesterdayHigh ?? null;
                    if (yHigh == null || this.todayHigh == null) return null;
                    let diffC = this.todayHigh - yHigh;
                    let diff  = this.units === 'imperial'
                        ? Math.round(diffC * 9 / 5)
                        : Math.round(diffC);
                    if (Math.abs(diff) < 1) return { label: t('Similar'), color: 'text-gray-400' };
                    const sign  = diff > 0 ? '+' : '';
                    const color = diff >= 3 ? 'text-orange-400' : diff <= -3 ? 'text-blue-400' : 'text-gray-300';
                    return { label: sign + diff + this.tempUnit(), color };
                },

                // #7 — Best time to go outside: least-rainy non-storm hour in next 12h.
                _bestOutdoor() {
                    try {
                        const hours = this.hourlyForecast?.slice(0, 12) ?? [];
                        if (!hours.length) return null;
                        let best = null;
                        let bestRain = Infinity;
                        for (let i = 0; i < hours.length; i++) {
                            const h = hours[i];
                            if ((h.wind_speed || 0) >= 70) continue;
                            const rain = h.precipitation_1h || 0;
                            if (rain < bestRain) {
                                bestRain = rain;
                                best = { time: this.formatHour(h.time), isNow: i === 0, dry: rain < 0.3 };
                            }
                        }
                        return best && bestRain < 2.0 ? best : null;
                    } catch (e) { return null; }
                },
                bestOutdoorLabel() {
                    const r = this._bestOutdoor();
                    if (!r) return '—';
                    return r.isNow ? t('Now') : r.time;
                },
                bestOutdoorColor() {
                    const r = this._bestOutdoor();
                    if (!r) return 'text-gray-500';
                    return r.dry ? 'text-emerald-400' : 'text-amber-400';
                },

                formatShortDay(dateStr) {
                    if (!dateStr) return '';
                    const tz = window.Meteo?.stationTimezone || 'UTC';
                    const date = new Date(dateStr);
                    const todayStr = new Date().toLocaleDateString('en-CA', { timeZone: tz });
                    const dateStrNorm = date.toLocaleDateString('en-CA', { timeZone: tz });
                    if (dateStrNorm === todayStr) return t('Today');
                    return date.toLocaleDateString(locale, { timeZone: tz, weekday: 'short' });
                },

                translateKey(key) {
                    if (!key) return '';
                    return t(key) || key;
                },
                
                // Temperature chart helpers
                formatHourFromTs(ts) {
                    if (!ts) return '--:--';
                    const tz = window.Meteo?.stationTimezone || 'UTC';
                    const date = new Date(ts);
                    return date.toLocaleTimeString(locale, { timeZone: tz, hour: '2-digit', minute: '2-digit' });
                },

                getTempChartStartTs() {
                    if (this.tempChartShowObserved) {
                        return this.nowTs - (12 * 60 * 60 * 1000);
                    }
                    const t0 = this.hourlyForecast?.[0]?.time;
                    if (!t0) return null;
                    const ts = new Date(t0).getTime();
                    return Number.isFinite(ts) ? ts : null;
                },

                getTempChartEndTs() {
                    if (this.tempChartShowObserved) {
                        return this.nowTs + (12 * 60 * 60 * 1000);
                    }
                    const t1 = this.hourlyForecast?.[23]?.time;
                    if (!t1) return null;
                    const ts = new Date(t1).getTime();
                    return Number.isFinite(ts) ? ts : null;
                },

                getTempChartX(ts) {
                    const start = this.getTempChartStartTs();
                    const end = this.getTempChartEndTs();
                    if (!Number.isFinite(start) || !Number.isFinite(end) || end <= start) return null;
                    const clamped = Math.min(end, Math.max(start, ts));
                    return ((clamped - start) / (end - start)) * 400;
                },

                getTempChartNowX() {
                    const start = this.getTempChartStartTs();
                    const end = this.getTempChartEndTs();
                    if (!Number.isFinite(start) || !Number.isFinite(end) || end <= start) return null;
                    if (this.nowTs < start || this.nowTs > end) return null;
                    return ((this.nowTs - start) / (end - start)) * 400;
                },

                getTempChartForecastPoints() {
                    if (!this.hourlyForecast?.length) return [];
                    const start = this.getTempChartStartTs();
                    const end = this.getTempChartEndTs();
                    if (!Number.isFinite(start) || !Number.isFinite(end)) return [];

                    const points = this.hourlyForecast
                        .map(h => ({
                            ts: new Date(h.time).getTime(),
                            temp: h.temperature,
                        }))
                        .filter(p => Number.isFinite(p.ts) && p.temp !== null && p.temp !== undefined)
                        .map(p => ({ ts: p.ts, temp: Number(p.temp) }))
                        .filter(p => Number.isFinite(p.temp));

                    if (!this.tempChartShowObserved) {
                        return points.slice(0, 24);
                    }

                    // Blend mode: show forecast from "now" into the future (up to +12h).
                    const future = points.filter(p => p.ts >= this.nowTs && p.ts <= end);
                    const baseTemp = this.current?.temperature;
                    if (baseTemp !== null && baseTemp !== undefined && Number.isFinite(Number(baseTemp))) {
                        future.unshift({ ts: this.nowTs, temp: Number(baseTemp) });
                    }
                    // Ensure ascending order and de-dupe on timestamp.
                    const seen = new Set();
                    return future
                        .sort((a, b) => a.ts - b.ts)
                        .filter(p => (seen.has(p.ts) ? false : (seen.add(p.ts), true)));
                },

                getTempChartObservedPoints() {
                    if (!this.tempChartShowObserved || !this.observedTempHistory?.length) return [];
                    const start = this.getTempChartStartTs();
                    const end = this.getTempChartEndTs();
                    if (!Number.isFinite(start) || !Number.isFinite(end)) return [];

                    const points = this.observedTempHistory
                        .map(p => ({ ts: new Date(p.time).getTime(), temp: p.value }))
                        .filter(p => Number.isFinite(p.ts) && p.temp !== null && p.temp !== undefined)
                        .map(p => ({ ts: p.ts, temp: Number(p.temp) }))
                        .filter(p => Number.isFinite(p.temp))
                        .filter(p => p.ts >= start && p.ts <= this.nowTs);

                    const baseTemp = this.current?.temperature;
                    if (baseTemp !== null && baseTemp !== undefined && Number.isFinite(Number(baseTemp))) {
                        points.push({ ts: this.nowTs, temp: Number(baseTemp) });
                    }

                    // Ensure ascending order and de-dupe on timestamp.
                    const seen = new Set();
                    return points
                        .sort((a, b) => a.ts - b.ts)
                        .filter(p => (seen.has(p.ts) ? false : (seen.add(p.ts), true)));
                },

                getTempChartTemps() {
                    const temps = [];
                    this.getTempChartForecastPoints().forEach(p => temps.push(p.temp));
                    this.getTempChartObservedPoints().forEach(p => temps.push(p.temp));
                    return temps.filter(t => Number.isFinite(t));
                },

                getHourlyMin() {
                    const temps = this.getTempChartTemps();
                    if (!temps.length) return 0;
                    return Math.floor(Math.min(...temps));
                },
                
                getHourlyMax() {
                    const temps = this.getTempChartTemps();
                    if (!temps.length) return 10;
                    return Math.ceil(Math.max(...temps));
                },
                
                getWeeklyMin() {
                    if (!this.forecast.length) return 0;
                    return Math.floor(Math.min(...this.forecast.slice(0, 7).map(d => d.temp_low)));
                },
                
                getWeeklyMax() {
                    if (!this.forecast.length) return 10;
                    return Math.ceil(Math.max(...this.forecast.slice(0, 7).map(d => d.temp_high)));
                },
                
                getTempY(temp, min, max) {
                    const range = max - min || 1;
                    const normalized = (temp - min) / range;
                    return 90 - (normalized * 80); // 10-90 range for padding
                },
                
                getHourlyTempPath(filled = false) {
                    const data = this.getTempChartForecastPoints();
                    if (!data.length) return '';
                    const min = this.getHourlyMin();
                    const max = this.getHourlyMax();
                    
                    let path = '';
                    let firstX = null;
                    let lastX = null;
                    data.forEach((p, i) => {
                        const x = this.getTempChartX(p.ts);
                        if (!Number.isFinite(x)) return;
                        const y = this.getTempY(p.temp, min, max);
                        firstX = firstX ?? x;
                        lastX = x;
                        path += (path === '' ? 'M' : 'L') + x + ',' + y + ' ';
                    });
                    
                    if (filled && firstX !== null && lastX !== null) {
                        path += 'L' + lastX + ',100 L' + firstX + ',100 Z';
                    }
                    return path;
                },

                getObservedTempPath() {
                    const data = this.getTempChartObservedPoints();
                    if (!data.length) return '';
                    const min = this.getHourlyMin();
                    const max = this.getHourlyMax();

                    let path = '';
                    data.forEach((p) => {
                        const x = this.getTempChartX(p.ts);
                        if (!Number.isFinite(x)) return;
                        const y = this.getTempY(p.temp, min, max);
                        path += (path === '' ? 'M' : 'L') + x + ',' + y + ' ';
                    });
                    return path;
                },

                getBlendedTempAxisLabels() {
                    // Past 12h .. now .. next 12h
                    const base = this.nowTs;
                    const h = (n) => base + (n * 60 * 60 * 1000);
                    return [
                        this.formatHourFromTs(h(-12)),
                        this.formatHourFromTs(h(-6)),
                        this.formatHourFromTs(h(0)),
                        this.formatHourFromTs(h(6)),
                        this.formatHourFromTs(h(12)),
                    ];
                },
                
                getWeeklyHighPath() {
                    if (!this.forecast.length) return '';
                    const data = this.forecast.slice(0, 7);
                    const min = this.getWeeklyMin();
                    const max = this.getWeeklyMax();
                    const step = 400 / Math.max(data.length - 1, 1);
                    
                    let path = '';
                    data.forEach((d, i) => {
                        const x = i * step;
                        const y = this.getTempY(d.temp_high, min, max);
                        path += (i === 0 ? 'M' : 'L') + x + ',' + y + ' ';
                    });
                    return path;
                },
                
                getWeeklyLowPath() {
                    if (!this.forecast.length) return '';
                    const data = this.forecast.slice(0, 7);
                    const min = this.getWeeklyMin();
                    const max = this.getWeeklyMax();
                    const step = 400 / Math.max(data.length - 1, 1);
                    
                    let path = '';
                    data.forEach((d, i) => {
                        const x = i * step;
                        const y = this.getTempY(d.temp_low, min, max);
                        path += (i === 0 ? 'M' : 'L') + x + ',' + y + ' ';
                    });
                    return path;
                },
                
                getWeeklyFillPath() {
                    if (!this.forecast.length) return '';
                    const data = this.forecast.slice(0, 7);
                    const min = this.getWeeklyMin();
                    const max = this.getWeeklyMax();
                    const step = 400 / Math.max(data.length - 1, 1);
                    
                    let path = '';
                    // High temps forward
                    data.forEach((d, i) => {
                        const x = i * step;
                        const y = this.getTempY(d.temp_high, min, max);
                        path += (i === 0 ? 'M' : 'L') + x + ',' + y + ' ';
                    });
                    // Low temps backward
                    for (let i = data.length - 1; i >= 0; i--) {
                        const x = i * step;
                        const y = this.getTempY(data[i].temp_low, min, max);
                        path += 'L' + x + ',' + y + ' ';
                    }
                    path += 'Z';
                    return path;
                },

	                updateClock() {
	                    const now = new Date();
	                    const tz = window.Meteo?.stationTimezone || 'UTC';
	                    const opts = { timeZone: tz };
	                    this.currentTime = now.toLocaleTimeString(locale, { ...opts, hour: '2-digit', minute: '2-digit', second: '2-digit' });
	                    this.currentDate = now.toLocaleDateString(locale, { ...opts, weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
	                    // Cache timezone label (expensive) – it doesn't change while the page is open.
	                    if (!this.currentTimeZoneLabel) {
	                        const parts = new Intl.DateTimeFormat(locale, { timeZone: tz, timeZoneName: 'short' }).formatToParts(now);
	                        const tzPart = parts.find(p => p.type === 'timeZoneName');
	                        this.currentTimeZoneLabel = tzPart ? tzPart.value : '';
	                    }
	                },

                detectChanges(newData) {
                    const changes = {
                        current: false,
                        forecast: false,
                        airQuality: false,
                        astronomy: false,
                        changedFields: []
                    };

                    // Check current weather.
                    // Prefer recorded_at changes, but also handle data sources that don't update recorded_at reliably.
                    const hasCurrent = !!newData.current;
                    const recordedAtChanged = hasCurrent && (newData.current.recorded_at !== this.previousData.currentRecordedAt);
                    const valueChanged = hasCurrent && !!this.current && (
                        newData.current.temperature !== this.current.temperature ||
                        newData.current.humidity !== this.current.humidity ||
                        newData.current.wind_speed !== this.current.wind_speed ||
                        newData.current.pressure !== this.current.pressure ||
                        newData.current.pressure_trend_key !== this.current.pressure_trend_key ||
                        newData.current.pressure_trend !== this.current.pressure_trend
                    );

                    if (recordedAtChanged || valueChanged || (hasCurrent && !this.current)) {
                        changes.current = true;

                        // Detect which specific fields changed
                        if (hasCurrent && this.current) {
                            if (newData.current.temperature !== this.current.temperature) changes.changedFields.push('temperature');
                            if (newData.current.humidity !== this.current.humidity) changes.changedFields.push('humidity');
                            if (newData.current.wind_speed !== this.current.wind_speed) changes.changedFields.push('wind_speed');
                            if (newData.current.pressure !== this.current.pressure) changes.changedFields.push('pressure');
                        }

                        // Only track recorded_at when present
                        if (hasCurrent && newData.current.recorded_at) {
                            this.previousData.currentRecordedAt = newData.current.recorded_at;
                        }
                    }

                    // Check forecast changes
                    // Include a small slice of hourly data so the temp chart updates without a separate
                    // request (and without triggering external API calls during HTTP requests).
                    const forecastHash = JSON.stringify({
                        d0: newData.forecast?.[0] ?? null,
                        h0: newData.hourlyForecast?.[0] ?? null,
                        h6: newData.hourlyForecast?.[6] ?? null,
                    });
                    if (forecastHash !== this.previousData.forecastHash) {
                        changes.forecast = true;
                        this.previousData.forecastHash = forecastHash;
                    }

                    // Check air quality changes
                    const airQualityHash = JSON.stringify(newData.air_quality?.aqi);
                    if (airQualityHash !== this.previousData.airQualityHash) {
                        changes.airQuality = true;
                        this.previousData.airQualityHash = airQualityHash;
                    }

                    // Check astronomy changes
                    const astronomyHash = JSON.stringify({
                        sun: newData.sun?.sunrise,
                        moon: newData.moon?.phase
                    });
                    if (astronomyHash !== this.previousData.astronomyHash) {
                        changes.astronomy = true;
                        this.previousData.astronomyHash = astronomyHash;
                    }

                    return changes;
                },

                updateFieldWithTransition(fieldId) {
                    const element = document.querySelector(`[data-field="${fieldId}"]`);
                    if (!element) return;

                    // Add highlight class
                    element.classList.add('field-updated');

                    // Remove after animation completes
                    setTimeout(() => {
                        element.classList.remove('field-updated');
                    }, 2000);
                },

                // Helper: Check if data is stale
                isDataStale() {
                    if (!this.lastDataTime) return false;
                    if (Date.now() < this.staleBannerGraceUntil) return false;
                    return (Date.now() - this.lastDataTime) > this.dataStaleThreshold;
                },

                // Helper: Format last update time
                getLastUpdateText() {
                    if (!this.lastUpdateTime) return t('Not updated yet');

                    const seconds = Math.floor((Date.now() - this.lastUpdateTime) / 1000);
                    if (seconds < 60) return `${seconds} ${t('seconds ago')}`;
                    const minutes = Math.floor(seconds / 60);
                    if (minutes < 60) return `${minutes} ${t('minutes ago')}`;
                    const hours = Math.floor(minutes / 60);
                    return `${hours} ${t('hours ago')}`;
                },

                getApiHeaders(extraHeaders = {}) {
                    if (typeof window.Meteo?.apiHeaders === 'function') {
                        return window.Meteo.apiHeaders(extraHeaders);
                    }
                    return Object.assign({ 'Accept': 'application/json' }, extraHeaders || {});
                },

                // Prefer backend-provided timestamps so stale detection follows actual data freshness.
                resolveLastUpdateTimestamp(payload) {
                    const candidates = [
                        payload?.current?.recorded_at,
                        payload?.last_update,
                        payload?.health_status?.sensor?.last_update,
                    ];

                    for (const candidate of candidates) {
                        if (!candidate) continue;
                        const parsed = Date.parse(candidate);
                        if (Number.isFinite(parsed)) {
                            return parsed;
                        }
                    }

                    return null;
                },

                getExtraTempLabel(key) {
                    const label = this.extraSensorLabels?.temps?.[key];
                    if (label && String(label).trim()) return label;
                    const id = String(key).replace('temp_', '');
                    return `${t('Sensor')} ${id}`;
                },

                getSoilLabel(key) {
                    const label = this.extraSensorLabels?.soil?.[key] || this.extraSensorLabels?.soil?.[`soil_${key}`];
                    if (label && String(label).trim()) return label;
                    const id = String(key).replace('soil_', '');
                    return `${t('Soil')} ${id}`;
                },

                getPm25Label(key) {
                    const rawKey = String(key);
                    const match = rawKey.match(/\d+/);
                    const id = match ? match[0] : '';
                    const label = this.extraSensorLabels?.pm25?.[rawKey]
                        || (id ? this.extraSensorLabels?.pm25?.[`pm25_${id}`] : null)
                        || (id ? this.extraSensorLabels?.pm25?.[`ch${id}`] : null);
                    if (label && String(label).trim()) return label;
                    if (!id) return t('Sensor');
                    return `${t('Sensor')} ${id}`;
                },

                // Helper: Format timestamp from health status (shows actual data source update time)
                getHealthTimestamp(source) {
                    // For "live" sensor cards, prefer the actual latest reading timestamp.
                    // This updates whenever real station data changes, independent of health-check cadence.
                    if (source === 'sensor' && this.lastDataTime) {
                        try {
                            const date = new Date(this.lastDataTime);
                            return date.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });
                        } catch (e) {
                            // Fall back to health status timestamp below.
                        }
                    }

                    const health = this.healthStatus[source];
                    if (!health?.last_update) return null;

                    try {
                        const date = new Date(health.last_update);
                        return date.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });
                    } catch (e) {
                        return null;
                    }
                },

                async fetchObservedTempHistory() {
                    try {
                        const res = await fetch('/api/weather/history?field=temperature&period=24h', {
                            headers: this.getApiHeaders(),
                        });
                        const payload = await res.json();
                        if (!payload?.success || !Array.isArray(payload?.data)) {
                            return;
                        }

                        // Downsample so the SVG stays light even with high-frequency stations.
                        const points = payload.data
                            .filter(p => p && p.time && p.value !== null && p.value !== undefined)
                            .map(p => ({ time: p.time, value: Number(p.value) }))
                            .filter(p => Number.isFinite(p.value));

                        const maxPoints = 240;
                        if (points.length <= maxPoints) {
                            this.observedTempHistory = points;
                            return;
                        }

                        const step = Math.ceil(points.length / maxPoints);
                        const sampled = [];
                        for (let i = 0; i < points.length; i += step) {
                            sampled.push(points[i]);
                        }
                        this.observedTempHistory = sampled;
                    } catch (e) {
                        console.error('Failed to fetch observed temperature history:', e);
                    }
                },

                applyPayload(data, options = {}) {
                    if (!data?.success) {
                        return;
                    }

                    const initial = options?.initial === true;

                    // Layout/config can change independently from weather readings.
                    if (data.enabled_widgets && Array.isArray(data.enabled_widgets) && data.enabled_widgets.length > 0) {
                        if (JSON.stringify(data.enabled_widgets) !== JSON.stringify(this.enabledWidgets)) {
                            this.enabledWidgets = data.enabled_widgets;
                        }
                    }
                    if (data.grid_cols && data.grid_cols !== this.gridCols) {
                        this.gridCols = data.grid_cols;
                    }
                    if (data.widget_order && (Array.isArray(data.widget_order) || typeof data.widget_order === 'object')) {
                        const hasOrder = Array.isArray(data.widget_order)
                            ? data.widget_order.length > 0
                            : Object.keys(data.widget_order).length > 0;
                        if (hasOrder) {
                            this.widgetOrder = data.widget_order;
                        }
                    }

                    if (Array.isArray(data.pressure_history) && data.pressure_history.length > 0) {
                        this.pressureHistory = data.pressure_history;
                    } else if (!Array.isArray(this.pressureHistory)) {
                        this.pressureHistory = [];
                    }

                    if (Array.isArray(data.wind_history) && data.wind_history.length > 0) {
                        this.windHistory = data.wind_history;
                    } else if (!Array.isArray(this.windHistory)) {
                        this.windHistory = [];
                    }

                    const applyCorePayload = () => {
                        this.current = data.current;
                        if (this.canUseDebugOverrides && !this.current) {
                            this.current = {};
                        }
                        this.forecast = data.forecast || [];
                        this.hourlyForecast = data.hourlyForecast || this.hourlyForecast;
                        this.sun = data.sun;
                        this.moon = data.moon;
                        this.aurora = data.aurora;
                        this.astronomicalEvents = data.astronomical_events || [];
                        this.airQuality = data.air_quality;
                        this.luftdaten = data.luftdaten;
                        if (data.luftdaten_noise) this.luftdatenNoise = data.luftdaten_noise;
                        if (data.pollen) this.pollenData = data.pollen;
                        if (data.tide) this.tideData = data.tide;
                        if (data.water_waves) this.waterWaves = data.water_waves;
                        this.metar = data.metar;
                        this.earthquakes = data.earthquakes || [];
                        this.alerts = data.alerts || [];
                        this.extraSensors = data.extra_sensors;
                        this.extraSensorLabels = data.extra_sensor_labels || { temps: {}, soil: {}, pm25: {}, leak: {}, battery: {} };
                        this.lightning = data.lightning;
                        this.batteryStatus = data.battery_status || {};
                        this.today = data.today;
                        this.station = data.station || this.station;
                        if (this.today) {
                            this.todayHigh = this.today.temp_high;
                            this.todayLow = this.today.temp_low;
                        } else if (this.forecast.length > 0) {
                            this.todayHigh = this.forecast[0].temp_high;
                            this.todayLow = this.forecast[0].temp_low;
                        }
                    };
                    const debugParams = this.canUseDebugOverrides
                        ? (() => {
                            try {
                                return new URLSearchParams(window.location.search);
                            } catch (e) {
                                return null;
                            }
                        })()
                        : null;
                    const hasDebugOverrideParams = !!(
                        debugParams &&
                        (
                            debugParams.has('debug_pressure') ||
                            debugParams.has('debug_temp') ||
                            debugParams.has('debug_wind_speed') ||
                            debugParams.has('debug_wind_dir') ||
                            debugParams.has('debug_rain_rate') ||
                            debugParams.has('debug_rain_daily')
                        )
                    );
                    const applyDebugOverrides = () => {
                        if (!debugParams) {
                            return;
                        }
                        const num = (v) => {
                            if (v === null || v === '') return null;
                            const n = Number(v);
                            return Number.isFinite(n) ? n : null;
                        };

                        const dbgWindSpeed = num(debugParams.get('debug_wind_speed'));
                        const dbgWindDir = num(debugParams.get('debug_wind_dir'));
                        const dbgTemp = num(debugParams.get('debug_temp'));
                        const dbgRainRate = num(debugParams.get('debug_rain_rate'));
                        const dbgRainDaily = num(debugParams.get('debug_rain_daily'));
                        const dbgPressure = num(debugParams.get('debug_pressure'));

                        this.debugPressure = dbgPressure;
                        if (!this.current) {
                            return;
                        }
                        if (dbgWindSpeed !== null) this.current.wind_speed = dbgWindSpeed;
                        if (dbgWindDir !== null) this.current.wind_direction = dbgWindDir;
                        if (dbgTemp !== null) this.current.temperature = dbgTemp;
                        if (dbgRainRate !== null) this.current.rain_rate = dbgRainRate;
                        if (dbgRainDaily !== null) this.current.rain_daily = dbgRainDaily;
                        if (dbgPressure !== null) this.current.pressure = dbgPressure;
                    };

                    if (initial) {
                        applyCorePayload();
                        applyDebugOverrides();
                        this.previousData.currentRecordedAt = data?.current?.recorded_at || null;
                        this.previousData.forecastHash = JSON.stringify({
                            d0: data.forecast?.[0] ?? null,
                            h0: data.hourlyForecast?.[0] ?? null,
                            h6: data.hourlyForecast?.[6] ?? null,
                        });
                        this.previousData.airQualityHash = JSON.stringify(data.air_quality?.aqi);
                        this.previousData.astronomyHash = JSON.stringify({
                            sun: data.sun?.sunrise,
                            moon: data.moon?.phase,
                        });
                    } else {
                        // Detect what changed
                        const changes = this.detectChanges(data);
                        // If admin debug overrides are present, force a UI update even when the API returns the same/null data.
                        if (hasDebugOverrideParams) {
                            changes.current = true;
                        }

                        // Only update UI if something actually changed
                        if (changes.current || changes.forecast || changes.airQuality || changes.astronomy) {
                            console.log('✅ Data changes detected:', changes);

                            // Store changed fields for highlighting
                            this.changedFields = new Set(changes.changedFields);

                            // Update card timestamps
                            const now = Date.now();
                            if (changes.current) {
                                this.cardUpdates.current = now;
                                console.log('📊 Current weather updated at', new Date(now).toLocaleTimeString());
                            }
                            if (changes.forecast) this.cardUpdates.forecast = now;
                            if (changes.airQuality) this.cardUpdates.airQuality = now;
                            if (changes.astronomy) this.cardUpdates.astronomy = now;

                            applyCorePayload();
                            applyDebugOverrides();

                            // Trigger field highlighting for changed fields
                            setTimeout(() => {
                                changes.changedFields.forEach(field => {
                                    this.updateFieldWithTransition(field);
                                });

                                // Clear changed fields after highlighting
                                setTimeout(() => {
                                    this.changedFields.clear();
                                }, 2000);
                            }, 50);
                        } else {
                            console.log('ℹ️ No changes detected - UI not updated (effects/conditions synced)');
                        }
                    }

                    // Update health status from backend (only if changed)
                    if (
                        data.health_status &&
                        typeof data.health_status === 'object' &&
                        !Array.isArray(data.health_status) &&
                        Object.keys(data.health_status).length > 0 &&
                        JSON.stringify(this.healthStatus) !== JSON.stringify(data.health_status)
                    ) {
                        this.healthStatus = data.health_status;
                    }

                    // Load effect settings (from API - needed for test mode)
                    if (data.effects) {
                        this.effectsEnabled = data.effects.enabled !== false;
                        this.effects = {
                            ...this.effects,
                            ...data.effects,
                            test_mode: data.effects.test_mode ?? false,
                            test_effect: data.effects.test_effect || 'rain',
                        };
                    }

                    this.updateWeatherConditions();
                    this.checkWeatherToasts();

                    // Apply grid and saved widget order only when the order actually changed.
                    document.documentElement.style.setProperty('--grid-cols', this.gridCols);
                    const hasWidgetOrder = Array.isArray(this.widgetOrder)
                        ? this.widgetOrder.length > 0
                        : Object.keys(this.widgetOrder || {}).length > 0;
                    const newWidgetOrderKey = hasWidgetOrder ? JSON.stringify(this.widgetOrder) : '';
                    const orderChanged = newWidgetOrderKey !== (this._lastAppliedWidgetOrder ?? '');
                    if (!this.editMode && hasWidgetOrder && orderChanged) {
                        this._lastAppliedWidgetOrder = newWidgetOrderKey;
                        this.$nextTick(() => this.applyWidgetOrder());
                    }

                    const resolvedLastUpdate = this.resolveLastUpdateTimestamp(data);
                    if (resolvedLastUpdate !== null) {
                        this.lastDataTime = resolvedLastUpdate;
                    }
                    // UI "Updated x ago" should represent last successful dashboard sync.
                    this.lastUpdateTime = Date.now();
                    this.lastUpdateText = this.getLastUpdateText();
                    this.dataIsStale = this.isDataStale();

                    this.refreshAdsConsentState();
                    this.scheduleLazyWidgetInitialization();
                },

                async fetchData(options = {}) {
                    const force = options?.force === true;
                    const silent = options?.silent === true;

                    // Skip background polling when page is hidden, unless explicitly forced.
                    if (!force && !this.isPageVisible) {
                        console.log('⏸️ Skipping fetch - page hidden');
                        return;
                    }
                    if (!force && this.isBrowserOnline === false) {
                        console.log('⏸️ Skipping fetch - browser offline');
                        return;
                    }
                    if (this._isFetchingData) {
                        console.log('⏳ Skipping fetch - request already in flight');
                        return;
                    }

                    console.log('🔄 Fetching dashboard data...');
                    this._isFetchingData = true;

                    try {
                        if (!silent) {
                            this.isRefreshing = true;
                        }
                        
                        // Add timeout for Safari - prevents hanging on slow/failed requests
                        const controller = new AbortController();
                        const timeoutId = setTimeout(() => controller.abort(), 15000); // 15 second timeout
                        
                        const lang = (window.Meteo?.jsLocale || 'en-GB').replace('_', '-');
                        const dashboardUrl = '/api/weather/dashboard' + (lang ? '?lang=' + encodeURIComponent(lang) : '');
                        const res = await fetch(dashboardUrl, {
                            headers: this.getApiHeaders(),
                            signal: controller.signal,
                        });
                        clearTimeout(timeoutId);
                        
                        const data = await res.json();

                        if (data.success) {
                            // ── Scroll-position guard ──────────────────────────────
                            // Alpine.js processes reactive updates across multiple microtask
                            // batches. Each batch can cause DOM insertions/removals (x-if, x-for)
                            // that reset the browser's scroll position.  A single-shot restore
                            // after $nextTick/rAF isn't enough because later batches can reset
                            // scroll AGAIN.  Instead, we install a temporary scroll listener
                            // that catches any jump-to-top and immediately corrects it.
                            const savedScrollY = window.scrollY;
                            if (savedScrollY > 30) {
                                const onScroll = () => {
                                    // Only correct large jumps toward the top (the bug).
                                    // Small movements are likely the user scrolling.
                                    if (window.scrollY < savedScrollY - 60) {
                                        window.scrollTo(0, savedScrollY);
                                    }
                                };
                                window.addEventListener('scroll', onScroll, { passive: true });
                                setTimeout(() => {
                                    window.removeEventListener('scroll', onScroll);
                                }, 800);
                            }
                            this.applyPayload(data, { silent });
                        } else {
                            console.warn('⚠️ Dashboard response did not contain success=true');
                        }
                    } catch (e) {
                        // Handle timeout/abort specifically
                        if (e.name === 'AbortError') {
                            console.warn('⏱️ Dashboard fetch timed out - this can happen on slow connections');
                        } else {
                            console.error('❌ Failed to fetch dashboard data:', e);
                        }
                    } finally {
                        this._isFetchingData = false;
                        this.isBrowserOnline = navigator.onLine !== false;
                        if (!silent) {
                            this.isRefreshing = false;
                            console.log('✓ Fetch complete. isRefreshing =', this.isRefreshing);
                        }
                    }
                },
                
                // Check if a widget is enabled
                isWidgetEnabled(widgetId) {
                    return this.enabledWidgets.includes(widgetId);
                },

                /**
                 * Whether to show the fog (mist) overlay.
                 * Requires: background effects on, effects on, Mist Effect enabled in admin, and isFoggy
                 * (set by test mode = fog, or by real conditions: humidity >= 98% and not raining).
                 */
                showFog() {
                    if (!this.backgroundEffectsEnabled || !this.effectsEnabled) return false;
                    const fogEnabled = this.effects?.fog && this.effects.fog.enabled !== false;
                    return !!fogEnabled && !!this.isFoggy;
                },

                // Battery status helpers
                getBatteryLabel(key) {
                    const customLabel = this.extraSensorLabels?.battery?.[key];
                    if (customLabel && String(customLabel).trim()) return customLabel;

                    const tempMatch = String(key).match(/^batt(\d+)$/);
                    if (tempMatch) {
                        const id = tempMatch[1];
                        return this.extraSensorLabels?.temps?.[`temp_${id}`]
                            || `${t('Sensor')} ${id}`;
                    }

                    const soilMatch = String(key).match(/^soilbatt(\d+)$/);
                    if (soilMatch) {
                        const id = soilMatch[1];
                        return this.extraSensorLabels?.soil?.[id]
                            || this.extraSensorLabels?.soil?.[`soil_${id}`]
                            || `${t('Soil Sensor')} ${id}`;
                    }

                    const pm25Match = String(key).match(/^pm25batt(\d+)$/);
                    if (pm25Match) {
                        const id = pm25Match[1];
                        return this.extraSensorLabels?.pm25?.[`pm25_${id}`]
                            || this.extraSensorLabels?.pm25?.[`ch${id}`]
                            || `${t('PM2.5 Sensor')} ${id}`;
                    }

                    const leakMatch = String(key).match(/^leakbatt(\d+)$/);
                    if (leakMatch) {
                        const id = leakMatch[1];
                        return this.extraSensorLabels?.leak?.[`leak_${id}`]
                            || `${t('Leak Sensor')} ${id}`;
                    }

                    const labels = {
                        'wh26batt': t('Temperature/Humidity Sensor'),
                        'wh57batt': t('Lightning Sensor (WH57)'),
                        'wh65batt': t('Outdoor Sensor (WH65)'),
                        'batt1': t('Extra Sensor 1'),
                        'batt2': t('Extra Sensor 2'),
                        'batt3': t('Extra Sensor 3'),
                        'batt4': t('Extra Sensor 4'),
                        'batt_co2': t('CO2 Sensor'),
                        'batt_pm25': t('PM2.5 Sensor'),
                        'batt_leak': t('Leak Sensor'),
                        'batt_soil': t('Soil Sensor'),
                        'co2_batt': t('CO2 Sensor'),
                    };
                    return labels[key] || key;
                },
                
                getBatteryIcon(value) {
                    // For most Ecowitt sensors: 0 = OK, 1+ = low
                    // WH57 lightning sensor: 0-5 battery level (5 = full)
                    if (typeof value === 'undefined' || value === null) return '❓';
                    if (value === 0) return '🔋'; // Good
                    if (value <= 2) return '🪫'; // Low/medium
                    return '⚡'; // High value (WH57 style - higher is better)
                },
                
                getBatteryStatus(key, value) {
                    // WH57 uses 0-5 scale where 5 is full
                    if (key === 'wh57batt') {
                        if (value >= 4) return { text: t('Full'), class: 'text-green-400' };
                        if (value >= 2) return { text: t('Moderate'), class: 'text-yellow-400' };
                        return { text: t('Low'), class: 'text-red-400' };
                    }
                    // Other sensors: 0 = OK, 1+ = low
                    if (value === 0) return { text: t('Good'), class: 'text-green-400' };
                    return { text: t('Low'), class: 'text-red-400' };
                },

	                clearWeatherEffectContainers() {
	                    const containers = [this.$refs?.rainContainer, this.$refs?.snowContainer, this.$refs?.windContainer];
	                    containers.forEach((container) => {
	                        if (!container) return;
	                        container.innerHTML = '';
	                    });
	                    this._particleCounts = { rain: 0, snow: 0, wind: 0 };

	                    // Ensure any transient classes are removed when disabling FX.
	                    document.body.classList.remove('thunder-shake');
	                    if (this.$refs?.lightningFlash) {
	                        this.$refs.lightningFlash.classList.remove('active');
	                    }
	                },

	                resetFxTransforms() {
	                    document.querySelectorAll('.card-3d').forEach((card) => {
	                        card.classList.remove('tilting');
	                        if (card.style && card.style.transform) {
	                            card.style.transform = '';
	                        }
	                    });
	                },

	                applyBackgroundEffectsState() {
	                    // Flat theme never runs effects (containers are not in DOM)
	                    if (document.body.classList.contains('theme-flat')) return;

	                    const enabled = !!this.backgroundEffectsEnabled && !!this.effectsEnabled;

	                    // Keep DOM classes in sync even if some x-bind class bindings are skipped/cached.
	                    document.body.classList.toggle('effects-disabled', !enabled);
	                    // Legacy alias: some code paths still check for fx-off.
	                    document.body.classList.toggle('fx-off', !enabled);

	                    const bg = document.querySelector('.weather-bg');
	                    if (bg) {
	                        bg.classList.toggle('weather-bg--animated', enabled);
	                        bg.classList.toggle('weather-bg--static', !enabled);
	                    }

	                    if (!enabled) {
	                        this.stopWeatherEffects();
	                        this.clearWeatherEffectContainers();
	                        this.resetFxTransforms();
	                        return;
	                    }

	                    this.startWeatherEffects();
	                },

	                // Background effects toggle (saves to localStorage)
	                toggleBackgroundEffects() {
	                    this.backgroundEffectsEnabled = !this.backgroundEffectsEnabled;
	                    localStorage.setItem('backgroundEffectsEnabled', this.backgroundEffectsEnabled);
	                },

                // Edit Mode functionality
                toggleEditMode() {
                    this.editMode = !this.editMode;

                    if (this.editMode) {
                        document.body.classList.add('edit-mode');
                        this.$nextTick(async () => {
                            await this.initSortable();
                            this.collectWidgetOrder(); // Collect initial order
                        });
                    } else {
                        document.body.classList.remove('edit-mode');
                        this.collectWidgetOrder(); // Collect final order before saving
                        this.destroySortable();
                        this.saveWidgetOrder();
                    }
                },

                async loadSortableCtor() {
                    if (this._sortableCtor) {
                        return this._sortableCtor;
                    }

                    if (!this._sortableImportPromise) {
                        this._sortableImportPromise = import('sortablejs')
                            .then((module) => {
                                this._sortableCtor = module.default || module.Sortable || module;
                                return this._sortableCtor;
                            })
                            .catch((error) => {
                                this._sortableImportPromise = null;
                                throw error;
                            });
                    }

                    return this._sortableImportPromise;
                },

                async initSortable() {
                    this.destroySortable();

                    let Sortable;
                    try {
                        Sortable = await this.loadSortableCtor();
                    } catch (error) {
                        console.error('Failed to load SortableJS:', error);
                        this.showToast(t('Error loading editor'), 'error');
                        return;
                    }

                    this.sortableInstances = [];
                    
                    // Configure sortable for all sections
                    const sortableSections = [
                        'sortable-left-column',
                        'sortable-middle-column', 
                        'sortable-right-column',
                        'sortable-media-row',
                        'sortable-widgets'
                    ];
                    
                    sortableSections.forEach(sectionId => {
                        const section = document.getElementById(sectionId);
                        if (!section) return;
                        
                        const sortable = new Sortable(section, {
                            animation: 200,
                            ghostClass: 'sortable-ghost',
                            chosenClass: 'sortable-chosen',
                            handle: '.drag-handle',
                            draggable: '.sortable-widget:not(.ssr-fallback-block)',
                            group: 'dashboard-widgets', // Allow moving between sections
                            onEnd: () => this.collectWidgetOrder()
                        });
                        
                        this.sortableInstances.push(sortable);
                    });
                },

                destroySortable() {
                    if (this.sortableInstances) {
                        this.sortableInstances.forEach(instance => instance.destroy());
                        this.sortableInstances = [];
                    }
                },

                collectWidgetOrder() {
                    // Collect order from all sections
                    const sections = ['sortable-left-column', 'sortable-middle-column', 'sortable-right-column', 'sortable-media-row', 'sortable-widgets'];
                    const order = {};
                    
                    sections.forEach(sectionId => {
                        const section = document.getElementById(sectionId);
                        if (!section) return;
                        
                        const widgets = Array.from(section.querySelectorAll('.sortable-widget:not(.ssr-fallback-block)'))
                            // Prefer currently visible widgets when collecting order (important for ads placeholder vs ad slot).
                            .filter((widget) => widget.offsetParent !== null);
                        const widgetIds = widgets.map((widget) => widget.dataset.widget).filter(Boolean);
                        order[sectionId] = Array.from(new Set(widgetIds));
                    });
                    
                    this.widgetOrder = order;
                },

                async saveWidgetOrder() {
                    // Check if we have any widgets to save (works for both array and object)
                    const hasWidgets = Array.isArray(this.widgetOrder) 
                        ? this.widgetOrder.length > 0 
                        : Object.keys(this.widgetOrder || {}).length > 0;
                    
                    if (!hasWidgets) return;
                    
                    this.showToast('Opslaan...', 'info');
                    
                    try {
                        const response = await fetch('/widgets/order', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({ widget_order: this.widgetOrder })
                        });
                        
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}`);
                        }
                        
                        const data = await response.json();
                        if (data.success) {
                            this.showToast(t('Layout saved!') + ' ✓', 'success');
                            console.log('Widget order saved:', data.widget_order);
                        } else {
                            this.showToast(t('Error') + ': ' + data.message, 'error');
                            console.error('Failed to save widget order:', data.message);
                        }
                    } catch (error) {
                        this.showToast(t('Error saving') + ': ' + error.message, 'error');
                        console.error('Error saving widget order:', error);
                    }
                },
                
                showToast(message, type = 'info') {
                    // Remove existing toast
                    const existingToast = document.getElementById('widget-toast');
                    if (existingToast) existingToast.remove();
                    
                    // Create toast element
                    const toast = document.createElement('div');
                    toast.id = 'widget-toast';
                    toast.className = `fixed bottom-4 right-4 px-6 py-3 rounded-xl shadow-2xl z-[9999] transition-all transform ${
                        type === 'success' ? 'bg-green-500/90' : 
                        type === 'error' ? 'bg-red-500/90' : 
                        'bg-blue-500/90'
                    } text-white font-medium backdrop-blur-sm`;
                    toast.textContent = message;
                    document.body.appendChild(toast);
                    
                    // Auto-remove after 3 seconds
                    setTimeout(() => {
                        toast.style.opacity = '0';
                        toast.style.transform = 'translateY(20px)';
                        setTimeout(() => toast.remove(), 300);
                    }, 3000);
                },

                applyWidgetOrder() {
                    const findWidgetById = (widgetId) => {
                        const selector = `.sortable-widget:not(.ssr-fallback-block)[data-widget="${widgetId}"]`;
                        const candidates = Array.from(document.querySelectorAll(selector));
                        if (!candidates.length) return null;
                        return candidates.find((candidate) => candidate.offsetParent !== null) || candidates[0];
                    };

                    // If widgetOrder is an object (new format), apply per-section
                    if (typeof this.widgetOrder === 'object' && !Array.isArray(this.widgetOrder)) {
                        Object.entries(this.widgetOrder).forEach(([sectionId, widgets]) => {
                            const container = document.getElementById(sectionId);
                            if (!container || !Array.isArray(widgets)) return;
                            
                            widgets.forEach((widgetId) => {
                                // Search ACROSS ALL CONTAINERS, not just the target
                                // This handles widgets that were moved between sections
                                const widget = findWidgetById(widgetId);
                                if (widget) {
                                    container.appendChild(widget);
                                }
                            });
                        });
                    }
                    // Backwards compatibility: if widgetOrder is array (old format)
                    else if (Array.isArray(this.widgetOrder)) {
                        const container = document.getElementById('sortable-widgets');
                        if (!container) return;
                        
                        this.widgetOrder.forEach((widgetId) => {
                            const widget = findWidgetById(widgetId);
                            if (widget) {
                                container.appendChild(widget);
                            }
                        });
                    }
                },

                /**
                 * Updates isRaining, isSnowing, isWindy, isFoggy, etc. from API data or test mode.
                 * Test mode: when effects.test_mode is on, flags follow effects.test_effect (rain/snow/fog/all/…).
                 * Otherwise: flags are derived from current + forecast (e.g. isFoggy = humidity >= 98% and not raining).
                 */
                updateWeatherConditions() {
                    const testModeEnabled = this.effects.test_mode === true || this.effects.test_mode === '1' || this.effects.test_mode === 'true';
                    if (testModeEnabled) {
                        const testEffect = (this.effects.test_effect || 'rain').toString().toLowerCase();
                        this.isRaining = testEffect === 'rain' || testEffect === 'all';
                        this.isSnowing = testEffect === 'snow' || testEffect === 'all';
                        this.isWindy = testEffect === 'wind' || testEffect === 'all';
                        this.isSunny = testEffect === 'sun' || testEffect === 'all';
                        this.isFoggy = testEffect === 'fog' || testEffect === 'all';
                        this.isThunderstorm = testEffect === 'lightning' || testEffect === 'all';
                        return;
                    }

                    // Real conditions from current + forecast
                    const temp = this.current?.temperature ?? 10;
                    const humidity = this.current?.humidity ?? 50;
                    
                    // Check for actual current rain
                    const hasActualRain = this.current?.rain_rate > 0;
                    
                    // Check forecast rain based on user settings
                    let hasForecastRain = false;
                    if (this.effects?.rain?.show_forecast !== false) {
                        const forecast = this.forecast?.[0];
                        const thresholdType = this.effects?.rain?.forecast_threshold_type || 'absolute';
                        const thresholdValue = this.effects?.rain?.forecast_threshold_value ?? 0.5;
                        
                        if (thresholdType === 'percentage') {
                            // Check precipitation probability (0-100)
                            // If probability field doesn't exist, fall back to checking if precipitation > 0
                            if (forecast?.precipitation_probability !== undefined && forecast?.precipitation_probability !== null) {
                                hasForecastRain = forecast.precipitation_probability >= thresholdValue;
                            } else {
                                // Fallback: if no probability data, don't show rain from forecast
                                hasForecastRain = false;
                            }
                        } else {
                            // Check absolute precipitation amount (mm)
                            hasForecastRain = (forecast?.precipitation ?? 0) >= thresholdValue;
                        }
                    }
                    
                    // Combine actual and forecast rain
                    const hasRain = hasActualRain || hasForecastRain;
                    const forecastSymbol = this.forecast[0]?.symbol?.toLowerCase() || '';
                    
                    // Snow: temp below 2°C and actual precipitation
                    this.isSnowing = temp < 2 && hasRain && (forecastSymbol.includes('snow') || forecastSymbol.includes('sleet'));
                    
                    // Rain: not snowing and has actual precipitation (or forecast if enabled)
                    this.isRaining = !this.isSnowing && hasRain;
                    
                    // Wind: based on speed/gusts
                    // NOTE: wind_speed and wind_gust are ALWAYS in km/h in the database/API
                    // (converted from mph at input time), regardless of user's display unit preference
                    this.isWindy = this.current?.wind_speed > 30 || this.current?.wind_gust > 40;
                    
                    // Sunny: no precipitation and good solar radiation
                    this.isSunny = !this.isRaining && !this.isSnowing && this.current?.solar_radiation > 200;
                    
                    // Fog: show mist when humidity 98% or above and not raining
                    this.isFoggy = !this.isRaining && humidity >= 98;
                    
                    // Thunderstorm: a *recent* lightning strike or a thunder forecast.
                    // Gate on recency (last strike within 30 min), NOT count_daily — the
                    // daily strike counter stays > 0 until midnight, which kept the flash
                    // effect running for the rest of the day after a storm had passed.
                    const RECENT_STRIKE_MS = 30 * 60 * 1000;
                    const lastStrike = this.lightning?.last_strike ? new Date(this.lightning.last_strike).getTime() : null;
                    const recentStrike = lastStrike !== null && !Number.isNaN(lastStrike)
                        && (Date.now() - lastStrike) < RECENT_STRIKE_MS;
                    this.isThunderstorm = recentStrike || forecastSymbol.includes('thunder');
                },

                // Store interval IDs for cleanup
                _weatherIntervals: [],
                // Particle count limits (uniform for all browsers - balanced for performance)
                _maxParticles: { rain: 50, snow: 30, wind: 20 },
                // Current particle counts
                _particleCounts: { rain: 0, snow: 0, wind: 0 },

                startWeatherEffects() {
                    // Do not run when flat theme (effect containers are not in DOM)
                    if (document.body.classList.contains('theme-flat')) return;
                    if (!this.$refs.rainContainer || !this.$refs.snowContainer) {
                        if (!this._effectsRetried) {
                            this._effectsRetried = true;
                            this.$nextTick(() => this.startWeatherEffects());
                        }
                        return;
                    }
                    this._effectsRetried = false;
                    // Clear any existing intervals first (prevents memory leaks on re-init)
                    this.stopWeatherEffects();
                    
                    console.log('🎬 startWeatherEffects called, conditions:', {
                        'rain.enabled': this.effects.rain.enabled,
                        'snow.enabled': this.effects.snow.enabled,
                        'wind.enabled': this.effects.wind.enabled,
                        isRaining: this.isRaining,
                        isSnowing: this.isSnowing,
                        isWindy: this.isWindy,
                        maxParticles: this._maxParticles,
                        particleCounts: this._particleCounts
                    });
                    
                    // Rain effect - interval based on intensity
                    const rainInterval = Math.max(80, 200 - this.effects.rain.intensity);
                    
                    const rainId = setInterval(() => {
                        if (this.effectsEnabled && this.effects.rain.enabled && this.isRaining) {
                            if (this._particleCounts.rain < this._maxParticles.rain) {
                                this.createRaindrop();
                            }
                        }
                    }, rainInterval);
                    this._weatherIntervals.push(rainId);
                    console.log('🌧️ Rain interval started:', rainInterval, 'ms');

                    // Snow effect - interval based on intensity
                    const snowInterval = Math.max(150, 250 - this.effects.snow.intensity);
                    const snowId = setInterval(() => {
                        if (this.effectsEnabled && this.effects.snow.enabled && this.isSnowing) {
                            if (this._particleCounts.snow < this._maxParticles.snow) {
                                this.createSnowflake();
                            }
                        }
                    }, snowInterval);
                    this._weatherIntervals.push(snowId);
                    console.log('❄️ Snow interval started:', snowInterval, 'ms');

                    // Wind effect - interval based on intensity
                    const windInterval = Math.max(150, 250 - this.effects.wind.intensity);
                    const windId = setInterval(() => {
                        if (this.effectsEnabled && this.effects.wind.enabled && this.isWindy) {
                            if (this._particleCounts.wind < this._maxParticles.wind) {
                                this.createWindParticle();
                            }
                        }
                    }, windInterval);
                    this._weatherIntervals.push(windId);
                    console.log('💨 Wind interval started:', windInterval, 'ms');

                    // Lightning effect - random flashes during thunderstorms
                    const lightningId = setInterval(() => {
                        if (this.effectsEnabled && this.effects.lightning.enabled && this.isThunderstorm) {
                            // Random chance of lightning (about every 5-15 seconds on average)
                            if (Math.random() < 0.05) {
                                this.triggerLightning();
                            }
                        }
                    }, 500);
                    this._weatherIntervals.push(lightningId);
                },

                // Clean up weather effect intervals
                stopWeatherEffects() {
                    if (this._weatherIntervals) {
                        this._weatherIntervals.forEach(id => clearInterval(id));
                        this._weatherIntervals = [];
                    }
                },

                // Trigger lightning flash effect
                triggerLightning() {
                    const flash = this.$refs.lightningFlash;
                    if (!flash) return;
                    
                    flash.classList.add('active');
                    
                    // Optional: add screen shake
                    document.body.classList.add('thunder-shake');
                    
                    setTimeout(() => {
                        flash.classList.remove('active');
                        document.body.classList.remove('thunder-shake');
                    }, 300);
                },

                // Create snowflake effect
                createSnowflake() {
                    const container = this.$refs.snowContainer;
                    if (!container) return;
                    
                    // Track particle count
                    this._particleCounts.snow++;
                    
                    const flake = document.createElement('div');
                    const rand = Math.random();
                    
                    // 60% foreground, 40% background for depth
                    flake.className = rand < 0.6 ? 'snowflake foreground' : 'snowflake background';
                    flake.style.left = Math.random() * 100 + '%';
                    
                    // Varied size for realistic effect
                    const size = 3 + Math.random() * 5;
                    flake.style.width = size + 'px';
                    flake.style.height = size + 'px';
                    
                    // Slower, more gentle fall
                    flake.style.animationDuration = (4 + Math.random() * 4) + 's';
                    flake.style.animationDelay = Math.random() * 2 + 's';
                    
                    container.appendChild(flake);
                    
                    // Remove after animation and decrement count
                    setTimeout(() => {
                        flake.remove();
                        this._particleCounts.snow = Math.max(0, this._particleCounts.snow - 1);
                    }, 10000);
                },

                createRaindrop() {
                    const container = this.$refs.rainContainer;
                    if (!container) return;
                    
                    // Track particle count
                    this._particleCounts.rain++;
                    
                    const drop = document.createElement('div');
                    const rand = Math.random();
                    
                    // 70% foreground (in front of cards), 30% background (behind cards)
                    drop.className = rand < 0.7 ? 'raindrop foreground' : 'raindrop background';
                    drop.style.left = Math.random() * 100 + '%';
                    drop.style.height = (15 + Math.random() * 20) + 'px';
                    drop.style.animationDuration = (0.5 + Math.random() * 0.5) + 's';
                    
                    container.appendChild(drop);
                    
                    // Occasionally create splash on a card (3% chance, CPU friendly)
                    // Only if splash_on_cards is enabled in settings
                    if (this.effects.rain.splash_on_cards && rand < 0.03) {
                        this.createCardSplash();
                    }
                    
                    // Remove after animation and decrement count
                    setTimeout(() => {
                        drop.remove();
                        this._particleCounts.rain = Math.max(0, this._particleCounts.rain - 1);
                    }, 2000);
                },
                
                createCardSplash() {
                    // Find visible cards
                    const cards = document.querySelectorAll('.bg-weather-card, [class*="card-3d"]');
                    if (cards.length === 0) return;
                    
                    // Pick a random card
                    const card = cards[Math.floor(Math.random() * cards.length)];
                    const rect = card.getBoundingClientRect();
                    
                    // Skip if card is not visible
                    if (rect.top > window.innerHeight || rect.bottom < 0) return;
                    
                    // Create splash at random position on card's top edge
                    const splash = document.createElement('div');
                    splash.className = Math.random() < 0.5 ? 'rain-splash' : 'rain-splash ripple';
                    splash.style.position = 'fixed';
                    splash.style.left = (rect.left + Math.random() * rect.width) + 'px';
                    splash.style.top = (rect.top + Math.random() * 30) + 'px'; // Near top of card
                    
                    document.body.appendChild(splash);
                    
                    // Remove after animation
                    setTimeout(() => splash.remove(), 600);
                },

                createWindParticle() {
                    const container = this.$refs.windContainer;
                    if (!container) return;
                    
                    // Track particle count
                    this._particleCounts.wind++;
                    
                    const particle = document.createElement('div');
                    particle.className = 'wind-global-particle';

                    const speed = this.current?.wind_speed ?? 0; // km/h
                    const fromDir = this.current?.wind_direction ?? 0; // meteorological: direction wind comes FROM
                    const toDir = (fromDir + 180) % 360; // direction it flows TO
                    const rad = (toDir * Math.PI) / 180;

                    const margin = 120;
                    const startX = (Math.random() * (window.innerWidth + margin * 2)) - margin;
                    const startY = (Math.random() * (window.innerHeight + margin * 2)) - margin;

                    // Travel far enough to cross the viewport regardless of direction
                    const travel = Math.max(window.innerWidth, window.innerHeight) + 400;
                    const dx = Math.cos(rad) * travel;
                    const dy = Math.sin(rad) * travel;

                    // Duration: slower for light wind, faster for strong wind
                    const baseDuration = Math.max(1.6, 6 - (speed * 0.25));
                    const duration = baseDuration + (Math.random() * 1.4);

                    particle.style.left = `${startX}px`;
                    particle.style.top = `${startY}px`;
                    particle.style.width = (40 + Math.random() * 140) + 'px';

                    particle.style.setProperty('--dx', `${dx}px`);
                    particle.style.setProperty('--dy', `${dy}px`);
                    particle.style.setProperty('--angle', `${toDir}deg`);
                    particle.style.setProperty('--dur', `${duration}s`);
                    
                    container.appendChild(particle);
                    
                    // Remove after animation and decrement count
                    const cleanupTime = Math.ceil(duration * 1000) + 500;
                    setTimeout(() => {
                        particle.remove();
                        this._particleCounts.wind = Math.max(0, this._particleCounts.wind - 1);
                    }, cleanupTime);
                },

	                // Card tilt effect - subtle with inertia
		                tiltCard(event) {
		                    if (window.innerWidth < 640) return;
		                    if (document.body.classList.contains('effects-disabled') || document.body.classList.contains('fx-off')) return;
		                    const card = event.currentTarget;
		                    card.classList.add('tilting'); // Fast response while tilting
                    
                    const rect = card.getBoundingClientRect();
                    const x = event.clientX - rect.left;
                    const y = event.clientY - rect.top;
                    const centerX = rect.width / 2;
                    const centerY = rect.height / 2;
                    
                    // Subtle rotation - max ~3-4 degrees
                    const rotateX = (y - centerY) / 40;
                    const rotateY = (centerX - x) / 40;
                    
                    card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale(1.01)`;
                },

		                resetCard(event) {
		                    if (window.innerWidth < 640) return;
		                    if (document.body.classList.contains('effects-disabled') || document.body.classList.contains('fx-off')) return;
		                    const card = event.currentTarget;
		                    card.classList.remove('tilting'); // Enable bounce transition
                    
                    // Slight overshoot for bounce effect, then settle
                    card.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) scale(1)';
                },

                formatDate(dateStr) {
                    const tz = window.Meteo?.stationTimezone || 'UTC';
                    const date = new Date(dateStr);
                    const now = new Date();
                    const todayStr = now.toLocaleDateString('en-CA', { timeZone: tz });
                    const tomorrow = new Date(now.getTime() + 86400000);
                    const tomorrowStr = tomorrow.toLocaleDateString('en-CA', { timeZone: tz });
                    const dateStrNorm = date.toLocaleDateString('en-CA', { timeZone: tz });
                    if (dateStrNorm === todayStr) return t('Today');
                    if (dateStrNorm === tomorrowStr) return t('Tomorrow');
                    return date.toLocaleDateString(locale, { timeZone: tz, weekday: 'short' });
                },

                // Determine if it's currently night based on sunrise/sunset (station timezone)
                isNightTime(timeStr = null) {
                    const tz = window.Meteo?.stationTimezone || 'UTC';
                    const now = timeStr ? new Date(timeStr) : new Date();
                    const timeStrFormatted = now.toLocaleTimeString('en-GB', { timeZone: tz, hour: '2-digit', minute: '2-digit', hour12: false });
                    const [h, m] = timeStrFormatted.split(':').map(Number);
                    const currentMinutes = (h || 0) * 60 + (m || 0);

                    if (this.sun?.sunrise && this.sun?.sunset) {
                        const sunrise = this.parseTime(this.sun.sunrise);
                        const sunset = this.parseTime(this.sun.sunset);
                        if (sunrise && sunset) {
                            const sunriseMinutes = sunrise.hours * 60 + sunrise.minutes;
                            const sunsetMinutes = sunset.hours * 60 + sunset.minutes;
                            return currentMinutes < sunriseMinutes || currentMinutes > sunsetMinutes;
                        }
                    }

                    return currentMinutes < 6 * 60 || currentMinutes >= 21 * 60;
                },

                getWeatherIcon() {
                    const c = this.current;
                    if (!c?.temperature) return 'partly-cloudy-day';

                    const isNight = this.isNightTime();
                    const suffix = isNight ? '-night' : '-day';

                    // Check for thunderstorm from forecast
                    if (this.hourlyForecast?.[0]?.symbol?.toLowerCase().includes('thunder')) {
                        return isNight ? 'thunderstorms-night-rain' : 'thunderstorms-day-rain';
                    }

                    // Precipitation - check directly from current data only (not forecast)
                    const hasRain = c.rain_rate > 0;
                    const forecastSymbol = (this.forecast?.[0]?.symbol || this.hourlyForecast?.[0]?.symbol || '').toLowerCase();
                    const isSnowing = c.temperature < 2 && hasRain && (forecastSymbol.includes('snow') || forecastSymbol.includes('sleet'));
                    const isRaining = !isSnowing && hasRain;

                    if (isRaining) {
                        if (c.temperature < 0) return 'snow';
                        if (c.temperature < 3) return 'sleet';
                        if (c.rain_rate && c.rain_rate < 2) return 'drizzle';
                        return 'rain';
                    }

                    if (isSnowing) {
                        return 'snow';
                    }

                    // Fog/Mist conditions
                    if (c.humidity >= 98) return `fog${suffix}`;
                    // Misty: requires very high humidity (>96%) and cool temperature (<8°C)
                    if (c.humidity > 96 && c.temperature < 8) return 'mist';

                    // Hazy conditions (low solar radiation during day with moderate humidity)
                    if (!isNight && c.humidity > 70 && c.solar_radiation && c.solar_radiation < 100) {
                        return `haze${suffix}`;
                    }

                    // Use cloud cover from forecast if available
                    const cloudCover = this.hourlyForecast?.[0]?.cloud_cover;
                    if (cloudCover !== undefined) {
                        if (cloudCover > 80) return `overcast${suffix}`;
                        if (cloudCover > 40) return `partly-cloudy${suffix}`;
                        if (cloudCover < 20) return `clear${suffix}`;
                    }

                    // Use solar radiation to determine clear vs cloudy during day
                    if (!isNight && c.solar_radiation !== undefined) {
                        if (c.solar_radiation > 200) return `clear${suffix}`;
                        if (c.solar_radiation < 50) return `overcast${suffix}`;
                        if (c.solar_radiation < 150) return `partly-cloudy${suffix}`;
                    }

                    // Temperature-based fallback
                    if (c.temperature > 25 && (!c.humidity || c.humidity < 60)) return `clear${suffix}`;
                    if (c.temperature < 0) return 'snow';

                    return `partly-cloudy${suffix}`;
                },

                getWeatherIconForSymbol(symbol, dateStr = null, timeStr = null) {
                    if (!symbol) return 'partly-cloudy-day';
                    const s = symbol.toLowerCase();

                    // Determine night from symbol suffix or time
                    let isNight = s.includes('_night') || s.includes('_polartwilight');
                    if (!isNight && timeStr) {
                        isNight = this.isNightTime(timeStr);
                    }
                    const suffix = isNight ? '-night' : '-day';

                    // Thunderstorms (check first as they combine with other conditions)
                    if (s.includes('thunder')) {
                        if (s.includes('snow')) return isNight ? 'thunderstorms-night-snow' : 'thunderstorms-day-snow';
                        if (s.includes('_day') || s.includes('_night')) {
                            return isNight ? 'thunderstorms-night-rain' : 'thunderstorms-day-rain';
                        }
                        if (s.includes('rain')) return 'thunderstorms-rain';
                        return 'thunderstorms';
                    }

                    // Clear sky
                    if (s.includes('clearsky')) return `clear${suffix}`;

                    // Fair (slightly cloudy)
                    if (s.includes('fair')) return `partly-cloudy${suffix}`;

                    // Fog
                    if (s.includes('fog')) return `fog${suffix}`;

                    // Snow conditions
                    if (s.includes('snow')) {
                        if (s.includes('showers')) return `partly-cloudy${suffix}-snow`;
                        return 'snow';
                    }

                    // Sleet conditions
                    if (s.includes('sleet')) {
                        if (s.includes('showers')) return `partly-cloudy${suffix}-sleet`;
                        return 'sleet';
                    }

                    // Rain conditions - differentiate by intensity
                    if (s.includes('rain')) {
                        if (s.includes('light')) {
                            if (s.includes('showers')) return `partly-cloudy${suffix}-drizzle`;
                            return 'drizzle';
                        }
                        if (s.includes('showers')) return `partly-cloudy${suffix}-rain`;
                        return 'rain';
                    }

                    // Partly cloudy
                    if (s.includes('partlycloudy')) return `partly-cloudy${suffix}`;

                    // Cloudy / Overcast
                    if (s.includes('cloudy') || s.includes('overcast')) return 'cloudy';

                    return `partly-cloudy${suffix}`;
                },

                getWeatherDescription() {
                    const c = this.current;
                    if (!c?.temperature) return t('Configure API');
                    
                    // Check actual weather conditions first (more accurate than just temperature)
                    // Only check current rain rate, not forecast (forecast is for future, not current conditions)
                    const hasRain = c.rain_rate && c.rain_rate > 0;
                    const forecastSymbol = (this.forecast?.[0]?.symbol || this.hourlyForecast?.[0]?.symbol || '').toLowerCase();
                    const isSnowing = c.temperature < 2 && hasRain && (forecastSymbol.includes('snow') || forecastSymbol.includes('sleet'));
                    const isRaining = !isSnowing && hasRain;
                    
                    if (isSnowing) return t('Snowy');
                    if (isRaining) return t('Rainy');
                    if (c.humidity && c.humidity >= 98) return t('Foggy');
                    // Misty: requires very high humidity (>96%) and cool temperature (<8°C)
                    if (c.humidity && c.humidity > 96 && c.temperature < 8) return t('Misty');
                    if (!this.isNightTime() && c.humidity && c.humidity > 70 && c.solar_radiation && c.solar_radiation < 100) {
                        return t('Hazy');
                    }
                    
                    // Temperature-based descriptions
                    const temperature = c.temperature;
                    if (temperature > 25) return t('Warm');
                    if (temperature > 15) return t('Pleasant');
                    if (temperature > 5) return t('Cool');
                    if (temperature > 0) return t('Cold');
                    return t('Freezing');
                },

                getUvColor(uv) {
                    if (!uv || uv < 3) return 'text-green-400';
                    if (uv < 6) return 'text-yellow-400';
                    if (uv < 8) return 'text-orange-400';
                    return 'text-red-400';
                },

                getUvLevel(uv) {
                    if (!uv || uv < 3) return t('Low');
                    if (uv < 6) return t('Moderate');
                    if (uv < 8) return t('High');
                    return t('Very high');
                },

                // Air Quality helpers
                getAqiEmoji(level) {
                    if (!level) return '😊';
                    const levelLower = level.toLowerCase();
                    // US EPA: Good, Moderate, Unhealthy for Sensitive, Unhealthy, Very Unhealthy, Hazardous
                    // EEA: Good, Fair, Moderate, Poor, Very Poor, Extremely Poor
                    // UK DAQI: Low, Moderate, High, Very High
                    if (levelLower === 'good' || levelLower === 'low') return '😊';
                    if (levelLower === 'fair') return '🙂';
                    if (levelLower === 'moderate') return '😐';
                    if (levelLower.includes('sensitive')) return '😷';
                    if (levelLower === 'poor' || levelLower === 'high') return '😷';
                    if (levelLower === 'unhealthy' || levelLower === 'very poor' || levelLower === 'very high') return '🤢';
                    if (levelLower === 'very unhealthy') return '🤮';
                    if (levelLower === 'hazardous' || levelLower === 'extremely poor') return '⚠️';
                    return '😊';
                },
                getAqiLevelTranslation(level) {
                    if (!level) return this.translations.good;
                    return this.translations.aqiLevels[level] || level;
                },

                pollenTranslateRisk(risk) {
                    if (!risk) return '—';
                    return this.translations.pollenRisk[risk] || risk;
                },

                // Kp-index helpers for Aurora
                getKpColor(kp) {
                    if (!kp || kp < 4) return 'text-green-400';
                    if (kp < 5) return 'text-yellow-400';
                    if (kp < 6) return 'text-orange-400';
                    if (kp < 7) return 'text-red-400';
                    return 'text-purple-400';
                },

                getKpBgColor(kp) {
                    if (!kp || kp < 4) return 'bg-green-500/20';
                    if (kp < 5) return 'bg-yellow-500/20';
                    if (kp < 6) return 'bg-orange-500/20';
                    if (kp < 7) return 'bg-red-500/20';
                    return 'bg-purple-500/20';
                },

                getKpLevel(kp) {
                    if (!kp || kp < 4) return t('Calm');
                    if (kp < 5) return t('Active');
                    if (kp < 6) return t('Storm');
                    if (kp < 7) return t('Strong');
                    return t('Extreme');
                },

                getKpChance(kp) {
                    if (!kp || kp < 4) return t('Very low');
                    if (kp < 5) return t('Low');
                    if (kp < 6) return t('Moderate');
                    if (kp < 7) return t('High');
                    return t('Very high');
                },

                getDaylightProgress() {
                    if (!this.sun) return 50;
                    const now = new Date();
                    const sunrise = this.parseTime(this.sun.sunrise);
                    const sunset = this.parseTime(this.sun.sunset);
                    
                    if (!sunrise || !sunset) return 50;
                    
                    const current = now.getHours() * 60 + now.getMinutes();
                    const start = sunrise.hours * 60 + sunrise.minutes;
                    const end = sunset.hours * 60 + sunset.minutes;
                    
                    if (current < start) return 0;
                    if (current > end) return 100;
                    
                    return Math.round(((current - start) / (end - start)) * 100);
                },

                parseTime(timeStr) {
                    if (!timeStr) return null;
                    const parts = timeStr.split(':');
                    return { hours: parseInt(parts[0]), minutes: parseInt(parts[1]) };
                },

                formatLightningTime(timestamp) {
                    if (!timestamp) return '--';
                    const date = new Date(timestamp * 1000);
                    const now = new Date();
                    const diff = Math.floor((now - date) / 1000 / 60); // minutes ago
                    
                    if (diff < 1) return t('Just now');
                    if (diff < 60) return diff + ' ' + t('minutes ago');
                    if (diff < 1440) return Math.floor(diff / 60) + ' ' + t('hours ago');
                    return date.toLocaleDateString(locale, { day: 'numeric', month: 'short' });
                }
            };
}

window.weatherDashboard = weatherDashboard;
