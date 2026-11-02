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
const windowPort = (typeof window !== 'undefined' && window.location.port) ? parseInt(window.location.port, 10) : null;
const defaultPort = windowPort || (isHttps ? 443 : 80);
const metaPort = getMeta('reverb-port');
const envPort = import.meta.env.VITE_REVERB_PORT;

let currentPort;
if (windowPort) {
    currentPort = windowPort;
} else if (metaPort && parseInt(metaPort, 10) && parseInt(metaPort, 10) !== 8080) {
    currentPort = parseInt(metaPort, 10);
} else if (envPort && parseInt(envPort, 10)) {
    currentPort = parseInt(envPort, 10);
} else {
    currentPort = defaultPort;
}

const scheme = isHttps ? 'https' : (getMeta('reverb-scheme') || import.meta.env.VITE_REVERB_SCHEME || 'http');
const useTLS = isHttps || scheme === 'https';

if (key && key.trim() !== '') {
    try {
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: key.trim(),
            wsHost: host,
            wsPort: currentPort,
            wssPort: currentPort,
            forceTLS: useTLS,
            enabledTransports: ['ws', 'wss'],
            disableStats: true,
        });

        if (window.Echo && window.Echo.connector && window.Echo.connector.pusher) {
            let consecutiveFailures = 0;
            const pusher = window.Echo.connector.pusher;

            pusher.connection.bind('state_change', function (states) {
                console.log(`[Echo] WebSocket state: ${states.previous} -> ${states.current}`);
            });

            pusher.connection.bind('error', function (err) {
                consecutiveFailures++;
                console.warn(`[Echo] WebSocket connection attempt issue (${consecutiveFailures}/10):`, err);
                if (consecutiveFailures >= 10) {
                    console.warn(`[Echo] WebSocket server unreachable at ${host}:${currentPort}. Run 'php artisan reverb:start' if real-time WebSockets are needed.`);
                    pusher.disconnect();
                }
            });

            pusher.connection.bind('connected', function () {
                console.log(`[Echo] Connected to Reverb on ${useTLS ? 'wss' : 'ws'}://${host}:${currentPort}`);
                consecutiveFailures = 0;
            });
        }

        if (typeof window !== 'undefined') {
            window.dispatchEvent(new CustomEvent('echo:ready', { detail: { echo: window.Echo } }));
        }
    } catch (e) {
        console.error('[Echo] Failed to initialize Laravel Echo:', e);
    }
} else {
    console.warn('[Echo] Reverb App Key is missing. WebSocket connection skipped.');
}
