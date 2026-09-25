# Docker deployment

> **Comparing hosting options?** See [HOSTING.md](HOSTING.md). Docker is one of the
> supported methods; you update by pulling a new image tag and recreating the container —
> the in-app one-click updater does not apply to containers (the image filesystem is
> immutable; persistent data lives in mounted volumes).

This document describes running WeatherNode in Docker and how to use it.

## Feasibility summary

**Yes, it is feasible** to run WeatherNode in Docker. The app is a standard Laravel 12 stack:

| Requirement | Docker approach |
|-------------|-----------------|
| PHP 8.2+ with extensions | Use official `php:8.2-fpm` and install `pdo`, `pdo_sqlite`, `pdo_mysql`, `mbstring`, `xml`, `curl`, `zip`, `gd`, `fileinfo` (see DEPLOYMENT.md). |
| Composer | Run `composer install --no-dev` in the image build. |
| Node/npm (frontend build) | Multi-stage build: run `npm ci && npm run build` in a Node stage and copy `public/build` into the PHP image. No Node in the final image. |
| SQLite or MySQL | SQLite: named volume at `/var/lib/weathernode`, outside the app directory. MySQL: use a `mysql` service in `docker-compose` and set `DB_*` in `.env`. |
| Web server (document root = `public/`) | Nginx runs in the same image via supervisord. |
| Laravel scheduler (cron) | A second container from the same image runs `php artisan schedule:run` every minute. Or set `DOCKER_RUN_SCHEDULER=true` to run it inside the app container instead (see below). |
| Writable storage | Named volumes for `storage/` and `bootstrap/cache`. |

## Quick start

Run commands from the repository root (the folder containing `docker-compose.yml` and `Dockerfile`).

1. **Edit `docker-compose.yml`**
   - Update `APP_URL` for your host/domain.
   - Replace `APP_KEY` with a real key.
   - Optional: set `ADMIN_EMAIL` and `ADMIN_PASSWORD` for first-run admin creation.
     - If you prefer the normal web flow, leave them empty and create the first admin from `/setup/admin` in the browser after startup.
     - `/setup/admin` is only available on a fresh install and disables itself after the first user is created.

   Generate a key without a local PHP/Composer install:
   ```bash
   echo "base64:$(openssl rand -base64 32)"
   ```
   (Or, if you already have a local dev checkout with dependencies installed,
   `php artisan key:generate --show`.)

2. **Start stack**
   Preferred helper:
   ```bash
   make docker-up
   ```

   Direct compose command:
   ```bash
   docker compose up -d
   ```
   Open http://localhost:8080 (or the port you set in docker-compose).

  On startup, the `app` container now:
   - normalizes mounted volume permissions for `storage/`, `bootstrap/cache`, and the SQLite directory
   - runs `php artisan migrate --force` (default on every start)
   - runs first-run bootstrap once (`storage:link`, `db:seed`, optional `admin:create` via env)

  If no user exists yet and you did not set `ADMIN_*` env values, open `/setup/admin` once to create the first admin account.

  Either way, the first login opens a short setup that asks where the station is and where its readings come from. Nothing in the image knows about any particular place, so answer that before judging what the weather pages show.

3. **Optional: initial weather data**
   ```bash
   docker compose exec app php artisan weather:fetch --save
   docker compose exec app php artisan weather:poll-external --force
   ```

### Which image tag to run

| Tag | What it is |
| --- | --- |
| `latest` | the newest release. What `docker-compose.yml` uses, and what you want |
| `v2026.09.4` and so on | one specific release, pinned |
| `edge` | the tip of `main`, built on every merge. Unreleased work, no release notes, and no promise it behaves |

`latest` followed `main` until September 2026, so an older compose file pulling `latest` was tracking unreleased commits. It now moves only when a release is tagged.

If you changed `Dockerfile` or other build-time files, rebuild with:

```bash
make docker-rebuild
```

## What’s included

- **Dockerfile**: Multi-stage build (Node for `npm run build`, then PHP 8.2-FPM + Nginx). Final image runs Nginx and PHP-FPM via supervisord.
- **docker-compose.yml**: `app` (web), `scheduler` (runs `schedule:run` every minute), compose-managed environment values (no `.env` copy required), and volumes for `storage/`, `bootstrap/cache`, and the SQLite database at `/var/lib/weathernode`.
- **docker/entrypoint.sh**: startup bootstrap for migrations and one-time first-run initialization.
- **.dockerignore**: Keeps build context small (excludes `.git`, `node_modules`, `vendor`, `storage/logs`, etc.).

## Using MySQL instead of SQLite

In `docker-compose.yml`, uncomment the `mysql` service and set these values in the shared environment block:

```env
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=weathernode
DB_USERNAME=weathernode
DB_PASSWORD=your_password
```

Run migrations (and optionally seed/create admin) as above.

## Notes

- **Laravel Sail**: The project has `laravel/sail` in `require-dev` for local development. This Docker setup is a self-contained production-style alternative.
- **Cron on the host**: Not required; the `scheduler` container replaces it.
- **One container instead of two**: set `DOCKER_RUN_SCHEDULER: "true"` and the app container runs the scheduler itself, next to nginx and php-fpm. This is for platforms that install one container per app, such as Unraid. It is off by default, so existing compose setups keep working unchanged. If you turn it on, remove the `scheduler` service from `docker-compose.yml`. If you forget, nothing runs twice: every task takes a lock in the shared cache first, so each one still runs once per slot.
- **Deploy script**: `deploy.sh` excludes `Dockerfile` and `docker-compose*.yml`, so they are not overwritten when deploying to a non-Docker server.
- **Bootstrap toggles**: set `DOCKER_AUTO_MIGRATE` or `DOCKER_AUTO_SEED` to `"false"` in `docker-compose.yml` to disable automatic startup actions.
- **Makefile helpers**:
  - `make docker-up` checks that the `APP_KEY` placeholder was replaced, then runs `docker compose up -d`.
  - `make docker-rebuild` does the same check, then runs `docker compose build --no-cache && docker compose up -d`.

## Lessons learned (first-boot reliability)

### 1) APP_KEY must be valid for Laravel

Use a 32-byte key encoded as `base64:...`:

```bash
echo "base64:$(openssl rand -base64 32)"
```

Sanity-check in a running container:

```bash
docker exec weathernode-app php -r '$k=getenv("APP_KEY"); $r=base64_decode(substr($k,7), true); echo strlen($r).PHP_EOL;'
```

Expected output: `32`.

### 2) Include scheme and port in APP_URL behind host-port mappings

When publishing non-default ports (for example `8089:80`), set:

```yaml
APP_URL: "http://192.168.1.15:8089"
```

Verify redirects keep the port:

```bash
curl -I http://192.168.1.15:8089/admin
```

Expected `Location` should include `:8089` (for unauthenticated users, usually `/login`).

### 3) Upgrading from a pre-2026.08 compose file

The SQLite volume used to be mounted at `/var/www/html/database`. That path also
holds `database/migrations`, and Docker only copies a named volume's contents
from the image the first time the volume is created, so every later image pull
ran new code against the migrations the volume was created with. New migrations
were never applied and `migrate` reported success anyway.

**Nothing breaks if you do nothing.** The container keeps using the database
where it already is, and migrations are applied from a copy inside the image
that no volume can cover, so an old compose file gets its migrations correctly.
The startup log points out the change each time.

When convenient, move the volume out of the application directory:

```yaml
    volumes:
-     - db_data:/var/www/html/database
+     - db_data:/var/lib/weathernode
```

and add to the environment block:

```yaml
  DB_DATABASE: "/var/lib/weathernode/database.sqlite"
```

Your database is not moved or copied. It is the same volume mounted at a
different path, and the SQLite file sits at the volume root either way.

Any migrations skipped while the old layout was in place are applied on the
first start after upgrading, whichever layout you are on.

### 4) Read-only volume symptoms

If first boot returns HTTP 500 with messages like:
- `laravel.log could not be opened in append mode`
- `attempt to write a readonly database`

the mounted volume permissions are too restrictive for PHP-FPM (`www-data`).
The container startup now normalizes writable paths (`storage`, `bootstrap/cache`, and the SQLite directory) automatically, but existing installations with strict host policies (for example Unraid) may need one manual ownership/permission correction once.
