import Echo from 'laravel-echo';

import Pusher from 'pusher-js';

if (typeof window !== 'undefined') {
    const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;

    if (!reverbKey) {
        // Reverb is optional in local/self-hosted setups.
        window.Echo = undefined;
    } else {
        window.Pusher = Pusher;

        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: reverbKey,
            wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
            wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
            wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
            forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') ===
                'https',
            enabledTransports: ['ws', 'wss'],
        });
    }
}
