import { useState } from 'react';

import { Filters } from '@/components/table-filters';
import { Button, Modal } from '@/components/ui-kit';
import { ContactOptions } from './create-contact';

export interface ColumnChoice {
    key: string;
    heading: string;
}

/**
 * Filter keys the dialog owns.
 *
 * The same names the Customers list uses, deliberately — both are read by the
 * same request class on the server, so a download narrowed the same way as the
 * list contains exactly the rows the list was showing. Inventing separate names
 * here is how the two drift apart and nobody notices until a client says the
 * file is missing people.
 */
const KEYS = ['team', 'member', 'tags', 'stage', 'group', 'creator', 'date_from', 'date_to'] as const;

type Draft = Partial<Record<(typeof KEYS)[number], string>>;

/**
 * Choosing what a contact download contains.
 *
 * It carries its own filters, separate from the ones on the list behind it, and
 * its own Reset. Someone downloading a slice of the book is usually not looking
 * at that slice on screen — and silently inheriting the page's filters produces
 * a file that is short of rows for reasons nobody can see from here.
 *
 * "Use the list's filters" is there for when they do want the two to agree.
 */
export default function DownloadContacts({
    filters,
    options,
    members,
    columns,
    defaults,
    onClose,
}: {
    /** What the list behind this dialog currently has applied. */
    filters: Filters;
    options: ContactOptions;
    members: { id: number; name: string }[];
    columns: ColumnChoice[];
    defaults: string[];
    onClose: () => void;
}) {
    const [draft, setDraft] = useState<Draft>({});
    const [picked, setPicked] = useState<string[]>(defaults);

    const set = (key: (typeof KEYS)[number], value: string | undefined) =>
        setDraft((d) => ({ ...d, [key]: value || undefined }));

    const toggleColumn = (key: string) =>
        setPicked((s) => (s.includes(key) ? s.filter((k) => k !== key) : [...s, key]));

    const chosen = KEYS.filter((k) => draft[k]).length;

    const params = new URLSearchParams(
        Object.entries(draft).filter(([, v]) => v) as [string, string][],
    );

    // Canonical order, whatever order they were ticked in, so the same
    // tick-boxes always produce the same file.
    params.set('columns', columns.filter((c) => picked.includes(c.key)).map((c) => c.key).join(','));

    /** Copy whatever the list has applied, so the two agree. */
    const copyFromList = () =>
        setDraft(Object.fromEntries(
            KEYS.filter((k) => filters[k]).map((k) => [k, filters[k]!]),
        ));

    const listHasFilters = KEYS.some((k) => filters[k]);

    return (
        <Modal open onClose={onClose} title="Download contacts" wide>
            <div className="space-y-5">
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Picker
                        label="Team name"
                        placeholder="All teams"
                        value={draft.team}
                        onChange={(v) => set('team', v)}
                        options={options.teams.map((t) => ({ value: String(t.id), label: t.name }))}
                    />
                    <Picker
                        label="Assigned to"
                        placeholder="Anyone"
                        value={draft.member}
                        onChange={(v) => set('member', v)}
                        options={members.map((m) => ({ value: String(m.id), label: m.name }))}
                    />
                    <Picker
                        label="Tags"
                        placeholder="Any tag"
                        value={draft.tags}
                        onChange={(v) => set('tags', v)}
                        options={options.tags.map((t) => ({
                            value: String(t.id),
                            label: `${t.emoji ?? ''} ${t.name}`.trim(),
                        }))}
                    />
                    <Picker
                        label="Lead stages"
                        placeholder="Any stage"
                        value={draft.stage}
                        onChange={(v) => set('stage', v)}
                        options={options.stages.map((s) => ({
                            value: String(s.id),
                            label: `${s.emoji ?? ''} ${s.name}`.trim(),
                        }))}
                    />
                    <Picker
                        label="Lead groups"
                        placeholder="Any group"
                        value={draft.group}
                        onChange={(v) => set('group', v)}
                        options={options.groups.map((g) => ({ value: String(g.id), label: g.name }))}
                    />
                    <Picker
                        label="Created by"
                        placeholder="Anyone"
                        value={draft.creator}
                        onChange={(v) => set('creator', v)}
                        options={options.creators.map((c) => ({ value: String(c.id), label: c.name }))}
                    />
                </div>

                <div className="flex flex-wrap items-end gap-3">
                    <Labelled label="Created date from">
                        <input
                            type="date"
                            value={draft.date_from ?? ''}
                            max={draft.date_to || undefined}
                            onChange={(e) => set('date_from', e.target.value)}
                            className={control}
                        />
                    </Labelled>
                    <Labelled label="Created date to">
                        <input
                            type="date"
                            value={draft.date_to ?? ''}
                            min={draft.date_from || undefined}
                            onChange={(e) => set('date_to', e.target.value)}
                            className={control}
                        />
                    </Labelled>

                    {/* No cap on the range. Exports stream row by row rather
                        than being built in memory, so a wider window costs
                        nothing and a limit would be borrowed for no reason. */}
                    <p className="pb-2 text-xs text-muted-foreground">
                        Any date range. Leave both empty for everything.
                    </p>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4">
                    <p className="text-sm text-muted-foreground">
                        {chosen === 0
                            ? 'No filters — the whole contact book will be downloaded.'
                            : `${chosen} filter${chosen === 1 ? '' : 's'} on this download.`}
                    </p>

                    <div className="flex gap-3 text-sm font-medium">
                        {listHasFilters && (
                            <button
                                type="button"
                                onClick={copyFromList}
                                className="text-primary underline-offset-4 hover:underline"
                            >
                                Use the list's filters
                            </button>
                        )}
                        <button
                            type="button"
                            onClick={() => setDraft({})}
                            disabled={chosen === 0}
                            className="underline-offset-4 hover:underline disabled:opacity-40"
                            style={{ color: 'var(--bad)' }}
                        >
                            Reset filters
                        </button>
                    </div>
                </div>

                <div>
                    <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                        <h3 className="font-display text-base font-bold">Select columns to download</h3>
                        <div className="flex gap-3 text-sm font-medium">
                            <button
                                type="button"
                                onClick={() => setPicked(columns.map((c) => c.key))}
                                className="text-primary underline-offset-4 hover:underline"
                            >
                                Select all
                            </button>
                            <button
                                type="button"
                                onClick={() => setPicked(defaults)}
                                className="text-muted-foreground underline-offset-4 hover:underline"
                            >
                                Reset columns
                            </button>
                        </div>
                    </div>

                    <div className="grid gap-1 rounded-lg border border-border p-2 sm:grid-cols-2 lg:grid-cols-3">
                        {columns.map((c) => (
                            <label
                                key={c.key}
                                className="flex cursor-pointer items-center gap-2.5 rounded-md px-2 py-1.5 text-sm hover:bg-muted"
                            >
                                <input
                                    type="checkbox"
                                    className="size-4 accent-primary"
                                    checked={picked.includes(c.key)}
                                    onChange={() => toggleColumn(c.key)}
                                />
                                <span className="truncate">{c.heading}</span>
                            </label>
                        ))}
                    </div>

                    <p className="mt-2 text-xs text-muted-foreground">
                        The first ten are the import template, spelled the same way — download
                        them all and the file can be edited and imported straight back.
                    </p>
                </div>

                <div className="flex items-center justify-between gap-3 border-t border-border pt-4">
                    <p className="text-sm text-muted-foreground">
                        {picked.length === 0
                            ? 'Pick at least one column'
                            : `${picked.length} column${picked.length === 1 ? '' : 's'}`}
                    </p>

                    <div className="flex gap-2">
                        <Button type="button" variant="ghost" onClick={onClose}>Cancel</Button>

                        {/*
                            A plain anchor, not a router visit: this is a file
                            download, and Inertia would try to render the CSV.
                        */}
                        <a
                            href={`/customers/export?${params}`}
                            onClick={onClose}
                            aria-disabled={picked.length === 0}
                            className={`inline-flex h-10 items-center gap-1.5 rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:opacity-90 ${
                                picked.length === 0 ? 'pointer-events-none opacity-50' : ''
                            }`}
                        >
                            <svg viewBox="0 0 24 24" className="size-4" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
                                <path d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" strokeLinecap="round" strokeLinejoin="round" />
                            </svg>
                            Download contacts
                        </a>
                    </div>
                </div>
            </div>
        </Modal>
    );
}

const control =
    'h-10 w-[9.5rem] rounded-lg border border-input bg-card px-2.5 text-sm outline-none transition ' +
    'focus:border-primary focus:ring-2 focus:ring-primary/15';

function Labelled({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <label className="flex flex-col gap-1">
            <span className="text-xs font-semibold text-muted-foreground">{label}</span>
            {children}
        </label>
    );
}

function Picker({
    label,
    placeholder,
    value,
    onChange,
    options,
}: {
    label: string;
    placeholder: string;
    value: string | undefined;
    onChange: (v: string | undefined) => void;
    options: { value: string; label: string }[];
}) {
    return (
        <Labelled label={label}>
            <select
                value={value ?? ''}
                onChange={(e) => onChange(e.target.value || undefined)}
                className={`${control} w-full ${value ? 'border-primary/60 font-medium text-primary' : ''}`}
            >
                <option value="">{placeholder}</option>
                {options.map((o) => (
                    <option key={o.value} value={o.value}>{o.label}</option>
                ))}
            </select>
        </Labelled>
    );
}
