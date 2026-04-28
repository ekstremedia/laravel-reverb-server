# syntax=docker/dockerfile:1.7

FROM dunglas/frankenphp:1-php8.4-bookworm AS app

ENV APP_ENV=local \
    APP_DEBUG=true \
    SERVER_NAME=":8000"

RUN install-php-extensions \
        pdo_sqlite \
        pcntl \
        sockets \
        opcache \
        intl \
    && apt-get update \
    && apt-get install -y --no-install-recommends git unzip sqlite3 ca-certificates curl gnupg \
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
    && mkdir -p database storage/framework/cache storage/framework/sessions storage/framework/testing storage/framework/views storage/logs bootstrap/cache \
    && touch database/database.sqlite \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache /app/database \
    && chmod -R ug+rwX /app/storage /app/bootstrap/cache /app/database

EXPOSE 8000 8080

ENTRYPOINT ["/app/scripts/entrypoint.sh"]
CMD ["frankenphp", "php-server", "--listen", "0.0.0.0:8000", "--root", "public/"]
