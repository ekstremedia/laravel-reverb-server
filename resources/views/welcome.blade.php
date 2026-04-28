@php
    $reverbHost = config('broadcasting.connections.reverb.options.host')
        ?: config('reverb.servers.reverb.host', '127.0.0.1');
    $reverbPort = (int) (config('broadcasting.connections.reverb.options.port')
        ?: config('reverb.servers.reverb.port', 8080));

    if ($reverbHost === '0.0.0.0' || $reverbHost === '') {
        $reverbHost = '127.0.0.1';
    }

    $reverbReachable = null;
    $socket = @fsockopen($reverbHost, $reverbPort, $errno, $errstr, 1.0);
    if ($socket !== false) {
        fclose($socket);
        $reverbReachable = true;
    } else {
        $reverbReachable = false;
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Laravel Reverb Server') }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <style>
        :root { color-scheme: dark; }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; }
        body {
            display: grid;
            place-items: center;
            min-height: 100svh;
            padding: 2rem;
            font: 400 16px/1.5 ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #e5e7eb;
            background: #0a0a0a;
            background-image:
                radial-gradient(60rem 40rem at 50% -10%, rgba(132, 204, 22, 0.08), transparent 60%),
                radial-gradient(40rem 30rem at 80% 110%, rgba(56, 189, 248, 0.06), transparent 60%);
            overflow: hidden;
        }
        main { text-align: center; max-width: 36rem; }
        .pill {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .35rem .8rem;
            border: 1px solid rgba(255, 255, 255, .08);
            border-radius: 9999px;
            font-size: .7rem;
            font-weight: 500;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #a3e635;
            background: rgba(255, 255, 255, .02);
        }
        .pill.offline { color: #f87171; }
        .pill::before {
            content: "";
            width: .45rem;
            height: .45rem;
            border-radius: 50%;
            background: #84cc16;
            box-shadow: 0 0 0 0 rgba(132, 204, 22, .5);
            animation: pulse 2.4s cubic-bezier(.4, 0, .6, 1) infinite;
        }
        .pill.offline::before {
            background: #ef4444;
            box-shadow: none;
            animation: none;
        }
        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(132, 204, 22, .5); }
            50% { box-shadow: 0 0 0 6px rgba(132, 204, 22, 0); }
        }
        h1 {
            margin-top: 1.5rem;
            font-size: clamp(2.25rem, 6vw, 3.75rem);
            font-weight: 600;
            letter-spacing: -.025em;
            line-height: 1.05;
            background: linear-gradient(180deg, #fafafa 0%, #71717a 130%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        p.lead { margin-top: .75rem; color: #a1a1aa; font-size: 1.0625rem; }
        nav { margin-top: 2.25rem; display: inline-flex; gap: .75rem; flex-wrap: wrap; justify-content: center; }
        a.btn {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .65rem 1.1rem;
            border-radius: .6rem;
            font-size: .875rem;
            font-weight: 500;
            text-decoration: none;
            transition: transform .15s ease, background-color .15s ease, border-color .15s ease;
        }
        a.btn.primary { color: #0a0a0a; background: #fafafa; }
        a.btn.primary:hover { transform: translateY(-1px); background: #fff; }
        a.btn.ghost { color: #e5e7eb; border: 1px solid rgba(255, 255, 255, .12); }
        a.btn.ghost:hover { border-color: rgba(255, 255, 255, .25); }
        button.ping-btn {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .65rem 1.1rem;
            border-radius: .6rem;
            font: inherit;
            font-size: .875rem;
            font-weight: 500;
            color: #0a0a0a;
            background: #84cc16;
            border: 0;
            cursor: pointer;
            transition: transform .15s ease, background-color .15s ease, opacity .15s ease;
        }
        button.ping-btn:hover { transform: translateY(-1px); background: #a3e635; }
        button.ping-btn:disabled { opacity: .5; cursor: progress; transform: none; }
        .ping-pane {
            margin-top: 1.25rem;
            min-height: 4.5rem;
            padding: .9rem 1rem;
            border: 1px solid rgba(255, 255, 255, .08);
            border-radius: .6rem;
            font-size: .8125rem;
            color: #a1a1aa;
            text-align: left;
            font-family: ui-monospace, "SF Mono", Menlo, Consolas, monospace;
        }
        .ping-pane .ws-state { color: #71717a; font-size: .7rem; letter-spacing: .04em; text-transform: uppercase; }
        .ping-pane .ws-state.connected { color: #84cc16; }
        .ping-pane ul { list-style: none; margin-top: .5rem; }
        .ping-pane li { padding: .25rem 0; display: flex; gap: .75rem; }
        .ping-pane li time { color: #71717a; }
        .ping-pane li .rtt { margin-left: auto; color: #84cc16; }
        footer {
            position: fixed;
            inset: auto 0 1.25rem 0;
            text-align: center;
            font-size: .75rem;
            color: #52525b;
            letter-spacing: .02em;
        }
        footer code { font: inherit; color: #71717a; }
    </style>
</head>
<body>
    <main>
        <span class="pill {{ $reverbReachable ? '' : 'offline' }}">{{ $reverbReachable ? 'Online' : 'Offline' }}</span>
        <h1>Laravel Reverb Server</h1>
        <p class="lead">Lightweight WebSocket broadcasting, ready to go.</p>
        <nav>
            @auth
                <a class="btn primary" href="{{ route('dashboard') }}">Open dashboard</a>
            @else
                <a class="btn primary" href="{{ route('login') }}">Sign in</a>
            @endauth
            <button type="button" class="ping-btn" id="ping-btn" disabled>Send ping</button>
            <a class="btn ghost" href="https://laravel.com/docs/reverb" rel="noopener" target="_blank">Reverb docs</a>
        </nav>
        <div class="ping-pane" id="ping-pane">
            <span class="ws-state" id="ws-state">connecting…</span>
            <ul id="ping-log"></ul>
        </div>
    </main>
    <footer>
        <code>{{ config('broadcasting.connections.reverb.options.host') }}:{{ config('broadcasting.connections.reverb.options.port') }}</code>
    </footer>
    @php
        $reverbApp = config('reverb.apps.apps.0', []);
        $publicReverb = [
            'key' => $reverbApp['key'] ?? null,
            'host' => data_get($reverbApp, 'options.host'),
            'port' => (int) (data_get($reverbApp, 'options.port') ?: 443),
            'scheme' => data_get($reverbApp, 'options.scheme') ?: 'http',
        ];
    @endphp
    <script>
        window.__REVERB__ = {!! json_encode($publicReverb, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) !!};
    </script>
    <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4/dist/web/pusher.min.js"></script>
    <script>
        (() => {
            const cfg = window.__REVERB__ || {};
            if (!cfg.key || !cfg.host) return;

            const stateEl = document.getElementById('ws-state');
            const logEl = document.getElementById('ping-log');
            const btn = document.getElementById('ping-btn');
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

            const pusher = new Pusher(cfg.key, {
                wsHost: cfg.host,
                wsPort: cfg.port,
                wssPort: cfg.port,
                forceTLS: cfg.scheme === 'https',
                enabledTransports: ['ws', 'wss'],
                cluster: 'mt1',
                disableStats: true,
            });

            pusher.connection.bind('state_change', ({ current }) => {
                stateEl.textContent = current;
                stateEl.classList.toggle('connected', current === 'connected');
                btn.disabled = current !== 'connected';
            });

            const channel = pusher.subscribe('ping');
            channel.bind('ping', (data) => {
                const sent = new Date(data.sent_at).getTime();
                const rtt = Date.now() - sent;
                const li = document.createElement('li');
                li.innerHTML = `<time>${new Date().toLocaleTimeString()}</time><span>${data.message ?? 'pong'}</span><span class="rtt">${rtt} ms</span>`;
                logEl.prepend(li);
                while (logEl.children.length > 6) logEl.removeChild(logEl.lastChild);
            });

            btn.addEventListener('click', async () => {
                btn.disabled = true;
                try {
                    await fetch('{{ route('api.ping') }}', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });
                } finally {
                    btn.disabled = pusher.connection.state !== 'connected';
                }
            });
        })();
    </script>
</body>
</html>
