import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

import { Button, Field, Modal, Pill, inputClass } from '@/components/ui-kit';
import ConsoleLayout from '@/layouts/console-layout';

interface Tag { id: number; name: string; color: string; emoji: string | null; is_hidden: boolean }
interface Stage { id: number; sequence: number; name: string; emoji: string | null; type: string; is_active: boolean; leads_count: number }
interface Group { id: number; name: string; type: string; is_active: boolean }
interface CustomFieldRow { id: number; label: string; key: string; field_type: string; is_required: boolean; is_active: boolean }
interface Priority { id: number; section: string; field_key: string; label: string; is_selected: boolean; sequence: number }
interface Member { id: number; name: string }
interface Integration {
    id: number; name: string; provider: string; page_name: string | null; form_name: string | null;
    connected_account: string | null; status: string; created_at: string; is_configured: boolean;
    members: Member[]; creator: { id: number; name: string } | null;
}
interface Provider {
    key: string; label: string; badge: string; color: string; available: boolean;
}

interface Props {
    tags: Tag[];
    leadStages: Stage[];
    leadGroups: Group[];
    customFields: CustomFieldRow[];
    fieldPriorities: Record<string, Priority[]>;
    integrations: Integration[];
    providers: Provider[];
    providerTabs: string[];
    members: Member[];
    stageTypes: string[];
    fieldTypes: string[];
}

const TOP_TABS = [
    { key: 'business', label: 'Business Management' },
    { key: 'crm', label: 'CRM Settings' },
    { key: 'integrations', label: 'Integrations' },
    { key: 'call', label: 'Call Settings', disabled: true },
    { key: 'automations', label: 'Automations', disabled: true },
    { key: 'webhooks', label: 'Webhooks', disabled: true },
    { key: 'api', label: 'API Keys', disabled: true },
];

export default function SettingsIndex(props: Props) {
    const [tab, setTab] = useState('business');

    return (
        <ConsoleLayout title="Settings" description="Business configuration, CRM structure and lead sources.">
            <Head title="Settings" />

            <div className="mb-6 flex flex-wrap gap-1 border-b border-border">
                {TOP_TABS.map((t) => (
                    <button
                        key={t.key}
                        disabled={t.disabled}
                        onClick={() => setTab(t.key)}
                        title={t.disabled ? 'Not part of the current build scope' : undefined}
                        className={`-mb-px border-b-2 px-4 py-2.5 text-sm font-semibold transition ${
                            tab === t.key
                                ? 'border-primary text-primary'
                                : t.disabled
                                  ? 'cursor-not-allowed border-transparent text-muted-foreground/40'
                                  : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        {t.label}
                    </button>
                ))}
            </div>

            {tab === 'business' && <TagsTab tags={props.tags} />}
            {tab === 'crm' && <CrmTab {...props} />}
            {tab === 'integrations' && (
                <IntegrationsTab
                    integrations={props.integrations}
                    providers={props.providers}
                    providerTabs={props.providerTabs}
                />
            )}
        </ConsoleLayout>
    );
}

/* ── Business Management → Tags ─────────────────────── */

const PALETTE = ['#0b5d51', '#2563eb', '#7c3aed', '#a855f7', '#be3a2b', '#c2410c',
    '#b4690e', '#0f7a52', '#0891b2', '#be185d', '#4338ca', '#6b7280'];

function TagsTab({ tags }: { tags: Tag[] }) {
    const [editing, setEditing] = useState<Tag | null>(null);
    const [creating, setCreating] = useState(false);
    const visible = tags.filter((t) => !t.is_hidden);
    const hidden = tags.filter((t) => t.is_hidden);

    return (
        <>
            <div className="rounded-xl border border-border bg-card">
                <div className="flex flex-wrap items-start justify-between gap-3 p-5">
                    <div>
                        <h2 className="eyebrow">Visible tags</h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Available for selection when tags are added to contacts
                        </p>
                    </div>
                    <Button onClick={() => setCreating(true)}>＋ Create</Button>
                </div>
                <TagTable rows={visible} onEdit={setEditing} />
            </div>

            {hidden.length > 0 && (
                <div className="mt-5 rounded-xl border border-border bg-card">
                    <div className="p-5">
                        <h2 className="eyebrow">Hidden tags</h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Not offered during selection. Contacts already tagged keep showing them.
                        </p>
                    </div>
                    <TagTable rows={hidden} onEdit={setEditing} />
                </div>
            )}

            {(creating || editing) && (
                <TagForm tag={editing ?? undefined} onClose={() => { setCreating(false); setEditing(null); }} />
            )}
        </>
    );
}

function TagTable({ rows, onEdit }: { rows: Tag[]; onEdit: (t: Tag) => void }) {
    return (
        <div className="overflow-x-auto border-t border-border">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b border-border">
                        <th className="px-5 py-3 text-left"><span className="eyebrow">Tag ID</span></th>
                        <th className="px-5 py-3 text-left"><span className="eyebrow">Tag</span></th>
                        <th className="px-5 py-3 text-right"><span className="eyebrow">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((t) => (
                        <tr key={t.id} className="border-b border-border/60 last:border-0 hover:bg-muted/50">
                            <td className="px-5 py-3 data text-muted-foreground">{t.id}</td>
                            <td className="px-5 py-3">
                                <span
                                    className="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-sm font-medium"
                                    style={{ background: `color-mix(in srgb, ${t.color} 14%, transparent)`, color: t.color }}
                                >
                                    {t.emoji} {t.name}
                                </span>
                            </td>
                            <td className="px-5 py-3">
                                <div className="flex justify-end gap-2">
                                    <Button variant="ghost" className="px-2.5 py-1.5" onClick={() => onEdit(t)}>Edit</Button>
                                    <Button
                                        variant="danger"
                                        className="px-2.5 py-1.5"
                                        onClick={() => confirm(`Delete tag "${t.name}"?`) && router.delete(`/settings/tags/${t.id}`, { preserveScroll: true })}
                                    >
                                        Delete
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    ))}
                    {rows.length === 0 && (
                        <tr><td colSpan={3} className="px-5 py-10 text-center text-muted-foreground">No tags yet.</td></tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}

function TagForm({ tag, onClose }: { tag?: Tag; onClose: () => void }) {
    const form = useForm({
        name: tag?.name ?? '',
        color: tag?.color ?? PALETTE[0],
        emoji: tag?.emoji ?? '',
        is_hidden: tag?.is_hidden ?? false,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: onClose };
        tag ? form.patch(`/settings/tags/${tag.id}`, opts) : form.post('/settings/tags', opts);
    };

    return (
        <Modal open onClose={onClose} title={tag ? 'Edit tag' : 'Create new tag'}>
            <form onSubmit={submit} className="space-y-4">
                <div className="grid gap-4 sm:grid-cols-[1fr_100px]">
                    <Field label="Tag name" required error={form.errors.name}>
                        <input className={inputClass} value={form.data.name} autoFocus
                            onChange={(e) => form.setData('name', e.target.value)} />
                    </Field>
                    <Field label="Emoji">
                        <input className={`${inputClass} text-center text-lg`} maxLength={4} value={form.data.emoji}
                            onChange={(e) => form.setData('emoji', e.target.value)} />
                    </Field>
                </div>

                <Field label="Colour" error={form.errors.color}>
                    <div className="flex flex-wrap gap-2.5">
                        {PALETTE.map((c) => (
                            <button key={c} type="button" onClick={() => form.setData('color', c)}
                                aria-label={c}
                                className={`size-9 rounded-full transition ${form.data.color === c ? 'ring-2 ring-foreground ring-offset-2 ring-offset-card' : ''}`}
                                style={{ background: c }} />
                        ))}
                    </div>
                </Field>

                <div className="flex items-center justify-between rounded-lg border border-border p-3.5">
                    <span className="text-sm font-semibold">Preview</span>
                    <span className="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-sm font-medium"
                        style={{ background: `color-mix(in srgb, ${form.data.color} 14%, transparent)`, color: form.data.color }}>
                        {form.data.emoji} {form.data.name || 'Tag name'}
                    </span>
                </div>

                <label className="flex items-center gap-2.5 text-sm font-medium">
                    <input type="checkbox" checked={form.data.is_hidden} className="size-4 accent-primary"
                        onChange={(e) => form.setData('is_hidden', e.target.checked)} />
                    Hide from selection
                </label>

                <div className="flex justify-end gap-3">
                    <Button type="button" variant="ghost" onClick={onClose}>Cancel</Button>
                    <Button type="submit" disabled={form.processing}>{tag ? 'Save' : 'Add'}</Button>
                </div>
            </form>
        </Modal>
    );
}

/* ── CRM Settings ───────────────────────────────────── */

const CRM_TABS = [
    { key: 'stage', label: 'Lead Stage' },
    { key: 'group', label: 'Lead Group' },
    { key: 'fields', label: 'Custom Fields' },
    { key: 'priority', label: 'Field Priority Order' },
];

function CrmTab({ leadStages, leadGroups, customFields, fieldPriorities, stageTypes, fieldTypes }: Props) {
    const [sub, setSub] = useState('stage');

    return (
        <>
            <div className="mb-5 flex flex-wrap gap-2">
                {CRM_TABS.map((t) => (
                    <button key={t.key} onClick={() => setSub(t.key)}
                        className={`rounded-lg border px-4 py-2 text-sm font-semibold transition ${
                            sub === t.key ? 'border-primary bg-accent text-accent-foreground' : 'border-border hover:bg-muted'
                        }`}>
                        {t.label}
                    </button>
                ))}
            </div>

            {sub === 'stage' && <StagesPanel stages={leadStages} stageTypes={stageTypes} />}
            {sub === 'group' && <GroupsPanel groups={leadGroups} />}
            {sub === 'fields' && <FieldsPanel fields={customFields} fieldTypes={fieldTypes} />}
            {sub === 'priority' && <PriorityPanel priorities={fieldPriorities} />}
        </>
    );
}

const TYPE_TONE: Record<string, 'good' | 'bad' | 'neutral'> = {
    FINAL_POSITIVE: 'good', FINAL_NEGATIVE: 'bad', INITIAL: 'neutral', NONE: 'neutral',
};

function StagesPanel({ stages, stageTypes }: { stages: Stage[]; stageTypes: string[] }) {
    const [editing, setEditing] = useState<Stage | null>(null);
    const [creating, setCreating] = useState(false);

    return (
        <>
            <div className="mb-4 flex justify-end">
                <Button onClick={() => setCreating(true)}>＋ Add custom lead stage</Button>
            </div>

            <div className="overflow-hidden rounded-xl border border-border bg-card">
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-border">
                                {['Sequence', 'ID', 'Lead Stage', 'Type', 'Leads Attached', 'Status', ''].map((h) => (
                                    <th key={h} className="px-4 py-3 text-left"><span className="eyebrow">{h}</span></th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {stages.map((s) => (
                                <tr key={s.id} className="border-b border-border/60 last:border-0 hover:bg-muted/50">
                                    <td className="px-4 py-3 data text-muted-foreground">{String(s.sequence).padStart(2, '0')}</td>
                                    <td className="px-4 py-3 data text-muted-foreground">{s.id}</td>
                                    <td className="px-4 py-3 font-medium">{s.emoji} {s.name}</td>
                                    <td className="px-4 py-3">
                                        <Pill tone={TYPE_TONE[s.type]}>{s.type.replace('_', ' ')}</Pill>
                                    </td>
                                    <td className="px-4 py-3 tabular">{s.leads_count}</td>
                                    <td className="px-4 py-3">
                                        <Toggle checked={s.is_active} onChange={() =>
                                            router.patch(`/settings/lead-stages/${s.id}`, {
                                                name: s.name, emoji: s.emoji, type: s.type, is_active: !s.is_active,
                                            }, { preserveScroll: true })} />
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Button variant="ghost" className="px-2.5 py-1.5" onClick={() => setEditing(s)}>Edit</Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {(creating || editing) && (
                <StageForm stage={editing ?? undefined} stageTypes={stageTypes}
                    onClose={() => { setCreating(false); setEditing(null); }} />
            )}
        </>
    );
}

function StageForm({ stage, stageTypes, onClose }: { stage?: Stage; stageTypes: string[]; onClose: () => void }) {
    const form = useForm({
        name: stage?.name ?? '',
        emoji: stage?.emoji ?? '',
        type: stage?.type ?? 'NONE',
        is_active: stage?.is_active ?? true,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: onClose };
        stage ? form.patch(`/settings/lead-stages/${stage.id}`, opts) : form.post('/settings/lead-stages', opts);
    };

    return (
        <Modal open onClose={onClose} title={stage ? 'Edit lead stage' : 'Add custom lead stage'}
            description="Stages describe where an enquiry has reached in your pipeline.">
            <form onSubmit={submit} className="space-y-4">
                <div className="grid gap-4 sm:grid-cols-[1fr_100px]">
                    <Field label="Stage name" required error={form.errors.name}>
                        <input className={inputClass} value={form.data.name} autoFocus
                            onChange={(e) => form.setData('name', e.target.value)} />
                    </Field>
                    <Field label="Emoji">
                        <input className={`${inputClass} text-center text-lg`} maxLength={4} value={form.data.emoji}
                            onChange={(e) => form.setData('emoji', e.target.value)} />
                    </Field>
                </div>
                <Field label="Type" required error={form.errors.type}
                    hint="INITIAL is where new leads land. FINAL types close the lead.">
                    <select className={inputClass} value={form.data.type}
                        onChange={(e) => form.setData('type', e.target.value)}>
                        {stageTypes.map((t) => <option key={t} value={t}>{t.replace('_', ' ')}</option>)}
                    </select>
                </Field>
                <div className="flex justify-end gap-3">
                    <Button type="button" variant="ghost" onClick={onClose}>Cancel</Button>
                    <Button type="submit" disabled={form.processing}>{stage ? 'Save' : 'Add'}</Button>
                </div>
            </form>
        </Modal>
    );
}

function GroupsPanel({ groups }: { groups: Group[] }) {
    const [creating, setCreating] = useState(false);
    const form = useForm({ name: '', type: 'DEFAULT' });

    return (
        <>
            <div className="mb-4 flex justify-end">
                <Button onClick={() => setCreating(true)}>＋ Add custom lead group</Button>
            </div>
            <div className="overflow-hidden rounded-xl border border-border bg-card">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-border">
                            {['ID', 'Lead Group', 'Type', 'Status'].map((h) => (
                                <th key={h} className="px-4 py-3 text-left"><span className="eyebrow">{h}</span></th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {groups.map((g) => (
                            <tr key={g.id} className="border-b border-border/60 last:border-0 hover:bg-muted/50">
                                <td className="px-4 py-3 data text-muted-foreground">{g.id}</td>
                                <td className="px-4 py-3 font-medium">{g.name}</td>
                                <td className="px-4 py-3 text-muted-foreground">{g.type}</td>
                                <td className="px-4 py-3">
                                    <Toggle checked={g.is_active} onChange={() =>
                                        router.patch(`/settings/lead-groups/${g.id}`,
                                            { name: g.name, type: g.type, is_active: !g.is_active },
                                            { preserveScroll: true })} />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {creating && (
                <Modal open onClose={() => setCreating(false)} title="Add custom lead group">
                    <form onSubmit={(e) => { e.preventDefault(); form.post('/settings/lead-groups', { preserveScroll: true, onSuccess: () => { setCreating(false); form.reset(); } }); }}
                        className="space-y-4">
                        <Field label="Group name" required error={form.errors.name}>
                            <input className={inputClass} value={form.data.name} autoFocus
                                onChange={(e) => form.setData('name', e.target.value)} />
                        </Field>
                        <Field label="Type" required>
                            <input className={inputClass} value={form.data.type}
                                onChange={(e) => form.setData('type', e.target.value.toUpperCase())} />
                        </Field>
                        <div className="flex justify-end gap-3">
                            <Button type="button" variant="ghost" onClick={() => setCreating(false)}>Cancel</Button>
                            <Button type="submit" disabled={form.processing}>Add</Button>
                        </div>
                    </form>
                </Modal>
            )}
        </>
    );
}

function FieldsPanel({ fields, fieldTypes }: { fields: CustomFieldRow[]; fieldTypes: string[] }) {
    const [creating, setCreating] = useState(false);
    // Typed explicitly so is_required widens to boolean, not the literal false.
    const form = useForm<{ label: string; field_type: string; is_required: boolean }>({
        label: '',
        field_type: 'text',
        is_required: false,
    });

    return (
        <>
            <div className="mb-4 flex justify-end">
                <Button onClick={() => setCreating(true)}>＋ Create custom field</Button>
            </div>

            {fields.length === 0 ? (
                <div className="rounded-xl border border-dashed border-border py-16 text-center">
                    <p className="font-display text-base font-semibold">No custom fields yet</p>
                    <p className="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
                        Add fields like &ldquo;GST number&rdquo; or &ldquo;Event date&rdquo; to capture exactly what your business needs on every lead.
                    </p>
                </div>
            ) : (
                <div className="overflow-hidden rounded-xl border border-border bg-card">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-border">
                                {['Label', 'Key', 'Type', 'Required', ''].map((h) => (
                                    <th key={h} className="px-4 py-3 text-left"><span className="eyebrow">{h}</span></th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {fields.map((f) => (
                                <tr key={f.id} className="border-b border-border/60 last:border-0 hover:bg-muted/50">
                                    <td className="px-4 py-3 font-medium">{f.label}</td>
                                    <td className="px-4 py-3 data text-muted-foreground">{f.key}</td>
                                    <td className="px-4 py-3 capitalize">{f.field_type}</td>
                                    <td className="px-4 py-3">{f.is_required ? 'Yes' : 'No'}</td>
                                    <td className="px-4 py-3 text-right">
                                        <Button variant="danger" className="px-2.5 py-1.5"
                                            onClick={() => confirm(`Delete "${f.label}"?`) && router.delete(`/settings/custom-fields/${f.id}`, { preserveScroll: true })}>
                                            Delete
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {creating && (
                <Modal open onClose={() => setCreating(false)} title="Create a custom field">
                    <form onSubmit={(e) => { e.preventDefault(); form.post('/settings/custom-fields', { preserveScroll: true, onSuccess: () => { setCreating(false); form.reset(); } }); }}
                        className="space-y-4">
                        <Field label="Label" required error={form.errors.label}>
                            <input className={inputClass} value={form.data.label} autoFocus
                                onChange={(e) => form.setData('label', e.target.value)} />
                        </Field>
                        <Field label="Field type" required>
                            <select className={`${inputClass} capitalize`} value={form.data.field_type}
                                onChange={(e) => form.setData('field_type', e.target.value)}>
                                {fieldTypes.map((t) => <option key={t} value={t}>{t}</option>)}
                            </select>
                        </Field>
                        <label className="flex items-center gap-2.5 text-sm font-medium">
                            <input type="checkbox" checked={form.data.is_required} className="size-4 accent-primary"
                                onChange={(e) => form.setData('is_required', e.target.checked)} />
                            Required
                        </label>
                        <div className="flex justify-end gap-3">
                            <Button type="button" variant="ghost" onClick={() => setCreating(false)}>Cancel</Button>
                            <Button type="submit" disabled={form.processing}>Create</Button>
                        </div>
                    </form>
                </Modal>
            )}
        </>
    );
}

function PriorityPanel({ priorities }: { priorities: Record<string, Priority[]> }) {
    const [rows, setRows] = useState<Priority[]>(Object.values(priorities).flat());
    const [saved, setSaved] = useState(false);

    const toggle = (id: number) =>
        setRows((rs) => rs.map((r) => (r.id === id ? { ...r, is_selected: !r.is_selected } : r)));

    const save = () => {
        router.put('/settings/field-priority', {
            fields: rows.map((r, i) => ({ id: r.id, is_selected: r.is_selected, sequence: i })),
        }, { preserveScroll: true, onSuccess: () => { setSaved(true); setTimeout(() => setSaved(false), 2000); } });
    };

    const sections: Array<[string, string]> = [
        ['lead_tracking', 'Lead tracking fields'],
        ['additional', 'Additional fields'],
    ];

    return (
        <>
            <div className="mb-4 flex items-center justify-end gap-3">
                {saved && <span className="text-sm font-medium" style={{ color: 'var(--good)' }}>Saved</span>}
                <Button onClick={save}>Set priority fields</Button>
            </div>
            <div className="grid gap-5 lg:grid-cols-2">
                {sections.map(([key, title]) => (
                    <div key={key} className="overflow-hidden rounded-xl border border-border bg-card">
                        <h3 className="border-b border-border px-4 py-3 font-display font-bold">{title}</h3>
                        <table className="w-full text-sm">
                            <tbody>
                                {rows.filter((r) => r.section === key).map((r) => (
                                    <tr key={r.id} className="border-b border-border/60 last:border-0">
                                        <td className="w-12 px-4 py-3">
                                            <input type="checkbox" checked={r.is_selected} className="size-4 accent-primary"
                                                aria-label={`Select ${r.label}`} onChange={() => toggle(r.id)} />
                                        </td>
                                        <td className="px-2 py-3 font-medium">{r.label}</td>
                                        <td className="px-4 py-3 text-right">
                                            <Pill tone="neutral">Static</Pill>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ))}
            </div>
        </>
    );
}

/* ── Integrations ───────────────────────────────────── */

function ProviderBadge({ provider, providers, size = 'size-9 text-sm' }: { provider: string; providers: Provider[]; size?: string }) {
    const p = providers.find((x) => x.key === provider);

    return (
        <span
            className={`grid ${size} flex-shrink-0 place-items-center rounded-full font-bold text-white`}
            style={{ background: p?.color ?? '#9ca3af' }}
            aria-hidden
        >
            {p?.badge ?? '?'}
        </span>
    );
}

function IntegrationsTab({
    integrations,
    providers,
    providerTabs,
}: {
    integrations: Integration[];
    providers: Provider[];
    providerTabs: string[];
}) {
    const [filter, setFilter] = useState('');
    const [wizard, setWizard] = useState(false);

    const visible = filter ? integrations.filter((i) => i.provider === filter) : integrations;
    const tabs = [{ key: '', label: 'All' }, ...providerTabs.map((k) => providers.find((p) => p.key === k)!)
        .filter(Boolean)
        .map((p) => ({ key: p.key, label: p.label }))];

    return (
        <>
            <div className="rounded-xl border border-border bg-muted/40 p-5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 className="font-display text-xl font-bold">Integrations</h2>
                        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                            Connect and sync your leads from different platforms in realtime. Stay organised and never miss a lead.
                        </p>
                    </div>
                    <Button onClick={() => setWizard(true)}>Create new Integration</Button>
                </div>
            </div>

            {/* Provider filter tabs */}
            <div className="mt-4 flex flex-wrap gap-1 border-b border-border">
                {tabs.map((t) => (
                    <button
                        key={t.key || 'all'}
                        onClick={() => setFilter(t.key)}
                        className={`-mb-px flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-semibold transition ${
                            filter === t.key
                                ? 'border-primary text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        {t.key && <ProviderBadge provider={t.key} providers={providers} size="size-5 text-[10px]" />}
                        {t.label}
                    </button>
                ))}
            </div>

            <div className="mt-4 space-y-4">
                {visible.map((it) => (
                    <Link
                        key={it.id}
                        href={`/settings/integrations/${it.id}`}
                        className="block rounded-xl border border-border bg-card p-5 transition hover:border-primary/40"
                    >
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div className="flex items-center gap-3">
                                <ProviderBadge provider={it.provider} providers={providers} size="size-10 text-base" />
                                <div>
                                    <p className="font-display text-base font-bold">{it.name}</p>
                                    <p className="text-xs text-muted-foreground">
                                        Created by {it.creator?.name ?? 'system'}
                                    </p>
                                </div>
                            </div>
                            <Pill tone={it.status === 'active' ? 'good' : 'neutral'}>{it.status.toUpperCase()}</Pill>
                        </div>

                        <div className="mt-3 flex flex-wrap items-center gap-2">
                            <Pill tone={it.is_configured ? 'good' : 'warn'}>
                                {it.is_configured ? '✓ Complete' : 'Needs setup'}
                            </Pill>
                            {it.page_name && (
                                <span className="text-sm text-muted-foreground">
                                    Page: <b className="text-foreground">{it.page_name}</b>
                                </span>
                            )}
                            {it.form_name && (
                                <span className="text-sm text-muted-foreground">
                                    Form: <b className="text-foreground">{it.form_name}</b>
                                </span>
                            )}
                        </div>

                        {it.connected_account && (
                            <p className="mt-2 flex items-center gap-2 text-sm font-medium text-primary">
                                <ProviderBadge provider={it.provider} providers={providers} size="size-4 text-[9px]" />
                                {it.connected_account}
                            </p>
                        )}
                    </Link>
                ))}

                {visible.length === 0 && (
                    <div className="rounded-xl border border-dashed border-border py-16 text-center">
                        <p className="font-display text-base font-semibold">No integrations here yet</p>
                        <p className="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
                            Connect a lead form so campaign enquiries flow straight into your leads list.
                        </p>
                    </div>
                )}
            </div>

            {wizard && <CreateIntegrationWizard providers={providers} onClose={() => setWizard(false)} />}
        </>
    );
}

/**
 * Create flow: pick a provider, name the integration, then choose the page
 * and lead form. Routing rules are configured afterwards on the integration.
 */
function CreateIntegrationWizard({ providers, onClose }: { providers: Provider[]; onClose: () => void }) {
    const [provider, setProvider] = useState<Provider | null>(null);
    const [step, setStep] = useState<1 | 2>(1);
    const [account, setAccount] = useState('');
    const [pages, setPages] = useState<Array<{ id: string; name: string; description: string | null }>>([]);
    const [forms, setForms] = useState<Array<{ id: string; name: string }>>([]);
    const [loadingForms, setLoadingForms] = useState(false);

    const form = useForm({
        name: '',
        provider: '',
        external_page_id: '',
        external_form_id: '',
        form_name: '',
    });

    const choose = async (p: Provider) => {
        if (! p.available) return;
        setProvider(p);
        form.setData('provider', p.key);

        const res = await fetch('/settings/integrations/facebook/pages', { headers: { Accept: 'application/json' } });
        const data = await res.json();
        setAccount(data.account.name);
        setPages(data.pages);
    };

    const pickPage = async (page: { id: string; name: string }) => {
        form.setData('external_page_id', page.id);
        setForms([]);
        setLoadingForms(true);
        const res = await fetch(`/settings/integrations/facebook/pages/${page.id}/forms`, {
            headers: { Accept: 'application/json' },
        });
        setForms(await res.json());
        setLoadingForms(false);
    };

    const save = (e: FormEvent) => {
        e.preventDefault();
        form.post('/settings/integrations');
    };

    /* Provider picker */
    if (! provider) {
        return (
            <Modal open onClose={onClose} title="Create a new integration"
                description="Pick a provider to start connecting your leads.">
                <p className="eyebrow mb-3">Select provider</p>
                <div className="flex flex-wrap gap-2.5">
                    {providers.map((p) => (
                        <button
                            key={p.key}
                            onClick={() => choose(p)}
                            disabled={! p.available}
                            title={p.available ? undefined : 'Coming soon'}
                            className={`flex items-center gap-2.5 rounded-full border px-4 py-2 text-sm font-semibold transition ${
                                p.available
                                    ? 'border-border hover:border-primary hover:bg-accent'
                                    : 'cursor-not-allowed border-border/60 opacity-40'
                            }`}
                        >
                            <ProviderBadge provider={p.key} providers={providers} size="size-6 text-[11px]" />
                            {p.label}
                        </button>
                    ))}
                </div>
                <p className="mt-4 text-xs text-muted-foreground">
                    Facebook is connected. The remaining providers become available as each one is enabled.
                </p>
            </Modal>
        );
    }

    /* Facebook wizard */
    return (
        <Modal open onClose={onClose} title={provider.label}>
            <div className="-mt-3 mb-5 flex flex-wrap items-center justify-between gap-3">
                <button onClick={() => setProvider(null)} className="text-sm font-medium text-muted-foreground hover:text-foreground">
                    ← Change provider
                </button>
                <div className="flex items-center gap-3">
                    <span className="text-sm font-semibold text-primary">{account || '…'}</span>
                    <Button
                        variant="ghost"
                        className="px-3 py-1.5"
                        onClick={() => alert('Reconnecting needs the Meta app credentials — on the client checklist.')}
                    >
                        Reconnect
                    </Button>
                </div>
            </div>

            {/* Steps */}
            <div className="mb-6 flex items-center gap-3 text-sm">
                <span className="grid size-6 place-items-center rounded-full bg-primary text-xs font-bold text-primary-foreground">
                    {step === 2 ? '✓' : '1'}
                </span>
                <span className={step === 1 ? 'font-semibold' : 'text-muted-foreground'}>Enter name</span>
                <span className="h-px w-8 bg-border" />
                <span className={`grid size-6 place-items-center rounded-full text-xs font-bold ${
                    step === 2 ? 'bg-primary text-primary-foreground' : 'border border-border text-muted-foreground'
                }`}>
                    2
                </span>
                <span className={step === 2 ? 'font-semibold' : 'text-muted-foreground'}>Select page and form</span>
            </div>

            {step === 1 && (
                <div className="space-y-4">
                    <Field label="Name" required error={form.errors.name}>
                        <input
                            className={inputClass}
                            autoFocus
                            placeholder="Enter name for the integration"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                        />
                    </Field>
                    <Button
                        className="w-full py-3"
                        disabled={form.data.name.trim().length < 2}
                        onClick={() => setStep(2)}
                    >
                        Next
                    </Button>
                </div>
            )}

            {step === 2 && (
                <form onSubmit={save} className="space-y-5">
                    <div>
                        <p className="eyebrow mb-2">Select page</p>
                        <div className="grid gap-2.5 sm:grid-cols-2">
                            {pages.map((p) => (
                                <button
                                    key={p.id}
                                    type="button"
                                    onClick={() => pickPage(p)}
                                    className={`rounded-xl border-2 p-3.5 text-left transition ${
                                        form.data.external_page_id === p.id
                                            ? 'border-primary bg-accent'
                                            : 'border-border hover:border-primary/40'
                                    }`}
                                >
                                    <span className="block text-sm font-semibold">{p.name}</span>
                                </button>
                            ))}
                        </div>
                        {form.errors.external_page_id && (
                            <p className="mt-1.5 text-xs font-medium" style={{ color: 'var(--bad)' }}>
                                {form.errors.external_page_id}
                            </p>
                        )}
                    </div>

                    {(forms.length > 0 || loadingForms) && (
                        <div>
                            <p className="eyebrow mb-2">Select form</p>
                            <div className="max-h-64 overflow-y-auto rounded-xl border border-border">
                                <table className="w-full text-sm">
                                    <thead className="sticky top-0 bg-muted">
                                        <tr>
                                            <th className="px-4 py-2.5 text-left"><span className="eyebrow">Name</span></th>
                                            <th className="px-4 py-2.5 text-right"><span className="eyebrow">Actions</span></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {loadingForms && (
                                            <tr><td colSpan={2} className="px-4 py-6 text-center text-muted-foreground">Loading forms…</td></tr>
                                        )}
                                        {forms.map((f) => {
                                            const selected = form.data.external_form_id === f.id;
                                            return (
                                                <tr key={f.id} className="border-t border-border/60">
                                                    <td className="px-4 py-2.5 font-medium">{f.name}</td>
                                                    <td className="px-4 py-2.5 text-right">
                                                        <button
                                                            type="button"
                                                            onClick={() => form.setData((d) => ({
                                                                ...d, external_form_id: f.id, form_name: f.name,
                                                            }))}
                                                            className="rounded-lg px-4 py-1.5 text-sm font-semibold text-white transition"
                                                            style={{ background: selected ? 'var(--good)' : 'var(--primary)' }}
                                                        >
                                                            {selected ? 'Selected' : 'Select'}
                                                        </button>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                            {form.errors.external_form_id && (
                                <p className="mt-1.5 text-xs font-medium" style={{ color: 'var(--bad)' }}>
                                    {form.errors.external_form_id}
                                </p>
                            )}
                        </div>
                    )}

                    <div className="flex justify-between gap-3">
                        <Button type="button" variant="ghost" onClick={() => setStep(1)}>Back</Button>
                        <Button type="submit" disabled={! form.data.external_form_id || form.processing}>
                            {form.processing ? 'Saving…' : 'Save & Next'}
                        </Button>
                    </div>
                </form>
            )}
        </Modal>
    );
}

/* ── shared ── */
function Toggle({ checked, onChange }: { checked: boolean; onChange: () => void }) {
    return (
        <button type="button" role="switch" aria-checked={checked} onClick={onChange}
            className={`relative h-6 w-11 rounded-full transition ${checked ? 'bg-[var(--good)]' : 'bg-input'}`}>
            <span className={`absolute top-0.5 size-5 rounded-full bg-white shadow transition-all ${checked ? 'left-[22px]' : 'left-0.5'}`} />
        </button>
    );
}
