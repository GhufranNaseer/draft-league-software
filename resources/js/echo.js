import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

const host = (typeof window !== 'undefined' && window.location.hostname)
    ? window.location.hostname
    : (import.meta.env.VITE_REVERB_HOST || '127.0.0.1');

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: host,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
    enabledTransports: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https' ? ['wss'] : ['ws'],
});
