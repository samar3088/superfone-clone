import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import { Spinner } from '@/components/data-table';
import { Button, Modal } from '@/components/ui-kit';

interface Candidate {
    id: number;
    name: string;
    mobile: string;
    email: string | null;
    city: string | null;
    business_name: string | null;
    leads_count: number;
    calls_count: number;
    notes_count: number;
    created_at: string | null;
}

interface Group {
    key: string;
    confidence: 'high' | 'medium' | 'low';
    reason: string;
    customers: Candidate[];
}

const TONE: Record<Group['confidence'], { label: string; bg: string; fg: string }> = {
    high: { label: 'Likely', bg: 'var(--bad-soft)', fg: 'var(--bad)' },
    medium: { label: 'Possible', bg: 'var(--warn-soft)', fg: 'var(--warn)' },
    low: { label: 'Weak', bg: 'var(--muted)', fg: 'var(--muted-foreground)' },
};

/**
 * Reviewing contacts that look like the same person.
 *
 * Deliberately not a button that cleans up by itself. Two contacts can never
 * share a number or an address — the database will not allow it — so anything
 * shown here is matched on name alone plus whatever else happens to agree.
 * That is a hint, not a fact, and a merge cannot be undone from this screen.
 *
 * So: nothing happens without a person choosing which record survives and
 * pressing the button on that one group.
 */
export default function Duplicates({ onClose }: { onClose: () => void }) {
    const [loading, setLoading] = useState(true);
    const [groups, setGroups] = useState<Group[]>([]);
    const [keepers, setKeepers] = useState<Record<string, number>>({});
    const [merging, setMerging] = useState<string | null>(null);

    const load = () => {
        setLoading(true);

        fetch('/customers/duplicates', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((r) => r.json())
            .then((data: { groups: Group[] }) => {
                setGroups(data.groups);

                // The oldest record is the default survivor: it is the original,
                // and anything already pointing at it stays valid.
                setKeepers(
                    Object.fromEntries(data.groups.map((g) => [g.key, g.customers[0].id])),
                );
            })
            .finally(() => setLoading(false));
    };

    useEffect(load, []);

    const mergeGroup = (group: Group) => {
        const keep = keepers[group.key];
        const losers = group.customers.filter((c) => c.id !== keep).map((c) => c.id);

        setMerging(group.key);

        router.post(
            `/customers/${keep}/merge`,
            { duplicate_ids: losers },
            {
                preserveScroll: true,
                onSuccess: load,
                onFinish: () => setMerging(null),
            },
        );
    };

    return (
        <Modal
            open
            onClose={onClose}
            title="Duplicate contacts"
            wide
        >
            <p className="mb-4 rounded-lg border border-border px-3 py-2.5 text-sm text-muted-foreground">
                Two contacts can never share a phone number or email — the book will not allow
                it. So these are matched on <b className="text-foreground">name</b>, plus
                whatever else agrees. <b className="text-foreground">Check each one before
                merging.</b> The record you keep takes the others' leads, calls, notes and
                numbers; the rest are archived, not deleted.
            </p>

            {loading ? (
                <div className="grid place-items-center py-12 text-muted-foreground">
                    <Spinner className="size-6" />
                </div>
            ) : groups.length === 0 ? (
                <p className="rounded-lg border border-dashed border-border px-4 py-12 text-center text-sm text-muted-foreground">
                    No contacts share a name. Nothing to merge.
                </p>
            ) : (
                <div className="space-y-4">
                    {groups.map((group) => (
                        <section key={group.key} className="rounded-xl border border-border">
                            <header className="flex flex-wrap items-center justify-between gap-2 border-b border-border px-4 py-2.5">
                                <div className="flex items-center gap-2">
                                    <span
                                        className="rounded-full px-2 py-0.5 text-xs font-bold uppercase"
                                        style={{
                                            background: TONE[group.confidence].bg,
                                            color: TONE[group.confidence].fg,
                                        }}
                                    >
                                        {TONE[group.confidence].label}
                                    </span>
                                    <span className="text-sm text-muted-foreground">{group.reason}</span>
                                </div>

                                <Button
                                    type="button"
                                    className="px-3 py-1.5"
                                    disabled={merging === group.key}
                                    onClick={() => mergeGroup(group)}
                                >
                                    {merging === group.key
                                        ? 'Merging…'
                                        : `Merge ${group.customers.length - 1} into the one kept`}
                                </Button>
                            </header>

                            <div className="divide-y divide-border/60">
                                {group.customers.map((c) => (
                                    <label
                                        key={c.id}
                                        className="flex cursor-pointer flex-wrap items-center gap-3 px-4 py-3 text-sm hover:bg-muted/40"
                                    >
                                        <input
                                            type="radio"
                                            name={`keep-${group.key}`}
                                            className="size-4 accent-primary"
                                            checked={keepers[group.key] === c.id}
                                            onChange={() =>
                                                setKeepers((k) => ({ ...k, [group.key]: c.id }))
                                            }
                                        />

                                        <span className="min-w-0 flex-1">
                                            <span className="block font-semibold">{c.name}</span>
                                            <span className="data block text-xs text-muted-foreground">
                                                {c.mobile}
                                                {c.email ? ` · ${c.email}` : ''}
                                                {c.city ? ` · ${c.city}` : ''}
                                                {c.business_name ? ` · ${c.business_name}` : ''}
                                            </span>
                                        </span>

                                        <span className="shrink-0 text-xs text-muted-foreground">
                                            {c.leads_count} lead{c.leads_count === 1 ? '' : 's'} ·{' '}
                                            {c.calls_count} call{c.calls_count === 1 ? '' : 's'} ·{' '}
                                            {c.notes_count} note{c.notes_count === 1 ? '' : 's'}
                                        </span>

                                        <span className="data w-24 shrink-0 text-right text-xs text-muted-foreground">
                                            {c.created_at}
                                        </span>
                                    </label>
                                ))}
                            </div>

                            <p className="border-t border-border px-4 py-2 text-xs text-muted-foreground">
                                The one ticked is kept. Oldest first, which is the default —
                                it is the original record.
                            </p>
                        </section>
                    ))}
                </div>
            )}

            <div className="mt-5 flex justify-end border-t border-border pt-4">
                <Button type="button" variant="ghost" onClick={onClose}>Close</Button>
            </div>
        </Modal>
    );
}
