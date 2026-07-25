import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, ReactNode, useEffect, useRef, useState } from 'react';

import { FacebookReconnect } from '@/components/facebook-reconnect';
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
    created_ago: string;
    created_at_label: string;
    team: string;
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
    const [reconnecting, setReconnecting] = useState(false);

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
                    <Button onClick={() => setReconnecting(true)}>Reconnect</Button>
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

            {reconnecting && (
                <FacebookReconnect
                    account={integration.connected_account}
                    onClose={() => setReconnecting(false)}
                />
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

/** "FIRST CALL with title "Bangalore Packages"" — the rule, not just whether it exists. */
function TodoRule({ type, title }: { type: string | null; title: string | null }) {
    if (!type) {
        return <span className="text-muted-foreground">Not set</span>;
    }

    return (
        <span>
            <span className="font-semibold">{type}</span>
            {title && <> with title “{title}”</>}
        </span>
    );
}

function SettingsSummary({ integration, onEdit }: { integration: Integration; onEdit: () => void }) {
    /*
     | Rows are laid out in pairs, left then right, so related settings sit
     | beside each other: Source with Source Type, and the two to-do rules
     | facing one another.
     */
    const rows: Array<[string, React.ReactNode]> = [
        ['Source', integration.source ?? '—'],
        ['Source Type', integration.source_type ?? '—'],

        [
            'Webhook',
            /*
                A webhook only means anything for the Custom Webhook provider —
                Facebook leads are pulled, not pushed. Saying so beats a dash
                that reads like something failed to load.
            */
            integration.provider === 'facebook' ? (
                <span className="text-muted-foreground">Not used — Facebook leads are pulled</span>
            ) : (
                <span className="text-muted-foreground">Not set</span>
            ),
        ],
        ['Team', <span key="t" className="uppercase">{integration.team}</span>],

        [
            'Assigned Users',
            integration.members.length > 0
                ? integration.members.map((m) => m.name).join(', ')
                : <span key="none" className="text-muted-foreground">Nobody — leads will land unassigned</span>,
        ],
        [
            'Lead Stage',
            integration.lead_stage
                ? `${integration.lead_stage.emoji ?? ''} ${integration.lead_stage.name}`
                : '—',
        ],

        ['Lead Group', integration.lead_group?.name ?? '—'],
        ['Created At', integration.created_at_label ?? '—'],

        [
            'Create to do on new lead',
            <TodoRule key="n" type={integration.todo_type} title={integration.todo_title} />,
        ],
        [
            'Create to do on existing lead',
            <TodoRule key="e" type={integration.existing_todo_type} title={integration.existing_todo_title} />,
        ],

        [
            'Labels',
            integration.tags.length > 0 ? (
                <span className="flex flex-wrap gap-1.5">
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
    ];

    return (
        <section className="mt-5 overflow-hidden rounded-xl border border-border bg-card">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-4">
                <div>
                    <h2 className="font-display text-lg font-bold">Integration Settings</h2>
                    <p className="mt-0.5 text-sm text-muted-foreground">Created {integration.created_ago}</p>
                </div>

                <div className="flex items-center gap-3">
                    <span className="eyebrow">{integration.provider} direct</span>
                    <Pill tone={integration.status === 'active' ? 'good' : 'neutral'}>
                        {integration.status.toUpperCase()}
                    </Pill>
                    <Button onClick={onEdit}>Edit</Button>
                </div>
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

                <div className="mt-2 divide-y divide-border/60">
                    <Row label="Integration name" required error={form.errors.name}>
                        <input className={inputClass} value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)} />
                    </Row>

                    <Row label="Source Type" required error={form.errors.source_type}
                        hint="Select the source from where you are getting the leads (e.g. Facebook, Whatsapp).">
                        <select className={inputClass} value={form.data.source_type}
                            onChange={(e) => form.setData('source_type', e.target.value)}>
                            {options.sourceTypes.map((t) => <option key={t} value={t}>{t}</option>)}
                        </select>
                    </Row>

                    <Row label="Source" required error={form.errors.source}
                        hint="Choose a subcategory of the source, e.g. Summer Campaign on Facebook.">
                        <input className={inputClass} placeholder="Facebook" value={form.data.source}
                            onChange={(e) => form.setData('source', e.target.value)} />
                    </Row>
                </div>
            </section>

            {/* Assignment */}
            <section className="rounded-xl border border-border bg-card p-5">
                <h3 className="font-display font-bold">Assignment</h3>
                <p className="mt-0.5 text-sm text-muted-foreground">Who should own and how to classify these leads?</p>

                <div className="mt-2 divide-y divide-border/60">
                    <Row label="Assignee Users" error={form.errors.member_ids}
                        hint="Pick team members to assign the leads to. They are shared between them in turn.">
                        <ChipSelect
                            options={options.members}
                            selected={form.data.member_ids}
                            onToggle={(id) => toggleIn('member_ids', id)}
                            placeholder="Select assignee users"
                        />
                    </Row>

                    <Row label="Lead Stage" required error={form.errors.lead_stage_id}
                        hint="Set the lead stage the lead should start on.">
                        <select className={inputClass} value={form.data.lead_stage_id}
                            onChange={(e) => form.setData('lead_stage_id', Number(e.target.value) || '')}>
                            <option value="">Select lead stage</option>
                            {options.stages.map((s) => (
                                <option key={s.id} value={s.id}>{s.emoji} {s.name}</option>
                            ))}
                        </select>
                    </Row>

                    <Row label="Lead Group" error={form.errors.lead_group_id}
                        hint="Another way to classify your leads, e.g. to segregate them in reports.">
                        <select className={inputClass} value={form.data.lead_group_id}
                            onChange={(e) => form.setData('lead_group_id', Number(e.target.value) || '')}>
                            <option value="">Select lead group</option>
                            {options.groups.map((g) => <option key={g.id} value={g.id}>{g.name}</option>)}
                        </select>
                    </Row>

                    <Row label="Labels" hint="Select tags for the leads.">
                        <div className="flex flex-wrap gap-2 rounded-lg border border-input p-2.5">
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
                    </Row>
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

/**
 * A settings row: label on the left, control and its hint on the right.
 *
 * The stacked layout `Field` gives works for a modal, but these forms are wide
 * and a full-width input under a two-word label reads as a wall.
 */
function Row({
    label,
    required = false,
    hint,
    error,
    children,
}: {
    label: string;
    required?: boolean;
    hint?: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2 py-4 sm:grid-cols-[220px_minmax(0,1fr)] sm:gap-6">
            <label className="pt-2 text-sm font-medium">
                {required && <span style={{ color: 'var(--bad)' }}>* </span>}
                {label}
            </label>
            <div className="min-w-0">
                {children}
                {error ? (
                    <p className="mt-1.5 text-sm" style={{ color: 'var(--bad)' }}>{error}</p>
                ) : (
                    hint && <p className="mt-1.5 text-sm text-muted-foreground">{hint}</p>
                )}
            </div>
        </div>
    );
}

/**
 * Chip picker: selections show as removable chips in the field itself, and the
 * list marks what is already chosen. Reads far better than a tall checkbox
 * column once a team grows past a handful of people.
 */
function ChipSelect({
    options,
    selected,
    onToggle,
    placeholder,
}: {
    options: Named[];
    selected: number[];
    onToggle: (id: number) => void;
    placeholder: string;
}) {
    const [open, setOpen] = useState(false);
    const box = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) return;

        const onDown = (e: MouseEvent) => {
            if (box.current && !box.current.contains(e.target as Node)) setOpen(false);
        };
        document.addEventListener('mousedown', onDown);

        return () => document.removeEventListener('mousedown', onDown);
    }, [open]);

    const chosen = options.filter((o) => selected.includes(o.id));

    return (
        <div className="relative" ref={box}>
            <div
                role="button"
                tabIndex={0}
                onClick={() => setOpen((o) => !o)}
                onKeyDown={(e) => (e.key === 'Enter' || e.key === ' ') && setOpen((o) => !o)}
                className="flex min-h-11 cursor-pointer flex-wrap items-center gap-1.5 rounded-lg border border-input bg-card px-2.5 py-2 text-sm transition focus:border-primary"
            >
                {chosen.length === 0 && <span className="text-muted-foreground">{placeholder}</span>}

                {chosen.map((o) => (
                    <span
                        key={o.id}
                        className="inline-flex items-center gap-1 rounded-md bg-accent px-2 py-0.5 text-xs font-semibold text-accent-foreground"
                    >
                        {o.name}
                        <button
                            type="button"
                            aria-label={`Remove ${o.name}`}
                            onClick={(e) => { e.stopPropagation(); onToggle(o.id); }}
                            className="opacity-60 transition hover:opacity-100"
                        >
                            ×
                        </button>
                    </span>
                ))}

                <svg viewBox="0 0 24 24" className="ml-auto size-4 shrink-0 opacity-50" fill="none" stroke="currentColor" strokeWidth="2.2" aria-hidden>
                    <path d="m6 9 6 6 6-6" strokeLinecap="round" strokeLinejoin="round" />
                </svg>
            </div>

            {open && (
                <div className="absolute left-0 right-0 top-[calc(100%+4px)] z-50 max-h-56 overflow-y-auto rounded-lg border border-border bg-card p-1 shadow-xl">
                    {options.map((o) => {
                        const on = selected.includes(o.id);

                        return (
                            <button
                                key={o.id}
                                type="button"
                                onClick={() => onToggle(o.id)}
                                className={`flex w-full items-center justify-between rounded-md px-2.5 py-2 text-left text-sm transition hover:bg-muted ${
                                    on ? 'font-semibold text-primary' : ''
                                }`}
                            >
                                {o.name}
                                {on && (
                                    <svg viewBox="0 0 24 24" className="size-4" fill="none" stroke="currentColor" strokeWidth="2.5" aria-hidden>
                                        <path d="m5 13 4 4L19 7" strokeLinecap="round" strokeLinejoin="round" />
                                    </svg>
                                )}
                            </button>
                        );
                    })}

                    {options.length === 0 && (
                        <p className="px-2 py-6 text-center text-sm text-muted-foreground">Nobody to pick yet</p>
                    )}
                </div>
            )}
        </div>
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
                            <div className="mt-2 divide-y divide-border/60">
                                <Row label="Type" required error={err('todo_type')} hint={typeHint}>
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
                                </Row>

                                <Row label="Title" required error={err('todo_title')} hint={titleHint}>
                                    <input
                                        className={inputClass}
                                        placeholder="Enter title"
                                        value={d('todo_title')}
                                        onChange={(e) => set('todo_title', e.target.value)}
                                    />
                                </Row>

                                <Row
                                    label="Due Date"
                                    required
                                    error={err('todo_due_value') ?? err('todo_due_unit')}
                                    hint="Enter the date/time of when a task should be created."
                                >
                                    <DurationInput
                                        value={d('todo_due_value')}
                                        unit={d('todo_due_unit')}
                                        onValue={(v) => set('todo_due_value', v)}
                                        onUnit={(u) => set('todo_due_unit', u)}
                                        placeholder="30"
                                    />
                                </Row>
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
                            <div className="mt-2">
                                <Row
                                    label="Notification Time"
                                    required
                                    error={err('notify_value') ?? err('notify_unit')}
                                    hint="Enter the date/time of notification. Zero means straight away."
                                >
                                    <DurationInput
                                        value={d('notify_value')}
                                        unit={d('notify_unit')}
                                        onValue={(v) => set('notify_value', v)}
                                        onUnit={(u) => set('notify_unit', u)}
                                        placeholder="30"
                                    />
                                </Row>
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
