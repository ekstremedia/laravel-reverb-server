#!/usr/bin/env bash
set -e

DB_FILE="/app/database/database.sqlite"
KEY_FILE="/app/storage/app/.app-key"

# Solo mode: run app + reverb in this single container. Defaults make the
# `docker run -p 8000:8000 -p 8080:8080 ... solo` one-liner work without
# any extra env. Override before this point if you need different values.
if [ "$1" = "solo" ]; then
    : "${REVERB_HOST:=localhost}"
    : "${REVERB_PORT:=8080}"
    : "${REVERB_SCHEME:=http}"
    : "${REVERB_SERVER_HOST:=0.0.0.0}"
    : "${REVERB_SERVER_PORT:=$REVERB_PORT}"
    : "${REVERB_APP_ID:=local-app-id}"
    : "${REVERB_APP_KEY:=local-app-key}"
    : "${REVERB_APP_SECRET:=local-app-secret}"
    : "${APP_URL:=http://localhost:8000}"
    export REVERB_HOST REVERB_PORT REVERB_SCHEME REVERB_SERVER_HOST REVERB_SERVER_PORT \
           REVERB_APP_ID REVERB_APP_KEY REVERB_APP_SECRET APP_URL
fi

if [ ! -f "$DB_FILE" ]; then
    touch "$DB_FILE"
    chown www-data:www-data "$DB_FILE" || true
fi

# Persist a generated APP_KEY across restarts when none is supplied via env.
# Both containers share the storage volume, so they end up with the same key.
if [ -z "$APP_KEY" ]; then
    if [ ! -s "$KEY_FILE" ]; then
        echo "[entrypoint] APP_KEY not set — generating one (persisted at $KEY_FILE)"
        mkdir -p "$(dirname "$KEY_FILE")"
        printf 'base64:%s' "$(php -r 'echo base64_encode(random_bytes(32));')" > "$KEY_FILE"
        chown www-data:www-data "$KEY_FILE" 2>/dev/null || true
    fi
    APP_KEY="$(cat "$KEY_FILE")"
    export APP_KEY
fi

# Idempotent migration. SQLite busy_timeout (5s) handles a brief race
# when both app + reverb containers start simultaneously.
php artisan migrate --force --no-interaction

# Bake config/events/routes from the runtime env (env_file). Views were
# already cached at image-build time and don't depend on env. Routes are
# cached here because Livewire 4's URL prefix is derived from app.key,
# which is only known at runtime.
php artisan config:cache --no-interaction
php artisan event:cache --no-interaction
php artisan route:cache --no-interaction

if [ "$1" = "solo" ]; then
    echo "[entrypoint] solo mode: starting reverb in background, frankenphp in foreground"
    php artisan reverb:start \
        --host="$REVERB_SERVER_HOST" \
        --port="$REVERB_SERVER_PORT" \
        --no-interaction &
    REVERB_PID=$!

    trap 'kill -TERM "$REVERB_PID" 2>/dev/null || true' TERM INT

    frankenphp php-server --listen "0.0.0.0:8000" --root public/ &
    FRANKEN_PID=$!

    # Exit when whichever child dies first; supervisor-style restart is
    # delegated to `docker run --restart unless-stopped`.
    wait -n "$REVERB_PID" "$FRANKEN_PID"
    EXIT_CODE=$?
    kill -TERM "$REVERB_PID" "$FRANKEN_PID" 2>/dev/null || true
    wait || true
    exit "$EXIT_CODE"
fi

exec "$@"
