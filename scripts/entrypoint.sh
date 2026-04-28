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

exec "$@"
