# ──────────────────────────────────────────────────────────────────────────────
# Stage 1 — Composer dependencies (production only)
# ──────────────────────────────────────────────────────────────────────────────
FROM php:8.3-cli-alpine AS composer

WORKDIR /app
ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apk add --no-cache git unzip
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Cache composer deps separately from application code
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
RUN composer dump-autoload --classmap-authoritative --no-dev --no-scripts \
    && composer clear-cache

# ──────────────────────────────────────────────────────────────────────────────
# Stage 2 — Production image (PHP-FPM + Nginx + Supervisor)
# ──────────────────────────────────────────────────────────────────────────────
FROM php:8.3-fpm-alpine AS production

# NaluzPHP needs mbstring, openssl, sodium, fileinfo and curl: all already compiled into the official image.
# pdo_pgsql and pdo_mysql so DB_CONNECTION=pgsql|mysql work (SQLite is built in); DEPLOY.md promises all three.
# `apk upgrade` first: when Alpine publishes a newer musl than the one in the base image, installing postgresql-dev (which needs the
# matching musl-dev) fails with "musl ... breaks: musl-dev" (this broke the v3.11.0 image build); upgrading aligns them.
RUN apk upgrade --no-cache \
    && apk add --no-cache nginx supervisor curl libpq \
    && apk add --no-cache --virtual .build-deps postgresql-dev \
    && docker-php-ext-install opcache pdo_pgsql pdo_mysql \
    && apk del .build-deps \
    && rm -rf /tmp/* /var/cache/apk/*

COPY docker/php/php.ini                 /usr/local/etc/php/conf.d/app.ini
COPY docker/php/php-fpm.conf            /usr/local/etc/php-fpm.d/www.conf
COPY docker/nginx/default.conf          /etc/nginx/http.d/default.conf
COPY docker/supervisor/supervisord.conf /etc/supervisord.conf

WORKDIR /var/www/html
COPY --from=composer /app /var/www/html

# Writable dirs (entrypoint.sh recreates them at start-up in case storage/ is a mounted volume).
RUN mkdir -p storage/app storage/cache storage/logs storage/sessions storage/nosql \
    /var/log/supervisor /run/nginx \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage

EXPOSE 80

# No Docker HEALTHCHECK on purpose: on the production Swarm it marked a perfectly working container unhealthy and Swarm
# stopped it (SIGQUIT, "Complete"), leaving the site at 0/1 replicas. The release workflow requests the live site after
# every deploy instead.

COPY deploy/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
