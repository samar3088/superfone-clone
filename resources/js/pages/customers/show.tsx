import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, ReactNode, useState } from 'react';

import { Button, Field, Modal, Pill, inputClass } from '@/components/ui-kit';
import ConsoleLayout from '@/layouts/console-layout';
import { LeadChoice, Note, NoteComposer, NoteList } from './notes';

interface Customer {
    id: number;
    name: string;
    first_name: string | null;
    last_name: string | null;
    mobile: string;
    email: string | null;
    city: string | null;
    notes: string | null;
    leads_count: number;
    calls_count: number;
    last_activity_at: string | null;
}

interface Lead {
    id: number;
    campaign: string | null;
    source: string;
    created_at: string;
    stage: { id: number; name: string; emoji: string | null; type: string } | null;
    assignee: { id: number; name: string } | null;
}

interface Duplicate {
    id: number;
    name: string;
    mobile: string;
    email: string | null;
}

const TONE: Record<string, 'good' | 'bad' | 'neutral'> = {
    FINAL_POSITIVE: 'good',
    FINAL_NEGATIVE: 'bad',
    INITIAL: 'neutral',
    NONE: 'neutral',
};

export default function CustomerShow({
    customer,
    leads,
    duplicates,
    notes,
}: {
    customer: Customer;
    leads: Lead[];
    duplicates: Duplicate[];
    notes: Note[];
}) {
    const [editing, setEditing] = useState(false);
    const [merging, setMerging] = useState(false);

    // The composer needs the same leads the history table shows, named the way
    // a picker names them. Matches NoteService::label on the server.
    const noteChoices: LeadChoice[] = leads.map((l) => ({
        id: l.id,
        label: l.campaign || l.source || 'Lead',
        stage: l.stage?.name ?? null,
        emoji: l.stage?.emoji ?? null,
        created_at: l.created_at.slice(0, 10),
    }));

    return (
        <ConsoleLayout
            title={customer.name}
            description={`${customer.leads_count} lead${customer.leads_count === 1 ? '' : 's'} · ${customer.calls_count} call${customer.calls_count === 1 ? '' : 's'}`}
            actions={
                <>
                    {duplicates.length > 0 && (
                        <Button variant="ghost" onClick={() => setMerging(true)}>
                            Merge duplicates
                        </Button>
                    )}
                    <Button onClick={() => setEditing(true)}>Edit customer</Button>
                </>
            }
        >
            <Head title={customer.name} />

            <div className="grid gap-5 lg:grid-cols-[320px_1fr]">
                <section className="rounded-xl border border-border bg-card p-5">
                    <h2 className="eyebrow">Details</h2>
                    <dl className="mt-4 space-y-3 text-sm">
                        <Row label="Mobile">
                            <span className="data">{customer.mobile}</span>
                        </Row>
                        <Row label="Email">{customer.email ?? '—'}</Row>
                        <Row label="City">{customer.city ?? '—'}</Row>
                        <Row label="Last activity">
                            {customer.last_activity_at ? (
                                <span className="data">{customer.last_activity_at.slice(0, 16).replace('T', ' ')}</span>
                            ) : (
                                '—'
                            )}
                        </Row>
                    </dl>

                    {/* The free-text field on the contact record itself, filled
                        when the contact was created. Distinct from the dated
                        notes below, which are a running conversation. */}
                    {customer.notes && (
                        <>
                            <h2 className="eyebrow mt-6">On the contact record</h2>
                            <p className="mt-2 whitespace-pre-wrap text-sm text-muted-foreground">{customer.notes}</p>
                        </>
                    )}
                </section>

                <section className="overflow-hidden rounded-xl border border-border bg-card">
                    <h2 className="border-b border-border px-5 py-4 font-display text-lg font-bold">Lead history</h2>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="table-head">
                                <tr className="border-b border-border">
                                    {['Campaign', 'Source', 'Stage', 'Owner', 'Raised'].map((h) => (
                                        <th key={h} className="px-4 py-3 text-left">
                                            <span className="eyebrow">{h}</span>
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {leads.map((l) => (
                                    <tr key={l.id} className="border-b border-border/60 last:border-0 hover:bg-muted/50">
                                        <td className="px-4 py-3 font-medium">{l.campaign ?? '—'}</td>
                                        <td className="px-4 py-3 capitalize text-muted-foreground">{l.source}</td>
                                        <td className="px-4 py-3">
                                            {l.stage ? (
                                                <Pill tone={TONE[l.stage.type]}>
                                                    {l.stage.emoji} {l.stage.name}
                                                </Pill>
                                            ) : (
                                                <span className="text-muted-foreground">—</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            {l.assignee?.name ?? <span className="text-muted-foreground">Unassigned</span>}
                                        </td>
                                        <td className="px-4 py-3 data text-muted-foreground">{l.created_at.slice(0, 10)}</td>
                                    </tr>
                                ))}
                                {leads.length === 0 && (
                                    <tr>
                                        <td colSpan={5} className="px-4 py-12 text-center text-muted-foreground">
                                            No leads yet.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>

                {/*
                    Spans the grid: a note can be long, and reading it in a
                    320px column is worse than reading it nowhere.
                */}
                <section className="rounded-xl border border-border bg-card p-5 lg:col-span-2">
                    <h2 className="font-display text-lg font-bold">Notes</h2>
                    <p className="mb-4 mt-0.5 text-sm text-muted-foreground">
                        {noteChoices.length === 0
                            ? 'This contact has no leads yet, so notes are saved against them.'
                            : 'Say which lead a note is about, or leave it against the contact.'}
                    </p>

                    <NoteComposer customerId={customer.id} leads={noteChoices} />

                    <div className="mt-5">
                        <NoteList notes={notes} />
                    </div>
                </section>
            </div>

            {editing && <EditForm customer={customer} onClose={() => setEditing(false)} />}
            {merging && <MergeForm customer={customer} duplicates={duplicates} onClose={() => setMerging(false)} />}
        </ConsoleLayout>
    );
}

function Row({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="flex justify-between gap-3">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="text-right font-medium">{children}</dd>
        </div>
    );
}

function EditForm({ customer, onClose }: { customer: Customer; onClose: () => void }) {
    const form = useForm({
        first_name: customer.first_name ?? '',
        last_name: customer.last_name ?? '',
        mobile: customer.mobile,
        email: customer.email ?? '',
        city: customer.city ?? '',
        notes: customer.notes ?? '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.patch(`/customers/${customer.id}`, { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Modal open onClose={onClose} title="Edit customer">
            <form onSubmit={submit} className="space-y-4">
                {/* Two fields here as well, so a contact edited by hand and
                    one imported from a sheet hold their name the same way. */}
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="First name" required error={form.errors.first_name}>
                        <input
                            className={inputClass}
                            value={form.data.first_name}
                            autoFocus
                            onChange={(e) => form.setData('first_name', e.target.value)}
                        />
                    </Field>
                    <Field label="Last name" error={form.errors.last_name}>
                        <input
                            className={inputClass}
                            value={form.data.last_name}
                            onChange={(e) => form.setData('last_name', e.target.value)}
                        />
                    </Field>
                </div>
                <Field label="Mobile" required error={form.errors.mobile}>
                    <input
                        className={`${inputClass} data`}
                        maxLength={10}
                        value={form.data.mobile}
                        onChange={(e) => form.setData('mobile', e.target.value.replace(/\D/g, ''))}
                    />
                </Field>
                <Field label="Email" error={form.errors.email}>
                    <input
                        type="email"
                        className={inputClass}
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                    />
                </Field>
                <Field label="City">
                    <input
                        className={inputClass}
                        value={form.data.city}
                        onChange={(e) => form.setData('city', e.target.value)}
                    />
                </Field>
                <Field label="Notes">
                    <textarea
                        rows={3}
                        className={inputClass}
                        value={form.data.notes}
                        onChange={(e) => form.setData('notes', e.target.value)}
                    />
                </Field>
                <div className="flex justify-end gap-3">
                    <Button type="button" variant="ghost" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        Save
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

function MergeForm({
    customer,
    duplicates,
    onClose,
}: {
    customer: Customer;
    duplicates: Duplicate[];
    onClose: () => void;
}) {
    const [selected, setSelected] = useState<number[]>([]);

    const toggle = (id: number) =>
        setSelected((s) => (s.includes(id) ? s.filter((x) => x !== id) : [...s, id]));

    return (
        <Modal
            open
            onClose={onClose}
            title={`Merge into ${customer.name}`}
            description="Their leads and calls move to this record. The duplicates are archived, not deleted."
        >
            <div className="space-y-2">
                {duplicates.map((d) => (
                    <label
                        key={d.id}
                        className="flex cursor-pointer items-center gap-3 rounded-lg border border-border p-3 hover:bg-muted"
                    >
                        <input
                            type="checkbox"
                            className="size-4 accent-primary"
                            checked={selected.includes(d.id)}
                            onChange={() => toggle(d.id)}
                        />
                        <span className="min-w-0 flex-1">
                            <span className="block font-medium">{d.name}</span>
                            <span className="block data text-xs text-muted-foreground">
                                {d.mobile}
                                {d.email ? ` · ${d.email}` : ''}
                            </span>
                        </span>
                    </label>
                ))}
            </div>
            <div className="mt-5 flex justify-end gap-3">
                <Button variant="ghost" onClick={onClose}>
                    Cancel
                </Button>
                <Button
                    disabled={selected.length === 0}
                    onClick={() =>
                        router.post(
                            `/customers/${customer.id}/merge`,
                            { duplicate_ids: selected },
                            { preserveScroll: true, onSuccess: onClose }
                        )
                    }
                >
                    Merge {selected.length > 0 ? selected.length : ''}
                </Button>
            </div>
        </Modal>
    );
}
