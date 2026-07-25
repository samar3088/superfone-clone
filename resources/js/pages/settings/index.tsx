import { Head, router, useForm } from '@inertiajs/react';
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
    connected_account: string | null; status: string; created_at: string;
    members: Member[]; creator: { id: number; name: string } | null;
}

interface Props {
    tags: Tag[];
    leadStages: Stage[];
    leadGroups: Group[];
    customFields: CustomFieldRow[];
    fieldPriorities: Record<string, Priority[]>;
    integrations: Integration[];
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
            {tab === 'integrations' && <IntegrationsTab integrations={props.integrations} members={props.members} />}
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

function IntegrationsTab({ integrations, members }: { integrations: Integration[]; members: Member[] }) {
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<Integration | null>(null);

    return (
        <>
            <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
                <p className="max-w-2xl text-sm text-muted-foreground">
                    Connect a Facebook lead form and map it to the team members who should work those leads.
                    New enquiries are shared between them in turn.
                </p>
                <Button onClick={() => setCreating(true)}>＋ Create new integration</Button>
            </div>

            <div className="space-y-4">
                {integrations.map((it) => (
                    <div key={it.id} className="rounded-xl border border-border bg-card p-5">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div className="flex items-center gap-3">
                                <span className="grid size-10 place-items-center rounded-lg bg-[#1877f2] font-bold text-white">f</span>
                                <div>
                                    <p className="font-display text-base font-bold">{it.name}</p>
                                    <p className="text-xs text-muted-foreground">
                                        Created by {it.creator?.name ?? 'system'}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <Pill tone={it.status === 'active' ? 'good' : 'neutral'}>{it.status}</Pill>
                                <Button variant="ghost" className="px-2.5 py-1.5" onClick={() => setEditing(it)}>Edit</Button>
                                <Button variant="ghost" className="px-2.5 py-1.5"
                                    onClick={() => router.patch(`/settings/integrations/${it.id}/toggle`, {}, { preserveScroll: true })}>
                                    {it.status === 'active' ? 'Pause' : 'Resume'}
                                </Button>
                            </div>
                        </div>

                        <div className="mt-3 flex flex-wrap items-center gap-x-5 gap-y-1 text-sm text-muted-foreground">
                            <span>Page: <b className="text-foreground">{it.page_name}</b></span>
                            <span>Form: <b className="text-foreground">{it.form_name}</b></span>
                            <span>Account: <b className="text-foreground">{it.connected_account}</b></span>
                        </div>

                        <div className="mt-3 flex flex-wrap items-center gap-2">
                            <span className="eyebrow">Leads go to</span>
                            {it.members.map((m) => (
                                <span key={m.id} className="rounded-full bg-accent px-2.5 py-1 text-xs font-semibold text-accent-foreground">
                                    {m.name}
                                </span>
                            ))}
                            {it.members.length === 0 && (
                                <span className="text-sm" style={{ color: 'var(--warn)' }}>
                                    No members mapped — leads will arrive unassigned
                                </span>
                            )}
                        </div>
                    </div>
                ))}

                {integrations.length === 0 && (
                    <div className="rounded-xl border border-dashed border-border py-16 text-center">
                        <p className="font-display text-base font-semibold">No integrations yet</p>
                        <p className="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
                            Connect a Facebook lead form so campaign enquiries flow straight into your leads list.
                        </p>
                    </div>
                )}
            </div>

            {(creating || editing) && (
                <IntegrationWizard integration={editing ?? undefined} members={members}
                    onClose={() => { setCreating(false); setEditing(null); }} />
            )}
        </>
    );
}

function IntegrationWizard({ integration, members, onClose }: { integration?: Integration; members: Member[]; onClose: () => void }) {
    const isEdit = !!integration;
    const [step, setStep] = useState(isEdit ? 3 : 1);
    const [pages, setPages] = useState<Array<{ id: string; name: string }>>([]);
    const [forms, setForms] = useState<Array<{ id: string; name: string }>>([]);
    const [account, setAccount] = useState('');

    const form = useForm({
        name: integration?.name ?? '',
        provider: 'facebook',
        external_page_id: '',
        page_name: '',
        external_form_id: '',
        form_name: '',
        member_ids: integration?.members.map((m) => m.id) ?? ([] as number[]),
    });

    const loadPages = async () => {
        const res = await fetch('/settings/integrations/facebook/pages', { headers: { Accept: 'application/json' } });
        const data = await res.json();
        setAccount(data.account.name);
        setPages(data.pages);
        setStep(2);
    };

    const pickPage = async (page: { id: string; name: string }) => {
        form.setData((d) => ({ ...d, external_page_id: page.id, page_name: page.name }));
        const res = await fetch(`/settings/integrations/facebook/pages/${page.id}/forms`, { headers: { Accept: 'application/json' } });
        setForms(await res.json());
    };

    const pickForm = (f: { id: string; name: string }) => {
        form.setData((d) => ({ ...d, external_form_id: f.id, form_name: f.name, name: d.name || f.name }));
        setStep(3);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: onClose };
        isEdit ? form.patch(`/settings/integrations/${integration!.id}`, opts) : form.post('/settings/integrations', opts);
    };

    const toggleMember = (id: number) =>
        form.setData('member_ids',
            form.data.member_ids.includes(id)
                ? form.data.member_ids.filter((m) => m !== id)
                : [...form.data.member_ids, id]);

    return (
        <Modal open onClose={onClose}
            title={isEdit ? `Edit ${integration!.name}` : 'Connect a Facebook lead form'}
            description={isEdit ? 'Change the name or who receives these leads.' : undefined}>

            {step === 1 && (
                <div className="space-y-4">
                    <p className="text-sm text-muted-foreground">
                        We will read the pages and lead forms on your connected Facebook account.
                    </p>
                    <div className="flex justify-end gap-3">
                        <Button type="button" variant="ghost" onClick={onClose}>Cancel</Button>
                        <Button onClick={loadPages}>Continue</Button>
                    </div>
                </div>
            )}

            {step === 2 && (
                <div className="space-y-4">
                    <p className="text-sm">Connected as <b>{account}</b></p>
                    <div>
                        <span className="eyebrow">Select page</span>
                        <div className="mt-2 flex flex-wrap gap-2">
                            {pages.map((p) => (
                                <button key={p.id} onClick={() => pickPage(p)}
                                    className={`rounded-lg border px-3.5 py-2 text-sm font-medium transition ${
                                        form.data.external_page_id === p.id ? 'border-primary bg-accent text-accent-foreground' : 'border-border hover:bg-muted'
                                    }`}>
                                    {p.name}
                                </button>
                            ))}
                        </div>
                    </div>
                    {forms.length > 0 && (
                        <div>
                            <span className="eyebrow">Select form</span>
                            <div className="mt-2 divide-y divide-border overflow-hidden rounded-lg border border-border">
                                {forms.map((f) => (
                                    <div key={f.id} className="flex items-center justify-between gap-3 px-3.5 py-2.5">
                                        <span className="text-sm font-medium">{f.name}</span>
                                        <Button className="px-3 py-1.5" onClick={() => pickForm(f)}>Select</Button>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                    <div className="flex justify-end">
                        <Button type="button" variant="ghost" onClick={() => setStep(1)}>Back</Button>
                    </div>
                </div>
            )}

            {step === 3 && (
                <form onSubmit={submit} className="space-y-4">
                    {!isEdit && (
                        <div className="rounded-lg border border-border bg-muted/40 p-3 text-sm">
                            <b>{form.data.page_name}</b> · {form.data.form_name}
                        </div>
                    )}

                    <Field label="Integration name" required error={form.errors.name}>
                        <input className={inputClass} value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)} />
                    </Field>

                    <Field label="Assign leads to" required error={form.errors.member_ids}
                        hint="New leads are shared between the selected members in turn.">
                        <div className="max-h-52 space-y-1 overflow-y-auto rounded-lg border border-border p-2">
                            {members.map((m) => (
                                <label key={m.id} className="flex cursor-pointer items-center gap-2.5 rounded-md px-2 py-1.5 text-sm hover:bg-muted">
                                    <input type="checkbox" className="size-4 accent-primary"
                                        checked={form.data.member_ids.includes(m.id)} onChange={() => toggleMember(m.id)} />
                                    {m.name}
                                </label>
                            ))}
                        </div>
                    </Field>

                    <div className="flex justify-end gap-3">
                        <Button type="button" variant="ghost" onClick={onClose}>Cancel</Button>
                        <Button type="submit" disabled={form.processing}>{isEdit ? 'Save' : 'Connect'}</Button>
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
