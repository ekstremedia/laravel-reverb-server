#!/usr/bin/env bash
set -euo pipefail

ENV_FILE="${ENV_FILE:-.env}"

if [[ ! -f "$ENV_FILE" ]]; then
    echo "× $ENV_FILE not found — run \`make up\` first" >&2
    exit 1
fi

read_env() {
    local key="$1"
    local value
    value=$(grep -E "^${key}=" "$ENV_FILE" | tail -n1 | cut -d'=' -f2- || true)
    value="${value%\"}"
    value="${value#\"}"
    echo "$value"
}

APP_URL=$(read_env APP_URL)
APP_PORT_HOST=${APP_PORT:-8120}
APP_URL=${APP_URL:-http://laravel-reverb-server.test:${APP_PORT_HOST}}

REVERB_APP_ID=$(read_env REVERB_APP_ID)
REVERB_APP_KEY=$(read_env REVERB_APP_KEY)
REVERB_APP_SECRET=$(read_env REVERB_APP_SECRET)
REVERB_HOST=$(read_env REVERB_HOST)
REVERB_PORT=$(read_env REVERB_PORT)
REVERB_SCHEME=$(read_env REVERB_SCHEME)

if [[ -t 1 ]]; then
    GREEN=$'\033[1;32m'
    CYAN=$'\033[1;36m'
    DIM=$'\033[2m'
    NC=$'\033[0m'
else
    GREEN=''; CYAN=''; DIM=''; NC=''
fi

printf '\n%s✅  Site is ready at %s%s\n' "$GREEN" "$APP_URL" "$NC"
printf '    %sLogin:%s    admin@admin.com\n' "$DIM" "$NC"
printf '    %sPassword:%s testing123\n\n' "$DIM" "$NC"

printf "%sPaste these into your other Laravel application's .env to broadcast through this server:%s\n\n" "$CYAN" "$NC"

cat <<EOF
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=${REVERB_APP_ID}
REVERB_APP_KEY=${REVERB_APP_KEY}
REVERB_APP_SECRET=${REVERB_APP_SECRET}
REVERB_HOST=${REVERB_HOST}
REVERB_PORT=${REVERB_PORT}
REVERB_SCHEME=${REVERB_SCHEME}

VITE_REVERB_APP_KEY="\${REVERB_APP_KEY}"
VITE_REVERB_HOST="\${REVERB_HOST}"
VITE_REVERB_PORT="\${REVERB_PORT}"
VITE_REVERB_SCHEME="\${REVERB_SCHEME}"

EOF
