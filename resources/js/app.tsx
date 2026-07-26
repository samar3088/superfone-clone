import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { route as routeFn } from 'ziggy-js';
import { initializeTheme } from './hooks/use-appearance';

declare global {
    const route: typeof routeFn;
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        // Brand teal rather than the default grey.
        color: '#0b5d51',
        // Inertia waits 250ms before showing anything, on the theory that a
        // fast response needs no bar. On a slow connection or a big filtered
        // query that quarter second is exactly when the page looks frozen.
        delay: 120,
        showSpinner: true,
    },
});

// This will set light / dark mode on load...
initializeTheme();
