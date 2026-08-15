# syntax=docker/dockerfile:1

# =============================================================================
#  Inthes API — PHP 8.3 / Laravel 13
#
#  Three stages so the runtime image carries neither Node nor Composer:
#    assets  — builds the Vite bundle (the welcome page uses @vite)
#    vendor  — resolves Composer deps against a Linux platform
#    runtime — nginx + php-fpm + the two build outputs
#
#  The runtime image is self-contained: nginx and php-fpm run side by side
#  under supervisord rather than as two containers on a compose network.
#  Single-container platforms (Render, Fly, Cloud Run, App Runner) run one
#  image per service and never read docker-compose.yml — an nginx-only image
#  there fails at boot with `host not found in upstream "app"`, because the
#  service name it wants to reach does not exist outside compose.
#
#  Not `artisan serve`: that is a single-process dev server and blocks on one
#  request at a time, which stalls the app the moment a 50MB intro video is
#  uploading.
# =============================================================================

# -----------------------------------------------------------------------------
# Stage 1 — front-end assets
# -----------------------------------------------------------------------------
FROM node:22-bookworm-slim AS assets

WORKDIR /app

# Lockfile first so this layer survives source edits. `npm ci` needs
# package-lock.json; fall back to `npm install` when the repo has none.
COPY package.json package-lock.json* ./
RUN if [ -f package-lock.json ]; then npm ci --ignore-scripts; \
    else npm install --ignore-scripts; fi

COPY vite.config.js ./
COPY resources ./resources

# Needs outbound HTTPS: vite.config.js registers laravel-vite-plugin's
# `bunny()` font plugin, which downloads Instrument Sans at build time and
# bundles it into public/build. On a network that cannot reach
# fonts.bunny.net the build fails with a ConnectTimeoutError rather than
# falling back — drop the `fonts:` block from vite.config.js to build offline.
#
# Output is public/build/ (laravel-vite-plugin's default), which the runtime
# stage copies.
RUN npm run build


# -----------------------------------------------------------------------------
# Stage 2 — Composer dependencies
# -----------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

# --no-scripts because post-autoload-dump runs `artisan package:discover`, and
# the application code is not in this stage yet. Discovery happens in the
# runtime stage once the full source is present.
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-interaction \
        --prefer-dist \
        --no-progress \
        --optimize-autoloader


# -----------------------------------------------------------------------------
# Stage 3 — runtime
# -----------------------------------------------------------------------------
FROM php:8.3-fpm-bookworm AS runtime

# `install-php-extensions` handles the system -dev packages each extension
# needs, which hand-rolled docker-php-ext-install lines get wrong often enough
# to be worth the extra binary.
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
        bcmath \
        exif \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        zip \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        # gettext-base provides envsubst, which renders $PORT into the nginx
        # config at start — the platform picks the port, not the image.
        gettext-base \
        # mysqladmin, for the entrypoint's readiness probe
        default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# pcntl is what lets `queue:work` handle SIGTERM, so a `docker compose down`
# lets the current job finish instead of killing it mid-write.

WORKDIR /var/www/html

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-www.conf
# Replaces Debian's own supervisord.conf rather than dropping a file into
# conf.d/: this file carries its own [supervisord] section, and having two of
# them in one config tree makes which settings win depend on include order.
COPY docker/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/nginx/default.conf.template /etc/nginx/templates/default.conf.template

# The distro's default site would otherwise also bind a port and shadow ours.
RUN rm -f /etc/nginx/sites-enabled/default /etc/nginx/conf.d/default.conf

# Application source, then the two build outputs on top.
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

# Now that source + vendor are both present, redo the autoloader and run the
# discovery that stage 2 deliberately skipped.
#
# The re-dump matters: stage 2 ran `--optimize` with only composer.json in the
# tree, so the classmap it built has no entry for anything under app/ — those
# classes would fall back to a PSR-4 filesystem lookup on every resolve.
#
# Composer is bind-mounted for this one command rather than installed. The
# runtime image has no use for it afterwards, and `COPY` + a later `rm` would
# still leave the binary sitting in an intermediate layer.
#
# Needs BuildKit (guaranteed by the `# syntax` line at the top). On a builder
# old enough to reject `--mount`, swap this for a plain
# `COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer` above the
# RUN and drop the mount flag — same result, ~3MB heavier.
RUN --mount=type=bind,from=composer:2,source=/usr/bin/composer,target=/usr/local/bin/composer \
    composer dump-autoload --no-dev --optimize --no-interaction \
    && php artisan package:discover --ansi

# Laravel writes to both trees at runtime.
RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        storage/app/public \
        storage/app/private \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

# Relative symlink, so it stays correct whether storage/ is the image's own
# directory or a mounted volume. nginx serves profile photos and org logos
# through it.
RUN rm -rf public/storage \
    && ln -s ../storage/app/public public/storage

COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/entrypoint.sh

# Documentation only — the actual port is whatever $PORT says at runtime.
# 8080 is the default the entrypoint falls back to.
ENV PORT=8080
EXPOSE 8080

ENTRYPOINT ["entrypoint.sh"]

# Supervisord runs nginx + php-fpm. Override for a worker:
#   command: php artisan queue:work
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf", "-n"]
