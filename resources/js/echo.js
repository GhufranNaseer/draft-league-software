import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

function getMeta(name) {
    if (typeof document === 'undefined') return null;
    const el = document.querySelector(`meta[name="${name}"]`);
    return el ? el.getAttribute('content') : null;
}

const key = (typeof window !== 'undefined' && window.__REVERB_KEY__)
    || getMeta('reverb-key')
    || import.meta.env.VITE_REVERB_APP_KEY
    || '';

const host = (typeof window !== 'undefined' && window.location.hostname)
    ? window.location.hostname
    : (getMeta('reverb-host') || import.meta.env.VITE_REVERB_HOST || '127.0.0.1');

const isHttps = (typeof window !== 'undefined' && window.location.protocol === 'https:');
const defaultPort = isHttps ? 443 : 80;
const metaPort = getMeta('reverb-port');

const currentPort = (typeof window !== 'undefined' && window.location.port)
    ? parseInt(window.location.port, 10)
    : (metaPort ? parseInt(metaPort, 10) : (import.meta.env.VITE_REVERB_PORT ? parseInt(import.meta.env.VITE_REVERB_PORT, 10) : defaultPort));

const scheme = isHttps ? 'https' : (getMeta('reverb-scheme') || import.meta.env.VITE_REVERB_SCHEME || 'http');

if (key && key.trim() !== '') {
    try {
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: key.trim(),
            wsHost: host,
            wsPort: currentPort,
            wssPort: currentPort,
            forceTLS: isHttps || scheme === 'https',
            enabledTransports: ['ws', 'wss'],
        });

        if (typeof window !== 'undefined') {
            window.dispatchEvent(new CustomEvent('echo:ready', { detail: { echo: window.Echo } }));
        }
    } catch (e) {
        console.error('[Echo] Failed to initialize Laravel Echo:', e);
    }
} else {
    console.warn('[Echo] Reverb App Key is missing. WebSocket connection skipped.');
}
