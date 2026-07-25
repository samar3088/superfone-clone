import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useEffect, useState } from 'react';

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
    notify_value: number | null;
    notify_unit: string | null;
    existing_lead_enabled: boolean;
    existing_todo_enabled: boolean;
    existing_todo_type: string | null;
    existing_todo_title: string | null;
    existing_todo_due_value: number | null;
    existing_todo_due_unit: string | null;
    existing_notify_enabled: boolean;
    existing_notify_value: number | null;
    existing_notify_unit: string | null;
    created_at: string;
    page_picture: string | null;
    members: Named[];
    tags: Named[];
    lead_stage: Named | null;
    lead_group: Named | null;
}

interface PageProfile {
    name: string;
    picture: string;
    cover: string | null;
    about: string | null;
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
                        {integration.leads_count} lead{integration.leads_count === 1 ? '' : 's'} received
                    </p>
                </div>

                <div className="ml-auto flex items-center gap-4">
                    {integration.connected_account && (
                        <span className="text-sm font-medium text-primary">{integration.connected_account}</span>
                    )}
                    {/*
                        Reconnecting means replacing the stored access token, which
                        lives under Notifications — there is no OAuth round-trip to
                        start from here.
                    */}
                    <Link href="/settings" title="Replace the Facebook access token under Settings → Notifications">
                        <Button>Reconnect</Button>
                    </Link>
                </div>
            </div>

            {/* Tabs + status */}
            <div className="mb-5 flex flex-wrap items-center justify-between gap-3 border-b border-border">
                <div className="flex gap-1">
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

                <div className="flex items-center gap-2.5 pb-2">
                    <span
                        className="flex items-center gap-1.5 text-xs font-bold tracking-wide"
                        style={{ color: integration.status === 'active' ? 'var(--good)' : 'var(--muted-foreground)' }}
                    >
                        <span className="size-1.5 rounded-full" style={{ background: 'currentColor' }} aria-hidden />
                        {integration.status.toUpperCase()}
                    </span>
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

            {tab === 'config' ? (
                <>
                    <PageCard integration={integration} />

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

/**
 * The connected page, with its cover and blurb pulled live from Facebook.
 *
 * Not stored: cover urls are signed and expire, so a saved copy becomes a
 * broken image within days. The avatar comes from the stable /picture endpoint.
 */
function PageCard({ integration }: { integration: Integration }) {
    const [profile, setProfile] = useState<PageProfile | null>(null);
    const [done, setDone] = useState(false);

    useEffect(() => {
        fetch(`/settings/integrations/${integration.id}/preview`, { headers: { Accept: 'application/json' } })
            .then((r) => (r.ok ? r.json() : Promise.reject()))
            .then((d) => setProfile(d.profile))
            .catch(() => undefined)
            .finally(() => setDone(true));
    }, [integration.id]);

    return (
        <section className="rounded-xl border border-border bg-card p-5">
            <div className="grid gap-6 sm:grid-cols-2">
                <div>
                    {profile?.cover ? (
                        <img
                            src={profile.cover}
                            alt={`${integration.page_name ?? 'Page'} cover`}
                            className="w-full rounded-lg border border-border object-cover"
                        />
                    ) : (
                        <div className="grid h-36 w-full place-items-center rounded-lg border border-border bg-muted text-sm text-muted-foreground">
                            {done ? 'No cover photo' : 'Loading…'}
                        </div>
                    )}
                </div>

                <div>
                    <div className="flex items-start gap-3">
                        {profile?.picture && (
                            <img src={profile.picture} alt="" className="size-11 shrink-0 rounded-full border border-border object-cover" />
                        )}
                        <div className="min-w-0">
                            <p className="font-display text-lg font-bold">{integration.page_name}</p>
                            {(profile?.about ?? integration.page_description) && (
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {profile?.about ?? integration.page_description}
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="mt-5 rounded-lg border border-border p-4">
                        <p className="eyebrow">Connected form</p>
                        <p className="mt-1.5 font-medium">{integration.form_name}</p>
                    </div>
                </div>
            </div>
        </section>
    );
}

function YesNo({ on }: { on: boolean }) {
    return <Pill tone={on ? 'good' : 'bad'}>{on ? 'YES' : 'NO'}</Pill>;
}

function SettingsSummary({ integration, onEdit }: { integration: Integration; onEdit: () => void }) {
    const chips = (items: Named[], colour = false) =>
        items.length > 0 ? (
            <span className="flex flex-wrap gap-1.5">
                {items.map((i) => (
                    <span
                        key={i.id}
                        className={`rounded-md px-2 py-0.5 text-xs font-semibold ${colour ? '' : 'bg-accent text-accent-foreground'}`}
                        style={colour ? { background: `color-mix(in srgb, ${i.color} 14%, transparent)`, color: i.color } : undefined}
                    >
                        {i.emoji} {i.name}
                    </span>
                ))}
            </span>
        ) : (
            '—'
        );

    /*
     | Two columns, read left to right in pairs. Source and Source Type sit
     | together because they answer the same question at different grain.
     */
    const rows: Array<[string, React.ReactNode]> = [
        ['Source', integration.source ?? '—'],
        ['Source Type', integration.source_type ?? '—'],
        ['Assigned Users', chips(integration.members)],
        ['Lead Stage', integration.lead_stage ? `${integration.lead_stage.emoji ?? ''} ${integration.lead_stage.name}` : '—'],
        ['Lead Group', integration.lead_group?.name ?? '—'],
        ['Created At', integration.created_at?.slice(0, 10) ?? '—'],
        ['Create to do on new lead', <YesNo key="n" on={integration.todo_enabled} />],
        ['Create to do on existing lead', <YesNo key="e" on={integration.existing_todo_enabled} />],
        ['Labels', chips(integration.tags, true)],
    ];

    return (
        <section className="mt-5 overflow-hidden rounded-xl border border-border bg-card">
            <div className="flex items-center justify-between gap-3 border-b border-border px-5 py-4">
                <h2 className="font-display text-lg font-bold">Integration Settings</h2>
                <Button onClick={onEdit}>Edit</Button>
            </div>

            <dl className="grid sm:grid-cols-2">
                {rows.map(([label, value], i) => (
                    <div
                        key={label}
                        className={`flex items-start gap-4 border-b border-border/60 px-5 py-3.5 text-sm ${
                            i % 2 === 0 ? 'sm:border-r' : ''
                        }`}
                    >
                        <dt className="w-44 shrink-0 text-muted-foreground">{label}</dt>
                        <dd className="min-w-0 font-medium">{value}</dd>
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
        notify_value: integration.notify_value ?? ('' as number | ''),
        notify_unit: integration.notify_unit ?? 'minutes',
        existing_lead_enabled: integration.existing_lead_enabled,
        existing_todo_enabled: integration.existing_todo_enabled,
        existing_todo_type: integration.existing_todo_type ?? '',
        existing_todo_title: integration.existing_todo_title ?? '',
        existing_todo_due_value: integration.existing_todo_due_value ?? ('' as number | ''),
        existing_todo_due_unit: integration.existing_todo_due_unit ?? 'minutes',
        existing_notify_enabled: integration.existing_notify_enabled,
        existing_notify_value: integration.existing_notify_value ?? ('' as number | ''),
        existing_notify_unit: integration.existing_notify_unit ?? 'minutes',
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

            {/*
                New and existing leads carry the same rule shape but usually want
                different handling — a first call versus a follow-up — so both
                render from one component rather than two copies that drift.
            */}
            <LeadRuleBlock
                form={form}
                options={options}
                prefix=""
                title="New lead"
                subtitle="What should happen when a new lead comes to you"
                typeHint="The task type assigned to new leads, e.g. FIRST CALL."
                titleHint="Added to the note when a new lead comes in."
            />

            <LeadRuleBlock
                form={form}
                options={options}
                prefix="existing_"
                title="Existing Lead"
                subtitle="What should happen when an existing lead comes to you"
                typeHint="The task type assigned to existing leads, e.g. FOLLOW-UP CALL."
                titleHint="Added to the note when an existing lead comes back."
            />

            <div className="flex justify-end gap-3">
                <Button type="button" variant="ghost" onClick={onCancel}>Cancel</Button>
                <Button type="submit" disabled={form.processing}>
                    {form.processing ? 'Saving…' : 'Save settings'}
                </Button>
            </div>
        </form>
    );
}

const UNITS = ['seconds', 'minutes', 'hours', 'days'] as const;

/** Number box plus a unit segmented control, as used for both due dates and notification delays. */
function DurationInput({
    value,
    unit,
    onValue,
    onUnit,
    placeholder,
}: {
    value: number | '';
    unit: string;
    onValue: (v: number | '') => void;
    onUnit: (u: string) => void;
    placeholder: string;
}) {
    return (
        <div className="flex gap-2">
            <input
                type="number"
                min={0}
                className={inputClass}
                placeholder={placeholder}
                value={value}
                onChange={(e) => onValue(e.target.value === '' ? '' : Number(e.target.value))}
            />
            <div className="flex shrink-0 overflow-hidden rounded-lg border border-border">
                {UNITS.map((u) => (
                    <button
                        key={u}
                        type="button"
                        onClick={() => onUnit(u)}
                        className={`px-3 py-2 text-sm font-medium transition ${
                            unit === u ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'
                        }`}
                    >
                        {u}
                    </button>
                ))}
            </div>
        </div>
    );
}

/**
 * One lead-arrival rule: whether to act at all, an optional to-do, and an
 * optional notification. Driven by a field-name prefix so the New Lead and
 * Existing Lead blocks stay identical in behaviour.
 */
/* eslint-disable @typescript-eslint/no-explicit-any */
function LeadRuleBlock({
    form,
    options,
    prefix,
    title,
    subtitle,
    typeHint,
    titleHint,
}: {
    form: any;
    options: Options;
    prefix: '' | 'existing_';
    title: string;
    subtitle: string;
    typeHint: string;
    titleHint: string;
}) {
    const k = (name: string) => (prefix === '' && name === 'lead_enabled' ? 'new_lead_enabled' : `${prefix}${name}`);
    const d = (name: string) => form.data[k(name)];
    const set = (name: string, value: unknown) => form.setData(k(name), value);
    const err = (name: string) => form.errors[k(name)];

    return (
        <section className="rounded-xl border border-border bg-card p-5">
            <label className="flex items-center gap-2.5">
                <input
                    type="checkbox"
                    className="size-4 accent-primary"
                    checked={d('lead_enabled')}
                    onChange={(e) => set('lead_enabled', e.target.checked)}
                />
                <span>
                    <span className="block font-display font-bold">{title}</span>
                    <span className="block text-sm text-muted-foreground">{subtitle}</span>
                </span>
            </label>

            {d('lead_enabled') && (
                <div className="mt-5 space-y-5 border-t border-border pt-5">
                    <div>
                        <label className="flex items-center gap-2.5 text-sm font-semibold">
                            <input
                                type="checkbox"
                                className="size-4 accent-primary"
                                checked={d('todo_enabled')}
                                onChange={(e) => set('todo_enabled', e.target.checked)}
                            />
                            Create a to-do for the assignee
                        </label>

                        {d('todo_enabled') && (
                            <div className="mt-4 space-y-4 pl-6">
                                <Field label="Type" required error={err('todo_type')} hint={typeHint}>
                                    <select
                                        className={inputClass}
                                        value={d('todo_type')}
                                        onChange={(e) => set('todo_type', e.target.value)}
                                    >
                                        <option value="">Select type</option>
                                        {options.todoTypes.map((t) => (
                                            <option key={t} value={t}>{t}</option>
                                        ))}
                                    </select>
                                </Field>

                                <Field label="Title" required error={err('todo_title')} hint={titleHint}>
                                    <input
                                        className={inputClass}
                                        placeholder="Enter title"
                                        value={d('todo_title')}
                                        onChange={(e) => set('todo_title', e.target.value)}
                                    />
                                </Field>

                                <Field
                                    label="Due date"
                                    required
                                    error={err('todo_due_value') ?? err('todo_due_unit')}
                                    hint="How long after the lead arrives the task is due."
                                >
                                    <DurationInput
                                        value={d('todo_due_value')}
                                        unit={d('todo_due_unit')}
                                        onValue={(v) => set('todo_due_value', v)}
                                        onUnit={(u) => set('todo_due_unit', u)}
                                        placeholder="30"
                                    />
                                </Field>
                            </div>
                        )}
                    </div>

                    <div>
                        <label className="flex items-center gap-2.5 text-sm font-semibold">
                            <input
                                type="checkbox"
                                className="size-4 accent-primary"
                                checked={d('notify_enabled')}
                                onChange={(e) => set('notify_enabled', e.target.checked)}
                            />
                            Notify me
                        </label>

                        {d('notify_enabled') && (
                            <div className="mt-4 pl-6">
                                <Field
                                    label="Notification time"
                                    required
                                    error={err('notify_value') ?? err('notify_unit')}
                                    hint="How long after the lead arrives to send the notification. Zero means straight away."
                                >
                                    <DurationInput
                                        value={d('notify_value')}
                                        unit={d('notify_unit')}
                                        onValue={(v) => set('notify_value', v)}
                                        onUnit={(u) => set('notify_unit', u)}
                                        placeholder="0"
                                    />
                                </Field>
                            </div>
                        )}
                    </div>
                </div>
            )}
        </section>
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
