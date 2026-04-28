#!/usr/bin/env sh
set -e

DB_FILE="/app/database/database.sqlite"

if [ ! -f "$DB_FILE" ]; then
    touch "$DB_FILE"
    chown www-data:www-data "$DB_FILE" || true
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
