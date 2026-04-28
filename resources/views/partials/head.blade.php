<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

@php
    $reverbApp = config('reverb.apps.apps.0', []);
    $reverbConfig = [
        'key' => $reverbApp['key'] ?? null,
        'host' => data_get($reverbApp, 'options.host'),
        'port' => (int) data_get($reverbApp, 'options.port', 80),
        'scheme' => data_get($reverbApp, 'options.scheme', 'http'),
    ];
@endphp
<script>
    window.__REVERB__ = {!! json_encode($reverbConfig, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) !!};
</script>

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
