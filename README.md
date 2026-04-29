# Laravel Reverb Server

Self-hosted [Laravel Reverb](https://laravel.com/docs/reverb) in a single container — Pusher-protocol WebSocket broadcasting plus a small dashboard. Spin it up, point your apps at it, broadcast.

![Welcome page](docs/screenshots/welcome.png)
## Run it

```sh
docker run -d --restart unless-stopped --name reverb-server -p 8000:8000 -p 8080:8080 terjen/laravel-reverb-server solo
```

- Dashboard at <http://localhost:8000> · login `admin@admin.com` / `wspassword`
- WebSocket at `ws://localhost:8080`
- Public welcome page at `/` with a no-auth Send-ping tester

### Stop & restart

The container is named `reverb-server`, so re-running `docker run …` will fail with "container name already in use". Remove it first:

```sh
docker rm -f reverb-server
```

Then start it again with whichever flags you need. The SQLite db lives inside the container layer — if you want stats to survive a remove, mount a volume: `-v reverb-data:/app/database`.

### Remapping ports

The container always listens on `:8000` (dashboard / HTTP) and `:8080` (WebSocket) **inside**. The `-p HOST:CONTAINER` flag only changes which port the host exposes — the container itself can't see the host-side port, so anywhere a port is *advertised* to a client (the dashboard's `.env` panel, the welcome page's WebSocket URL, the `/api/stats` payload) you need a matching env override.

**WebSocket port (8080).** This is the one that bites — Echo / pusher-js connects to whatever the dashboard advertises, so if it doesn't match the published port the connection just fails. Pass `-e REVERB_PORT=<host port>`:

```sh
docker rm -f reverb-server 2>/dev/null
docker run -d --restart unless-stopped --name reverb-server -p 8000:8000 -p 8086:8080 -e REVERB_PORT=8086 terjen/laravel-reverb-server solo
```

**HTTP / dashboard port (8000).** If `8000` is also taken, remap it too. Set `APP_URL` so links rendered by the dashboard (and the welcome page footer) point at the right host port:

```sh
docker rm -f reverb-server 2>/dev/null
docker run -d --restart unless-stopped --name reverb-server -p 9000:8000 -p 8086:8080 -e APP_URL=http://localhost:9000 -e REVERB_PORT=8086 terjen/laravel-reverb-server solo
```

Then browse <http://localhost:9000>.

**Different public host (reverse proxy, TLS).** If clients reach the WS through a domain rather than `localhost`, override the host and scheme too — add these flags to the `docker run` line:

```sh
-e REVERB_HOST=ws.example.com -e REVERB_SCHEME=https -e REVERB_PORT=443
```

Rule of thumb: `-p HOST:CONTAINER` for what Docker exposes, `-e REVERB_HOST/REVERB_PORT/REVERB_SCHEME/APP_URL` for what the container *tells the world about itself*.

### Seeded admin

Override the seeded admin via env on first boot — `-e SEED_ADMIN_EMAIL=you@example.com -e SEED_ADMIN_PASSWORD=...`. Subsequent boots `updateOrCreate`, so changing the env updates the existing admin.

`solo` runs Reverb and the dashboard in one container. Multi-arch image (`linux/amd64`, `linux/arm64`) is on [Docker Hub](https://hub.docker.com/r/terjen/laravel-reverb-server). For TLS-fronted production, see [`docs/PROD-DEPLOYMENT.md`](docs/PROD-DEPLOYMENT.md).

## Connect a Laravel app

In the consuming app's `.env`:

```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=local-app-id
REVERB_APP_KEY=local-app-key
REVERB_APP_SECRET=local-app-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

Then `composer require laravel/reverb && npm i -D laravel-echo pusher-js`, and broadcast as usual on `ShouldBroadcast` events. The dashboard's "Connection .env" panel shows the live values for whichever container is running.

## What's inside

- WebSocket server, Pusher-protocol — works with `laravel-echo` + `pusher-js` out of the box.
- Login-gated dashboard with reachability check, lifecycle counters (`MessageSent`, `MessageReceived`, `ChannelCreated`/`Removed`, `ConnectionPruned`), 7-day chart, ping/pong tester.
- Log viewer at `/settings/logs` ([opcodes/log-viewer](https://github.com/opcodesio/log-viewer)).
- `GET /api/stats` returns reachability + counters as JSON, no auth.
- Stats persisted to SQLite (WAL mode, idempotent migrations on boot).

![Dashboard](docs/screenshots/dashboard.png)


![Log viewer](docs/screenshots/log-viewer.png)

## Develop

Clone, then:

```sh
make up
```

Spins up the two-container compose stack (FrankenPHP app + Reverb daemon), migrates, seeds, prints a copy-paste `.env` block.

| | |
|---|---|
| `make up` / `make down` | start / stop the stack |
| `make logs` | tail combined logs (`logs-app`, `logs-reverb` for one) |
| `make shell` / `make tinker` | bash / tinker into the app container |
| `make fresh` | drop, migrate, seed |
| `make ping` / `make stats` | broadcast a test event / print counter table |
| `make test` / `make pint` | Pest suite / Pint formatter |
| `make help` | full target list |

Requires PHP 8.4 (Laravel 13).

## License

MIT.
