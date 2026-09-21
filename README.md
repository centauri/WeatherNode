# WeatherNode

WeatherNode is a self hosted weather dashboard for your personal weather station.
It combines live station readings with external data like forecasts, alerts, radar, air quality, astronomy, and more.

I built WeatherNode for my own station first.
I wanted a dashboard that loads fast, stays usable during API outages, and lets me choose where the data comes from.
Once it worked well enough for daily use, I decided to share it so you can run it too.

## Screenshots

The public dashboard — live station readings, the current forecast, and the day's trends:

![WeatherNode dashboard showing live weather station data, forecast and charts](screenshots/dashboard.webp)

| Precipitation radar | Community stations map |
| --- | --- |
| ![Precipitation radar centred on the station location](screenshots/radar.webp) | ![Map of community weather stations that opted in to share their location](screenshots/community-stations.webp) |

The admin panel — a dashboard with live ingest stats and station status, and settings to configure dozens of data sources, sensors, widgets and themes:

![WeatherNode admin dashboard with live ingest statistics, station info, battery and sensor status](screenshots/admin-dashboard.webp)

![WeatherNode admin settings showing dozens of configurable data sources and integrations](screenshots/admin-settings.webp)

### More pages

WeatherNode has dedicated pages for each data source — a few more:

| Forecast | Satellite |
| --- | --- |
| ![Forecast page with daily and hourly outlook](screenshots/forecast.webp) | ![Satellite page with solar radiation forecast and imagery](screenshots/satellite.webp) |

| Statistics &amp; records | History |
| --- | --- |
| ![Statistics and records with monthly averages and extremes](screenshots/statistics.webp) | ![History page with daily charts of past weather](screenshots/history.webp) |

| Air quality | Fire weather |
| --- | --- |
| ![Air quality page with AQI index and health advice](screenshots/airquality.webp) | ![Fire weather page with fire-danger and Angstrom index](screenshots/fireweather.webp) |

| Pollen forecast | Aviation / METAR |
| --- | --- |
| ![Pollen forecast with 5-day grass, tree and weed levels](screenshots/pollen.webp) | ![Aviation METAR page with airport weather and atmospheric profile](screenshots/metar.webp) |

| Tides | River levels |
| --- | --- |
| ![Tides page with tidal chart and forecast](screenshots/tides.webp) | ![River levels with live gauge readings](screenshots/rivers.webp) |

| Noise level | Astronomy |
| --- | --- |
| ![Noise level with live readings and 24-hour history](screenshots/noise.webp) | ![Astronomy page with sun and moon times and moon phases](screenshots/astronomy.webp) |

> Live demo running a real station: [meteouitgeest.nl](https://meteouitgeest.nl)

If you are setting up a server, start with [HOSTING.md](HOSTING.md) to choose the right
setup for your situation (VPS, shared hosting, or Docker), then follow [DEPLOYMENT.md](DEPLOYMENT.md) for the step-by-step install.

## Support

[![Buy me a coffee](https://img.buymeacoffee.com/button-api/?text=Buy%20me%20a%20coffee&emoji=&slug=centauriprime&button_colour=FFDD00&font_colour=000000&font_family=Cookie&outline_colour=000000&coffee_colour=ffffff)](https://www.buymeacoffee.com/centauriprime)

## Documentation

- Admin guide, [ADMIN_GUIDE.md](ADMIN_GUIDE.md). Use this if you run the site.
- User guide, [USER_GUIDE.md](USER_GUIDE.md). Use this if you visit the site.
- **Hosting guide (start here to choose a setup), [HOSTING.md](HOSTING.md)**
- Deployment guide (step-by-step install), [DEPLOYMENT.md](DEPLOYMENT.md)
- Shared hosting quickstart (no server npm), [SHARED_HOSTING_QUICKSTART.md](SHARED_HOSTING_QUICKSTART.md)
- Development guide, [DEVELOPMENT.md](DEVELOPMENT.md)
- Versioning guide, [VERSIONING_GUIDE.md](VERSIONING_GUIDE.md)
- Release runbook (commit/build/release flow), [docs/RELEASE_RUNBOOK.md](docs/RELEASE_RUNBOOK.md)
- GitHub (source control and releases), [GITHUB.md](GITHUB.md)
- Changelog, [CHANGELOG.md](CHANGELOG.md)
- Telemetry aggregator notes, [docs/AGGREGATOR_SERVICE.md](docs/AGGREGATOR_SERVICE.md)
- License, [LICENSE.txt](LICENSE.txt)
- Trademarks, [TRADEMARKS.md](TRADEMARKS.md)

## Where the details live

This README stays focused on what WeatherNode is and what you get.
Detailed server setup lives in [DEPLOYMENT.md](DEPLOYMENT.md).
Detailed admin operations live in [ADMIN_GUIDE.md](ADMIN_GUIDE.md).

## Features

🌡️ Live station data

- Live station readings from Ecowitt, WeatherFlow, WeatherLink, Ambient Weather, Wunderground, and local file sources.
- Per card timestamps so you see when each data source last updated.
- Sensor health detection and OFFLINE badges when a source goes stale.
- Optional alert notifications when fetching or saving fails.

🌤️ External data

- Forecast sources include Yr.no, OpenWeatherMap, Weather Underground, Environment Canada, and Wxsim.
- Weather alerts support multiple providers by region.
- Air quality from WAQI and Sensor.Community.
- Aurora, ISS passes, astronomy calculations, earthquakes, and METAR.

🗺️ Pages

- Radar and satellite pages.
- Forecast, air quality, astronomy, earthquakes, lightning, and pressure map pages.
- History and statistics pages.
- Community stations map.

🧰 Admin and operations

- Admin panel for data sources, widgets, appearance, effects, mail, and updates.
- In app updater with preview, backups, and rollback.
- Cache first public site. WeatherNode updates data in the background and serves public pages from cache.

🎨 Customization

- FX and Flat visual modes, with WeatherNode, Ocean, and Forest colour palettes and visitor-selectable light/dark/system modes.
- Admin drag and drop widget ordering on the dashboard.
- Optional visual effects like rain, snow, wind, lightning, and fog.

🌐 Locale and units

- Language and unit defaults set by the site admin.
- Optional browser based locale and region detection, with fallback to the site defaults.

🔐 API and security

- JSON API is protected by API keys. You manage keys at /admin/api-keys.
- Authentication, endpoints, response details, and integration examples are documented in [docs/API.md](docs/API.md).
- Optional secure push mode for Ecowitt receivers.

📡 Community telemetry

- Optional station telemetry upload to a community aggregator.
- Community stations map page, if you enable telemetry.

🤖 Optional AI

- Built in forecast narrator.
- Optional AI rephrasing for forecast text, off by default.

## Requirements

- PHP 8.2 or newer.
- SQLite or MySQL.
- Composer.
- Node.js and npm for building frontend assets.

## Quick start for local development

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

Local development helpers

```bash
composer run setup
composer run dev
composer test
```

## Admin bootstrap

`php artisan db:seed` seeds system settings.
Create your first admin account with:

```bash
php artisan admin:create
```

For automation, use non-interactive flags:

```bash
php artisan admin:create --email=admin@example.com --password="strong-password" --name="Administrator"
```

After login, users can change their own password from `/profile`.
If you lose admin access, use `php artisan admin:fix-access <email> --password="new-strong-password"`.

## Deployment

[DEPLOYMENT.md](DEPLOYMENT.md) covers production setup, cron, storage permissions, and web server configuration.
For in-app updater behavior (what it runs and what it does not), see the "In-app updater command behavior" section in [DEPLOYMENT.md](DEPLOYMENT.md).
If you do not have Node.js/npm available, use [SHARED_HOSTING_QUICKSTART.md](SHARED_HOSTING_QUICKSTART.md) for a no-npm deployment flow.

## Docker container

WeatherNode is also available as a Docker image (published on GitHub Container Registry):

```bash
docker pull ghcr.io/centauri/weathernode:latest
```

Recommended Docker setup (compose-first):

```bash
git clone https://github.com/centauri/WeatherNode.git
cd WeatherNode
# Edit docker-compose.yml: set APP_URL and APP_KEY
php artisan key:generate --show
make docker-up
```

The container startup runs migrations automatically. On first startup it also runs one-time bootstrap (`storage:link`, `db:seed`, and optional `admin:create` when `ADMIN_EMAIL` + `ADMIN_PASSWORD` are set in `docker-compose.yml`).
If you leave `ADMIN_EMAIL` / `ADMIN_PASSWORD` empty, create the first admin account in the browser at `/setup/admin` (available only while no users exist).

Log in and WeatherNode asks where the station is and where its readings come from, with a map for the coordinates. It ships knowing nothing about any particular place, so this is the one thing it needs before the weather pages mean anything. It takes a minute and can be put off.

For a friendlier startup check flow, use:

```bash
make docker-up
```

If you changed Docker build files, rebuild with:

```bash
make docker-rebuild
```

For full Docker usage (including the scheduler container), see [DOCKER.md](DOCKER.md).

### Release artifacts: why no ZIP on normal push?

A normal commit + push to `main` only builds/publishes the Docker image (`latest`).
The deploy ZIP (`weathernode-deploy.zip`) is created only for tag pushes (`v*`), for example:

```bash
git tag v2026.05.1
git push origin v2026.05.1
```

## AI features

WeatherNode can generate short forecast text.
It uses a built in narrator.
You can enable optional AI rephrasing for that text, it is off by default.
Configuration lives in config/nlg.php and the related environment variables.
If you enable AI rephrasing, the app may send forecast text to the configured provider.

## Developer utilities

```bash
make clean
make release
```

`make release` expects a plain text/Markdown changelog bullet (raw HTML tags are rejected).
