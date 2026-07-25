import { useCallback, useState } from 'react';

/**
 * Tab state that lives in the query string.
 *
 * Without this, a refresh drops you back on the first tab — which bites
 * hardest exactly when you are iterating on one screen and reloading often. It
 * also makes a tab linkable: /settings?tab=integrations opens where you meant.
 *
 * history.replaceState rather than an Inertia visit: switching tab is not a
 * navigation, it should not hit the server, and it should not stack up back-
 * button entries for every tab someone clicks through.
 */
export function useUrlTab<T extends string>(key: string, fallback: T, allowed?: readonly T[]) {
    const read = (): T => {
        if (typeof window === 'undefined') {
            return fallback;
        }

        const value = new URLSearchParams(window.location.search).get(key) as T | null;

        // An unknown value in a hand-edited url should land somewhere sane
        // rather than render nothing at all.
        if (!value || (allowed && !allowed.includes(value))) {
            return fallback;
        }

        return value;
    };

    const [tab, setTabState] = useState<T>(read);

    const setTab = useCallback(
        (next: T) => {
            setTabState(next);

            const url = new URL(window.location.href);

            // The default is implied, so keep it out of the url.
            if (next === fallback) {
                url.searchParams.delete(key);
            } else {
                url.searchParams.set(key, next);
            }

            window.history.replaceState({}, '', url);
        },
        [key, fallback],
    );

    return [tab, setTab] as const;
}
