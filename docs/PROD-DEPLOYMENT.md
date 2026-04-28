# Production deployment

Single-VPS layout where this Reverb server is shared by one main Laravel app and any number of cooperating microservices, all on the same host, all using one set of Reverb credentials.

```
[Internet] ──443── nginx (TLS) ──127.0.0.1:8086── reverb-server (docker)
                                                           ▲
                              ┌─ broadcasts ────────────────┤
[Internet] ──80/443── nginx ─►   monolith (PHP-FPM)         │
                          ─►   microservice A (PHP-FPM)     │
                          ─►   microservice B (...)         │
                                  └── all use one ──────────┘
                                       REVERB_APP_KEY/SECRET
```

The same recipe runs on a separate dev-mirror-of-production VPS — different domain, different secrets.

## Why bind 8086 to loopback

The container listens on `0.0.0.0:8086` *inside* its own network namespace. Publishing it to the host as `-p 127.0.0.1:8086:8086` keeps it reachable from local processes (nginx, your Laravel apps) but not from the public internet. nginx, with TLS, becomes the only path in.

## 1. Run reverb-server

```sh
docker run -d --restart unless-stopped --name reverb-server \
  -p 127.0.0.1:8086:8086 \
  --env-file /etc/reverb-server/.env.production \
  terjen/laravel-reverb-server:0.1.7 solo
```

Pin the image tag — never `:latest` in production. The dashboard footer shows the running version for at-a-glance auditing.

`/etc/reverb-server/.env.production` (`chown root:root && chmod 600`):

```ini
REVERB_PORT=8086
REVERB_APP_ID=<random-id>
REVERB_APP_KEY=<32-char-random>
REVERB_APP_SECRET=<32-char-random>
APP_URL=https://reverb.example.com
```

The image's solo defaults fill in the rest (`BROADCAST_CONNECTION=reverb`, `REVERB_HOST=localhost`, etc.) — those are correct for the container's *own* internal use, separate from what your Laravel apps use to reach it.

## 2. nginx — TLS-terminated WebSocket reverse proxy

```nginx
server {
    listen 443 ssl http2;
    server_name reverb.example.com;

    ssl_certificate     /etc/ssl/private/reverb.example.com.fullchain.pem;
    ssl_certificate_key /etc/ssl/private/reverb.example.com.key;

    location / {
        proxy_pass http://127.0.0.1:8086;
        proxy_http_version 1.1;
        proxy_set_header Host              $host;
        proxy_set_header X-Real-IP         $remote_addr;
        proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header Upgrade           $http_upgrade;
        proxy_set_header Connection        "upgrade";
        proxy_read_timeout  3600s;
        proxy_send_timeout  3600s;
    }
}
```

The `Upgrade` and `Connection` headers are mandatory — without them nginx won't let the WebSocket upgrade through. The long timeouts keep idle connections from being torn down by the proxy.

## 3. Each Laravel app's `.env`

The monolith and every microservice share one credential triple — that's what makes them one cooperating broadcaster fleet.

```ini
BROADCAST_CONNECTION=reverb

# Server-side: PHP talks to the local container directly. No TLS hop, no nginx.
REVERB_HOST=127.0.0.1
REVERB_PORT=8086
REVERB_SCHEME=http
REVERB_APP_ID=<same id from step 1>
REVERB_APP_KEY=<same key>
REVERB_APP_SECRET=<same secret>

# Browser-side: through nginx with TLS.
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST=reverb.example.com
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

After updating each app, `npm run build` — `VITE_REVERB_*` values are baked into the JS bundle at build time, not read at runtime.

### Why server-side and browser-side differ

The most common deployment trap. Server-side PHP runs on the same host as the container, so it can talk to it directly over loopback (`127.0.0.1:8086`, plain HTTP) — fast, no certificate handshake. The browser is on the public internet, so it has to go in through nginx with TLS (`wss://reverb.example.com`). Setting both sides to the same value works for the demo `solo` one-liner in the README but breaks one or the other in production.

## 4. Firewall

- Allow `443/tcp` from anywhere (public WS via nginx).
- Block `8086/tcp` publicly. The `-p 127.0.0.1:8086:8086` bind already prevents external exposure, but a `ufw deny 8086` (or firewalld equivalent) is sensible defence-in-depth.

## 5. Dev-mirror-of-production VPS

Same recipe on a separate VPS. Two differences:

- Different domain (e.g. `reverb.staging.example.com`) and a separate cert.
- **Rotate the credential triple.** Use a different `REVERB_APP_ID/KEY/SECRET` from production. A leak in dev shouldn't be able to broadcast into the production namespace, and a different `REVERB_APP_ID` makes any cross-talk obvious.

## Operational cheatsheet

| Want to … | Run |
| --- | --- |
| Tail Reverb activity | `docker logs -f reverb-server` |
| Confirm container is listening | `curl -sI http://127.0.0.1:8086` (404 is the expected response — Reverb has no root) |
| Restart Reverb gracefully | `docker exec reverb-server php artisan reverb:restart` (drops all WS connections cleanly; clients reconnect) |
| Update to a new tag | `docker pull terjen/laravel-reverb-server:<new-tag> && docker rm -f reverb-server && docker run … <new-tag> solo` |
| Rotate secrets | Update `/etc/reverb-server/.env.production` and every app's `.env` together, then `docker rm -f reverb-server && docker run …` |
| Verify multi-arch image | `docker buildx imagetools inspect terjen/laravel-reverb-server:<tag>` (should show `linux/amd64` and `linux/arm64`) |

## Verifying it works end-to-end

1. **Container reachable from the host:** `curl -sI http://127.0.0.1:8086` returns `404`.
2. **nginx serving TLS:** `curl -sI https://reverb.example.com/` returns `404` over TLS, no cert warnings.
3. **WebSocket upgrade reaches Reverb through nginx:** open one of your Laravel apps that uses Echo, DevTools → Network → WS — the connection to `wss://reverb.example.com/app/<key>?...` should show `101 Switching Protocols` and immediately receive a `pusher:connection_established` frame.
4. **Server-side broadcast lands in browsers:** trigger any `ShouldBroadcast` event from the monolith or a microservice. Subscribed browsers across all apps receive it.
5. **Cross-service co-operation:** broadcast from microservice A on a channel; subscribe in monolith and microservice B; both see the event. That confirms the shared-credentials, shared-namespace setup is working as intended.
