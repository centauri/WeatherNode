/**
 * Aviation Weather — Atmospheric Profile Visualization
 *
 * Canvas-based vertical sky view with cloud layers at real altitudes,
 * weather phenomena particles, and visibility effects.
 */

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;
    Alpine.data('aviationWeather', () => {
        const translations = JSON.parse(document.getElementById('aviation-translations')?.textContent || '{}');
        const config = window.aviationConfig || {};
        const isMobile = window.matchMedia('(max-width: 768px)').matches;

        return {
        // State
        currentIcao: config.defaultIcao || 'EHAM',
        stationName: null,
        metar: null,
        loading: false,
        error: null,
        searchInput: '',
        searchFocused: false,
        recentSearches: [],
        observedAgo: '',

        // Canvas internals
        _canvas: null,
        _ctx: null,
        _animFrame: null,
        _particles: [],
        _windStreaks: [],
        _windSpeed: 0,
        _fogState: null,
        _cloudBlobs: [],
        _time: 0,
        _resizeTimer: null,
        _refreshTimer: null,

        // Altitude config
        _maxAltFeet: 45000,
        _scaleMarks: [0, 1000, 2000, 5000, 10000, 15000, 20000, 30000, 45000],
        _groundHeight: 60, // pixels from bottom for ground + village

        async init() {
            this.loadRecentSearches();

            // Wait for next tick so Alpine has finished rendering the DOM
            await this.$nextTick();

            this._setupCanvas();
            this._startAnimation();
            this._startAgeTicker();
            this._startAutoRefresh();

            // Handle resize
            window.addEventListener('resize', () => {
                clearTimeout(this._resizeTimer);
                this._resizeTimer = setTimeout(() => {
                    this._resizeCanvas();
                    this._generateCloudBlobs();
                }, 200);
            });

            // Handle browser back/forward
            window.addEventListener('popstate', (e) => {
                if (e.state?.icao) {
                    this.currentIcao = e.state.icao;
                    this.searchInput = e.state.icao;
                    this.fetchMetar(e.state.icao);
                }
            });

            // Fetch data (don't block the animation loop)
            this.fetchMetar(this.currentIcao);
        },

        // ─── Data Fetching ───────────────────────────────────

        async fetchMetar(icao) {
            if (!icao || icao.length !== 4) {
                this.error = translations.invalidIcao || 'Invalid ICAO code';
                return;
            }
            this.loading = true;
            this.error = null;

            try {
                const url = '/api/weather/metar?icao=' + encodeURIComponent(icao);
                const headers = window.Meteo?.apiHeaders ? window.Meteo.apiHeaders() : {};
                const response = await fetch(url, { headers });
                const json = await response.json();

                if (json.success && json.data && json.data.length > 0) {
                    this.metar = json.data[0];
                    this._fillMissingFromRaw();
                    this.currentIcao = this.metar.icao || icao;
                    this.stationName = this.metar.name || null;
                    this._generateCloudBlobs();
                    this._generateParticles();
                    this._updateObservedAgo();
                } else if (json.success && json.data === null) {
                    this.metar = null;
                    this.error = translations.noData || 'No METAR data available';
                } else {
                    this.metar = null;
                    this.error = json.message || translations.noData || 'No METAR data available';
                }
            } catch (e) {
                this.error = e.message;
            } finally {
                this.loading = false;
            }
        },

        // ─── Search ──────────────────────────────────────────

        searchIcao() {
            const icao = this.searchInput.toUpperCase().trim();
            if (icao.length !== 4) return;
            this.currentIcao = icao;
            this.fetchMetar(icao);
            this.addRecentSearch(icao);
            this.searchFocused = false;
            this._pushUrl(icao);
        },

        _pushUrl(icao) {
            const base = config.baseUrl || '/aviation';
            const url = base.replace(/\/$/, '') + '/' + icao;
            if (window.location.pathname !== url) {
                window.history.pushState({ icao }, '', url);
            }
            // Update page title
            document.title = document.title.replace(/Aviation Weather\s*\w*/, 'Aviation Weather ' + icao);
        },

        loadRecentSearches() {
            try {
                this.recentSearches = JSON.parse(localStorage.getItem('aviation_recent') || '[]');
            } catch { this.recentSearches = []; }
        },

        addRecentSearch(icao) {
            this.recentSearches = [icao, ...this.recentSearches.filter(s => s !== icao)].slice(0, 5);
            localStorage.setItem('aviation_recent', JSON.stringify(this.recentSearches));
        },

        // ─── Display Helpers ─────────────────────────────────

        formatWind() {
            if (!this.metar?.wind) return '--';
            const dir = this.metar.wind.direction ?? '--';
            const spd = this.metar.wind.speed_kmh != null ? Math.round(this.metar.wind.speed_kmh) + ' km/h' : '--';
            return dir + '\u00B0 / ' + spd;
        },

        formatVisibility() {
            if (!this.metar?.visibility) return '--';
            const m = this.metar.visibility.meters;
            if (m == null) return '--';
            if (m >= 9999) return '10+ km';
            if (m >= 1000) return (m / 1000).toFixed(1) + ' km';
            return Math.round(m) + ' m';
        },

        flightCategoryBorderClass() {
            const cat = this.metar?.flight_category;
            if (!cat) return '';
            const map = { VFR: 'border-l-4 border-l-green-500', MVFR: 'border-l-4 border-l-blue-500', IFR: 'border-l-4 border-l-red-500', LIFR: 'border-l-4 border-l-fuchsia-500' };
            return map[cat] || '';
        },

        flightCategoryBadgeClass() {
            const cat = this.metar?.flight_category;
            if (!cat) return 'bg-ui-soft text-ui-secondary';
            const map = {
                VFR: 'bg-green-500/20 text-data-green-400',
                MVFR: 'bg-blue-500/20 text-data-blue-400',
                IFR: 'bg-red-500/20 text-data-red-400',
                LIFR: 'bg-fuchsia-500/20 text-data-fuchsia-400',
            };
            return map[cat] || 'bg-ui-soft text-ui-secondary';
        },

        _updateObservedAgo() {
            if (!this.metar?.observed) { this.observedAgo = ''; return; }
            const obs = new Date(this.metar.observed);
            const diff = Math.floor((Date.now() - obs.getTime()) / 60000);
            if (diff < 1) this.observedAgo = translations.justNow || 'just now';
            else if (diff < 60) this.observedAgo = (translations.minutesAgo || ':min min ago').replace(':min', diff);
            else this.observedAgo = (translations.hoursAgo || ':hours h ago').replace(':hours', Math.floor(diff / 60));
        },

        _startAgeTicker() {
            setInterval(() => this._updateObservedAgo(), 30000);
        },

        _startAutoRefresh() {
            // Refresh METAR every 5 minutes (METARs update ~every 30 min, 5 min keeps it fresh)
            this._refreshTimer = setInterval(() => {
                if (this.currentIcao && !this.loading) {
                    this.fetchMetar(this.currentIcao);
                }
            }, 5 * 60 * 1000);
        },

        // ─── Raw METAR Fallback Parser ──────────────────────

        _fillMissingFromRaw() {
            const raw = this.metar?.raw;
            if (!raw) return;

            // Fill missing cloud altitudes from raw METAR
            // Pattern: FEW037, SCT100, BKN027, OVC060 — number is hundreds of feet
            if (this.metar.clouds) {
                const cloudPattern = /(FEW|SCT|BKN|OVC|VV)(\d{3})(CB|TCU)?/g;
                const rawClouds = [];
                let m;
                while ((m = cloudPattern.exec(raw)) !== null) {
                    rawClouds.push({ code: m[1], feet: parseInt(m[2], 10) * 100 });
                }

                this.metar.clouds.forEach((cloud, i) => {
                    if (cloud.base_feet == null && rawClouds[i]) {
                        cloud.base_feet = rawClouds[i].feet;
                        cloud.base_meters = Math.round(rawClouds[i].feet * 0.3048);
                    }
                });
            }

            // Fill missing visibility from raw METAR
            if (this.metar.visibility?.meters == null) {
                // METAR visibility: 9999 (meters), or SM format like 10SM, 1/2SM
                const smMatch = raw.match(/\b(\d+)\s*SM\b/);
                const mMatch = raw.match(/\b(\d{4})\b(?!\s*Z)/); // 4-digit meters, not time
                if (smMatch) {
                    const miles = parseInt(smMatch[1], 10);
                    if (!this.metar.visibility) this.metar.visibility = {};
                    this.metar.visibility.meters = Math.round(miles * 1609.34);
                    this.metar.visibility.miles = miles;
                } else if (mMatch && parseInt(mMatch[1]) <= 9999) {
                    // Could be meters (European format) but only if it looks reasonable
                    const val = parseInt(mMatch[1], 10);
                    if (val >= 100 && val <= 9999) {
                        if (!this.metar.visibility) this.metar.visibility = {};
                        this.metar.visibility.meters = val;
                    }
                }
            }

            // Fill wind speed from raw if missing (pattern: 18012KT or 18012G25KT)
            if (this.metar.wind?.speed_kts == null) {
                const windMatch = raw.match(/\b(\d{3}|VRB)(\d{2,3})(G(\d{2,3}))?KT\b/);
                if (windMatch) {
                    if (!this.metar.wind) this.metar.wind = {};
                    this.metar.wind.speed_kts = parseInt(windMatch[2], 10);
                    this.metar.wind.speed_kmh = Math.round(this.metar.wind.speed_kts * 1.852);
                    if (windMatch[4]) this.metar.wind.gust_kts = parseInt(windMatch[4], 10);
                    if (windMatch[1] !== 'VRB') this.metar.wind.direction = parseInt(windMatch[1], 10);
                }
            }

            // Fill humidity from temp/dewpoint if missing
            if (this.metar.humidity == null && this.metar.temperature != null && this.metar.dewpoint != null) {
                const t = this.metar.temperature;
                const td = this.metar.dewpoint;
                this.metar.humidity = Math.round(100 * Math.exp((17.625 * td) / (243.04 + td)) / Math.exp((17.625 * t) / (243.04 + t)));
            }
        },

        // ─── Canvas Setup ────────────────────────────────────

        _setupCanvas() {
            this._canvas = document.getElementById('atmosphere-canvas');
            if (!this._canvas) return;
            this._ctx = this._canvas.getContext('2d');
            this._resizeCanvas();
        },

        _resizeCanvas() {
            if (!this._canvas || !this._ctx) return;
            const rect = this._canvas.getBoundingClientRect();
            if (rect.width === 0 || rect.height === 0) return;
            const dpr = window.devicePixelRatio || 1;
            this._canvas.width = rect.width * dpr;
            this._canvas.height = rect.height * dpr;
            // Reset transform before scaling (prevents accumulation on repeated resize)
            this._ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            this._w = rect.width;
            this._h = rect.height;
        },

        // ─── Coordinate Helpers ──────────────────────────────

        _altToY(feet) {
            // Square-root scale: gives lower altitudes (where weather happens) more visual space.
            // At 5,000 ft ≈ 33% up, at 10,000 ft ≈ 47%, at 20,000 ft ≈ 67%, at 45,000 ft = 100%.
            const usable = this._h - this._groundHeight;
            const ratio = Math.sqrt(Math.min(feet / this._maxAltFeet, 1));
            return usable * (1 - ratio);
        },

        // ─── Cloud Blob Generation ──────────────────────────

        _generateCloudBlobs() {
            this._cloudBlobs = [];
            if (!this.metar?.clouds) return;

            const coverageDensity = { FEW: 0.15, SCT: 0.4, BKN: 0.7, OVC: 0.95, CLR: 0, SKC: 0, NSC: 0, NCD: 0 };

            for (const layer of this.metar.clouds) {
                const code = layer.code;
                const baseFeet = layer.base_feet;
                if (baseFeet == null || !code) continue;

                const density = coverageDensity[code] ?? 0.3;
                if (density === 0) continue;

                const blobCount = Math.floor((isMobile ? 15 : 35) * density) + 3;
                const blobs = [];

                for (let i = 0; i < blobCount; i++) {
                    blobs.push({
                        xRatio: Math.random(),             // 0-1 across canvas width
                        yOffsetFeet: (Math.random() - 0.5) * 800, // spread within ~800ft band
                        size: 30 + Math.random() * 60,
                        opacity: 0.3 + density * 0.5 + Math.random() * 0.2,
                        drift: (Math.random() - 0.5) * 0.3, // slow horizontal drift
                    });
                }

                this._cloudBlobs.push({ code, baseFeet, density, blobs });
            }
        },

        // ─── Particle Generation (rain, snow, fog) ──────────

        _generateParticles() {
            this._particles = [];
            this._windStreaks = [];
            if (!this.metar?.conditions) return;

            const codes = this.metar.conditions.map(c => c.code || '');
            // Determine rain/snow intensity from METAR qualifiers:
            //   VC (vicinity) = very light, just hints
            //   - (light, e.g. -RA) = light
            //   no prefix = moderate
            //   + (heavy, e.g. +RA) = heavy
            const hasRain = codes.some(c => /RA|DZ|SH/.test(c));
            const hasSnow = codes.some(c => /SN|SG|GS/.test(c));
            const hasTS = codes.some(c => /TS/.test(c));
            const hasFog = codes.some(c => /FG/.test(c));
            const hasMist = codes.some(c => /BR/.test(c));
            const hasHaze = codes.some(c => /HZ/.test(c));

            // Intensity factor: VC=0.15, -=0.4, (none)=0.7, +=1.0
            let precipIntensity = 0.7;
            if (codes.some(c => /^VC/.test(c))) precipIntensity = 0.15;
            else if (codes.some(c => /^\+/.test(c))) precipIntensity = 1.0;
            else if (codes.some(c => /^-/.test(c))) precipIntensity = 0.4;

            // Store fog/mist state for rendering
            this._fogState = { fog: hasFog, mist: hasMist, haze: hasHaze };

            // Wind data for effects
            const windKts = this.metar?.wind?.speed_kts ?? 0;
            this._windSpeed = windKts;

            // Generate wind streaks for strong winds (>20kts / ~37km/h)
            if (windKts > 20) {
                const effectiveWind = windKts - 20;
                const streakCount = isMobile ? 10 : Math.min(40, Math.floor(effectiveWind * 1.2));
                for (let i = 0; i < streakCount; i++) {
                    this._windStreaks.push({
                        x: Math.random(),
                        y: Math.random() * 0.85,
                        length: 15 + Math.random() * 20 + effectiveWind * 0.4,
                        speed: 2 + Math.random() * 2 + effectiveWind * 0.06,
                        opacity: 0.04 + Math.random() * 0.08,
                    });
                }
            }

            const baseParticles = isMobile ? 80 : 200;
            const maxParticles = Math.floor(baseParticles * precipIntensity);

            // Find lowest cloud base for rain/snow origin
            const lowestCloud = this.metar.clouds?.reduce((min, c) => {
                if (c.base_feet != null && c.base_feet < min) return c.base_feet;
                return min;
            }, this._maxAltFeet) ?? 10000;

            if (hasRain) {
                for (let i = 0; i < maxParticles; i++) {
                    this._particles.push({
                        type: 'rain',
                        x: Math.random(),
                        y: Math.random(),
                        speed: 2.5 + Math.random() * 3,
                        length: 6 + Math.random() * 8,
                        originAlt: lowestCloud,
                    });
                }
            }

            if (hasSnow) {
                const count = Math.floor(maxParticles * 0.6);
                for (let i = 0; i < count; i++) {
                    this._particles.push({
                        type: 'snow',
                        x: Math.random(),
                        y: Math.random(),
                        speed: 0.6 + Math.random() * 1.2,
                        size: 1.5 + Math.random() * 2.5,
                        drift: (Math.random() - 0.5) * 0.6,
                        originAlt: lowestCloud,
                    });
                }
            }

            if (hasTS) {
                this._tsFlash = { active: false, nextFlash: 60 + Math.random() * 120, timer: 0 };
            } else {
                this._tsFlash = null;
            }
        },

        // ─── Animation Loop ─────────────────────────────────

        _startAnimation() {
            const animate = () => {
                this._time++;
                this._render();
                this._animFrame = requestAnimationFrame(animate);
            };
            animate();
        },

        _render() {
            const ctx = this._ctx;
            const w = this._w;
            const h = this._h;
            if (!ctx || !w) return;

            ctx.clearRect(0, 0, w, h);

            // 1. Sky gradient
            this._drawSky(ctx, w, h);

            // 2. Altitude scale
            this._drawScale(ctx, w, h);

            // 3. Cloud layers
            this._drawClouds(ctx, w, h);

            // 4. Weather particles
            this._drawParticles(ctx, w, h);

            // 5. Visibility haze
            this._drawVisibility(ctx, w, h);

            // 6. Thunderstorm flash
            this._drawThunderstorm(ctx, w, h);

            // 7. Ground
            this._drawGround(ctx, w, h);

            // 8. HUD overlays (compass, pressure, local time)
            this._drawCompass(ctx, w, h);
            this._drawPressureGauge(ctx, w, h);
            this._drawStationClock(ctx, w, h);
        },

        // Solar elevation angle (simplified formula, good enough for sky rendering)
        // Returns degrees: negative = below horizon (night), 0 = horizon, 90 = zenith
        _getSolarElevation() {
            if (!this.metar?.observed) return null;
            const lat = this.metar.latitude;
            const lon = this.metar.longitude;
            if (lat == null || lon == null) return null;

            const now = new Date(this.metar.observed);
            const dayOfYear = Math.floor((now - new Date(now.getFullYear(), 0, 0)) / 86400000);
            const utcHours = now.getUTCHours() + now.getUTCMinutes() / 60;

            // Solar declination (simplified)
            const declination = 23.45 * Math.sin((2 * Math.PI / 365) * (dayOfYear - 81));
            const declRad = declination * Math.PI / 180;
            const latRad = lat * Math.PI / 180;

            // Hour angle (15° per hour from solar noon)
            const solarNoonUTC = 12 - lon / 15; // approximate solar noon in UTC
            const hourAngle = (utcHours - solarNoonUTC) * 15;
            const haRad = hourAngle * Math.PI / 180;

            // Solar elevation
            const sinElev = Math.sin(latRad) * Math.sin(declRad) +
                            Math.cos(latRad) * Math.cos(declRad) * Math.cos(haRad);
            return Math.asin(Math.max(-1, Math.min(1, sinElev))) * 180 / Math.PI;
        },

        _drawSky(ctx, w, h) {
            const sunElev = this._getSolarElevation();

            // Sky phase based on solar elevation:
            //   > 15°: full day
            //   6° to 15°: day transitioning
            //   0° to 6°: golden hour / low sun
            //   -6° to 0°: civil twilight (sunset/sunrise glow)
            //   -12° to -6°: nautical twilight
            //   < -12°: full night
            const grad = ctx.createLinearGradient(0, 0, 0, h);

            if (sunElev == null || sunElev < -12) {
                // Full night
                grad.addColorStop(0, '#050a15');
                grad.addColorStop(0.2, '#0c1a2e');
                grad.addColorStop(0.5, '#1a3050');
                grad.addColorStop(0.8, '#1e3a5f');
                grad.addColorStop(1, '#1a2e4a');
            } else if (sunElev < -6) {
                // Nautical twilight — deep blue with faint horizon glow
                const f = (sunElev + 12) / 6; // 0=deep night, 1=civil twilight
                grad.addColorStop(0, '#050a15');
                grad.addColorStop(0.3, '#0c1a2e');
                grad.addColorStop(0.6, this._lerpColor('#1a3050', '#1a2848', f));
                grad.addColorStop(0.85, this._lerpColor('#1e3a5f', '#2a3050', f));
                grad.addColorStop(1, this._lerpColor('#1a2e4a', '#3a3048', f));
            } else if (sunElev < 0) {
                // Civil twilight — orange/pink horizon glow
                const f = (sunElev + 6) / 6; // 0=nautical, 1=horizon
                grad.addColorStop(0, this._lerpColor('#050a15', '#0a1028', f));
                grad.addColorStop(0.3, this._lerpColor('#0c1a2e', '#152040', f));
                grad.addColorStop(0.6, this._lerpColor('#1a2848', '#2a3058', f));
                grad.addColorStop(0.85, this._lerpColor('#2a3050', '#6a4040', f));
                grad.addColorStop(1, this._lerpColor('#3a3048', '#c06830', f));
            } else if (sunElev < 6) {
                // Golden hour — warm horizon, deepening blue above
                const f = sunElev / 6; // 0=horizon, 1=low sun
                grad.addColorStop(0, this._lerpColor('#0a1028', '#1a2848', f));
                grad.addColorStop(0.2, this._lerpColor('#152040', '#2a4068', f));
                grad.addColorStop(0.5, this._lerpColor('#2a3058', '#3a5878', f));
                grad.addColorStop(0.8, this._lerpColor('#6a4040', '#5a6878', f));
                grad.addColorStop(1, this._lerpColor('#c06830', '#7a8898', f));
            } else if (sunElev < 15) {
                // Morning/evening — transitioning to full day
                const f = (sunElev - 6) / 9;
                grad.addColorStop(0, this._lerpColor('#1a2848', '#204068', f));
                grad.addColorStop(0.2, this._lerpColor('#2a4068', '#3060a0', f));
                grad.addColorStop(0.5, this._lerpColor('#3a5878', '#4878b8', f));
                grad.addColorStop(0.8, this._lerpColor('#5a6878', '#5a90c8', f));
                grad.addColorStop(1, this._lerpColor('#7a8898', '#6aa0d0', f));
            } else {
                // Full day — bright blue sky
                grad.addColorStop(0, '#204068');
                grad.addColorStop(0.2, '#3060a0');
                grad.addColorStop(0.5, '#4878b8');
                grad.addColorStop(0.8, '#5a90c8');
                grad.addColorStop(1, '#6aa0d0');
            }

            ctx.fillStyle = grad;
            ctx.fillRect(0, 0, w, h);

            // Sun/moon rendering
            if (sunElev != null && sunElev > -6) {
                // Sun position — map elevation to y, place at ~70% width
                const sunY = h * (1 - Math.max(0, sunElev) / 90) * 0.8;
                const sunX = w * 0.7;
                const sunSize = sunElev < 6 ? 12 : 10;

                if (sunElev > 0) {
                    // Sun glow
                    const glowR = sunSize + (sunElev < 10 ? 30 : 15);
                    const glowGrad = ctx.createRadialGradient(sunX, sunY, sunSize * 0.5, sunX, sunY, glowR);
                    const glowOpacity = sunElev < 6 ? 0.25 : 0.1;
                    glowGrad.addColorStop(0, `rgba(255, 220, 150, ${glowOpacity})`);
                    glowGrad.addColorStop(1, 'rgba(255, 220, 150, 0)');
                    ctx.fillStyle = glowGrad;
                    ctx.beginPath();
                    ctx.arc(sunX, sunY, glowR, 0, Math.PI * 2);
                    ctx.fill();

                    // Sun disc
                    const discColor = sunElev < 6 ? 'rgba(255, 160, 50, 0.8)' : 'rgba(255, 230, 160, 0.6)';
                    ctx.fillStyle = discColor;
                    ctx.beginPath();
                    ctx.arc(sunX, sunY, sunSize * 0.4, 0, Math.PI * 2);
                    ctx.fill();
                } else {
                    // Below horizon but in twilight — horizon glow only
                    const glowOpacity = 0.15 * (1 + sunElev / 6); // fades to 0 at -6°
                    if (glowOpacity > 0) {
                        const groundY = h - this._groundHeight;
                        const horizGrad = ctx.createRadialGradient(sunX, groundY, 0, sunX, groundY, w * 0.3);
                        horizGrad.addColorStop(0, `rgba(255, 140, 50, ${glowOpacity})`);
                        horizGrad.addColorStop(0.5, `rgba(255, 100, 40, ${glowOpacity * 0.3})`);
                        horizGrad.addColorStop(1, 'rgba(255, 80, 30, 0)');
                        ctx.fillStyle = horizGrad;
                        ctx.beginPath();
                        ctx.arc(sunX, groundY, w * 0.3, 0, Math.PI * 2);
                        ctx.fill();
                    }
                }
            }

            // Stars — only visible when sun is below ~6°
            if (sunElev == null || sunElev < 6) {
                const starOpacity = sunElev == null ? 0.4 : sunElev < -12 ? 0.4 : Math.max(0, (6 - sunElev) / 18) * 0.4;
                if (starOpacity > 0.02) {
                    if (this._time % 3 === 0) return; // twinkle
                    ctx.fillStyle = `rgba(255,255,255,${starOpacity})`;
                    const seed = 42;
                    for (let i = 0; i < 30; i++) {
                        const sx = ((seed * (i + 1) * 7) % 1000) / 1000 * w;
                        const sy = ((seed * (i + 1) * 13) % 1000) / 1000 * (h * 0.3);
                        const ss = 0.5 + ((seed * i) % 3) * 0.4;
                        ctx.beginPath();
                        ctx.arc(sx, sy, ss, 0, Math.PI * 2);
                        ctx.fill();
                    }
                }
            }
        },

        // Linear interpolation between two hex colors
        _lerpColor(c1, c2, t) {
            const r1 = parseInt(c1.slice(1, 3), 16), g1 = parseInt(c1.slice(3, 5), 16), b1 = parseInt(c1.slice(5, 7), 16);
            const r2 = parseInt(c2.slice(1, 3), 16), g2 = parseInt(c2.slice(3, 5), 16), b2 = parseInt(c2.slice(5, 7), 16);
            const r = Math.round(r1 + (r2 - r1) * t);
            const g = Math.round(g1 + (g2 - g1) * t);
            const b = Math.round(b1 + (b2 - b1) * t);
            return `#${r.toString(16).padStart(2, '0')}${g.toString(16).padStart(2, '0')}${b.toString(16).padStart(2, '0')}`;
        },

        _drawScale(ctx, w, h) {
            ctx.save();
            ctx.strokeStyle = 'rgba(255,255,255,0.1)';
            ctx.fillStyle = 'rgba(255,255,255,0.35)';
            ctx.font = '10px system-ui, sans-serif';
            ctx.textAlign = 'left';

            for (const alt of this._scaleMarks) {
                if (alt === 0) continue;
                const y = this._altToY(alt);

                // Grid line
                ctx.beginPath();
                ctx.setLineDash([4, 6]);
                ctx.moveTo(50, y);
                ctx.lineTo(w, y);
                ctx.stroke();
                ctx.setLineDash([]);

                // Label
                const label = alt >= 10000 ? (alt / 1000) + 'k ft' : alt.toLocaleString() + ' ft';
                const meters = Math.round(alt * 0.3048);
                ctx.fillText(label, 4, y - 3);
                ctx.fillStyle = 'rgba(255,255,255,0.2)';
                ctx.fillText(meters + ' m', 4, y + 9);
                ctx.fillStyle = 'rgba(255,255,255,0.35)';
            }
            ctx.restore();
        },

        _drawClouds(ctx, w, h) {
            const t = this._time;
            const groundY = h - this._groundHeight;

            // Clip clouds above ground zone so they don't overlap ground elements (windsock, trees)
            ctx.save();
            ctx.beginPath();
            ctx.rect(0, 0, w, groundY);
            ctx.clip();

            const sunElev = this._getSolarElevation();
            // Cloud brightness: dim at night, bright white in daytime
            // Sunset/sunrise: warm-tinted clouds
            let coreR = 210, coreG = 215, coreB = 230;
            let cloudAlphaMul = 0.7;
            if (sunElev != null) {
                if (sunElev > 15) {
                    // Day — bright white clouds
                    coreR = 240; coreG = 245; coreB = 250; cloudAlphaMul = 0.85;
                } else if (sunElev > 0) {
                    // Golden hour — warm-tinted clouds
                    const f = sunElev / 15;
                    coreR = Math.round(240 - f * 10); coreG = Math.round(210 + f * 35); coreB = Math.round(200 + f * 50);
                    cloudAlphaMul = 0.8;
                } else if (sunElev > -6) {
                    // Twilight — orange/pink tinted
                    coreR = 230; coreG = 190; coreB = 170; cloudAlphaMul = 0.75;
                }
                // else night — keep defaults
            }

            for (const layer of this._cloudBlobs) {
                for (const blob of layer.blobs) {
                    const altFeet = layer.baseFeet + blob.yOffsetFeet;
                    const y = this._altToY(altFeet);
                    const x = (blob.xRatio * (w - 100) + 50) + Math.sin(t * 0.005 + blob.drift * 10) * 15 * blob.drift;
                    const s = blob.size;

                    const prevAlpha = ctx.globalAlpha;
                    ctx.globalAlpha = blob.opacity * cloudAlphaMul;

                    const grad = ctx.createRadialGradient(x, y, 0, x, y, s);
                    grad.addColorStop(0, `rgba(${coreR}, ${coreG}, ${coreB}, 0.8)`);
                    grad.addColorStop(0.5, `rgba(${coreR - 10}, ${coreG - 7}, ${coreB - 5}, 0.4)`);
                    grad.addColorStop(1, `rgba(${coreR - 20}, ${coreG - 15}, ${coreB - 10}, 0)`);
                    ctx.fillStyle = grad;

                    ctx.beginPath();
                    ctx.ellipse(x, y, s, s * 0.4, 0, 0, Math.PI * 2);
                    ctx.fill();

                    ctx.beginPath();
                    ctx.ellipse(x + s * 0.3, y - s * 0.1, s * 0.7, s * 0.3, 0, 0, Math.PI * 2);
                    ctx.fill();

                    ctx.globalAlpha = prevAlpha;
                }
            }

            // Restore clipping region
            ctx.restore();
        },

        _drawParticles(ctx, w, h) {
            const groundY = h - this._groundHeight;
            const windKts = this._windSpeed || 0;
            // Wind thresholds:
            //   < 10 kts (~18 km/h): no visible wind effect on precipitation
            //   10-20 kts: slight angle
            //   20-35 kts: moderate angle + drift
            //   35+ kts: strong angle + fast drift
            const effectiveWind = Math.max(0, windKts - 10); // no angle below 10kts
            const windDrift = Math.min(effectiveWind * 0.12, 6);

            for (const p of this._particles) {
                if (p.type === 'rain') {
                    p.y += p.speed / h;
                    p.x += windDrift * 0.0005; // gentle horizontal drift
                    if (p.y > 1) { p.y = 0; p.x = Math.random(); }
                    if (p.x > 1) p.x = 0;

                    const originY = this._altToY(p.originAlt);
                    const drawY = originY + (groundY - originY) * p.y;
                    const drawX = p.x * w;

                    ctx.strokeStyle = 'rgba(120, 170, 255, 0.5)';
                    ctx.lineWidth = 1;
                    ctx.beginPath();
                    ctx.moveTo(drawX, drawY);
                    // Angle rain with wind (only noticeable above 10kts)
                    ctx.lineTo(drawX + windDrift, drawY + p.length);
                    ctx.stroke();
                }

                if (p.type === 'snow') {
                    p.y += p.speed / h;
                    p.x += (p.drift + windDrift * 0.2) * 0.001;
                    if (p.y > 1) { p.y = 0; p.x = Math.random(); }
                    if (p.x < 0) p.x = 1;
                    if (p.x > 1) p.x = 0;

                    const originY = this._altToY(p.originAlt);
                    const drawY = originY + (groundY - originY) * p.y;
                    const drawX = p.x * w;

                    ctx.fillStyle = 'rgba(230, 235, 255, 0.7)';
                    ctx.beginPath();
                    ctx.arc(drawX, drawY, p.size, 0, Math.PI * 2);
                    ctx.fill();
                }
            }

            // Wind streaks (visible from ~15kts)
            if (this._windStreaks) {
                ctx.save();
                for (const s of this._windStreaks) {
                    s.x += s.speed / w;
                    if (s.x > 1.2) { s.x = -0.1; s.y = Math.random() * 0.85; }

                    const drawX = s.x * w;
                    const drawY = s.y * groundY;

                    ctx.strokeStyle = `rgba(200, 215, 240, ${s.opacity})`;
                    ctx.lineWidth = 0.6;
                    ctx.beginPath();
                    ctx.moveTo(drawX, drawY);
                    ctx.lineTo(drawX + s.length, drawY - 1);
                    ctx.stroke();
                }
                ctx.restore();
            }
        },

        _drawVisibility(ctx, w, h) {
            const groundY = h - this._groundHeight;
            const vis = this.metar?.visibility?.meters;
            const fog = this._fogState;

            // Explicit fog/mist/haze condition codes — show even if visibility field is missing
            if (fog?.fog || fog?.mist || fog?.haze) {
                const fogOpacity = fog.fog ? 0.55 : fog.mist ? 0.3 : 0.15;
                // Dense ground-hugging fog layer (bottom 30% of atmosphere)
                const fogTop = groundY * 0.6; // fog extends up to ~40% from ground
                const fogGrad = ctx.createLinearGradient(0, fogTop, 0, groundY);
                fogGrad.addColorStop(0, `rgba(180, 190, 210, 0)`);
                fogGrad.addColorStop(0.3, `rgba(180, 190, 210, ${fogOpacity * 0.3})`);
                fogGrad.addColorStop(0.7, `rgba(180, 190, 210, ${fogOpacity * 0.7})`);
                fogGrad.addColorStop(1, `rgba(180, 190, 210, ${fogOpacity})`);
                ctx.fillStyle = fogGrad;
                ctx.fillRect(0, fogTop, w, groundY - fogTop);

                // Wispy fog patches near ground for more realism
                if (fog.fog || fog.mist) {
                    ctx.save();
                    ctx.globalAlpha = fogOpacity * 0.4;
                    const t = this._time;
                    for (let i = 0; i < 8; i++) {
                        const fx = ((i * 137 + 42) % 100) / 100 * w;
                        const fy = groundY - 10 - ((i * 53) % 40);
                        const fw = 60 + (i % 3) * 30;
                        const drift = Math.sin(t * 0.003 + i) * 15;
                        const grad = ctx.createRadialGradient(fx + drift, fy, 0, fx + drift, fy, fw);
                        grad.addColorStop(0, 'rgba(190, 200, 220, 0.6)');
                        grad.addColorStop(0.5, 'rgba(190, 200, 220, 0.3)');
                        grad.addColorStop(1, 'rgba(190, 200, 220, 0)');
                        ctx.fillStyle = grad;
                        ctx.beginPath();
                        ctx.ellipse(fx + drift, fy, fw, fw * 0.3, 0, 0, Math.PI * 2);
                        ctx.fill();
                    }
                    ctx.restore();
                }
            }

            // General visibility-based haze (from visibility meters)
            if (!vis || vis >= 10000) return;

            const opacity = Math.max(0, Math.min(0.7, (1 - vis / 10000) * 0.7));

            // Haze rises from ground, stronger at lower altitudes
            const grad = ctx.createLinearGradient(0, 0, 0, groundY);
            grad.addColorStop(0, `rgba(180, 190, 210, ${opacity * 0.1})`);
            grad.addColorStop(0.6, `rgba(180, 190, 210, ${opacity * 0.3})`);
            grad.addColorStop(1, `rgba(180, 190, 210, ${opacity})`);
            ctx.fillStyle = grad;
            ctx.fillRect(0, 0, w, groundY);
        },

        _drawThunderstorm(ctx, w, h) {
            if (!this._tsFlash) return;
            this._tsFlash.timer++;

            if (this._tsFlash.timer >= this._tsFlash.nextFlash) {
                this._tsFlash.active = true;
                this._tsFlash.timer = 0;
                this._tsFlash.nextFlash = 90 + Math.random() * 200;

                // Flash lasts 3-5 frames
                setTimeout(() => { this._tsFlash.active = false; }, 80);
            }

            if (this._tsFlash.active) {
                ctx.fillStyle = 'rgba(255, 255, 240, 0.15)';
                ctx.fillRect(0, 0, w, h);
            }
        },

        // Station local time — computed from UTC observation + longitude offset,
        // then ticked forward in real-time from the animation loop
        _getStationLocalTime() {
            if (!this.metar?.observed) return null;
            const lon = this.metar.longitude;
            if (lon == null) return null;

            // Approximate timezone offset from longitude (1 hour per 15°)
            // This is close enough for display — proper IANA timezones would need a geo lookup
            const offsetHours = lon / 15;
            const now = new Date();
            // Current UTC + station offset
            const utcMs = now.getTime() + now.getTimezoneOffset() * 60000;
            return new Date(utcMs + offsetHours * 3600000);
        },

        _drawStationClock(ctx, w, h) {
            if (!this.metar) return;
            const localTime = this._getStationLocalTime();
            if (!localTime) return;

            const cat = this.metar.flight_category;

            ctx.save();

            // Position: top-left, below the altitude scale labels
            const px = isMobile ? 55 : 60;
            const py = 14;

            // Background box
            const boxW = isMobile ? 100 : 120;
            const boxH = isMobile ? 34 : 40;
            ctx.fillStyle = 'rgba(0, 0, 0, 0.45)';
            ctx.beginPath();
            if (ctx.roundRect) { ctx.roundRect(px - 4, py - 4, boxW, boxH, 5); }
            else { ctx.rect(px - 4, py - 4, boxW, boxH); }
            ctx.fill();

            // Local time (HH:MM:SS)
            const hh = String(localTime.getHours()).padStart(2, '0');
            const mm = String(localTime.getMinutes()).padStart(2, '0');
            const ss = String(localTime.getSeconds()).padStart(2, '0');
            // Blink the colon every other second for a clock feel
            const sep = localTime.getSeconds() % 2 === 0 ? ':' : ' ';

            ctx.fillStyle = 'rgba(255, 255, 255, 0.8)';
            ctx.font = `bold ${isMobile ? 13 : 16}px "SF Mono", "Cascadia Code", "Consolas", monospace`;
            ctx.textAlign = 'left';
            ctx.textBaseline = 'top';
            ctx.fillText(hh + sep + mm + sep + ss, px, py);

            // Date + "LT" label below time
            const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            const day = dayNames[localTime.getDay()];
            const dd = String(localTime.getDate()).padStart(2, '0');
            const mon = String(localTime.getMonth() + 1).padStart(2, '0');
            ctx.fillStyle = 'rgba(255, 255, 255, 0.4)';
            ctx.font = `${isMobile ? 8 : 9}px system-ui, sans-serif`;
            ctx.fillText(day + ' ' + dd + '/' + mon + '  LT', px, py + (isMobile ? 16 : 20));

            // Flight category indicator — colored dot + label right of the clock
            if (cat) {
                const catColors = { VFR: '#22c55e', MVFR: '#3b82f6', IFR: '#ef4444', LIFR: '#d946ef' };
                const color = catColors[cat] || '#9ca3af';
                const dotX = px + boxW + 8;
                const dotY = py + (isMobile ? 6 : 8);

                // Dot
                ctx.fillStyle = color;
                ctx.beginPath();
                ctx.arc(dotX, dotY, isMobile ? 4 : 5, 0, Math.PI * 2);
                ctx.fill();

                // Label
                ctx.fillStyle = color;
                ctx.font = `bold ${isMobile ? 9 : 11}px system-ui, sans-serif`;
                ctx.textAlign = 'left';
                ctx.fillText(cat, dotX + (isMobile ? 7 : 8), dotY + 1);
            }

            ctx.restore();
        },

        _drawCompass(ctx, w, h) {
            if (!this.metar?.wind) return;
            const deg = this.metar.wind.direction;
            const spd = this.metar.wind.speed_kts ?? 0;
            if (deg == null && spd === 0) return;

            const r = isMobile ? 32 : 42;
            const cx = w - r - 14;
            const cy = r + 14;

            ctx.save();

            // Outer background disc
            ctx.fillStyle = 'rgba(0, 0, 0, 0.5)';
            ctx.beginPath();
            ctx.arc(cx, cy, r + 6, 0, Math.PI * 2);
            ctx.fill();

            // Compass ring — outer edge
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.25)';
            ctx.lineWidth = 1.5;
            ctx.beginPath();
            ctx.arc(cx, cy, r, 0, Math.PI * 2);
            ctx.stroke();

            // Inner ring
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.08)';
            ctx.lineWidth = 0.5;
            ctx.beginPath();
            ctx.arc(cx, cy, r * 0.55, 0, Math.PI * 2);
            ctx.stroke();

            // Tick marks every 10°, longer every 30°
            for (let d = 0; d < 360; d += 10) {
                const rad = (d - 90) * Math.PI / 180;
                const isMajor = d % 30 === 0;
                const outerR = r - 1;
                const innerR = isMajor ? r - (isMobile ? 7 : 9) : r - (isMobile ? 4 : 5);
                ctx.strokeStyle = isMajor ? 'rgba(255, 255, 255, 0.4)' : 'rgba(255, 255, 255, 0.15)';
                ctx.lineWidth = isMajor ? 1 : 0.5;
                ctx.beginPath();
                ctx.moveTo(cx + Math.cos(rad) * innerR, cy + Math.sin(rad) * innerR);
                ctx.lineTo(cx + Math.cos(rad) * outerR, cy + Math.sin(rad) * outerR);
                ctx.stroke();
            }

            // Heading numbers every 30° (aviation style: 3, 6, 9, 12, 15, 18, 21, 24, 27, 30, 33, 36)
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            const fontSize = isMobile ? 7 : 9;
            ctx.font = `${fontSize}px system-ui, sans-serif`;
            const labelR = r - (isMobile ? 13 : 16);
            for (let d = 0; d < 360; d += 30) {
                const rad = (d - 90) * Math.PI / 180;
                const lx = cx + Math.cos(rad) * labelR;
                const ly = cy + Math.sin(rad) * labelR;
                const hdg = d === 0 ? 36 : d / 10;
                // N (36) slightly brighter
                ctx.fillStyle = d === 0 ? 'rgba(255, 255, 255, 0.65)' : 'rgba(255, 255, 255, 0.4)';
                ctx.fillText(hdg.toString(), lx, ly);
            }

            // North triangle marker on outer ring
            ctx.fillStyle = 'rgba(255, 255, 255, 0.6)';
            ctx.beginPath();
            ctx.moveTo(cx, cy - r + 1);
            ctx.lineTo(cx - 3, cy - r - 4);
            ctx.lineTo(cx + 3, cy - r - 4);
            ctx.closePath();
            ctx.fill();

            // Wind direction indicator
            if (deg != null) {
                const windRad = (deg - 90) * Math.PI / 180; // FROM direction

                // Wind source marker on the ring — wide chevron pointing inward
                const markerOuter = r - 1;
                const markerInner = r * 0.58;
                const chevronHalf = (isMobile ? 8 : 10) * Math.PI / 180; // half-width in radians
                // Filled wedge from outer ring pointing inward
                ctx.fillStyle = 'rgba(255, 255, 255, 0.75)';
                ctx.beginPath();
                ctx.moveTo(cx + Math.cos(windRad) * markerOuter, cy + Math.sin(windRad) * markerOuter);
                ctx.lineTo(cx + Math.cos(windRad - chevronHalf) * markerInner, cy + Math.sin(windRad - chevronHalf) * markerInner);
                ctx.lineTo(cx + Math.cos(windRad + chevronHalf) * markerInner, cy + Math.sin(windRad + chevronHalf) * markerInner);
                ctx.closePath();
                ctx.fill();

                // Downwind direction — thin line from center outward (opposite)
                const downRad = windRad + Math.PI;
                ctx.strokeStyle = 'rgba(255, 255, 255, 0.25)';
                ctx.lineWidth = 1;
                ctx.setLineDash([3, 3]);
                ctx.beginPath();
                ctx.moveTo(cx, cy);
                ctx.lineTo(cx + Math.cos(downRad) * (r * 0.5), cy + Math.sin(downRad) * (r * 0.5));
                ctx.stroke();
                ctx.setLineDash([]);
            }

            // Center dot
            ctx.fillStyle = 'rgba(255, 255, 255, 0.3)';
            ctx.beginPath();
            ctx.arc(cx, cy, 2, 0, Math.PI * 2);
            ctx.fill();

            // Info box below compass — degrees and speed
            const boxY = cy + r + 10;
            const boxW = isMobile ? 52 : 64;
            const boxH = isMobile ? 18 : 22;
            const boxX = cx - boxW / 2;
            ctx.fillStyle = 'rgba(0, 0, 0, 0.45)';
            ctx.beginPath();
            if (ctx.roundRect) { ctx.roundRect(boxX, boxY, boxW, boxH, 4); }
            else { ctx.rect(boxX, boxY, boxW, boxH); }
            ctx.fill();

            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            if (deg != null) {
                // "310° 11kt" style
                ctx.fillStyle = 'rgba(255, 255, 255, 0.75)';
                ctx.font = `bold ${isMobile ? 9 : 11}px system-ui, sans-serif`;
                ctx.fillText(deg + '°  ' + spd + 'kt', cx, boxY + boxH / 2);
            } else {
                ctx.fillStyle = 'rgba(255, 255, 255, 0.6)';
                ctx.font = `bold ${isMobile ? 9 : 11}px system-ui, sans-serif`;
                ctx.fillText('VRB ' + spd + 'kt', cx, boxY + boxH / 2);
            }

            ctx.restore();
        },

        _drawPressureGauge(ctx, w, h) {
            const pressure = this.metar?.pressure;
            if (pressure == null) return;

            // Small vertical gauge on the right, below compass
            const compassR = isMobile ? 32 : 42;
            const gx = w - 20;
            const gy = compassR * 2 + 56;
            const gw = 8;
            const gh = isMobile ? 50 : 70;

            ctx.save();

            // Background
            ctx.fillStyle = 'rgba(0, 0, 0, 0.4)';
            const bgPad = 20;
            const bgX = gx - gw / 2 - bgPad, bgY = gy - 8, bgW = gw + bgPad * 2, bgH = gh + 22;
            ctx.beginPath();
            if (ctx.roundRect) { ctx.roundRect(bgX, bgY, bgW, bgH, 6); }
            else { ctx.rect(bgX, bgY, bgW, bgH); }
            ctx.fill();

            // Gauge range: 950 to 1060 hPa (covers most weather)
            const minP = 950, maxP = 1060;
            const clamped = Math.max(minP, Math.min(maxP, pressure));
            const ratio = (clamped - minP) / (maxP - minP); // 0=low, 1=high

            // Gauge track
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.15)';
            ctx.lineWidth = gw;
            ctx.lineCap = 'round';
            ctx.beginPath();
            ctx.moveTo(gx, gy);
            ctx.lineTo(gx, gy + gh);
            ctx.stroke();

            // Filled portion (bottom-up, colored by pressure)
            // Low pressure = red/orange, normal = green, high = blue
            let gaugeColor;
            if (pressure < 1000) gaugeColor = 'rgba(220, 120, 50, 0.7)';
            else if (pressure < 1013) gaugeColor = 'rgba(200, 200, 80, 0.6)';
            else if (pressure < 1030) gaugeColor = 'rgba(80, 180, 120, 0.6)';
            else gaugeColor = 'rgba(80, 140, 220, 0.6)';

            ctx.strokeStyle = gaugeColor;
            ctx.lineWidth = gw - 2;
            ctx.beginPath();
            ctx.moveTo(gx, gy + gh);
            ctx.lineTo(gx, gy + gh - (gh * ratio));
            ctx.stroke();

            // Indicator line at current value
            const indY = gy + gh - (gh * ratio);
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.6)';
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(gx - gw, indY);
            ctx.lineTo(gx + gw, indY);
            ctx.stroke();

            // Standard pressure mark (1013.25 hPa)
            const stdRatio = (1013.25 - minP) / (maxP - minP);
            const stdY = gy + gh - (gh * stdRatio);
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.15)';
            ctx.lineWidth = 0.5;
            ctx.setLineDash([2, 2]);
            ctx.beginPath();
            ctx.moveTo(gx - gw - 2, stdY);
            ctx.lineTo(gx + gw + 2, stdY);
            ctx.stroke();
            ctx.setLineDash([]);

            // Labels
            ctx.fillStyle = 'rgba(255, 255, 255, 0.5)';
            ctx.font = `${isMobile ? 7 : 8}px system-ui, sans-serif`;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText('QNH', gx, gy - 5);

            // Value
            ctx.fillStyle = 'rgba(255, 255, 255, 0.6)';
            ctx.font = `bold ${isMobile ? 8 : 9}px system-ui, sans-serif`;
            ctx.fillText(Math.round(pressure), gx, gy + gh + 10);

            ctx.restore();
        },

        _drawGround(ctx, w, h) {
            const groundY = h - this._groundHeight;
            const t = this._time;
            const temp = this.metar?.temperature;

            // Temperature-based ground colors:
            //   < -5°C: frosty white/blue (snow on ground)
            //   -5 to 5°C: muted brown/grey (dormant winter)
            //   5 to 15°C: medium green (spring/autumn)
            //   15 to 25°C: lush green (summer)
            //   > 25°C: warm green with yellow tint (dry heat)
            //   > 35°C: parched yellow-brown
            let groundTop, groundMid, groundBot, terrainFill, horizonColor;
            if (temp != null && temp < -5) {
                // Deep frost — blue-grey, icy
                groundTop = '#2a3040'; groundMid = '#252a38'; groundBot = '#1a1e28';
                terrainFill = '#2a3040'; horizonColor = 'rgba(160, 170, 200, 0.3)';
            } else if (temp != null && temp < 5) {
                // Cold — dormant brown-grey
                groundTop = '#1e2618'; groundMid = '#1a2214'; groundBot = '#121810';
                terrainFill = '#1e2618'; horizonColor = 'rgba(100, 130, 90, 0.25)';
            } else if (temp != null && temp > 35) {
                // Scorching — parched yellow-brown, cracked earth
                groundTop = '#3a3018'; groundMid = '#342a12'; groundBot = '#241c08';
                terrainFill = '#3a3018'; horizonColor = 'rgba(180, 150, 60, 0.35)';
            } else if (temp != null && temp > 25) {
                // Hot — dry olive-yellow, sun-baked
                groundTop = '#303820'; groundMid = '#2a3018'; groundBot = '#1e2210';
                terrainFill = '#303820'; horizonColor = 'rgba(160, 170, 70, 0.3)';
            } else if (temp != null && temp > 15) {
                // Warm — lush green
                groundTop = '#1a3218'; groundMid = '#162c14'; groundBot = '#0e1e0e';
                terrainFill = '#1a3218'; horizonColor = 'rgba(80, 180, 90, 0.3)';
            } else {
                // Mild / default (5-15°C) — medium green
                groundTop = '#1a2e1a'; groundMid = '#152814'; groundBot = '#0d1a0d';
                terrainFill = '#1a2e1a'; horizonColor = 'rgba(100, 160, 100, 0.25)';
            }

            // Ground fill
            const grad = ctx.createLinearGradient(0, groundY, 0, h);
            grad.addColorStop(0, groundTop);
            grad.addColorStop(0.3, groundMid);
            grad.addColorStop(1, groundBot);
            ctx.fillStyle = grad;
            ctx.fillRect(0, groundY, w, this._groundHeight);

            // Frost/snow overlay on ground when freezing
            if (temp != null && temp < 0) {
                const frostOpacity = Math.min(0.35, Math.abs(temp) * 0.03);
                ctx.fillStyle = `rgba(220, 230, 245, ${frostOpacity})`;
                ctx.fillRect(0, groundY, w, this._groundHeight);
            }

            // Flat terrain with very gentle undulation (it's Holland after all)
            ctx.save();
            ctx.fillStyle = terrainFill;
            ctx.beginPath();
            ctx.moveTo(0, groundY);
            for (let x = 0; x <= w; x += 3) {
                const hill = Math.sin(x * 0.005) * 3 + Math.sin(x * 0.02) * 1.5;
                ctx.lineTo(x, groundY - Math.max(0, hill));
            }
            ctx.lineTo(w, groundY);
            ctx.closePath();
            ctx.fill();
            ctx.restore();

            // Dike / horizon line
            ctx.strokeStyle = horizonColor;
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(0, groundY);
            ctx.lineTo(w, groundY);
            ctx.stroke();

            // ─── Dutch Village Scene ────────────────────────────
            ctx.save();
            const gy = groundY - 2; // base for all structures

            // ── Helper: draw a stepped gable (trapgevel) ──
            const drawSteppedGable = (x, baseY, bw, bh, steps, wallColor) => {
                // Wall body
                ctx.fillStyle = wallColor;
                ctx.fillRect(x - bw / 2, baseY - bh, bw, bh);
                // Stepped gable on top
                ctx.fillStyle = wallColor;
                const stepW = bw / (steps * 2);
                const stepH = 3;
                let sy = baseY - bh;
                let sl = x - bw / 2;
                let sr = x + bw / 2;
                for (let i = 0; i < steps; i++) {
                    sl += stepW;
                    sr -= stepW;
                    sy -= stepH;
                    ctx.fillRect(sl, sy, sr - sl, stepH);
                }
                // Capstone
                ctx.fillRect(sl + 1, sy - 2, (sr - sl) - 2, 2);
            };

            // ── Helper: draw a neck gable (halsgevel) ──
            const drawNeckGable = (x, baseY, bw, bh, neckH, wallColor) => {
                ctx.fillStyle = wallColor;
                ctx.fillRect(x - bw / 2, baseY - bh, bw, bh);
                // Neck (narrower continuation)
                const nw = bw * 0.4;
                ctx.fillRect(x - nw / 2, baseY - bh - neckH, nw, neckH);
                // Decorative scroll/volutes on sides
                ctx.strokeStyle = 'rgba(80, 70, 60, 0.4)';
                ctx.lineWidth = 0.8;
                // Left scroll
                ctx.beginPath();
                ctx.moveTo(x - bw / 2, baseY - bh);
                ctx.quadraticCurveTo(x - bw / 2 - 2, baseY - bh - neckH * 0.5, x - nw / 2, baseY - bh - neckH);
                ctx.stroke();
                // Right scroll
                ctx.beginPath();
                ctx.moveTo(x + bw / 2, baseY - bh);
                ctx.quadraticCurveTo(x + bw / 2 + 2, baseY - bh - neckH * 0.5, x + nw / 2, baseY - bh - neckH);
                ctx.stroke();
                // Top triangle
                ctx.fillStyle = wallColor;
                ctx.beginPath();
                ctx.moveTo(x - nw / 2 - 1, baseY - bh - neckH);
                ctx.lineTo(x, baseY - bh - neckH - 4);
                ctx.lineTo(x + nw / 2 + 1, baseY - bh - neckH);
                ctx.closePath();
                ctx.fill();
            };

            // ── Helper: draw a bell gable (klokgevel) ──
            const drawBellGable = (x, baseY, bw, bh, bellH, wallColor) => {
                ctx.fillStyle = wallColor;
                ctx.fillRect(x - bw / 2, baseY - bh, bw, bh);
                // Bell curve on top
                ctx.beginPath();
                ctx.moveTo(x - bw / 2, baseY - bh);
                ctx.bezierCurveTo(
                    x - bw / 2 - 1, baseY - bh - bellH * 0.7,
                    x - bw * 0.15, baseY - bh - bellH,
                    x, baseY - bh - bellH - 2
                );
                ctx.bezierCurveTo(
                    x + bw * 0.15, baseY - bh - bellH,
                    x + bw / 2 + 1, baseY - bh - bellH * 0.7,
                    x + bw / 2, baseY - bh
                );
                ctx.closePath();
                ctx.fill();
            };

            // ── Helper: draw windows ──
            const drawWindows = (x, baseY, bw, bh, rows, cols) => {
                const winW = 1.5, winH = 2;
                const padX = (bw - cols * winW) / (cols + 1);
                const padY = (bh - rows * winH) / (rows + 1);
                for (let r = 0; r < rows; r++) {
                    for (let c = 0; c < cols; c++) {
                        const wx = x - bw / 2 + padX + c * (winW + padX);
                        const wy = baseY - bh + padY + r * (winH + padY);
                        ctx.fillStyle = 'rgba(255, 210, 120, 0.25)';
                        ctx.fillRect(wx, wy, winW, winH);
                        // Glazing bar
                        ctx.strokeStyle = 'rgba(40, 30, 20, 0.3)';
                        ctx.lineWidth = 0.3;
                        ctx.beginPath();
                        ctx.moveTo(wx + winW / 2, wy);
                        ctx.lineTo(wx + winW / 2, wy + winH);
                        ctx.stroke();
                    }
                }
            };

            // ── Helper: draw a door ──
            const drawDoor = (x, baseY, dw, dh) => {
                ctx.fillStyle = 'rgba(30, 20, 15, 0.6)';
                ctx.fillRect(x - dw / 2, baseY - dh, dw, dh);
                // Arched top
                ctx.beginPath();
                ctx.arc(x, baseY - dh, dw / 2, Math.PI, 0);
                ctx.fill();
                // Door knob
                ctx.fillStyle = 'rgba(180, 160, 80, 0.3)';
                ctx.beginPath();
                ctx.arc(x + dw * 0.25, baseY - dh * 0.4, 0.5, 0, Math.PI * 2);
                ctx.fill();
            };

            // ── Wind turbines (modern, tall, behind everything) ──
            const turbinePositions = [0.02, 0.10, 0.22, 0.82, 0.95];
            for (let i = 0; i < turbinePositions.length; i++) {
                const tx = turbinePositions[i] * w;
                const towerH = 30 + (i % 3) * 4;
                const bladeLen = 16 + (i % 2) * 3;
                const hubY = groundY - towerH;

                // Tower (tapered)
                ctx.strokeStyle = 'rgba(180, 190, 200, 0.35)';
                ctx.lineWidth = 2;
                ctx.beginPath();
                ctx.moveTo(tx - 1, groundY - 2);
                ctx.lineTo(tx, hubY);
                ctx.stroke();
                ctx.lineWidth = 1.2;
                ctx.beginPath();
                ctx.moveTo(tx + 1, groundY - 2);
                ctx.lineTo(tx, hubY);
                ctx.stroke();

                // Nacelle
                ctx.fillStyle = 'rgba(200, 210, 220, 0.4)';
                ctx.fillRect(tx - 1, hubY - 1.5, 3, 3);

                // Hub
                ctx.fillStyle = 'rgba(210, 220, 230, 0.5)';
                ctx.beginPath();
                ctx.arc(tx, hubY, 1.5, 0, Math.PI * 2);
                ctx.fill();

                // Blades — 3 blades, rotation speed scales with wind (noticeable above 15kts)
                const windFactor = 1 + Math.max(0, (this._windSpeed || 0) - 10) * 0.04;
                const rpm = (0.008 + (i * 0.002)) * windFactor;
                const angle = t * rpm + i * 2.1;
                ctx.lineWidth = 1.2;
                for (let b = 0; b < 3; b++) {
                    const ba = angle + b * (Math.PI * 2 / 3);
                    const bx = tx + Math.cos(ba) * bladeLen;
                    const by = hubY + Math.sin(ba) * bladeLen;
                    // Blade with slight taper
                    ctx.strokeStyle = 'rgba(210, 220, 230, 0.45)';
                    ctx.beginPath();
                    ctx.moveTo(tx, hubY);
                    ctx.lineTo(bx, by);
                    ctx.stroke();
                }
            }

            // ── Village houses — left cluster (along a canal street) ──
            // Stepped gable houses
            drawSteppedGable(0.28 * w, gy, 10, 14, 3, '#3a2218');
            drawWindows(0.28 * w, gy, 10, 14, 3, 2);
            drawDoor(0.28 * w, gy, 3, 4);

            drawSteppedGable(0.32 * w, gy, 8, 16, 4, '#2e1a22');
            drawWindows(0.32 * w, gy, 8, 16, 4, 2);

            // Neck gable
            drawNeckGable(0.355 * w, gy, 9, 13, 6, '#33201a');
            drawWindows(0.355 * w, gy, 9, 13, 3, 2);
            drawDoor(0.355 * w, gy, 2.5, 4);

            // Bell gable
            drawBellGable(0.39 * w, gy, 8, 15, 5, '#2a1e28');
            drawWindows(0.39 * w, gy, 8, 15, 3, 2);

            // Simple triangular gable
            ctx.fillStyle = '#351e14';
            ctx.fillRect(0.42 * w - 5, gy - 12, 10, 12);
            ctx.fillStyle = '#301a12';
            ctx.beginPath();
            ctx.moveTo(0.42 * w - 6, gy - 12);
            ctx.lineTo(0.42 * w, gy - 18);
            ctx.lineTo(0.42 * w + 6, gy - 12);
            ctx.closePath();
            ctx.fill();
            drawWindows(0.42 * w, gy, 10, 12, 2, 2);
            drawDoor(0.42 * w, gy, 2.5, 3.5);

            // Small stepped gable
            drawSteppedGable(0.45 * w, gy, 7, 11, 2, '#2c1a20');
            drawWindows(0.45 * w, gy, 7, 11, 2, 2);

            // ── Church (centrum) ──
            const cx = 0.54 * w;
            // Church nave
            ctx.fillStyle = '#221825';
            ctx.fillRect(cx - 8, gy - 16, 16, 16);
            // Church roof
            ctx.fillStyle = '#1a1220';
            ctx.beginPath();
            ctx.moveTo(cx - 9, gy - 16);
            ctx.lineTo(cx, gy - 22);
            ctx.lineTo(cx + 9, gy - 16);
            ctx.closePath();
            ctx.fill();
            // Tower base
            ctx.fillStyle = '#251a28';
            ctx.fillRect(cx - 4, gy - 28, 8, 12);
            // Tower belfry (clock section)
            ctx.fillStyle = '#201525';
            ctx.fillRect(cx - 3.5, gy - 34, 7, 6);
            // Arched belfry window
            ctx.fillStyle = 'rgba(180, 200, 220, 0.15)';
            ctx.fillRect(cx - 1.5, gy - 33, 3, 3);
            ctx.beginPath();
            ctx.arc(cx, gy - 33, 1.5, Math.PI, 0);
            ctx.fill();
            // Spire
            ctx.fillStyle = '#1a1020';
            ctx.beginPath();
            ctx.moveTo(cx - 3, gy - 34);
            ctx.lineTo(cx, gy - 46);
            ctx.lineTo(cx + 3, gy - 34);
            ctx.closePath();
            ctx.fill();
            // Cross/weathervane on top
            ctx.strokeStyle = 'rgba(200, 180, 100, 0.4)';
            ctx.lineWidth = 0.6;
            ctx.beginPath();
            ctx.moveTo(cx, gy - 46);
            ctx.lineTo(cx, gy - 49);
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(cx - 1.5, gy - 47.5);
            ctx.lineTo(cx + 1.5, gy - 47.5);
            ctx.stroke();
            // Church windows (gothic arches)
            for (let i = -2; i <= 2; i++) {
                if (i === 0) continue;
                const wx = cx + i * 3;
                ctx.fillStyle = 'rgba(180, 160, 220, 0.12)';
                ctx.fillRect(wx - 0.8, gy - 12, 1.6, 4);
                ctx.beginPath();
                ctx.arc(wx, gy - 12, 0.8, Math.PI, 0);
                ctx.fill();
            }
            // Rose window
            ctx.strokeStyle = 'rgba(180, 160, 220, 0.2)';
            ctx.lineWidth = 0.5;
            ctx.beginPath();
            ctx.arc(cx, gy - 18.5, 2, 0, Math.PI * 2);
            ctx.stroke();

            // ── Village houses — right cluster ──
            drawBellGable(0.61 * w, gy, 9, 14, 5, '#30201a');
            drawWindows(0.61 * w, gy, 9, 14, 3, 2);
            drawDoor(0.61 * w, gy, 2.5, 4);

            drawSteppedGable(0.645 * w, gy, 8, 17, 4, '#2a1820');
            drawWindows(0.645 * w, gy, 8, 17, 4, 2);

            drawNeckGable(0.675 * w, gy, 9, 12, 5, '#33221a');
            drawWindows(0.675 * w, gy, 9, 12, 3, 2);

            // Wider warehouse-style with hoist beam
            ctx.fillStyle = '#2e1a18';
            ctx.fillRect(0.71 * w - 6, gy - 13, 12, 13);
            ctx.fillStyle = '#281518';
            ctx.beginPath();
            ctx.moveTo(0.71 * w - 7, gy - 13);
            ctx.lineTo(0.71 * w, gy - 19);
            ctx.lineTo(0.71 * w + 7, gy - 13);
            ctx.closePath();
            ctx.fill();
            drawWindows(0.71 * w, gy, 12, 13, 3, 3);
            // Hoist beam sticking out from top
            ctx.strokeStyle = 'rgba(100, 80, 50, 0.4)';
            ctx.lineWidth = 0.8;
            ctx.beginPath();
            ctx.moveTo(0.71 * w, gy - 17);
            ctx.lineTo(0.71 * w + 6, gy - 17);
            ctx.stroke();
            // Hook
            ctx.strokeStyle = 'rgba(140, 120, 70, 0.3)';
            ctx.beginPath();
            ctx.moveTo(0.71 * w + 5.5, gy - 17);
            ctx.lineTo(0.71 * w + 5.5, gy - 15);
            ctx.stroke();

            drawSteppedGable(0.75 * w, gy, 7, 12, 3, '#2c1822');
            drawWindows(0.75 * w, gy, 7, 12, 3, 2);

            // ── Traditional windmill (on the edge of village) ──
            const mx = 0.13 * w;
            // Mill body (tapered)
            ctx.fillStyle = '#2a2018';
            ctx.beginPath();
            ctx.moveTo(mx - 5, gy);
            ctx.lineTo(mx - 3, gy - 20);
            ctx.lineTo(mx + 3, gy - 20);
            ctx.lineTo(mx + 5, gy);
            ctx.closePath();
            ctx.fill();
            // Cap (onion-shaped top)
            ctx.fillStyle = '#1e1815';
            ctx.beginPath();
            ctx.moveTo(mx - 3.5, gy - 20);
            ctx.quadraticCurveTo(mx - 4, gy - 24, mx, gy - 25);
            ctx.quadraticCurveTo(mx + 4, gy - 24, mx + 3.5, gy - 20);
            ctx.closePath();
            ctx.fill();
            // Gallery/balcony around body
            ctx.strokeStyle = 'rgba(100, 80, 50, 0.35)';
            ctx.lineWidth = 0.5;
            ctx.beginPath();
            ctx.moveTo(mx - 6, gy - 12);
            ctx.lineTo(mx + 6, gy - 12);
            ctx.stroke();
            // Mill sails — 4 blades, speed scales with wind (noticeable above 15kts)
            const millWindFactor = 1 + Math.max(0, (this._windSpeed || 0) - 10) * 0.04;
            const millAngle = t * 0.012 * millWindFactor;
            ctx.strokeStyle = 'rgba(140, 120, 90, 0.45)';
            ctx.lineWidth = 1;
            for (let b = 0; b < 4; b++) {
                const ba = millAngle + b * (Math.PI / 2);
                const bLen = 18;
                const ex = mx + Math.cos(ba) * bLen;
                const ey = (gy - 22) + Math.sin(ba) * bLen;
                ctx.beginPath();
                ctx.moveTo(mx, gy - 22);
                ctx.lineTo(ex, ey);
                ctx.stroke();
                // Sail lattice (small cross-bars)
                for (let s = 0.3; s <= 0.9; s += 0.2) {
                    const sx = mx + Math.cos(ba) * bLen * s;
                    const sy = (gy - 22) + Math.sin(ba) * bLen * s;
                    const perp = ba + Math.PI / 2;
                    ctx.lineWidth = 0.4;
                    ctx.beginPath();
                    ctx.moveTo(sx + Math.cos(perp) * 2, sy + Math.sin(perp) * 2);
                    ctx.lineTo(sx - Math.cos(perp) * 2, sy - Math.sin(perp) * 2);
                    ctx.stroke();
                    ctx.lineWidth = 1;
                }
            }
            // Small windows on mill
            ctx.fillStyle = 'rgba(255, 210, 120, 0.2)';
            ctx.fillRect(mx - 1, gy - 16, 2, 2);
            ctx.fillRect(mx - 1, gy - 8, 2, 2);

            // ── Canal (cut into the ground between house clusters) ──
            const canalX = 0.485 * w;
            const canalW = 0.025 * w;
            const canalDepth = 10;
            // Cut out the ground — draw over with dark water color
            // Steep quay walls on each side
            ctx.fillStyle = '#0a1208'; // dark earth below ground
            ctx.fillRect(canalX, gy, canalW, canalDepth);
            // Water filling the canal (below ground level)
            ctx.fillStyle = 'rgba(12, 30, 55, 0.9)';
            ctx.fillRect(canalX + 0.5, gy + 2, canalW - 1, canalDepth - 2);
            // Quay walls — vertical brick edges
            ctx.strokeStyle = 'rgba(80, 65, 45, 0.5)';
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(canalX, gy);
            ctx.lineTo(canalX, gy + canalDepth);
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(canalX + canalW, gy);
            ctx.lineTo(canalX + canalW, gy + canalDepth);
            ctx.stroke();
            // Water shimmer
            ctx.strokeStyle = 'rgba(80, 130, 180, 0.2)';
            ctx.lineWidth = 0.4;
            ctx.beginPath();
            ctx.moveTo(canalX + 2, gy + 4);
            ctx.lineTo(canalX + canalW - 2, gy + 4);
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(canalX + 3, gy + 7);
            ctx.lineTo(canalX + canalW - 3, gy + 7);
            ctx.stroke();

            // ── Trees — tall poplars lining the road/canal, and smaller trees ──
            const drawPoplar = (tx, treeH) => {
                // Trunk
                ctx.strokeStyle = 'rgba(50, 70, 40, 0.5)';
                ctx.lineWidth = 1;
                ctx.beginPath();
                ctx.moveTo(tx, gy);
                ctx.lineTo(tx, gy - treeH);
                ctx.stroke();
                // Narrow columnar foliage (characteristic of Lombardy poplars)
                ctx.fillStyle = 'rgba(25, 50, 25, 0.55)';
                ctx.beginPath();
                ctx.ellipse(tx, gy - treeH * 0.6, 2.5, treeH * 0.45, 0, 0, Math.PI * 2);
                ctx.fill();
            };

            const drawRoundTree = (tx, treeH) => {
                ctx.strokeStyle = 'rgba(50, 70, 40, 0.45)';
                ctx.lineWidth = 0.8;
                ctx.beginPath();
                ctx.moveTo(tx, gy);
                ctx.lineTo(tx, gy - treeH * 0.5);
                ctx.stroke();
                ctx.fillStyle = 'rgba(30, 55, 30, 0.5)';
                ctx.beginPath();
                ctx.arc(tx, gy - treeH * 0.65, treeH * 0.35, 0, Math.PI * 2);
                ctx.fill();
            };

            // Poplar row flanking canal (not in the water!)
            drawPoplar(0.47 * w, 18);
            drawPoplar(0.48 * w, 20);
            drawPoplar(0.515 * w, 19);
            drawPoplar(0.525 * w, 17);

            // Trees scattered around village
            drawRoundTree(0.16 * w, 12);
            drawRoundTree(0.24 * w, 10);
            drawPoplar(0.26 * w, 15);
            drawRoundTree(0.77 * w, 11);
            drawRoundTree(0.82 * w, 13);
            drawPoplar(0.85 * w, 16);
            drawPoplar(0.88 * w, 14);

            // ── Windsock ──
            // On mobile, move left to avoid the cloud layers legend overlay (bottom-right)
            const wsX = isMobile ? 0.55 * w : 0.79 * w;
            const poleH = 22;
            const wsWind = this._windSpeed || 0;
            // Pole
            ctx.strokeStyle = 'rgba(180, 180, 180, 0.5)';
            ctx.lineWidth = 1.2;
            ctx.beginPath();
            ctx.moveTo(wsX, gy);
            ctx.lineTo(wsX, gy - poleH);
            ctx.stroke();
            // Hoop at top
            ctx.strokeStyle = 'rgba(200, 200, 200, 0.4)';
            ctx.lineWidth = 0.8;
            ctx.beginPath();
            ctx.arc(wsX, gy - poleH, 1.5, 0, Math.PI * 2);
            ctx.stroke();
            // Sock — extends right, droop depends on wind
            // Standard windsock: fully extended at 15kts, limp at 0
            // 0 kts: hangs straight down
            // 3 kts: ~one stripe lifts (15°)
            // 7 kts: ~45 degrees
            // 15+ kts: fully horizontal
            const sockLen = 14;
            const extension = Math.min(wsWind / 15, 1); // 0=limp, 1=fully extended
            const sockAngle = (Math.PI / 2) * (1 - extension); // PI/2=down, 0=horizontal
            const sockTopY = gy - poleH;
            const sockEndX = wsX + Math.cos(sockAngle) * sockLen;
            const sockEndY = sockTopY + Math.sin(sockAngle) * sockLen;
            // Tapered cone shape with 5 red/white stripes
            const segments = 5;
            for (let s = 0; s < segments; s++) {
                const t0 = s / segments;
                const t1 = (s + 1) / segments;
                // Flutter: slight wave on outer segments in wind
                const flutter0 = wsWind > 5 && s >= 2 ? Math.sin(t * 0.15 + s * 1.5) * wsWind * 0.03 : 0;
                const flutter1 = wsWind > 5 && s >= 2 ? Math.sin(t * 0.15 + (s + 1) * 1.5) * wsWind * 0.03 : 0;
                const x0 = wsX + (sockEndX - wsX) * t0;
                const y0 = sockTopY + (sockEndY - sockTopY) * t0 + flutter0;
                const x1 = wsX + (sockEndX - wsX) * t1;
                const y1 = sockTopY + (sockEndY - sockTopY) * t1 + flutter1;
                // Taper: wide at hoop, narrow at tip
                const r0 = 3 * (1 - t0 * 0.7);
                const r1 = 3 * (1 - t1 * 0.7);
                // Perpendicular direction for width
                const dx = x1 - x0, dy = y1 - y0;
                const segLen = Math.sqrt(dx * dx + dy * dy) || 1;
                const nx = -dy / segLen, ny = dx / segLen;
                // Alternating orange-red / white
                ctx.fillStyle = s % 2 === 0 ? 'rgba(200, 60, 30, 0.7)' : 'rgba(220, 220, 220, 0.7)';
                ctx.beginPath();
                ctx.moveTo(x0 + nx * r0, y0 + ny * r0);
                ctx.lineTo(x1 + nx * r1, y1 + ny * r1);
                ctx.lineTo(x1 - nx * r1, y1 - ny * r1);
                ctx.lineTo(x0 - nx * r0, y0 - ny * r0);
                ctx.closePath();
                ctx.fill();
            }

            ctx.restore();

            // Ground label
            ctx.fillStyle = 'rgba(255,255,255,0.3)';
            ctx.font = '10px system-ui, sans-serif';
            ctx.fillText(translations.ground || 'Ground', 4, groundY + 14);
        },

        // ─── Cleanup ─────────────────────────────────────────

        destroy() {
            if (this._animFrame) cancelAnimationFrame(this._animFrame);
            if (this._refreshTimer) clearInterval(this._refreshTimer);
        },
    };
    });
});
