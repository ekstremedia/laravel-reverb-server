<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
        .pill::before {
            content: "";
            width: .45rem;
            height: .45rem;
            border-radius: 50%;
            background: #84cc16;
            box-shadow: 0 0 0 0 rgba(132, 204, 22, .5);
            animation: pulse 2.4s cubic-bezier(.4, 0, .6, 1) infinite;
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
        <span class="pill">Online</span>
        <h1>Laravel Reverb Server</h1>
        <p class="lead">Lightweight WebSocket broadcasting, ready to go.</p>
        <nav>
            @auth
                <a class="btn primary" href="{{ route('dashboard') }}">Open dashboard</a>
            @else
                <a class="btn primary" href="{{ route('login') }}">Sign in</a>
            @endauth
            <a class="btn ghost" href="https://laravel.com/docs/reverb" rel="noopener" target="_blank">Reverb docs</a>
        </nav>
    </main>
    <footer>
        <code>{{ config('broadcasting.connections.reverb.options.host') }}:{{ config('broadcasting.connections.reverb.options.port') }}</code>
    </footer>
</body>
</html>
