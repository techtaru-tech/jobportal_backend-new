# syntax=docker/dockerfile:1

# =============================================================================
#  Inthes API — PHP 8.3 / Laravel 13
#
#  Three stages so the runtime image carries neither Node nor Composer:
#    assets  — builds the Vite bundle (the welcome page uses @vite)
#    vendor  — resolves Composer deps against a Linux platform
#    runtime — php-fpm + the two build outputs
#
#  Serving is php-fpm behind the nginx container in docker-compose.yml, not
#  `artisan serve`: that is a single-process dev server and blocks on one
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
        # mysqladmin, for the entrypoint's readiness probe
        default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# pcntl is what lets `queue:work` handle SIGTERM, so a `docker compose down`
# lets the current job finish instead of killing it mid-write.

WORKDIR /var/www/html

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-www.conf

# Application source, then the two build outputs on top.
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

# Now that source + vendor are both present, run the discovery that stage 2
# deliberately skipped.
RUN composer dump-autoload --no-dev --optimize --no-interaction \
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

# Baked in rather than left to the entrypoint so it is present in the copy the
# `web` stage takes below — nginx serves profile photos and org logos through
# this link, and it has no entrypoint of its own to create one.
RUN rm -rf public/storage \
    && ln -s ../storage/app/public public/storage

COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]


# -----------------------------------------------------------------------------
# Stage 4 — nginx
#
# Carries its own copy of public/ instead of sharing a named volume with the
# app container. A named volume is seeded from the image only when it is first
# created, so after a rebuild it would keep serving the *previous* build's
# assets until someone deleted the volume by hand — a stale-asset bug that
# looks like a caching problem and isn't.
#
# storage/ is still a volume (it holds uploads, which must survive a rebuild)
# and is mounted read-only here so the public/storage symlink resolves.
# -----------------------------------------------------------------------------
FROM nginx:1.27-alpine AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=runtime /var/www/html/public /var/www/html/public

EXPOSE 80
