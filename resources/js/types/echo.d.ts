import type Echo from 'laravel-echo';

declare global {
    interface Window {
        Pusher?: typeof import('pusher-js').default;
        Echo?: Echo;
    }
}
