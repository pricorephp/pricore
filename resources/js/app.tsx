import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { Settings } from 'luxon';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { Toaster } from './components/toaster';
import { initializeTheme } from './hooks/use-appearance';
import { toast } from './hooks/use-toast';
import type { SharedData } from './types';

// Set Luxon default locale to English
Settings.defaultLocale = 'en';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Handle flash messages via router events
let previousFlash: SharedData['flash'] = undefined;

router.on('success', (event) => {
    const props = event.detail.page.props as unknown as SharedData;
    const flash = props.flash;

    // Only show toast if flash message is new
    if (flash?.status && flash.status !== previousFlash?.status) {
        toast({
            title: 'Success',
            description: flash.status,
            variant: 'success',
        });
    }

    if (flash?.error && flash.error !== previousFlash?.error) {
        toast({
            title: 'Error',
            description: flash.error,
            variant: 'destructive',
        });
    }

    previousFlash = flash;
});

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.tsx`,
            import.meta.glob('./pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <StrictMode>
                <App {...props} />
                <Toaster />
            </StrictMode>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
