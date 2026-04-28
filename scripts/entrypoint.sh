#!/usr/bin/env sh
set -e

DB_FILE="/app/database/database.sqlite"
KEY_FILE="/app/storage/app/.app-key"

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

exec "$@"
