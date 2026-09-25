#!/bin/sh
set -eu

APP_DIR="/var/www/html"
FIRST_RUN_MARKER="$APP_DIR/storage/app/.docker-initialized"

# Where the SQLite file lives. Follows DB_DATABASE so the data volume can be
# mounted outside the app directory; mounting it over $APP_DIR/database would
# also cover database/migrations, which a named volume only ever copies from
# the image once, silently freezing migrations at the version that created it.
#
# Only meaningful on SQLite. On MySQL and Postgres, DB_DATABASE is a schema
# name, and DOCKER.md tells people to set DB_DATABASE=weathernode, so this used
# to leave SQLITE_DIR as `dirname weathernode` = "." — the application
# directory, since the script cd's there. The ownership fixups below then
# recursively chowned the whole app, copying every file out of the image layer
# on overlayfs. Reported as a multi-hour startup with an empty log.
#
# Left empty for other drivers. Every consumer is [ -d ] / [ -f ] guarded, so
# empty makes them all no-ops.
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    SQLITE_PATH="${DB_DATABASE:-/var/lib/weathernode/database.sqlite}"
    SQLITE_DIR="$(dirname "$SQLITE_PATH")"
else
    SQLITE_PATH=""
    SQLITE_DIR=""
fi

# Migrations as shipped in the image, at a path no volume is mounted over.
PRISTINE_MIGRATIONS="/opt/weathernode/migrations"

cd "$APP_DIR"

# Recursive chown is expensive on overlayfs, so only do it when something is
# actually mis-owned. The find stops at the first offender, so the common case
# where everything is already correct costs a traversal and no writes.
#
# Checks every entry rather than just the top directory: the failure this
# guards against is a root-owned directory nested inside an already-correct
# storage/, created by the artisan commands this script runs as root.
fix_ownership() {
    target="$1"
    [ -d "$target" ] || return 0

    if [ -z "$(find "$target" ! -user www-data -print -quit 2>/dev/null)" ]; then
        return 0
    fi

    echo "[entrypoint] Fixing ownership under $target..."
    chown -R www-data:www-data "$target" || true
}

# Size the php-fpm pool to the memory the container actually has.
#
# The stock pool ships pm.max_children = 5. A dashboard load is one payload
# request plus a burst of radar tiles, so five workers saturate immediately and
# the payload queues behind the tiles.
#
# Raising it blindly is the wrong fix: this runs on everything from a Raspberry
# Pi to a VPS, and pm = dynamic means the ceiling is free at idle but a burst
# against too high a ceiling turns a slow page into an OOM kill. Slow beats
# dead, so the ceiling is derived from the limit rather than guessed.
#
# Set PHP_FPM_MAX_CHILDREN to override.
configure_php_fpm_pool() {
    max_children="${PHP_FPM_MAX_CHILDREN:-}"

    if [ -z "$max_children" ]; then
        # cgroup v2, then v1, then the host's total as a last resort.
        mem_bytes=""
        if [ -r /sys/fs/cgroup/memory.max ]; then
            mem_bytes="$(cat /sys/fs/cgroup/memory.max 2>/dev/null)"
        elif [ -r /sys/fs/cgroup/memory/memory.limit_in_bytes ]; then
            mem_bytes="$(cat /sys/fs/cgroup/memory/memory.limit_in_bytes 2>/dev/null)"
        fi

        # "max", or a sentinel so large it means unlimited: fall back to total RAM.
        case "$mem_bytes" in
            ''|max|*[!0-9]*) mem_bytes="" ;;
        esac
        if [ -z "$mem_bytes" ] || [ "$mem_bytes" -gt 1099511627776 ] 2>/dev/null; then
            mem_kb="$(awk '/MemTotal/ {print $2}' /proc/meminfo 2>/dev/null || echo 0)"
            mem_bytes=$((mem_kb * 1024))
        fi

        mem_mb=$((mem_bytes / 1048576))

        # Measured ~55 MB resident per worker serving the dashboard; 64 leaves
        # headroom. Only 60% of memory is handed to php-fpm, since nginx, the
        # scheduler and possibly a database live here too.
        if [ "$mem_mb" -gt 0 ]; then
            max_children=$(( (mem_mb * 60 / 100) / 64 ))
        else
            max_children=5
        fi

        [ "$max_children" -lt 5 ] && max_children=5
        [ "$max_children" -gt 32 ] && max_children=32
    fi

    start_servers=$(( max_children / 4 ))
    [ "$start_servers" -lt 2 ] && start_servers=2
    max_spare=$(( max_children / 2 ))
    [ "$max_spare" -lt "$start_servers" ] && max_spare="$start_servers"

    cat > /usr/local/etc/php-fpm.d/zz-weathernode.conf <<CONF
[www]
pm = dynamic
pm.max_children = ${max_children}
pm.start_servers = ${start_servers}
pm.min_spare_servers = 2
pm.max_spare_servers = ${max_spare}
CONF

    echo "[entrypoint] php-fpm pool: max_children=${max_children} (container memory: ${mem_mb:-unknown} MB)"
}

ensure_writable_paths() {
    # The full tree, not just the top: a named volume is seeded from the image,
    # but a bind-mounted host folder (Unraid appdata, for one) starts empty, and
    # without framework/views every page 500s with "Please provide a valid
    # cache path".
    mkdir -p "$APP_DIR/storage/logs" "$APP_DIR/storage/app/public" \
        "$APP_DIR/storage/framework/cache/data" "$APP_DIR/storage/framework/sessions" \
        "$APP_DIR/storage/framework/views" "$APP_DIR/bootstrap/cache"

    # Only for SQLite: a MySQL/Postgres install has no file to create, and
    # DB_DATABASE holds a schema name rather than a path.
    if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
        mkdir -p "$SQLITE_DIR"

        # Keep using a database left in the pre-2026.08 location.
        #
        # A new image with an un-edited compose file still has the volume over
        # $APP_DIR/database, so the live database is there while DB_DATABASE
        # points at a path with no volume behind it. Carrying on regardless
        # would create a blank database, migrate it, and look perfectly healthy
        # while the real data sat in the old volume until the next
        # `docker compose down` removed it.
        #
        # So use the file where it actually is. Not moved: that would relocate
        # live data out of the volume and onto the container filesystem, which
        # the next recreate would discard.
        if [ ! -f "$SQLITE_PATH" ] && [ -f "$APP_DIR/database/database.sqlite" ]; then
            echo "[entrypoint] NOTE: using the database at $APP_DIR/database/database.sqlite."
            echo "[entrypoint] The data volume has moved out of the application directory. When convenient, update docker-compose.yml:"
            echo "[entrypoint]     - db_data:/var/www/html/database    ->    - db_data:/var/lib/weathernode"
            echo "[entrypoint]   and set DB_DATABASE: \"/var/lib/weathernode/database.sqlite\""
            echo "[entrypoint] Same volume, new mount point, so nothing is copied or moved. Migrations apply correctly either way."
            SQLITE_PATH="$APP_DIR/database/database.sqlite"
            SQLITE_DIR="$(dirname "$SQLITE_PATH")"
            DB_DATABASE="$SQLITE_PATH"
            export DB_DATABASE
        fi

        touch "$SQLITE_PATH"
    fi

    # Mounted volumes can come in with restrictive host-side ownership/permissions.
    # Normalize write access at startup so Laravel can write logs/cache/sqlite.
    #
    # Only ever aimed at volume mounts, never at image content: chown -R against
    # an image-layer path forces overlayfs to copy every file up into the
    # container's writable layer, on every recreate.
    if [ "$(id -u)" -eq 0 ]; then
        fix_ownership "$APP_DIR/storage"
        fix_ownership "$APP_DIR/bootstrap/cache"
        [ -n "$SQLITE_DIR" ] && fix_ownership "$SQLITE_DIR"
    fi

    chmod 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" || true
    [ -n "$SQLITE_DIR" ] && [ -d "$SQLITE_DIR" ] && chmod 775 "$SQLITE_DIR" || true
    # -exec ... + batches the paths into as few chmod calls as possible. With
    # \; it is one fork per file, and storage/ holds the file cache: an install
    # with 45k cached entries spent ~2 minutes here on every container recreate,
    # before the first log line after this function.
    find "$APP_DIR/storage" -type d -exec chmod 775 {} + || true
    find "$APP_DIR/storage" -type f -exec chmod 664 {} + || true
    find "$APP_DIR/bootstrap/cache" -type f -exec chmod 664 {} + || true
    [ -f "$SQLITE_PATH" ] && chmod 664 "$SQLITE_PATH" || true
}

# Before any filesystem work, so a stall here shows up in `docker logs` instead
# of presenting as a container that is Up with an empty log.
echo "[entrypoint] Preparing filesystem (database driver: ${DB_CONNECTION:-sqlite})..."
ensure_writable_paths
configure_php_fpm_pool

# APP_KEY encrypts saved API keys and passwords, so it has to stay the same
# for the life of the install.
#
# When none is set (or the compose placeholder is still there), generate one
# on first start and keep it on the storage volume. Every later start reuses
# it. Platforms like Unraid expect an app to start with its defaults, and a
# key that is not saved anywhere is lost on the next container recreate,
# taking every encrypted setting with it.
#
# config/app.php reads the same file when APP_KEY is unset, which covers the
# compose scheduler container: it overrides this entrypoint and never gets
# the export below.
#
# A key set in the environment always wins, so existing installs are
# unaffected.
APP_KEY_FILE="$APP_DIR/storage/app/.app-key"

app_key_is_unset() {
    case "$1" in
        ""|*REPLACE_WITH_YOUR*) return 0 ;;
    esac
    return 1
}

if app_key_is_unset "${APP_KEY:-}"; then
    if [ -s "$APP_KEY_FILE" ]; then
        APP_KEY="$(tr -d '\r\n' < "$APP_KEY_FILE")"
        echo "[entrypoint] Using the APP_KEY saved in storage/app/.app-key."
    else
        APP_KEY="base64:$(head -c 32 /dev/urandom | base64 | tr -d '\n')"
        if ! ( umask 077 && printf '%s\n' "$APP_KEY" > "$APP_KEY_FILE.tmp" && mv "$APP_KEY_FILE.tmp" "$APP_KEY_FILE" ); then
            echo "ERROR: APP_KEY is not set, and a new one could not be saved to $APP_KEY_FILE."
            echo "Set APP_KEY in the container settings. Generate one with:"
            echo "  echo \"base64:\$(openssl rand -base64 32)\""
            exit 1
        fi
        echo "[entrypoint] No APP_KEY was set, so one was generated and saved to storage/app/.app-key."
        echo "[entrypoint] It encrypts saved API keys and passwords. Keep the storage folder in your backups."
    fi
    [ "$(id -u)" -eq 0 ] && chown www-data:www-data "$APP_KEY_FILE" 2>/dev/null || true
    export APP_KEY
elif [ -s "$APP_KEY_FILE" ] && [ "$(tr -d '\r\n' < "$APP_KEY_FILE")" != "$APP_KEY" ]; then
    echo "[entrypoint] WARNING: APP_KEY differs from the key saved in storage/app/.app-key. Using APP_KEY."
    echo "[entrypoint] Settings encrypted with the saved key (API keys, passwords) cannot be read with this one."
    echo "[entrypoint] To go back to the saved key, remove APP_KEY from the container settings."
fi

if [ "${DOCKER_AUTO_MIGRATE:-true}" = "true" ]; then
    echo "[entrypoint] Running database migrations..."

    # Migrate from the image's own copy when it is available. A volume mounted
    # over $APP_DIR/database hides the migrations the image shipped, and Docker
    # only seeds a named volume once, so without this an upgraded container
    # runs new code against the migrations its volume was created with and
    # reports success having applied nothing.
    #
    # Laravel records migrations by filename, so which directory they were run
    # from makes no difference to the migrations table.
    if [ -d "$PRISTINE_MIGRATIONS" ]; then
        php artisan migrate --force --no-interaction --path="$PRISTINE_MIGRATIONS" --realpath
    else
        php artisan migrate --force --no-interaction
    fi
fi

if [ ! -f "$FIRST_RUN_MARKER" ]; then
    echo "[entrypoint] First container startup detected, running one-time bootstrap..."

    php artisan storage:link || true

    if [ "${DOCKER_AUTO_SEED:-true}" = "true" ]; then
        php artisan db:seed --force --no-interaction
    fi

    if [ -n "${ADMIN_EMAIL:-}" ] && [ -n "${ADMIN_PASSWORD:-}" ]; then
        php artisan admin:create \
            --email="${ADMIN_EMAIL}" \
            --password="${ADMIN_PASSWORD}" \
            --name="${ADMIN_NAME:-Administrator}" \
            --no-interaction || true
    fi

    touch "$FIRST_RUN_MARKER"
fi

# Re-check ownership after the artisan commands above.
#
# Those run as root, and anything they write under storage/ is created
# root-owned, after the fixup at the top has already run. php-fpm then serves
# as www-data and cannot write there. With the database cache store this went
# unnoticed, because nothing created storage/framework/cache/data in the first
# place; on a file-backed cache every page 500s with
# "file_put_contents(...): Failed to open stream: No such file or directory",
# which reads as a missing path rather than a permissions problem.
if [ "$(id -u)" -eq 0 ]; then
    fix_ownership "$APP_DIR/storage"
    fix_ownership "$APP_DIR/bootstrap/cache"
fi

# Optionally run the Laravel scheduler inside this container.
#
# docker-compose.yml runs it in a second container, which is why this is off
# unless DOCKER_RUN_SCHEDULER=true. Platforms that install one container per
# app, such as Unraid, turn it on. If both run anyway, every task still runs
# once per slot: routes/console.php marks them all onOneServer().
#
# Written or removed on every start rather than baked into the image, so
# flipping the variable and restarting is enough. schedule:work starts
# schedule:run at the top of each minute, so tasks do not drift the way a
# `sleep 60` loop does.
SCHEDULER_CONF="/etc/supervisor/weathernode.d/scheduler.conf"
if [ "${DOCKER_RUN_SCHEDULER:-false}" = "true" ]; then
    if [ -w "$(dirname "$SCHEDULER_CONF")" ]; then
        echo "[entrypoint] DOCKER_RUN_SCHEDULER=true: running the scheduler in this container."
        cat > "$SCHEDULER_CONF" <<CONF
[program:scheduler]
command=php $APP_DIR/artisan schedule:work --whisper --no-interaction
user=www-data
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
CONF
    else
        echo "[entrypoint] WARNING: DOCKER_RUN_SCHEDULER=true, but $(dirname "$SCHEDULER_CONF") is not writable, so the scheduler will not run in this container."
    fi
else
    rm -f "$SCHEDULER_CONF" 2>/dev/null || true
fi

# Run whatever the container was asked to run. The default comes from CMD in
# the Dockerfile, so this is supervisord unless a command was given. Without
# this the entrypoint ended at supervisord unconditionally and
# `docker run <image> php artisan migrate` silently booted the container
# instead of running the command, which makes the image impossible to poke at
# and hides typos in one-off commands.
exec "$@"
