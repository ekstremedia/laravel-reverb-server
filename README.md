# Laravel Reverb Server

A self-contained, dockerised [Laravel Reverb](https://laravel.com/docs/reverb) WebSocket server with a small admin dashboard. Spin it up once, then point any other Laravel application at it for real-time broadcasting.

## What it gives you

- **WebSocket server** on `:8080` (Pusher-protocol compatible — works with Laravel Echo out of the box).
- **Login-gated dashboard** at `/dashboard` showing reachability status, message/channel counters, a 7-day usage chart, and a live ping/pong tester.
- **Stats persisted to SQLite** via listeners on every Reverb lifecycle event (`MessageSent`, `MessageReceived`, `ChannelCreated`, `ChannelRemoved`, `ConnectionPruned`).
- **Log viewer** at `/settings/logs` ([opcodes/log-viewer](https://github.com/opcodesio/log-viewer)).
- **Copy-paste `.env` block** rendered on the dashboard so wiring up another Laravel app is one paste away.

## Requirements

- Docker + Docker Compose
- A `.test` resolver (Laravel Herd, dnsmasq, or an `/etc/hosts` entry pointing `laravel-reverb-server.test` to `127.0.0.1`).

## Get it running

### One-liner (single container, both processes)

For a quick spin-up — same shape as Mailpit:

```sh
docker run -d --restart unless-stopped --name reverb-server \
  -p 8000:8000 -p 8080:8080 \
  terjen/laravel-reverb-server solo
```

- Dashboard: <http://localhost:8000>
- Reverb WebSocket: `ws://localhost:8080`
- Login: `admin@admin.com` / `testing123`

`solo` runs both Reverb and FrankenPHP in the same container with sane localhost defaults. APP_KEY auto-generates and persists in the container's storage on first boot. For production splits across two containers, see Option A below.

### Option A — prebuilt image (no clone needed)

Multi-arch images (`linux/amd64`, `linux/arm64`) are published on Docker Hub: [`terjen/laravel-reverb-server`](https://hub.docker.com/r/terjen/laravel-reverb-server). Drop a `docker-compose.yml` like the one below next to a `.env` and `docker compose up -d` — the entrypoint generates and persists an `APP_KEY` in the storage volume on first boot, so no key juggling.

```yaml
services:
  app:
    image: terjen/laravel-reverb-server:latest
    restart: unless-stopped
    environment:
      APP_URL: http://laravel-reverb-server.test:8121
      REVERB_HOST: laravel-reverb-server.test
      REVERB_PORT: 8180
      REVERB_SCHEME: http
      REVERB_APP_ID: local-app-id
      REVERB_APP_KEY: local-app-key
      REVERB_APP_SECRET: local-app-secret
    ports:
      - "8121:8000"
    volumes:
      - sqlite-data:/app/database
      - app-storage:/app/storage
    depends_on: [reverb]
    command: ["frankenphp", "php-server", "--listen", "0.0.0.0:8000", "--root", "public/"]

  reverb:
    image: terjen/laravel-reverb-server:latest
    restart: unless-stopped
    environment:
      REVERB_SERVER_HOST: 0.0.0.0
      REVERB_SERVER_PORT: 8180
      REVERB_APP_ID: local-app-id
      REVERB_APP_KEY: local-app-key
      REVERB_APP_SECRET: local-app-secret
    ports:
      - "8180:8180"
    volumes:
      - sqlite-data:/app/database
      - app-storage:/app/storage
    networks:
      default:
        aliases: [laravel-reverb-server.test]
    command: ["php", "artisan", "reverb:start", "--host=0.0.0.0", "--port=8180", "--no-interaction"]

volumes:
  sqlite-data:
  app-storage:
```

### Option B — clone and build (for development)

```sh
make up
```

That builds the image, starts the `app` (FrankenPHP) and `reverb` containers, runs migrations, seeds the admin user, and prints the ready banner with copy-paste env values.

Default URLs:

- Dashboard: <http://laravel-reverb-server.test:8120>
- Reverb WebSocket: `ws://laravel-reverb-server.test:8080`

Default login: `admin@admin.com` / `testing123`

If those host ports are taken, override them:

```sh
APP_PORT=18120 REVERB_PORT=18080 make up
```

## Use it from another Laravel app

The `make up` banner prints something like this — paste into the consuming app's `.env`:

```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=local-app-id
REVERB_APP_KEY=local-app-key
REVERB_APP_SECRET=local-app-secret
REVERB_HOST=laravel-reverb-server.test
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

Then in that app:

```sh
composer require laravel/reverb
npm install --save-dev laravel-echo pusher-js
```

…and broadcast as usual (`event(new YourEvent(...))` on a `ShouldBroadcast` event). Counters tick on this server's dashboard.

## Public API

`GET /api/stats` returns JSON with current Reverb status and the last 7 days of usage. No auth required:

```json
{
  "status": "online",
  "checked_at": "2026-04-28T14:51:21+00:00",
  "reverb": { "host": "laravel-reverb-server.test", "port": 8080, "scheme": "http" },
  "totals": {
    "message_sent":     { "today": 0, "total": 0 },
    "message_received": { "today": 0, "total": 0 },
    "channel_created":  { "today": 0, "total": 0 },
    "channel_removed":  { "today": 0, "total": 0 },
    "connection_pruned":{ "today": 0, "total": 0 }
  },
  "last_7_days": {
    "message_sent":     [ { "date": "2026-04-22", "count": 0 }, ... ],
    "message_received": [ ... ],
    "channel_created":  [ ... ],
    "channel_removed":  [ ... ],
    "connection_pruned":[ ... ]
  }
}
```

## Make targets

| Target                | Does                                                    |
|-----------------------|---------------------------------------------------------|
| `make up`             | Build, start, migrate, seed, print info banner          |
| `make down`           | Stop the stack                                          |
| `make logs`           | Tail combined logs (`logs-app` / `logs-reverb` for one) |
| `make shell`          | Bash into the app container                             |
| `make tinker`         | Open `php artisan tinker`                               |
| `make migrate`        | Run pending migrations                                  |
| `make seed`           | Seed the admin user                                     |
| `make fresh`          | Drop, migrate, seed                                     |
| `make ping`           | Broadcast a test ping (`reverb:ping`)                   |
| `make stats`          | Print counter table (`reverb:stats`)                    |
| `make restart-reverb` | `php artisan reverb:restart`                            |
| `make test`           | Run Pest test suite                                     |
| `make pint`           | Run Pint formatter                                      |
| `make info`           | Re-print the ready banner                               |
| `make clean`          | Stop + remove volumes (deletes the SQLite db)           |

## Notes

- SQLite runs in WAL mode with a 5s `busy_timeout`, so the app and reverb processes can both write to `websocket_stats` concurrently.
- The reverb container exposes the alias `laravel-reverb-server.test` on the internal Docker network so the app container can broadcast to it without leaving the network.
- Stat listeners are wrapped in try/catch — a missing/locked DB never disrupts websocket traffic.
- Requires **PHP 8.4** (Laravel 13 + symfony 8.x).
