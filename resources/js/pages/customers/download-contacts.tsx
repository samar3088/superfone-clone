import { useState } from 'react';

import { Filters } from '@/components/table-filters';
import { Button, Modal } from '@/components/ui-kit';

export interface ColumnChoice {
    key: string;
    heading: string;
}

/**
 * Choosing what a contact download contains.
 *
 * Two halves. The top narrows which contacts — starting from whatever the
 * screen already has applied, so "download what I am looking at" needs no
 * setting up. The bottom picks the columns.
 *
 * The first ten columns are the import template, spelled identically: a
 * download of everything can be edited and fed straight back in, which is how
 * a bulk update is actually done.
 */
export default function DownloadContacts({
    filters,
    columns,
    defaults,
    onClose,
}: {
    filters: Filters;
    columns: ColumnChoice[];
    defaults: string[];
    onClose: () => void;
}) {
    const [picked, setPicked] = useState<string[]>(defaults);

    const toggle = (key: string) =>
        setPicked((s) => (s.includes(key) ? s.filter((k) => k !== key) : [...s, key]));

    // Applied filters, not drafted ones — the file should match the rows on
    // screen, not choices nobody has committed to.
    const params = new URLSearchParams(
        Object.entries(filters).filter(([k, v]) => v && k !== 'page' && k !== 'columns') as [string, string][],
    );

    // Canonical order, whatever order they were ticked in, so the same
    // tick-boxes always produce the same file.
    params.set('columns', columns.filter((c) => picked.includes(c.key)).map((c) => c.key).join(','));

    const applied = Object.entries(filters).filter(
        ([k, v]) => v && !['page', 'sort', 'direction', 'per_page', 'columns'].includes(k),
    ).length;

    return (
        <Modal open onClose={onClose} title="Download contacts" wide>
            <div className="space-y-5">
                <p className="rounded-lg border border-border px-3 py-2.5 text-sm text-muted-foreground">
                    {applied === 0 ? (
                        <>Every contact will be downloaded. Narrow the list first with the filters if you only want part of it.</>
                    ) : (
                        <>
                            The <b className="text-foreground">{applied}</b> filter{applied === 1 ? '' : 's'} applied
                            to the list will be applied to the file — it downloads exactly what is on screen.
                        </>
                    )}
                </p>

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
                                Reset
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
                                    onChange={() => toggle(c.key)}
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
