# syntax=docker/dockerfile:1.7

# ---------- builder: composer + node, produces /app artifacts ----------
FROM dunglas/frankenphp:1-php8.4-bookworm AS builder

RUN install-php-extensions \
        pdo_sqlite \
        pcntl \
        sockets \
        opcache \
        intl \
    && apt-get update \
    && apt-get install -y --no-install-recommends git unzip ca-certificates curl gnupg \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/root/.composer/cache \
    composer install \
        --no-dev \
        --no-interaction \
        --no-scripts \
        --prefer-dist \
        --optimize-autoloader

COPY package.json package-lock.json vite.config.js ./
COPY resources ./resources
RUN --mount=type=cache,target=/root/.npm \
    npm ci && npm run build && rm -rf node_modules

COPY . .

RUN composer dump-autoload --optimize \
    && php artisan view:cache

# ---------- runtime: slim FrankenPHP, no node, no composer ----------
FROM dunglas/frankenphp:1-php8.4-bookworm AS app

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_LEVEL=warning \
    SERVER_NAME=":8000"

RUN install-php-extensions \
        pdo_sqlite \
        pcntl \
        sockets \
        opcache \
        intl \
    && apt-get update \
    && apt-get install -y --no-install-recommends sqlite3 ca-certificates \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini

WORKDIR /app

COPY --from=builder /app /app

RUN mkdir -p database storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && touch database/database.sqlite \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache /app/database \
    && chmod -R ug+rwX /app/storage /app/bootstrap/cache /app/database

EXPOSE 8000 8080

ENTRYPOINT ["/app/scripts/entrypoint.sh"]
CMD ["frankenphp", "php-server", "--listen", "0.0.0.0:8000", "--root", "public/"]
