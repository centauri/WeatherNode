# Admin Guide (WeatherNode)

This guide is for **admins** managing a WeatherNode dashboard.

## Login

- Admin panel: `/admin`
- After login you can access all settings under `/admin/settings/*`.

## First run

A new install asks where it is before it will let you do anything else, because
forecasts, sunrise times, warnings and tides all start from that. Until it is
answered, any admin page sends you to the step still owed.

**Step one, the station.** Name, place, position, height and timezone, plus the
address the site is reached at. The position is the awkward part, so the form
does the work: click the map or drag the marker and the numbers fill in, type
the numbers and the marker follows. A **Use my location** button appears when
the browser will answer, which needs a secure context, so it is hidden on a
plain HTTP address on your own network. Typing two numbers always works, map or
no map. The timezone list opens on whatever zone your browser is in.

**Step two, the data source.** The station or software that sends the readings,
and optionally which kit it is. Choosing a source takes you to its own settings
page afterwards, which is where keys and addresses go.

**I will do this later** stops the redirecting at once and leaves a reminder in
the admin until you finish. Nothing here is permanent: it all lives in
`/admin/settings/station` and `/admin/settings/livedata` afterwards.

Existing installs never see any of this. The setup only opens on a database
that has just been seeded for the first time.

## Key admin pages

- **Updates** (`/admin/settings/updates`)
  - Check for available updates
  - Preview updates before deploying
  - Deploy updates with automatic backups and rollback
  - View update history and rollback to previous versions
  - See compatibility status for browser-based updates
- **Users** (`/admin/users`)
  - Create, edit, and delete user accounts
  - Assign admin roles
  - **Enable/disable public registration** (see below)
- **Widgets** (`/admin/settings/widgets`)
  - Enable/disable widgets
  - Bottom row columns (3/4 columns)
  - Configure per-widget visualization styles (where available)
- **Effects** (`/admin/settings/effects`)
  - Configure global weather effects (rain/snow/wind/lightning/fog)
  - Tune intensity and test-mode options
- **Appearance** (`/admin/settings/appearance`)
  - **Colour palette**: choose WeatherNode (original/default blue), Ocean (optional teal), Forest (green), or Solar Flare (plum, orange and apricot). Each has light and dark versions and works with both FX and Flat.
  - **Default colour mode**: Dark, Light, or System. Visitors can override it in the public header or return to the station default. Existing installations keep WeatherNode Dark until changed. The admin panel keeps its own theme preference.
  - Custom colour editing and palette import/export are not included.
  - Visual effects: choose **FX** (glass, blur, animations) or **Flat** (simplified design, no glass/blur/animations) for the entire public site. Data and functionality are unchanged; only the visual style switches. Useful for performance, accessibility, or a minimal look.
- **Social Sharing Cards** (`/admin/settings/og`)
  - Enable/disable dynamic Open Graph images for social media previews
  - Shows which image driver (GD / Imagick) is available on the server
  - When both GD and Imagick are installed, choose preferred driver; otherwise the available one is used automatically
  - Live preview links for all card types once enabled
  - When enabled, all public pages serve a dynamic `og:image` meta tag (1200×630 PNG) so that sharing any page on WhatsApp, X/Twitter, Facebook, etc. shows a branded weather card
- **Tides** (`/admin/settings/tide`)
  - Enable/disable tidal data; choose station and data source
  - Defaults to Open-Meteo Marine, which works anywhere from your station coordinates and needs no gauge
  - Rijkswaterstaat, NOAA and others are available too; the gauge networks ask you to pick a station
  - Polled hourly; public page at `/water`
- **Waves & Sea Temp** (`/admin/settings/waves`)
  - Enable/disable wave and sea temperature data from Open-Meteo Marine API (free, coordinate-based)
  - Public pages at `/water/waves` and `/water/temp`
- **Rivers** (`/admin/settings/rivers`)
  - Enable/disable river level data per provider; configure station codes
  - Rijkswaterstaat provider: search 329+ stations, filter by river, add custom stations
  - Public page at `/water/rivers`
- **Pollen** (`/admin/settings/pollen`)
  - Enable/disable pollen forecast; configure optional API keys for Google Pollen API and Ambee
  - Data sources are blended: Open-Meteo (free default) → Google Pollen → Ambee (priority order)
  - Public tab at `/pollen` (on the Air Quality page)
- **Notifications** (`/admin/settings/notifications`)
  - Configure system health notifications: sensor offline, data fetch failures, data save failures, stale source file, cache missing, API errors
  - Choose delivery method: email, webhook, or both
  - See also: mail configuration at `/admin/settings/mail`
- **Scheduler** (`/admin/settings/scheduler`)
  - Verify scheduled jobs are running
  - See which pollers run and when
- **Station / Live Data Source**
  - A new install asks for all of this on first login, with a map for the coordinates. See **First run** below
  - Station info (name, place, coordinates, height, timezone, site address) at `/admin/settings/station`
  - Coordinates are checked: latitude -90 to 90, longitude -180 to 180, and neither may be left blank
  - Configure your live station feed (local file/API or cloud)

## User Registration

Control whether new users can register on the site:

1. Go to **Admin → Users** (`/admin/users`)
2. At the top, find the **Registration Settings** card
3. Toggle **Allow User Registration** on/off
4. Click **Save**

**When disabled:**
- The `/register` page redirects to login with a message
- Only admins can create new users via the admin panel

**When enabled (default):**
- Anyone can register at `/register`
- New users are created as regular (non-admin) users

### API credential fields (WeatherLink, Mail, Telemetry, etc.)

API keys, secrets, and passwords are entered in **masked text fields** on their dedicated settings pages (e.g. WeatherLink at `/admin/settings/weatherlink`, Mail at `/admin/settings/mail`). This avoids browser autofill issues so values save reliably. **Leave a field blank to keep the current value**; only fill it when you want to set or change it.

## Editing the dashboard layout (from the public page)

When you are logged in as admin, you can edit widget ordering directly on the dashboard:

1. Open `/`
2. Click **Edit/Bewerk**
3. Drag & drop widgets
4. Click **Done/Klaar**

Changes apply for all visitors.

## Visual testing: admin-only debug overrides

For quick visual testing (e.g. simulate storm wind or heavy rain), the dashboard supports **URL-based debug overrides**.
These overrides are **only applied for logged-in admins** (`auth()->user()->is_admin`). For visitors, the parameters are ignored.

### Parameters

- **Wind**
  - `debug_wind_speed` (number, km/h)
  - `debug_wind_dir` (number, degrees)
- **Temperature**
  - `debug_temp` (number, uses the same unit as `current.temperature`)
- **Precipitation**
  - `debug_rain_rate` (number, mm/h)
  - `debug_rain_daily` (number, mm)

### Examples

- Storm wind (triggers wind FX):
  - `/?debug_wind_speed=80&debug_wind_dir=240`
- Storm-force gusts (triggers **extreme-wind toast**, threshold ≥ 89 km/h):
  - `/?debug_wind_speed=95&debug_wind_dir=240`
- Heat test (triggers **extreme-heat toast**, threshold ≥ 35 °C):
  - `/?debug_temp=35`
- Heavy rain (triggers rain FX at any rate; triggers **heavy-rain toast** at ≥ 10 mm/h):
  - `/?debug_rain_rate=6&debug_rain_daily=18`
  - `/?debug_rain_rate=15&debug_rain_daily=30`

### Disable

Remove the query parameters (refresh without them) to return to real values.

## Application Updates

WeatherNode includes an update system that allows you to update the application directly from the admin panel.

> **One-click updates require an auto-update-ready install** (the web root follows a
> `current/public` symlink). If your site is a static install — the app served from a fixed
> `public/` folder, common on shared hosting — the Updates page may report success while the
> live site stays on the old version. See [HOSTING.md](HOSTING.md) to check which layout you
> have; on a static install, update via Git or file sync instead.

### Accessing Updates

Navigate to **Admin → Settings → Updates** (`/admin/settings/updates`) to:
- Check if your server supports browser-based updates
- View your current version
- See available updates from GitHub
- Preview updates before deploying
- Deploy updates with one click
- View update history
- Rollback to previous versions if needed

### Update Methods

#### Docker Updates (Preferred for container deployments)

If WeatherNode runs in Docker, use image-based updates instead of the in-app updater:

```bash
docker compose pull
docker compose up -d --force-recreate
docker compose exec app php artisan migrate --force
```

Reason: containerized deployments are most reliable when code and dependencies are updated together via a new image.

#### Browser-Based Updates (Tier 1)

If your server supports it, you can update directly from the admin panel. The system will:

1. **Check Compatibility**: Verifies your server has the required capabilities
2. **Validate Requirements**: Checks PHP version, extensions, disk space, and database
3. **Create Backup**: Automatically backs up `.env`, database, and `storage/` directory
4. **Download Update**: Fetches the latest release from GitHub
5. **Verify Integrity**: Checks SHA256 checksum to ensure authenticity
6. **Deploy**: Extracts files, runs migrations, and switches to new version
7. **Health Check**: Verifies the site is working correctly
8. **Auto-Rollback**: If health check fails, automatically reverts to previous version

During browser-based ZIP updates, the updater runs migrations and clears runtime caches, but it does **not** run dependency/build commands such as `composer install`, `composer update`, `npm install`, or `npm run build`.

This works because official release ZIP artifacts are prebuilt and include production dependencies (`vendor/`) and frontend build output.

If you use Git-based updates (`UPDATER_ALLOW_GIT=true`) and the target version changed dependencies, run this manually after update:

```bash
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Do not use `composer update` on production for routine updates.

**Requirements:**
- Write access to project directory
- Symlink support
- ZIP extraction capability
- Artisan command execution

#### Manual ZIP Updates (Tier 0)

If browser updates aren't supported, you can update manually:

1. **Backup**: Manually backup your `.env` file and database
2. **Download**: Get the latest release ZIP from GitHub
3. **Upload**: Extract the ZIP over your current installation
4. **Restore**: Restore your `.env` file if it was overwritten
5. **Migrate**: Run `php artisan migrate --force`
6. **Clear Cache**: Run `php artisan cache:clear`

### Update Safety Features

All updates include multiple safety layers:

- **Automatic Backups**: Your data is backed up before every update
- **Pre-Update Validation**: System requirements are checked before downloading
- **Health Checks**: Site functionality is verified after deployment
- **Auto-Rollback**: Failed deployments are automatically reverted
- **Filesystem Lock**: Prevents multiple updates from running simultaneously
- **SHA256 Verification**: Ensures downloaded files are authentic and untampered
- **Maintenance Mode**: Site goes offline during critical update steps
- **Audit Logging**: Complete history of all update attempts with user attribution

### Preview Updates

Before deploying, use the **"Preview Update"** button to:
- Check if the update is compatible with your system
- See validation results (PHP version, extensions, disk space, etc.)
- Review what would change without actually deploying

This is a safe way to test updates without making any changes to your installation.

### Update History

The update history shows:
- All update attempts (successful and failed)
- Who initiated each update
- When updates were deployed
- Duration of each update
- Rollback information
- Error messages (if any)

### Rollback

If an update causes issues, you can rollback to any previous version:
1. Go to the "Previous Releases" section
2. Click "Rollback" on the version you want to restore
3. The system will switch back to that version immediately

### Update Notifications

Enable email notifications to be alerted when new versions are available:

1. Set `UPDATER_NOTIFY_EMAIL=true` in `.env`
2. The system checks for updates daily at 2 AM
3. All admin users receive email notifications when updates are available

### Configuration

Enable the updater in `.env`:
```env
UPDATER_ENABLED=true
UPDATER_GITHUB_REPO=your-org/WeatherNode
```

For Git-based updates (requires SSH access):
```env
UPDATER_ALLOW_GIT=true
```

### Troubleshooting Updates

**"Class Laravel\Pail\PailServiceProvider not found" error:**
- This happens when `bootstrap/cache/` from dev was uploaded to production
- Fix: `rm -f bootstrap/cache/*.php && composer install --no-dev --optimize-autoloader`
- See `DEPLOY_TO_PRODUCTION.md` for detailed instructions

**Update fails with "compatibility check failed":**
- Check the detailed compatibility results on the updates page
- Ensure your server has write access, symlink support, and ZIP extraction
- Consider using manual ZIP update instead

**Update fails with "health check failed":**
- The system automatically rolled back to the previous version
- Check the update history for error details
- Review release notes for breaking changes
- Note: release notes are sanitized on the updates page; raw HTML may be removed for security
- Contact support if the issue persists

**Can't see updates page:**
- Ensure `UPDATER_ENABLED=true` in `.env`
- Check that you're logged in as an admin user
- Verify the route is accessible: `/admin/settings/updates`


### Theme creator

Open **Settings → Appearance → Theme creator** to start from WeatherNode, Ocean, Forest or Solar Flare. Edit the dark and light colours independently with a colour picker or six-digit hex value. **More colours** exposes secondary surfaces, overlays, dividers and chart grids. The isolated preview is a compact sample using shared dashboard styles, with FX on, FX off (static), and Flat options. Layout, weather data and widget visuals can differ from the actual dashboard; it does not change the public site or admin palette. Contrast warnings check common text/surface pairs against 4.5:1 and do not block saving.

**Save theme** stores a draft in the station's single custom-theme slot. **Save and apply** also publishes a snapshot to the public site. Subsequent draft edits do not alter that snapshot until applied again. The saved custom theme is also available in Appearance alongside the protected presets. The station's default mode, visitor mode choice and FX/Flat selection remain independent.

**Export theme** downloads the current draft as JSON. **Import theme** validates a JSON file and loads it into the editor for review; it does not save or apply it. Export before replacing a custom theme if you want to keep multiple designs. Weather measurements and warning colours cannot be edited. **Restore original theme** switches back to WeatherNode Dark, retaining the saved custom theme and FX preference.
