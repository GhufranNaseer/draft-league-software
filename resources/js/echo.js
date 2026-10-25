import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

const host = (typeof window !== 'undefined' && window.location.hostname)
    ? window.location.hostname
    : (import.meta.env.VITE_REVERB_HOST || '127.0.0.1');

const isHttps = (typeof window !== 'undefined' && window.location.protocol === 'https:');
const defaultPort = isHttps ? 443 : 80;
const currentPort = (typeof window !== 'undefined' && window.location.port)
    ? parseInt(window.location.port, 10)
    : (import.meta.env.VITE_REVERB_PORT ? parseInt(import.meta.env.VITE_REVERB_PORT, 10) : defaultPort);

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: host,
    wsPort: currentPort,
    wssPort: currentPort,
    forceTLS: isHttps || (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
    enabledTransports: isHttps || (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https' ? ['wss'] : ['ws', 'wss'],
});
