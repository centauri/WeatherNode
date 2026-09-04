# Changelog

All notable changes to this project will be documented in this file.

---

## [2026.09.1] - 2026-09-04

- Add DWD as a forecast source. Deutscher Wetterdienst publishes MOSMIX, a forecast for each of its stations, as open data with no key or account needed. Switch DWD on in Admin > Settings > Open Data and it appears in the forecast source list; nothing changes over on its own (#81)
- With no station picked, the nearest one is found automatically. MOSMIX covers stations worldwide, not only German ones, so this is useful outside Germany too
- Corrections to 29 German strings, including "Ecowitt Datum Endpunkt" which meant date rather than data, and the air quality grade "Fair" which read as moderate instead of good

## [2026.09.0] - 2026-09-04

- Fix weather cards failing on Docker with a 500 and "undefined function imageftbbox()". The image was building GD with PNG only, losing FreeType, JPEG and WebP, because the build configured GD and then discarded that work before installing it (#87)
- The same fault made the proxied pressure charts fall back to PNG on Docker, so those now download at roughly a quarter of the size. The image build fails now if GD comes out missing any of the four (#74)
- Fix the PM2.5 widget showing "--" instead of the reading. A station with one sensor saw six empty rows, two of which were not sensors at all (#86)
- The radar widget's "+" button works on a fresh install. It was greyed out because the map opened at its own maximum zoom. Zooming in now sharpens the street map and scales the radar, which only has detail to zoom 7 (#59)
- Fix the satellite panel width on the radar page (#84)
- More German translations, and the missing Italian sea temperature strings

## [2026.08.9] - 2026-08-23

- Fix the header on a phone cutting off buttons when signed in as an admin. The row now wraps, and the header ends up shorter than it was (#78)
- The dashboard radar widget shows the most recent frame when the flat theme turns the animation off, instead of parking on one from two hours ago (#49)
- Fix expired file cache entries never being deleted from disk. On one install the sweep freed 27MB and took 45,477 files down to 768. The Docker entrypoint also stops running one chmod per file, which cost about two minutes on every container start (#43)
- History pages for days outside the recorded period now return 404. They used to answer for any date at all and link one day further back, so a crawler could walk from 2020 back to the year 1800 in each of the 18 languages. On one site that was 99% of all traffic (#55)
- robots.txt now blocks the API and the ?units= duplicates, asks Bing to slow down, and explains what every line does so you can adapt it
- Fix three Spanish translations: "Max temp" read as "maximum time" and "Min temp" read as "at what time?" (#79)

## [2026.08.8] - 2026-08-18

- Fix the Atlantic and Pacific pressure charts. NOAA retired both URLs, so they had been returning 404 and the page sat on "Loading" instead of saying so
- When a chart cannot be loaded the page now tries the rest of the list rather than giving up after one, and a failed 15 minute refresh keeps the chart already on screen
- Nine more regional charts: Canada, Alaska, Mexico, Hawaii, both US coasts, the Atlantic and Pacific tropics, and the whole northern hemisphere
- The chart picker is a dropdown rather than a row of buttons, which did not fit thirteen charts on a phone
- Region names are translated in all eighteen languages

## [2026.08.7] - 2026-08-18

- Fix radar tiles failing for everyone behind the tile proxy: RainViewer moved to hexadecimal frame ids and the path check only accepted digits (#35)
- The radar animation no longer strobes between frames, and the map no longer goes blank after switching provider
- The radar page honours the configured sources instead of a hardcoded list, and the admin can choose which sources appear on the radar card
- Fix the dashboard radar locking up after the first zoom (#52)
- Add AEMET support for stations in Spain (#22)
- Fix Ambient Weather credentials not being saved, and its current conditions going stale (#39, #61)
- Fix stale source detection: the freshness timestamps were read but never written, so no source was ever reported as stale (#48)
- Earthquakes are requested for the configured radius and period rather than the most recent worldwide events, which could return thirty quakes spanning an hour (#54)
- Marine data can use a coastal point instead of the station, so inland installs get waves, sea temperature and tides (#53)
- Add a Europe option to the pressure map, and serve the charts through the app instead of hotlinking them. The European chart drops from 4.4MB to roughly 0.4MB (#58)
- Stop shipping defaults that point at one particular install: the webcam URL, the station server URL and the NOAA application name (#42)
- Refuse destructive database commands in production, so an update can never drop an existing database
- Fill in the missing Italian translations, which covered roughly half the interface (#56)

## [2026.08.6] - 2026-08-17

- Fix individual sensor failures never being detected: the last-seen map was seeded with nulls and guarded with `isset()`, so no sensor was ever reported as failed and the alert could not fire at any threshold
- Sensor health alerts now go to the address set in Admin → Settings → Notifications. They previously needed an `alerts.email` key that had no admin field and no seeder row, so unless you had added it by hand nothing was ever sent
- Sensors are detected from the normalized reading columns rather than only Ecowitt battery keys, so outdoor temp/humidity, wind, rain, solar and UV are covered for every data source. A station that keeps sending indoor values while its outdoor array is silent no longer looks healthy
- An alert that could not be delivered is no longer recorded as sent. A failure detected before notifications were configured used to silence the alert for 24 hours after they were, and a second sensor failing produced nothing
- The admin dashboard lists every known sensor with its state and last-seen time instead of dropping the missing ones, which is what made a failed sensor look like it had never existed
- Alert threshold is configurable and now defaults to 30 minutes instead of 120; existing installs still on the old default are moved to 30
- Sensor health no longer rescans the reading window on every admin page load, which added seconds on stations recording every minute
- Alert webhooks verify TLS certificates. One pointed at a self-signed host will now fail, with the reason in the log

## [2026.08.5] - 2026-08-10

- Add API documentation covering the public endpoints, authentication and integration examples (`docs/API.md`, linked from the README)
- Show ready-to-copy curl examples on the admin API keys page
- Allowlist those examples in the secret scan; their `X-API-Key: YOUR_API_KEY` placeholder tripped a false positive

## [2026.08.4] - 2026-08-09

- Fix Docker containers on MySQL or Postgres stalling at startup with an empty log: the entrypoint derived a SQLite path from `DB_DATABASE`, which is a schema name on those drivers, and ended up recursively chowning the whole application directory (#34)
- Radar tiles are no longer written into the cache store. On MySQL the cache table cannot hold PNG bytes, so tiles were either served as a blank pixel and then 500, or silently truncated and re-fetched every time (#37)
- Docker now uses a file-backed cache instead of the database, and the entrypoint re-checks ownership after its own bootstrap commands, which is what made a file cache fail before
- The php-fpm pool is sized from the container's memory limit rather than the stock 5 workers, so a dashboard load no longer queues behind its own radar tiles. `PHP_FPM_MAX_CHILDREN` overrides it
- Fix the published `weathernode-deploy.zip.sha256` not matching the zip, which made `shasum -c` fail on a perfectly good download. In-app updates were unaffected, they use GitHub's own asset digest
- Fix the update check being scheduled twice when `updater.notify_email` was enabled, which sent admins two emails a day

## [2026.08.3] - 2026-08-09

- Visitor analytics now reads the daily rollup instead of rebuilding every chart from raw logs on each request, which took roughly 700ms on a busy site and grew with traffic
- The rollup stores bot-excluded figures too, so the default view can use it; historical days are filled in automatically on the first nightly run after upgrading
- Days the scheduler previously missed are now picked up rather than being lost when the raw logs age out
- Fix `visitorlog:rollup --date=` failing when that day already had a row
- The range selector says how much data actually exists, and gains another year for each year the install has been running
- New weekly job returns space freed by the nightly purges to the filesystem; SQLite never shrank the file on its own, and a real install was 64% empty space
- Note: the visitor page reads the rollup, so figures fill in after the first nightly run (or run `php artisan visitorlog:rollup` to do it now)

## [2026.08.2] - 2026-08-09

- Quick Stats bar tiles can now be toggled in Settings → Dashboard Widgets and reordered on the dashboard in edit mode (#13)
- Fix Docker upgrades silently skipping database migrations: migrations now run from a copy inside the image that no volume can cover; the data volume moves to `/var/lib/weathernode` (optional cleanup, existing compose files keep working — see DOCKER.md)
- Fix the in-app updater deleting a release's migrations on SQLite installs, so `migrate` ran against an empty folder and reported success
- Show a banner in the admin area when a new release is available (checked daily, can be turned off on the Updates page)
- Stop browser-caching the dashboard for admins so settings changes show up without a hard refresh
- Note: migrations skipped by either bug are applied on the first start after this update — take a backup before upgrading

## [2026.08.01] - 2026-08-07

- Fix webcam image refresh after the dashboard renders conditional widgets
- Refresh webcam snapshots in both image-only and image-with-stream modes
- Show a compact, mobile-friendly image update time and failure status
- Only show data saver controls for paused livestreams

## [2026.07.2] - 2026-07-31

- Localize weekly temperature chart day labels to the active site locale
- Return English i18n keys from WeatherReading accessors (compass, Beaufort, UV, PM2.5)
- Localize wind rose and history/day chart compass labels
- Neutralize hardcoded MeteoUitgeest branding defaults to WeatherNode
- Map realtime.txt average wind direction and daily max gust fields
- Unify missing jsLocale fallback to en-US

## [2026.07.1] - 2026-07-30

- Footer "Data since" year now uses `station.start_date` instead of a hardcoded 2020
- Statistics "Most sunshine hours" is populated from Cumulus/WD sunshine data (with radiation estimate fallback)
- Weather alerts widget shows up to 3 alerts with severity colors
- Fixed Docker multi-arch image `manifest unknown` pull errors

## [Unreleased]

### Added

- **In-page weather alert toasts** — non-intrusive slide-down notifications at the top-center of the dashboard for extreme conditions
  - Reuses existing `AlertAggregatorService` backend alerts (severity ≥ 3: lightning, UV, AQI, pollen, waves, fire, floods, frost)
  - Real-time frontend checks reusing existing FX condition booleans: heavy rain (≥ 10 mm/h), storm-force wind gusts (≥ 89 km/h), extreme heat (≥ 35 °C), extreme cold (≤ −10 °C), slippery roads (isSnowing)
  - Max 2 visible toasts; 12-second auto-dismiss; coloured left accent strip; manual dismiss button
  - All visual weather effects (rain drops, snow particles, wind, fog, lightning flash) remain completely independent and untouched
- **Frost warning** — `checkFrost()` added to `LocalWarningService`: scans the first 24 hourly forecast entries; severity 3 (orange) if min ≤ −2 °C, severity 2 (yellow) if ≤ 2 °C; `warning_type = 'frost'`
- **Pollen / Allergy Forecast** — new `/pollen` tab on `/air-quality`; dashboard widget links through
  - Data sources blended by priority: Ambee (paid, optional) → Google Pollen API (optional) → Open-Meteo Air Quality (free default)
  - Shows today's overall risk index, per-category risk badges (grass / tree / weed), grains/m³ counts, 5-day forecast bar chart, species breakdown table, colour-coded allergy advice cards
  - Polled hourly via `weather:poll-external --source=pollen`; admin settings at `/admin/settings/pollen`
- **Water page** — dedicated `/water` section replacing the old Sky & Sea tides tab, with four sub-routes served independently for fast loading:
  - **Tides** (`/water`) — Rijkswaterstaat Waterinfo API; tidal curve (12 h past + 48 h future), 3-day tide table, trend arrow, NAP reference; admin settings at `/admin/settings/tide`
  - **Waves** (`/water/waves`) — Open-Meteo Marine API; wave height/period/direction, wind wave vs. swell separation; admin settings at `/admin/settings/waves`
  - **Sea Temperature** (`/water/temp`) — sea surface temperature from Open-Meteo Marine with trend chart
  - **Rivers** (`/water/rivers`) — multi-provider river level data; Rijkswaterstaat (329+ stations via live catalog); provider registry pattern for future sources; admin settings at `/admin/settings/rivers`
- **Social Sharing Cards** — dynamic 1200×630 Open Graph PNG images served at `/og/*.png`
  - 9 card types: Home (live), Forecast, History, Statistics, Fire Weather, Air Quality, Astronomy, Aviation, Generic
  - Powered by `intervention/image` v3 with GD or Imagick driver (auto-detected, admin-selectable)
  - Station logo composited onto every card; dark branded design with per-page accent colours
  - PNGs are base64-encoded before caching so any cache driver (file, database, Redis) handles them safely
  - Admin settings page at `/admin/settings/og` with driver status badges and live preview links
  - All 14 public page views emit a dynamic `og:image` meta tag when OG is enabled
- **Share & Embed page** (`/share`) — public page accessible from the fat footer "Share & Embed" link
  - Large social share buttons for WhatsApp, X/Twitter, Facebook, Telegram, and copy-link
  - Per-page compact share buttons for Live, Forecast, Fire Weather, Statistics, Air Quality, Astronomy
  - Grid of OG card embed previews with `<img>` snippet + "Copy code" button when OG is enabled
- **Phenology / Season Tracker** — new section on `/statistics` (year-aware)
  - Day-type count grid (6 KNMI types): Frost, Ice, Spring, Summer, Tropical, Precipitation days
  - Seasonal milestones table: first/last occurrences vs. historical average date with ± days
  - GDD accumulation area chart (ApexCharts, base 10 °C, from Jan 1)
  - Powered by `PhenologyCalculator` service; cached per year, warmed daily at 00:10
- **Fire Weather page** (`/fire-weather`)
  - Angström Index with colour-coded danger badge (Low / Moderate / High / Extreme)
  - Consecutive dry days counter, 7-day and 30-day rolling rain totals
  - 90-day historical chart with ApexCharts, colour-coded markers, threshold annotation lines
  - Powered by `FireWeatherCalculator` service; cached until 00:10 next day
- **Statistics: climate comparison** — compare two date ranges side by side (JSON endpoint + tab UI)
- **Astronomy, Aviation, Forecast, Pressure pages** — added meta descriptions, scientific intro text, and localized content across 18 languages

### Changed

- `daily-cache-warm` scheduler (00:10 daily) extended to also rebuild OG image caches for fire weather and statistics year cards
- Home OG card cache TTL reduced from 30 minutes to 5 minutes (station updates every ~1 minute)
- Admin sidebar "Display" section now includes a "Social Sharing Cards" link

### Fixed

- Docker first-boot reliability improvements:
  - startup now normalizes writable permissions for mounted `storage/`, `bootstrap/cache`, and `database` paths to avoid readonly SQLite and log-file permission failures on stricter hosts
  - auth redirect URL generation now consistently honors configured `APP_URL` (including custom host ports such as `:8089`)
- Documentation updates for Docker/Unraid troubleshooting:
  - valid Laravel `APP_KEY` format (`base64:` + 32-byte key)
  - custom-port `APP_URL` examples and redirect verification
  - first-boot diagnostics workflow for isolating scheduler noise vs web request failures

- PHP 8.4 `ErrorException: Undefined array key` on `/admin/settings/og` when `og.*` settings had not yet been seeded — fixed by using `Collection::get()` instead of array access `[]`
- OG image endpoints returning a cascading `JsonException: Malformed UTF-8 characters` error (caused by binary PNG data appearing in Ignition's exception context) — fixed by wrapping all generation in `try/catch` with clean logging and base64-encoding cached values

---

## Notes

- See `temp/feature-roadmap.md` for upcoming features
- See `docs/` for legal, terms, and privacy documentation
