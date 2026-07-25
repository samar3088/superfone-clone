import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

import { Button, Field, Pill, inputClass } from '@/components/ui-kit';
import ConsoleLayout from '@/layouts/console-layout';

interface Named { id: number; name: string; emoji?: string | null; color?: string }

interface Integration {
    id: number;
    name: string;
    provider: string;
    page_name: string | null;
    page_description: string | null;
    form_name: string | null;
    connected_account: string | null;
    status: string;
    is_configured: boolean;
    leads_count: number;
    source_type: string | null;
    source: string | null;
    lead_stage_id: number | null;
    lead_group_id: number | null;
    new_lead_enabled: boolean;
    todo_enabled: boolean;
    todo_type: string | null;
    todo_title: string | null;
    todo_due_value: number | null;
    todo_due_unit: string | null;
    notify_enabled: boolean;
    members: Named[];
    tags: Named[];
    lead_stage: Named | null;
    lead_group: Named | null;
}

interface LogRow {
    id: number;
    status: 'success' | 'warning' | 'error';
    message: string;
    leads_fetched: number;
    created_at: string;
}

interface Options {
    members: Named[];
    stages: Named[];
    groups: Named[];
    tags: Named[];
    sourceTypes: string[];
    todoTypes: string[];
}

export default function IntegrationPage({
    integration,
    logs,
    options,
}: {
    integration: Integration;
    logs: LogRow[];
    options: Options;
}) {
    const [tab, setTab] = useState<'config' | 'logs'>('config');
    const [editing, setEditing] = useState(!integration.is_configured);

    return (
        <ConsoleLayout>
            <Head title={integration.name} />

            {/* Header */}
            <div className="mb-6 flex flex-wrap items-center gap-4">
                <Link href="/settings" className="text-xl text-muted-foreground hover:text-foreground" aria-label="Back to settings">
                    ←
                </Link>
                <span className="grid size-10 place-items-center rounded-full bg-[#1877f2] font-bold text-white" aria-hidden>
                    f
                </span>
                <div>
                    <h1 className="font-display text-2xl font-bold">{integration.name}</h1>
                    <p className="text-sm text-muted-foreground">
                        {integration.connected_account} · {integration.leads_count} lead
                        {integration.leads_count === 1 ? '' : 's'} received
                    </p>
                </div>

                <div className="ml-auto flex items-center gap-3">
                    <Pill tone={integration.status === 'active' ? 'good' : 'neutral'}>
                        {integration.status.toUpperCase()}
                    </Pill>
                    <button
                        type="button"
                        role="switch"
                        aria-checked={integration.status === 'active'}
                        aria-label="Toggle integration"
                        onClick={() => router.patch(`/settings/integrations/${integration.id}/toggle`, {}, { preserveScroll: true })}
                        className={`relative h-6 w-11 rounded-full transition ${
                            integration.status === 'active' ? 'bg-[var(--good)]' : 'bg-input'
                        }`}
                    >
                        <span
                            className={`absolute top-0.5 size-5 rounded-full bg-white shadow transition-all ${
                                integration.status === 'active' ? 'left-[22px]' : 'left-0.5'
                            }`}
                        />
                    </button>
                </div>
            </div>

            {!integration.is_configured && (
                <div
                    className="mb-5 rounded-lg px-4 py-3 text-sm font-medium"
                    style={{
                        background: 'var(--warn-soft)',
                        color: 'var(--warn)',
                        border: '1px solid color-mix(in srgb, var(--warn) 25%, transparent)',
                    }}
                >
                    Finish the settings below — until a source and lead stage are set, arriving leads have nowhere to go.
                </div>
            )}

            {/* Tabs */}
            <div className="mb-5 flex gap-1 border-b border-border">
                {(['config', 'logs'] as const).map((t) => (
                    <button
                        key={t}
                        onClick={() => setTab(t)}
                        className={`-mb-px border-b-2 px-4 py-2.5 text-sm font-semibold transition ${
                            tab === t ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        {t === 'config' ? 'Configuration' : 'Logs'}
                    </button>
                ))}
            </div>

            {tab === 'config' ? (
                <>
                    {/* Connected page + form */}
                    <section className="rounded-xl border border-border bg-card p-5">
                        <div className="grid gap-5 sm:grid-cols-2">
                            <div>
                                <p className="eyebrow">Connected page</p>
                                <p className="mt-2 font-display text-lg font-bold">{integration.page_name}</p>
                                {integration.page_description && (
                                    <p className="mt-1 text-sm text-muted-foreground">{integration.page_description}</p>
                                )}
                            </div>
                            <div>
                                <p className="eyebrow">Lead form</p>
                                <div className="mt-2 rounded-lg border border-border p-3.5">
                                    <p className="font-medium">{integration.form_name}</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    {editing ? (
                        <SettingsForm
                            integration={integration}
                            options={options}
                            onCancel={() => setEditing(false)}
                        />
                    ) : (
                        <SettingsSummary integration={integration} onEdit={() => setEditing(true)} />
                    )}
                </>
            ) : (
                <LogsTable logs={logs} />
            )}
        </ConsoleLayout>
    );
}

function SettingsSummary({ integration, onEdit }: { integration: Integration; onEdit: () => void }) {
    const rows: Array<[string, React.ReactNode]> = [
        ['Source type', integration.source_type ?? '—'],
        ['Source', integration.source ?? '—'],
        ['Lead stage', integration.lead_stage ? `${integration.lead_stage.emoji ?? ''} ${integration.lead_stage.name}` : '—'],
        ['Lead group', integration.lead_group?.name ?? '—'],
        [
            'Assignee users',
            integration.members.length > 0 ? (
                <span className="flex flex-wrap justify-end gap-1.5">
                    {integration.members.map((m) => (
                        <span key={m.id} className="rounded-full bg-accent px-2.5 py-0.5 text-xs font-semibold text-accent-foreground">
                            {m.name}
                        </span>
                    ))}
                </span>
            ) : (
                '—'
            ),
        ],
        [
            'Labels',
            integration.tags.length > 0 ? (
                <span className="flex flex-wrap justify-end gap-1.5">
                    {integration.tags.map((t) => (
                        <span
                            key={t.id}
                            className="rounded-md px-2 py-0.5 text-xs font-semibold"
                            style={{ background: `color-mix(in srgb, ${t.color} 14%, transparent)`, color: t.color }}
                        >
                            {t.emoji} {t.name}
                        </span>
                    ))}
                </span>
            ) : (
                '—'
            ),
        ],
        [
            'On new lead',
            integration.todo_enabled
                ? `Create "${integration.todo_title}" (${integration.todo_type}), due in ${integration.todo_due_value} ${integration.todo_due_unit}`
                : integration.new_lead_enabled
                  ? 'Enabled, no to-do rule'
                  : '—',
        ],
        ['Notify me', integration.notify_enabled ? 'Yes' : 'No'],
    ];

    return (
        <section className="mt-5 overflow-hidden rounded-xl border border-border bg-card">
            <div className="flex items-center justify-between gap-3 border-b border-border px-5 py-4">
                <h2 className="font-display text-lg font-bold">Integration settings</h2>
                <Button onClick={onEdit}>Edit</Button>
            </div>
            <dl className="divide-y divide-border">
                {rows.map(([label, value]) => (
                    <div key={label} className="flex flex-wrap items-start justify-between gap-3 px-5 py-3.5 text-sm">
                        <dt className="text-muted-foreground">{label}</dt>
                        <dd className="max-w-[65%] text-right font-medium">{value}</dd>
                    </div>
                ))}
            </dl>
        </section>
    );
}

function SettingsForm({
    integration,
    options,
    onCancel,
}: {
    integration: Integration;
    options: Options;
    onCancel: () => void;
}) {
    const form = useForm({
        name: integration.name,
        source_type: integration.source_type ?? 'Facebook Integration',
        source: integration.source ?? '',
        member_ids: integration.members.map((m) => m.id),
        lead_stage_id: integration.lead_stage_id ?? ('' as number | ''),
        lead_group_id: integration.lead_group_id ?? ('' as number | ''),
        tag_ids: integration.tags.map((t) => t.id),
        new_lead_enabled: integration.new_lead_enabled,
        todo_enabled: integration.todo_enabled,
        todo_type: integration.todo_type ?? '',
        todo_title: integration.todo_title ?? '',
        todo_due_value: integration.todo_due_value ?? ('' as number | ''),
        todo_due_unit: integration.todo_due_unit ?? 'minutes',
        notify_enabled: integration.notify_enabled,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.patch(`/settings/integrations/${integration.id}/settings`, {
            preserveScroll: true,
            onSuccess: onCancel,
        });
    };

    const toggleIn = (key: 'member_ids' | 'tag_ids', id: number) =>
        form.setData(
            key,
            form.data[key].includes(id) ? form.data[key].filter((x) => x !== id) : [...form.data[key], id]
        );

    return (
        <form onSubmit={submit} className="mt-5 space-y-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 className="font-display text-lg font-bold">Integration settings</h2>
                <Button type="button" variant="ghost" onClick={onCancel}>Cancel</Button>
            </div>

            {/* Source */}
            <section className="rounded-xl border border-border bg-card p-5">
                <h3 className="font-display font-bold">Source</h3>
                <p className="mt-0.5 text-sm text-muted-foreground">Where are these leads coming from?</p>

                <div className="mt-4 space-y-4">
                    <Field label="Integration name" required error={form.errors.name}>
                        <input className={inputClass} value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)} />
                    </Field>

                    <Field label="Source type" required error={form.errors.source_type}
                        hint="Where you are getting the leads from, e.g. Facebook or Google.">
                        <select className={inputClass} value={form.data.source_type}
                            onChange={(e) => form.setData('source_type', e.target.value)}>
                            {options.sourceTypes.map((t) => <option key={t} value={t}>{t}</option>)}
                        </select>
                    </Field>

                    <Field label="Source" required error={form.errors.source}
                        hint="A subcategory of the source, e.g. Summer Campaign on Facebook.">
                        <input className={inputClass} placeholder="Facebook" value={form.data.source}
                            onChange={(e) => form.setData('source', e.target.value)} />
                    </Field>
                </div>
            </section>

            {/* Assignment */}
            <section className="rounded-xl border border-border bg-card p-5">
                <h3 className="font-display font-bold">Assignment</h3>
                <p className="mt-0.5 text-sm text-muted-foreground">Who should own and how to classify these leads?</p>

                <div className="mt-4 space-y-4">
                    <Field label="Assignee users" error={form.errors.member_ids}
                        hint="Leads are shared between the selected members in turn.">
                        <div className="max-h-48 space-y-1 overflow-y-auto rounded-lg border border-border p-2">
                            {options.members.map((m) => (
                                <label key={m.id} className="flex cursor-pointer items-center gap-2.5 rounded-md px-2 py-1.5 text-sm hover:bg-muted">
                                    <input type="checkbox" className="size-4 accent-primary"
                                        checked={form.data.member_ids.includes(m.id)}
                                        onChange={() => toggleIn('member_ids', m.id)} />
                                    {m.name}
                                </label>
                            ))}
                        </div>
                    </Field>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Lead stage" required error={form.errors.lead_stage_id}
                            hint="The stage new leads start on.">
                            <select className={inputClass} value={form.data.lead_stage_id}
                                onChange={(e) => form.setData('lead_stage_id', Number(e.target.value) || '')}>
                                <option value="">Select lead stage</option>
                                {options.stages.map((s) => (
                                    <option key={s.id} value={s.id}>{s.emoji} {s.name}</option>
                                ))}
                            </select>
                        </Field>

                        <Field label="Lead group" error={form.errors.lead_group_id}>
                            <select className={inputClass} value={form.data.lead_group_id}
                                onChange={(e) => form.setData('lead_group_id', Number(e.target.value) || '')}>
                                <option value="">Select lead group</option>
                                {options.groups.map((g) => <option key={g.id} value={g.id}>{g.name}</option>)}
                            </select>
                        </Field>
                    </div>

                    <Field label="Labels" hint="Tags applied to every lead from this campaign.">
                        <div className="flex flex-wrap gap-2 rounded-lg border border-border p-2.5">
                            {options.tags.map((t) => {
                                const on = form.data.tag_ids.includes(t.id);
                                return (
                                    <button key={t.id} type="button" onClick={() => toggleIn('tag_ids', t.id)}
                                        className={`rounded-md px-2.5 py-1 text-xs font-semibold transition ${on ? '' : 'opacity-45'}`}
                                        style={{ background: `color-mix(in srgb, ${t.color} 14%, transparent)`, color: t.color }}>
                                        {t.emoji} {t.name}
                                    </button>
                                );
                            })}
                        </div>
                    </Field>
                </div>
            </section>

            {/* New lead */}
            <section className="rounded-xl border border-border bg-card p-5">
                <label className="flex items-center gap-2.5">
                    <input type="checkbox" className="size-4 accent-primary"
                        checked={form.data.new_lead_enabled}
                        onChange={(e) => form.setData('new_lead_enabled', e.target.checked)} />
                    <span>
                        <span className="block font-display font-bold">New lead</span>
                        <span className="block text-sm text-muted-foreground">What should happen when a new lead comes in</span>
                    </span>
                </label>

                {form.data.new_lead_enabled && (
                    <div className="mt-5 space-y-4 border-t border-border pt-5">
                        <label className="flex items-center gap-2.5 text-sm font-semibold">
                            <input type="checkbox" className="size-4 accent-primary"
                                checked={form.data.todo_enabled}
                                onChange={(e) => form.setData('todo_enabled', e.target.checked)} />
                            Create a to-do for the assignee
                        </label>

                        {form.data.todo_enabled && (
                            <div className="space-y-4 pl-6">
                                <Field label="Type" required error={form.errors.todo_type}
                                    hint="The task type assigned on a new lead, e.g. FIRST CALL.">
                                    <select className={inputClass} value={form.data.todo_type}
                                        onChange={(e) => form.setData('todo_type', e.target.value)}>
                                        <option value="">Select type</option>
                                        {options.todoTypes.map((t) => <option key={t} value={t}>{t}</option>)}
                                    </select>
                                </Field>

                                <Field label="Title" required error={form.errors.todo_title}
                                    hint="Added to the note when the lead arrives.">
                                    <input className={inputClass} placeholder="Enter title" value={form.data.todo_title}
                                        onChange={(e) => form.setData('todo_title', e.target.value)} />
                                </Field>

                                <Field label="Due in" required error={form.errors.todo_due_value ?? form.errors.todo_due_unit}
                                    hint="How long after the lead arrives the task is due.">
                                    <div className="flex gap-2">
                                        <input type="number" min={1} className={inputClass} placeholder="30"
                                            value={form.data.todo_due_value}
                                            onChange={(e) => form.setData('todo_due_value', Number(e.target.value) || '')} />
                                        <div className="flex overflow-hidden rounded-lg border border-border">
                                            {(['seconds', 'minutes', 'hours', 'days'] as const).map((u) => (
                                                <button key={u} type="button" onClick={() => form.setData('todo_due_unit', u)}
                                                    className={`px-3 py-2 text-sm font-medium transition ${
                                                        form.data.todo_due_unit === u
                                                            ? 'bg-primary text-primary-foreground'
                                                            : 'hover:bg-muted'
                                                    }`}>
                                                    {u}
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                </Field>
                            </div>
                        )}

                        <label className="flex items-center gap-2.5 text-sm font-semibold">
                            <input type="checkbox" className="size-4 accent-primary"
                                checked={form.data.notify_enabled}
                                onChange={(e) => form.setData('notify_enabled', e.target.checked)} />
                            Notify me when a new lead arrives
                        </label>
                    </div>
                )}
            </section>

            <div className="flex justify-end gap-3">
                <Button type="button" variant="ghost" onClick={onCancel}>Cancel</Button>
                <Button type="submit" disabled={form.processing}>
                    {form.processing ? 'Saving…' : 'Save settings'}
                </Button>
            </div>
        </form>
    );
}

function LogsTable({ logs }: { logs: LogRow[] }) {
    const tone = { success: 'good', warning: 'warn', error: 'bad' } as const;

    return (
        <section className="overflow-hidden rounded-xl border border-border bg-card">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b border-border">
                        {['When', 'Status', 'Message', 'Leads'].map((h) => (
                            <th key={h} className="px-4 py-3 text-left"><span className="eyebrow">{h}</span></th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {logs.map((l) => (
                        <tr key={l.id} className="border-b border-border/60 last:border-0">
                            <td className="whitespace-nowrap px-4 py-3 data text-muted-foreground">
                                {l.created_at.slice(0, 16).replace('T', ' ')}
                            </td>
                            <td className="px-4 py-3"><Pill tone={tone[l.status]}>{l.status}</Pill></td>
                            <td className="px-4 py-3">{l.message}</td>
                            <td className="px-4 py-3 tabular">{l.leads_fetched}</td>
                        </tr>
                    ))}
                    {logs.length === 0 && (
                        <tr>
                            <td colSpan={4} className="px-4 py-12 text-center text-muted-foreground">
                                No sync activity yet. Entries appear here each time leads are fetched.
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </section>
    );
}
