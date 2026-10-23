# syntax=docker/dockerfile:1

# PHP 8.5 with the extensions the app needs: bcmath for money, intl, zip for Composer.
FROM php:8.5-cli AS php
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libicu-dev libzip-dev \
    && docker-php-ext-install bcmath intl zip \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

# Install PHP dependencies and build the frontend. Node runs next to PHP because the
# Wayfinder Vite plugin calls `php artisan wayfinder:generate` during the build.
FROM php AS build
COPY --from=node:24-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:24-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    && npm run build \
    && rm -rf node_modules

# The runtime image: the app with its vendor and built assets, no Node.
FROM php AS app
COPY --from=build --chown=www-data:www-data /var/www/html /var/www/html
RUN mkdir -p /data && chown www-data:www-data /data

# On start: create the SQLite database and an app key in the /data volume on first run,
# migrate (roles, permissions and expense categories are seeded by migrations), then serve.
COPY --chmod=755 <<'EOF' /usr/local/bin/start
#!/bin/sh
set -e
touch "$DB_DATABASE"
if [ -z "$APP_KEY" ]; then
    [ -f /data/app_key ] || php artisan key:generate --show > /data/app_key
    export APP_KEY="$(cat /data/app_key)"
fi
php artisan migrate --force
php artisan optimize
exec php artisan serve --host=0.0.0.0 --port=8000 --no-reload
EOF

USER www-data
ENV APP_ENV=production \
    APP_DEBUG=false \
    APP_URL=http://localhost:8000 \
    APP_LOCALE=uz \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/data/database.sqlite \
    SESSION_DRIVER=database \
    CACHE_STORE=database \
    QUEUE_CONNECTION=sync \
    MAIL_MAILER=log \
    PHP_CLI_SERVER_WORKERS=4
VOLUME /data
EXPOSE 8000
CMD ["start"]
