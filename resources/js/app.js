import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const reverb = window.__REVERB__ ?? {};

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: reverb.key,
    wsHost: reverb.host,
    wsPort: reverb.port ?? 80,
    wssPort: reverb.port ?? 443,
    forceTLS: (reverb.scheme ?? 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
});
