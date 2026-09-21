# Development guide

This guide covers local development, testing, and the parts of the codebase you will touch most often.

If you are deploying a server, use DEPLOYMENT.md instead.

## Requirements

- PHP 8.2 or newer
- Composer
- Node.js and npm
- SQLite or MySQL

## Project layout

- app, application code
- app/Console/Commands, background tasks and scheduled jobs
- app/Http, controllers and middleware
- app/Services, data sources and integrations
- docs, extra documentation
- routes/web.php, public pages
- routes/api.php, JSON API
- routes/console.php, scheduler definitions
- resources/views, Blade templates
- resources/js and resources/css, frontend entrypoints for Vite
- database/migrations and database/seeders, schema and defaults
- public/build, compiled frontend assets
- scripts, developer and release scripts

## First time setup

From the project root.

```bash
git clone https://github.com/centauri/WeatherNode.git
cd WeatherNode
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate && php artisan db:seed
php artisan admin:create
npm run build && php artisan serve
```

Open your site and sign in at `/admin` using the admin account you created with `php artisan admin:create`.

The first login opens a two step setup asking where the station is and where its readings come from. A seeded database knows about no particular place, so nothing on the weather pages means much until that is answered. It can be put off with **I will do this later**, which is what you want when you are only running the test suite.

For automation only, you can set `ADMIN_EMAIL` and `ADMIN_PASSWORD` before `php artisan db:seed`.

## One command setup

This repo ships a composer setup script.
It runs install, creates the .env if missing, generates APP_KEY, migrates, installs npm deps, and builds assets.

```bash
composer run setup
```

## Run the app in dev mode

The composer dev script runs a full local stack.
It starts the PHP server, queue listener, logs, and Vite.

```bash
composer run dev
```

If you prefer manual processes.

```bash
php artisan serve
npm run dev
```

## Scheduler and polling

WeatherNode relies on the Laravel scheduler for background polling and maintenance.
Scheduled tasks are defined in routes/console.php.

Production uses a cron entry that runs every minute.

```bash
* * * * * cd /path/to/WeatherNode && php artisan schedule:run >> /dev/null 2>&1
```

Local development options.

```bash
php artisan schedule:work
```

Useful commands to run manually.

```bash
php artisan weather:fetch --save
php artisan weather:poll-external --force
php artisan weather:summarize
php artisan system:readiness
```

## Command reference approach

This repo does not try to document every artisan command and every flag in this file.
That list changes over time and goes stale fast.

Use these commands to discover what is available in your current checkout.

```bash
php artisan list
php artisan help weather:poll-external
php artisan help weather:fetch
php artisan help system:readiness
```

For scheduled jobs, this shows what the scheduler will run.

```bash
php artisan schedule:list
```

Useful commands for debugging and inspection.

```bash
php artisan route:list
php artisan config:show app
php artisan config:show localization
```

## Architecture, cache first dashboard

WeatherNode is designed to keep page requests fast and predictable.
Public pages read data from cache.
Background jobs update that cache on a schedule.

High level flow.

```text
External APIs and station sources -> pollers and fetchers -> Laravel cache and database -> Blade pages and JSON API
```

Main entry points.

- Live station fetch, `php artisan weather:fetch --save`
- External polling, `php artisan weather:poll-external`
- Daily summary generation, `php artisan weather:summarize`

Code pointers.

- Scheduler definition, `routes/console.php`
- Live data fetch command, `app/Console/Commands/FetchWeatherData.php`
- External poller, `app/Console/Commands/PollExternalData.php`
- Cache only API endpoints, `app/Http/Controllers/Api/WeatherController.php`

## Polling and scheduler details

### Smart interval tracking

The external poller runs often, but each source only polls when due.
This reduces load and helps keep within fair use and rate limits.

You can bypass interval checks for debugging.

- Force refresh everything, `php artisan weather:poll-external --force`
- Poll one source, `php artisan weather:poll-external --source=forecast`

The scheduler uses a wrapper command so each task gets a dedicated log file.
That wrapper is `scheduler:run-task` in `app/Console/Commands/RunScheduledTask.php`.

### Scheduled jobs

Scheduled tasks live in `routes/console.php`.
They cover live data, external polling, telemetry, maintenance, and housekeeping.

Useful commands.

```bash
php artisan schedule:list
php artisan schedule:run
php artisan schedule:work
```

### Task logs

The scheduler wrapper writes logs under `storage/logs`.
Look for files like `weather-fetch.log`, `poll-forecast.log`, and `poll-airquality.log`.

If you are debugging a production issue, start with the task log for the failing source and then check `storage/logs/laravel.log`.

## Self healing and resilience

The external poller has self healing behavior.
If a cache is missing or invalid, it can fetch immediately even if the interval has not passed.

There is also a scheduler health check job that runs periodically and refills critical caches after cache clears.
See `routes/console.php` for the health check logic.

## Source structure

Weather data is split into live station data and external data.

- Live station sources and normalization live under `app/Services/Weather`
- Forecast providers live under `app/Services/Forecast`
- Alerts providers live under `app/Services/Alerts`
- Air quality providers live under `app/Services/AirQuality`
- Open data providers live under `app/Services/OpenData`

When you add a new source, keep it pure and testable.
Do not fetch external APIs inside view requests.
Add it to a command or a scheduled job, then cache results for the UI to read.

## Frontend workflow

Vite entrypoints live in resources/css/app.css and resources/js/app.js.

- `npm run dev`, hot reload
- `npm run build`, production build to public/build

### When you need to rebuild assets

You only need a build when you change frontend inputs.

- resources/css or resources/js changes, build needed
- tailwind.config.js changes, build needed
- Blade templates that add new Tailwind class names, build needed
- PHP only changes, build not needed

## Tests

```bash
composer test
```

You can also run the Laravel test runner directly.

```bash
php artisan test
```

### Public appearance

Run `npm run test:theme` for visitor preference behaviour and palette contrast checks, and `php artisan test --filter=AppearanceSettingsTest` for settings and public rendering. A frontend build is required after changing Tailwind classes.

Public palette tokens live in `public/css/public-theme.css`; `public/js/public-theme.js` applies the visitor preference before first paint. These small local assets are separate from Vite so error pages can resolve a theme without a build. Public shells include `public-theme-head` and use `data-public-theme`, `data-default-color-mode`, and `data-color-mode` on the root element. Never use the admin's `theme` storage key for public preferences.

`appearance.visitor_palettes` is an opt-in JSON allowlist of built-in palette IDs and optionally `custom`. The public head emits only the allowed IDs/base mappings; `weathernode.public.palette` stores the visitor's choice separately from colour mode. Revoked choices are cleared on the next page load. Custom CSS requires `data-custom-theme` so switching to a built-in does not inherit the custom colours. Only `appearance.active_custom_theme` can be offered to visitors, never the saved draft. `VisitorThemesTest` covers validation, access control, opt-in defaults and snapshot isolation; the JS preference tests cover persistence, revoked choices, cross-tab sync, denied storage and custom/preset switching.

Use `ui-*` utilities for interface surfaces/text/accents and `data-*` text colours for weather categories. Keep provider imagery and measurement ramps independent of station branding. Public chart modules import `themed-apexcharts`, which updates chart chrome on `weathernode:theme-change` without replacing the chart instance. Its RGB strings use comma-separated channels for compatibility with the bundled ApexCharts version.

Before shipping changes, check every palette with both light/dark and FX/Flat, mobile/desktop, chart tooltips and preserved zoom/series visibility, blocked storage, System mode, and login/embed/error pages. Contrast tests cover opaque token pairs; also inspect text over glass, gradients and images visually.

## Formatting

This repo includes Laravel Pint.

```bash
./vendor/bin/pint
```

## Resetting your local database

If you want a clean slate.

```bash
php artisan migrate:fresh --seed
```

## API keys in development

The JSON API expects an API key.
Use the `X-API-Key` header for normal API calls.

Query parameter keys (`api_key`) are only accepted on radar visual endpoints that are loaded as images/tiles:

- `/api/radar/tile/*`
- `/api/radar/future-image`

The app creates a public API key on first page load after migrations.
You can view it in the admin at /admin/api-keys.

Example request.

```bash
curl -sS -H X-API-Key:<public key> http://localhost:8000/api/weather/dashboard
```

## Locale and units

Locale and unit selection happens in middleware.
It uses these inputs, in order.

- URL query parameters, `?lang=` or `?locale=`, and `?units=`
- Visitor cookies
- Admin defaults from settings
- Optional Accept-Language based detection when enabled

Implementation lives in `app/Http/Middleware/LocaleUnitsMiddleware.php`.
Defaults come from `display.language` and `display.unit_system` in the settings table.

## Admin only debug overrides

The public dashboard supports debug query parameters for visual testing.
These only apply for logged in admins.

Common parameters.

- `debug_wind_speed` and `debug_wind_dir`
- `debug_temp`
- `debug_rain_rate` and `debug_rain_daily`
- `debug_pressure`

Implementation lives in `resources/views/weather/dashboard.blade.php`.

## Settings and configuration

Most runtime configuration lives in the database settings table.
Defaults are seeded by database/seeders/SettingsSeeder.php.

Use the admin settings pages to configure services, station details, polling, and UI behavior.

## Open data sources

Open data providers are registered in `app/Providers/AppServiceProvider.php`.
The registry is `app/Services/OpenData/OpenDataProviderRegistry.php`.

The admin page is `/admin/settings/opendata`.
Providers can expose metadata and optional feature flags.

## Telemetry, community stations

Telemetry is optional.
When enabled, the site can send a station payload to a central aggregator.
The community map reads stations from a GitHub hosted JSON file.

Code pointers.

- Command, `app/Console/Commands/SendTelemetry.php`
- Collection and payload shaping, `app/Services/Telemetry/TelemetryService.php`
- Aggregator client, `app/Services/Telemetry/TelemetryAggregatorService.php`

Default settings live under the `telemetry.*` keys in `database/seeders/SettingsSeeder.php`.
Aggregator details live in `docs/AGGREGATOR_SERVICE.md`.

## Optional features

AI forecast text.

- Built in narrator is always available.
- Optional AI rephrasing is off by default.
- Settings live in config/nlg.php and environment variables.

Telemetry.

- Station telemetry upload is optional and controlled by admin settings.
- Community aggregator details live in docs/AGGREGATOR_SERVICE.md.

## Versioning and releases

The current version lives in the `VERSION` file.
You can manage it with the version command.

```bash
php artisan version show
php artisan version set v0.1.0
php artisan version bump patch
```

There is also a small release helper that updates `VERSION` and adds a changelog entry.

```bash
make release
```

Release-note safety rules:

- Write changelog/release notes as Markdown or plain text.
- Do not include raw HTML tags (`<...>`).
- The admin updates page sanitizes release-note HTML to prevent script injection.

## Debugging and diagnostics

System readiness focuses on scheduler, polling freshness, and a security baseline.

```bash
php artisan system:readiness
php artisan system:readiness --strict
```

System diagnostics writes a JSON snapshot for troubleshooting.

```bash
php artisan system:diagnostics
php artisan system:diagnostics --pretty
```

## Common setup and deploy errors

If you hit errors during install, they usually come from scripts that expect a ready database, or from missing Node.js.

Common fixes.

- If composer scripts fail, run `composer install --no-scripts`, set up `.env`, run `php artisan migrate`, then run `php artisan package:discover`.
- If npm is not available, build assets locally and copy `public/build` to the server.
- If you see missing table errors, run migrations first.

### Docker-specific first-boot examples

- **Invalid APP_KEY format**
  - Symptom: startup/login errors, encryption/session exceptions.
  - Fix:
    ```bash
    echo "base64:$(openssl rand -base64 32)"
    ```
  - Validate key length inside container:
    ```bash
    docker exec weathernode-app php -r '$k=getenv("APP_KEY"); $r=base64_decode(substr($k,7), true); echo strlen($r).PHP_EOL;'
    ```
    Expected: `32`.

- **Redirect drops custom port (for example lands on host login page)**
  - Symptom: `/admin` redirects to host URL without `:8089`.
  - Fix: set full URL including scheme and port:
    ```yaml
    APP_URL: "http://192.168.1.15:8089"
    ```
  - Verify:
    ```bash
    curl -I http://192.168.1.15:8089/admin
    ```

- **Read-only volume 500 on first boot**
  - Symptom: `laravel.log` append denied or SQLite readonly errors.
  - Root cause: mounted `storage/`, `bootstrap/cache`, or `database/` not writable by app user.
  - Current image startup normalizes permissions automatically; on strict hosts you may still need one manual ownership/permission correction.

## Keeping your workspace clean

Remove local artifacts.

```bash
make clean
```

Create a new version and changelog entry.

```bash
make release
```

WeatherNode Dark preserves the original shipped colours, including muted text, measurement colours and Flat styling. Do not brighten or recolour this preset as part of theme improvements; use an optional palette instead. Contrast improvements apply to the new palettes and light modes. The theme tests pin representative original colours, and the chart adapter restores each chart's original dark label colours after mode changes.


### Portable custom themes

Theme creator endpoints use the existing admin middleware and CSRF protection. `CustomTheme` validates a strict, versioned JSON document (`format: "weathernode-theme"`, integer `version: 1`, `name`, built-in `base`, and complete `dark`/`light` objects under `modes`). Each mode contains the 19 allowlisted interface tokens as six-digit hex strings. Unknown fields, weather/alert token overrides, unsupported versions, missing colours, arbitrary CSS/URLs and documents over 20,000 bytes are rejected. The name is at most 80 characters. Files are never executed or stored on disk.

`appearance.custom_theme` is the saved draft; `appearance.active_custom_theme` is the applied snapshot. `appearance.palette=custom` selects that snapshot. Invalid stored themes fall back to the original preset. No schema migration is needed. Public CSS is emitted only after validation; the name never enters CSS. Public roots identify custom themes so legacy Flat overrides and chart compatibility colours do not mask their interface colours. The original presets and data colours remain intact.

The admin editor previews in a separate, authenticated iframe. It accepts messages only from its same-origin parent and validates the schema again before changing CSS. Import/export validates on the server as well as in the browser. `ThemeCreatorTest` covers permissions, schema rejection, round trips, snapshot behaviour, reset and public shells; `theme-document.test.js` checks browser validation and contrast calculations. Theme creator strings are included in all 18 locale files.
