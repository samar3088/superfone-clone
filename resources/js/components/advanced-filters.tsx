import { router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

import { Button, Modal } from '@/components/ui-kit';
import { Filters } from '@/components/table-filters';

export interface FilterOption {
    value: string;
    label: string;
    /** Rendered before the label — a tag colour or a stage emoji. */
    swatch?: string | null;
    emoji?: string | null;
}

export interface FilterGroup {
    /** Query-string key this group writes to. */
    name: string;
    label: string;
    /** Omitted for a date range, which has its own two keys. */
    options?: FilterOption[];
    kind?: 'list' | 'dates';
    fromName?: string;
    toName?: string;
}

/**
 * The overflow filter panel: everything that does not earn a place in the
 * always-visible row.
 *
 * Nothing is applied until Apply is pressed. Ticking through five categories
 * would otherwise be five page loads and four throwaway result sets — and half
 * of them would be states nobody wanted to see.
 */
export function AdvancedFilters({
    url,
    filters,
    groups,
    onClose,
}: {
    url: string;
    /** Filters currently applied by the server. */
    filters: Filters;
    groups: FilterGroup[];
    onClose: () => void;
}) {
    const keys = useMemo(
        () => groups.flatMap((g) => (g.kind === 'dates' ? [g.fromName!, g.toName!] : [g.name])),
        [groups],
    );

    const [draft, setDraft] = useState<Filters>(() =>
        Object.fromEntries(keys.map((k) => [k, filters[k]])),
    );
    const [active, setActive] = useState(groups[0]?.name ?? '');

    // Re-sync if the server sends different filters back — a Reset elsewhere,
    // or the browser Back button.
    useEffect(() => {
        setDraft(Object.fromEntries(keys.map((k) => [k, filters[k]])));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [keys.map((k) => filters[k] ?? '').join(' ')]);

    const selected = (name: string) => (draft[name] ?? '').split(',').filter(Boolean);

    const toggle = (name: string, value: string) => {
        const current = selected(name);
        const next = current.includes(value)
            ? current.filter((v) => v !== value)
            : [...current, value];

        setDraft((d) => ({ ...d, [name]: next.join(',') || undefined }));
    };

    const setAll = (group: FilterGroup, on: boolean) =>
        setDraft((d) => ({
            ...d,
            [group.name]: on ? (group.options ?? []).map((o) => o.value).join(',') : undefined,
        }));

    /** How many of this group's values are chosen, for the badge in the rail. */
    const countFor = (group: FilterGroup) =>
        group.kind === 'dates'
            ? [group.fromName!, group.toName!].filter((k) => draft[k]).length
            : selected(group.name).length;

    const totalGroups = groups.filter((g) => countFor(g) > 0).length;

    const apply = () => {
        router.get(url, { ...filters, ...draft, page: undefined }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onSuccess: onClose,
        });
    };

    const clearAll = () => setDraft(Object.fromEntries(keys.map((k) => [k, undefined])));

    const group = groups.find((g) => g.name === active) ?? groups[0];

    return (
        <Modal open onClose={onClose} title="Filters" wide hideHeader>
            <div className="-m-6">
                <header className="flex items-center justify-between gap-3 border-b border-border px-5 py-4">
                    <div className="flex items-center gap-2">
                        <svg viewBox="0 0 24 24" className="size-4" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
                            <path d="M3 5h18l-7 8v5l-4 2v-7z" strokeLinejoin="round" />
                        </svg>
                        <h2 className="font-display text-lg font-bold">Filters</h2>
                        {totalGroups > 0 && (
                            <span className="rounded-full bg-accent px-2 py-0.5 text-xs font-bold text-accent-foreground">
                                {totalGroups}
                            </span>
                        )}
                    </div>

                    <button
                        type="button"
                        onClick={clearAll}
                        className="text-sm font-medium text-primary underline-offset-4 hover:underline"
                    >
                        Clear all
                    </button>
                </header>

                <div className="grid min-h-[22rem] sm:grid-cols-[13rem_minmax(0,1fr)]">
                    {/* Categories */}
                    <nav className="border-b border-border p-2 sm:border-b-0 sm:border-r">
                        {groups.map((g) => {
                            const n = countFor(g);
                            const on = g.name === group?.name;

                            return (
                                <button
                                    key={g.name}
                                    type="button"
                                    onClick={() => setActive(g.name)}
                                    className={`flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-left text-sm transition ${
                                        on ? 'bg-accent font-semibold text-accent-foreground' : 'hover:bg-muted'
                                    }`}
                                >
                                    {g.label}
                                    {n > 0 && (
                                        <span className="tabular rounded-full bg-primary px-1.5 text-xs font-bold text-primary-foreground">
                                            {n}
                                        </span>
                                    )}
                                </button>
                            );
                        })}
                    </nav>

                    {/* Options for the chosen category */}
                    <div className="max-h-[26rem] overflow-y-auto p-4">
                        {group?.kind === 'dates' ? (
                            <DateRange
                                from={draft[group.fromName!] ?? ''}
                                to={draft[group.toName!] ?? ''}
                                onFrom={(v) => setDraft((d) => ({ ...d, [group.fromName!]: v || undefined }))}
                                onTo={(v) => setDraft((d) => ({ ...d, [group.toName!]: v || undefined }))}
                            />
                        ) : (
                            <OptionList
                                group={group}
                                selected={selected(group?.name ?? '')}
                                onToggle={(v) => toggle(group!.name, v)}
                                onSelectAll={(on) => setAll(group!, on)}
                            />
                        )}
                    </div>
                </div>

                <footer className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-4">
                    <p className="text-sm text-muted-foreground">
                        {totalGroups === 0
                            ? 'No filters selected'
                            : `${totalGroups} filter${totalGroups === 1 ? '' : 's'} will be applied`}
                    </p>

                    <div className="flex gap-2">
                        <Button type="button" variant="ghost" onClick={onClose}>Cancel</Button>
                        <Button type="button" onClick={apply}>Apply filters</Button>
                    </div>
                </footer>
            </div>
        </Modal>
    );
}

function OptionList({
    group,
    selected,
    onToggle,
    onSelectAll,
}: {
    group?: FilterGroup;
    selected: string[];
    onToggle: (v: string) => void;
    onSelectAll: (on: boolean) => void;
}) {
    const options = group?.options ?? [];

    if (options.length === 0) {
        return <p className="py-10 text-center text-sm text-muted-foreground">Nothing to filter by yet</p>;
    }

    const allOn = selected.length === options.length;

    return (
        <>
            <label className="mb-2 flex cursor-pointer items-center gap-3 border-b border-border pb-3 text-sm font-semibold">
                <input
                    type="checkbox"
                    className="size-4 accent-primary"
                    checked={allOn}
                    // Some but not all — the box says "not everything" rather
                    // than implying a state that is not true.
                    ref={(el) => {
                        if (el) {
                            el.indeterminate = selected.length > 0 && !allOn;
                        }
                    }}
                    onChange={(e) => onSelectAll(e.target.checked)}
                />
                Select all
            </label>

            <div className="space-y-0.5">
                {options.map((o) => (
                    <label
                        key={o.value}
                        className="flex cursor-pointer items-center gap-3 rounded-lg px-2 py-2 text-sm hover:bg-muted"
                    >
                        <input
                            type="checkbox"
                            className="size-4 accent-primary"
                            checked={selected.includes(o.value)}
                            onChange={() => onToggle(o.value)}
                        />

                        {o.swatch && (
                            <span
                                className="size-2.5 shrink-0 rounded-full"
                                style={{ background: o.swatch }}
                                aria-hidden
                            />
                        )}

                        <span className="truncate">
                            {o.emoji ? `${o.emoji} ` : ''}
                            {o.label}
                        </span>
                    </label>
                ))}
            </div>
        </>
    );
}

function DateRange({
    from,
    to,
    onFrom,
    onTo,
}: {
    from: string;
    to: string;
    onFrom: (v: string) => void;
    onTo: (v: string) => void;
}) {
    const box = 'h-10 w-full rounded-lg border border-input bg-card px-2.5 text-sm outline-none focus:border-primary';

    return (
        <div className="flex flex-wrap items-center gap-2">
            <input
                type="date"
                aria-label="Start date"
                className={box}
                style={{ maxWidth: '11rem' }}
                value={from}
                max={to || undefined}
                onChange={(e) => onFrom(e.target.value)}
            />
            <span className="text-sm text-muted-foreground">to</span>
            <input
                type="date"
                aria-label="End date"
                className={box}
                style={{ maxWidth: '11rem' }}
                value={to}
                min={from || undefined}
                onChange={(e) => onTo(e.target.value)}
            />
        </div>
    );
}
