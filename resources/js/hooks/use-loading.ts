import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/**
 * Whether an Inertia request is currently in flight for this screen.
 *
 * Filtering, sorting and paging all go over ajax, which is fast enough locally
 * that nothing appears to happen — and slow enough on a real connection with a
 * real dataset that the page looks frozen. This drives the "working on it"
 * state either way.
 *
 * @param pathPrefix Only count visits to this path, so navigating away does
 *                   not flash a loading state over the screen being left.
 */
export function useLoading(pathPrefix?: string): boolean {
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        const matches = (url: string | URL | undefined) => {
            if (!pathPrefix || !url) {
                return true;
            }

            try {
                return new URL(url, window.location.origin).pathname.startsWith(pathPrefix);
            } catch {
                return true;
            }
        };

        const offStart = router.on('start', (e) => {
            if (matches(e.detail.visit.url)) {
                setLoading(true);
            }
        });

        // finish covers success, failure and cancellation alike — anything
        // else risks a spinner that never stops.
        const offFinish = router.on('finish', () => setLoading(false));

        return () => {
            offStart();
            offFinish();
        };
    }, [pathPrefix]);

    return loading;
}
