import Echo from 'laravel-echo';

import Pusher from 'pusher-js';

if (typeof window !== 'undefined') {
    const meta = (name: string) =>
        document.head
            .querySelector(`meta[name="${name}"]`)
            ?.getAttribute('content');

    const reverbKey = meta('reverb-key');

    if (!reverbKey) {
        // Reverb is optional in local/self-hosted setups.
        window.Echo = undefined;
    } else {
        const port = Number(meta('reverb-port') ?? 443);
        const forceTLS = (meta('reverb-scheme') ?? 'https') === 'https';

        window.Pusher = Pusher;

        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: reverbKey,
            wsHost: window.location.hostname,
            wsPort: port,
            wssPort: port,
            forceTLS,
            enabledTransports: ['ws', 'wss'],
        });
    }
}
