#!/bin/sh
#
# Container entrypoint. Runs before php-fpm (and before the queue worker,
# which reuses this image), then execs whatever CMD was given.
#
# `set -e` so a failed migration stops the container instead of leaving it
# serving requests against a half-migrated schema.
set -e

ROLE="${CONTAINER_ROLE:-app}"

log() { echo "[entrypoint] $*"; }

# ── .env ────────────────────────────────────────────────────────────────────
# Config is normally passed as compose environment variables. A file is still
# created because `artisan key:generate` and a few packages expect one to
# exist; values already in the environment win over anything in it.
if [ ! -f .env ]; then
    if [ -f .env.docker ]; then
        log "no .env — seeding from .env.docker"
        cp .env.docker .env
    else
        log "no .env — seeding from .env.example"
        cp .env.example .env
    fi
fi

# ── APP_KEY ─────────────────────────────────────────────────────────────────
# Generated only as a fallback. A key that is minted at container start is a
# *new* key on every rebuild, which invalidates every encrypted value and
# signed URL already issued — so a real deployment must pass APP_KEY in.
if [ -z "${APP_KEY:-}" ] && ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
    log "APP_KEY missing — generating an ephemeral one"
    log "WARNING: set APP_KEY in the environment for anything but local dev;"
    log "         a fresh key per start breaks existing signed URLs."
    php artisan key:generate --force --no-interaction
fi

# ── wait for MySQL ──────────────────────────────────────────────────────────
# The compose healthcheck already gates startup, but this covers running the
# image outside compose, and a database that restarts under us.
if [ "${WAIT_FOR_DB:-true}" = "true" ]; then
    DB_HOST="${DB_HOST:-mysql}"
    DB_PORT="${DB_PORT:-3306}"
    ATTEMPTS=0
    MAX_ATTEMPTS="${DB_WAIT_ATTEMPTS:-60}"

    log "waiting for ${DB_HOST}:${DB_PORT}"
    until mysqladmin ping -h"$DB_HOST" -P"$DB_PORT" --silent 2>/dev/null; do
        ATTEMPTS=$((ATTEMPTS + 1))
        if [ "$ATTEMPTS" -ge "$MAX_ATTEMPTS" ]; then
            log "database not reachable after ${MAX_ATTEMPTS} attempts — giving up"
            exit 1
        fi
        sleep 2
    done
    log "database is up"
fi

# ── one-time-per-deploy work, app container only ────────────────────────────
# Guarded by role so the queue worker does not race the web container running
# the same migrations at the same time.
if [ "$ROLE" = "app" ]; then

    if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
        log "running migrations"
        php artisan migrate --force --no-interaction
    fi

    # Opt-in: seeding an existing database would duplicate the demo rows.
    if [ "${RUN_SEEDERS:-false}" = "true" ]; then
        log "seeding database"
        php artisan db:seed --force --no-interaction
    fi

    # The image already ships public/storage as a relative symlink (see the
    # Dockerfile), which stays correct when storage/ is mounted as a volume.
    # This only covers the case where it is somehow absent — never replaces a
    # working link, because `storage:link` writes an absolute path that the
    # nginx image's copy of public/ would not resolve.
    if [ ! -e public/storage ]; then
        log "public/storage missing — relinking"
        ln -s ../storage/app/public public/storage || \
            php artisan storage:link --no-interaction || true
    fi

    # Caches are built at start, not at build time: they bake in environment
    # values (APP_URL, DB credentials) that are not known until the container
    # actually runs.
    if [ "${CACHE_CONFIG:-true}" = "true" ]; then
        log "caching config, routes and views"
        php artisan config:cache --no-interaction
        php artisan route:cache --no-interaction
        php artisan view:cache --no-interaction
    else
        # Clear instead, so a stale cache from a previous run cannot survive.
        php artisan config:clear --no-interaction
        php artisan route:clear --no-interaction
        php artisan view:clear --no-interaction
    fi
fi

# Writable even when storage/ is a fresh named volume owned by root.
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

log "starting: $*"
exec "$@"
