# Issue #12 — Neutral WeatherNode branding (no place-hardcoded UI)

**Date:** 2026-07-30  
**GitHub:** https://github.com/centauri/WeatherNode/issues/12  
**Status:** Approved in conversation; awaiting spec review before implementation

## Goal

Remove hardcoded **MeteoUitgeest** / **Uitgeest** from user-visible product strings and code defaults, so WeatherNode reads as a generic station product. Keep functional demo geography where it helps a fresh install work out of the box. Do **not** migrate or rewrite existing database rows.

## Non-goals

- No database migrations.
- No seeder overwrite of already-persisted setting **values** (existing installs keep their station name, SEO, radar provider, etc.).
- Do not rename `localStorage` key `meteouitgeest_ads_consent_v1` (would reset consent for live users).
- Do not rewrite README demo links or historical docs beyond what is needed for product strings in the app.
- Do not remove KNMI as an available radar/satellite option—only change the **default** provider for new installs / code fallbacks.

## Constraints

- **Safety:** Existing servers must keep working after upgrade with zero DB schema or value mutations. Code/string/default changes only.
- **Seeder behavior today:** `SettingsSeeder` creates missing keys; it does **not** replace existing `value` fields (except a narrow satellite URL repair path). Rely on that—do not add a “force rebrand” update path.
- **Product vs station:** Product brand is **WeatherNode**. Station display name comes from `station.name` (and related settings). On login, product brand is primary; station name is secondary.

## Design

### 1. Defaults & fallbacks (code + seeder)

| Key / fallback | New default | Existing installs |
|----------------|-------------|-------------------|
| `station.name` (seeder + `Setting::stationName()` fallback) | `WeatherNode` | Unchanged DB value |
| `station.location`, lat, lon, tz, elevation, WU id, etc. | Keep Uitgeest / Waldijk demo geography | Unchanged |
| `station.server_url` | Prefer neutral placeholder or empty if already optional; if kept, avoid implying every install is meteouitgeest.nl—use empty or `https://example.com/` style only if required | Unchanged |
| `seo.site_title` / `seo.site_description` / `seo.site_keywords` | Neutral WeatherNode copy (no place names) | Unchanged |
| `webcam.url` | Keep `https://www.meteouitgeest.nl/thumbnail/image.jpg` | Unchanged |
| `radar.provider` (seeder + Blade/`getValue` fallbacks) | `rainviewer` | Unchanged if already `knmi` |
| Blade SEO description fallbacks | Neutral i18n string (parameterized or place-free) | N/A |
| HTTP User-Agent / similar app identifiers | `WeatherNode/…` | N/A |
| `config('app.name')` / admin layout fallbacks | Prefer `WeatherNode` where code hardcodes `MeteoUitgeest` | Env `APP_NAME` still wins |

### 2. Login page UX

- **Primary title:** hardcoded `WeatherNode` (product brand; not from DB).
- **Smaller subtitle:** `Setting::stationName()` (station display name).
- Replace frozen header copy (`Local weather and forecast in Uitgeest`) and hardcoded coords line with settings-driven secondary info where practical (station name required; location/coords optional if already available without clutter).
- Document `<title>` / meta description: WeatherNode + login wording (`Log in to the WeatherNode admin panel` or equivalent i18n key), not MeteoUitgeest.

### 3. Internationalization

Replace place-specific **translation keys** with parameterized or neutral keys, and update call sites + all locale files under `resources/lang/`.

Examples (exact wording may be refined in implementation):

| Old key (concept) | New approach |
|-------------------|--------------|
| `14-day forecast for Uitgeest` | `14-day forecast for :location` |
| `Current air quality in Uitgeest and surroundings` | `Current air quality in :location and surroundings` |
| `Sun, moon and celestial bodies above Uitgeest` | `… above :location` |
| `Webcam Uitgeest` | `Webcam` or `Webcam :location` |
| `Live weather in Uitgeest, North Holland…` | Neutral: `Live weather data from a local station.` (or `:location` if useful) |
| `Local weather and forecast in Uitgeest` | Retire for login; elsewhere use parameterized form if still needed |
| `Log in to the MeteoUitgeest admin panel` | `Log in to the WeatherNode admin panel` |
| `MeteoUitgeest - Weather station Uitgeest` | Retire or replace with WeatherNode-neutral / `:name` |
| `MeteoUitgeest Setup Guide` / `MeteoUitgeest supports…` | `WeatherNode Setup Guide` / `WeatherNode supports…` |
| Help examples like `meteouitgeest/community-stations` | `weathernode/community-stations` or `owner/repo` |

Pass `:location` from `Setting::stationLocation()` (or a short location) / `:name` from `Setting::stationName()` at call sites that already use that pattern (forecast, astronomy, footer).

### 4. Surfaces to touch (implementation checklist)

- `database/seeders/SettingsSeeder.php` — name, SEO, `radar.provider`
- `app/Models/Setting.php` — `stationName()` default
- `resources/views/auth/login.blade.php` — brand + subtitle + meta
- Dashboard / layout SEO fallbacks; webcam alt text
- Radar `getValue(..., 'knmi')` → `'rainviewer'` (dashboard, radar page, admin defaults as applicable)
- Console / fetch User-Agent strings
- Admin help / setup copy keys in lang files + any Blade that embeds MeteoUitgeest
- Leave ads consent storage key alone

### 5. Existing-install behavior (explicit)

After upgrade:

- Operator who already has `station.name = "MeteoUitgeest"` (or Dutch tagline) **keeps** that name until they change it in admin.
- Operator on `radar.provider = knmi` **keeps** KNMI.
- Fresh seed / missing keys get WeatherNode / rainviewer / neutral SEO.
- UI strings and hardcoded Blade titles stop saying Uitgeest/MeteoUitgeest even when DB still has old branding—except where the UI deliberately shows `station.name` / location from settings.

## Testing

- Fresh seed: station name WeatherNode; radar default rainviewer; SEO neutral; login shows WeatherNode + station subtitle.
- Simulated existing DB: with old `station.name` / `radar.provider` rows present, re-running seeder must **not** overwrite those values.
- Spot-check NL + EN locales for parameterized strings.
- Login + dashboard header/SEO render without leftover MeteoUitgeest hardcodes in views (grep).

## Out of scope follow-ups (optional later)

- One-time admin banner: “Your station name still looks like the old demo brand—update in Settings?”
- Renaming ads consent localStorage key with a read-migrate-write of the old key.
- Changing live demo `station.server_url` / WU id for non-demo deploys (operators set these themselves).
