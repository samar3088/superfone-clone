import { useEffect, useState } from 'react';

/**
 * Which columns a table shows, remembered per browser.
 *
 * Kept in localStorage rather than the database: it is a personal view
 * preference, it should survive a refresh, and it is not worth a round trip or
 * a migration. The cost is that it does not follow someone to another machine,
 * which for a column choice is a fair trade.
 */
export function useColumns(storageKey: string, defaults: string[]) {
    const [visible, setVisible] = useState<string[]>(defaults);

    useEffect(() => {
        try {
            const saved = window.localStorage.getItem(storageKey);

            if (saved) {
                const parsed = JSON.parse(saved);

                if (Array.isArray(parsed)) {
                    setVisible(parsed);
                }
            }
        } catch {
            // A corrupted or unavailable store should leave the defaults
            // standing, not break the page.
        }
    }, [storageKey]);

    const save = (next: string[]) => {
        setVisible(next);

        try {
            window.localStorage.setItem(storageKey, JSON.stringify(next));
        } catch {
            // Private browsing can refuse writes; the choice still applies
            // for this session.
        }
    };

    return [visible, save] as const;
}
