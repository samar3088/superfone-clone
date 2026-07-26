import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

import { Button, Field, Modal, inputClass } from '@/components/ui-kit';

interface Named {
    id: number;
    name: string;
    emoji?: string | null;
}

interface TeamOption {
    id: number;
    name: string;
    virtual_number: string | null;
}

export interface ContactOptions {
    teams: TeamOption[];
    sourceTypes: string[];
    todoTypes: string[];
    stages: Named[];
    groups: Named[];
}

/** "+919403890373, VARIETY VINTAGE" — the number only once one exists. */
function orgLabel(team: TeamOption): string {
    return team.virtual_number ? `${team.virtual_number}, ${team.name}` : team.name;
}

/**
 * Create Contact.
 *
 * Several phone numbers and email addresses, because people have them — and
 * because every one of them counts when we later ask "have we met this person
 * before?". The first of each is the primary; the rest sit alongside and match
 * just as well.
 *
 * A stage turns the contact into a lead as well. Without one it is only an
 * address book entry, which is a legitimate thing to want.
 */
export default function CreateContact({
    options,
    members,
    onClose,
}: {
    options: ContactOptions;
    members: { id: number; name: string }[];
    onClose: () => void;
}) {
    /*
     | The generic is spelled out because useForm would otherwise infer
     | create_task as the literal `false` from its initial value, and refuse to
     | be set true.
     */
    const form = useForm<{
        team_id: number | '';
        name: string;
        phones: string[];
        emails: string[];
        website: string;
        business_name: string;
        house_no: string;
        city: string;
        address_1: string;
        address_2: string;
        additional_info: string;
        source: string;
        source_type: string;
        deal_value: string;
        lead_group_id: number | '';
        campaign: string;
        lead_stage_id: number | '';
        assigned_to: number | '';
        create_task: boolean;
        task_note: string;
        task_type: string;
        task_due_at: string;
    }>({
        // Defaults to the only organisation, so a contact is never orphaned
        // just because nobody touched the field.
        team_id: (options.teams[0]?.id ?? '') as number | '',
        name: '',
        phones: [''],
        emails: [''],
        website: '',
        business_name: '',
        house_no: '',
        city: '',
        address_1: '',
        address_2: '',
        additional_info: '',
        source: '',
        source_type: '',
        deal_value: '',
        lead_group_id: '' as number | '',
        campaign: '',
        lead_stage_id: '' as number | '',
        assigned_to: '' as number | '',
        create_task: false,
        task_note: '',
        task_type: '',
        task_due_at: '',
    });

    const setList = (key: 'phones' | 'emails', i: number, value: string) =>
        form.setData(key, form.data[key].map((v, n) => (n === i ? value : v)));

    const addTo = (key: 'phones' | 'emails') => form.setData(key, [...form.data[key], '']);

    const removeFrom = (key: 'phones' | 'emails', i: number) =>
        form.setData(key, form.data[key].filter((_, n) => n !== i));

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/customers', { preserveScroll: true, onSuccess: onClose });
    };

    // A task hangs off a lead, and a lead only exists once a stage is chosen.
    const canCreateTask = form.data.lead_stage_id !== '';

    return (
        <Modal open onClose={onClose} title="Create Contact" wide>
            <form onSubmit={submit} className="space-y-6">
                <Field label="Organization" error={form.errors.team_id}>
                    <select
                        className={inputClass}
                        value={form.data.team_id}
                        onChange={(e) => form.setData('team_id', (Number(e.target.value) || '') as number | '')}
                    >
                        {options.teams.map((t) => (
                            <option key={t.id} value={t.id}>{orgLabel(t)}</option>
                        ))}
                    </select>
                </Field>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Name" required error={form.errors.name}>
                        <input
                            className={inputClass}
                            placeholder="Enter full name"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                        />
                    </Field>

                    <Field label="Business name" error={form.errors.business_name}>
                        <input
                            className={inputClass}
                            placeholder="Enter business name"
                            value={form.data.business_name}
                            onChange={(e) => form.setData('business_name', e.target.value)}
                        />
                    </Field>
                </div>

                <RepeatableList
                    legend="Phone"
                    required
                    values={form.data.phones}
                    error={form.errors.phones ?? (form.errors as Record<string, string>)['phones.0']}
                    placeholder="98765 43210"
                    prefix="+91"
                    onChange={(i, v) => setList('phones', i, v)}
                    onAdd={() => addTo('phones')}
                    onRemove={(i) => removeFrom('phones', i)}
                />

                <RepeatableList
                    legend="Email"
                    values={form.data.emails}
                    error={(form.errors as Record<string, string>)['emails.0']}
                    placeholder="name@example.com"
                    type="email"
                    onChange={(i, v) => setList('emails', i, v)}
                    onAdd={() => addTo('emails')}
                    onRemove={(i) => removeFrom('emails', i)}
                />

                <Field label="Website" error={form.errors.website}>
                    <input
                        className={inputClass}
                        placeholder="Enter website"
                        value={form.data.website}
                        onChange={(e) => form.setData('website', e.target.value)}
                    />
                </Field>

                <Section title="Address">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="House no" error={form.errors.house_no}>
                            <input className={inputClass} placeholder="Enter house no"
                                value={form.data.house_no}
                                onChange={(e) => form.setData('house_no', e.target.value)} />
                        </Field>
                        <Field label="City" error={form.errors.city}>
                            <input className={inputClass} placeholder="Enter city"
                                value={form.data.city}
                                onChange={(e) => form.setData('city', e.target.value)} />
                        </Field>
                    </div>

                    <Field label="Address 1" error={form.errors.address_1}>
                        <input className={inputClass} placeholder="Enter address 1"
                            value={form.data.address_1}
                            onChange={(e) => form.setData('address_1', e.target.value)} />
                    </Field>

                    <Field label="Address 2" error={form.errors.address_2}>
                        <input className={inputClass} placeholder="Enter address 2"
                            value={form.data.address_2}
                            onChange={(e) => form.setData('address_2', e.target.value)} />
                    </Field>
                </Section>

                {/*
                    Full width rather than a narrow column, so related pairs sit
                    side by side — source with its type, lead group with the
                    campaign it came from.
                */}
                <Section title="Lead tracking">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Source" error={form.errors.source}>
                            <input className={inputClass} placeholder="Enter source"
                                value={form.data.source}
                                onChange={(e) => form.setData('source', e.target.value)} />
                        </Field>

                        <Field label="Source type" error={form.errors.source_type}>
                            <select className={inputClass} value={form.data.source_type}
                                onChange={(e) => form.setData('source_type', e.target.value)}>
                                <option value="">Select source type</option>
                                {options.sourceTypes.map((t) => <option key={t} value={t}>{t}</option>)}
                            </select>
                        </Field>

                        <Field label="Lead group" error={form.errors.lead_group_id}>
                            <select className={inputClass} value={form.data.lead_group_id}
                                onChange={(e) => form.setData('lead_group_id', (Number(e.target.value) || '') as number | '')}>
                                <option value="">Select lead group</option>
                                {options.groups.map((g) => <option key={g.id} value={g.id}>{g.name}</option>)}
                            </select>
                        </Field>

                        <Field label="Campaign" error={form.errors.campaign}>
                            <input className={inputClass} placeholder="Enter campaign"
                                value={form.data.campaign}
                                onChange={(e) => form.setData('campaign', e.target.value)} />
                        </Field>

                        <Field label="Deal value" error={form.errors.deal_value}>
                            <input type="number" min={0} step="0.01" className={inputClass}
                                placeholder="Enter deal value"
                                value={form.data.deal_value}
                                onChange={(e) => form.setData('deal_value', e.target.value)} />
                        </Field>
                    </div>
                </Section>

                <Section title="Additional info">
                    <Field label="Additional info" error={form.errors.additional_info}>
                        <textarea rows={3} className={inputClass} placeholder="Enter additional info"
                            value={form.data.additional_info}
                            onChange={(e) => form.setData('additional_info', e.target.value)} />
                    </Field>
                </Section>

                <Section title="Assignment">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label="Lead stage"
                            error={form.errors.lead_stage_id}
                            hint="Choosing a stage also opens a lead for this contact. Leave it blank for an address book entry only."
                        >
                            <select className={inputClass} value={form.data.lead_stage_id}
                                onChange={(e) => form.setData('lead_stage_id', (Number(e.target.value) || '') as number | '')}>
                                <option value="">No lead — contact only</option>
                                {options.stages.map((s) => (
                                    <option key={s.id} value={s.id}>{s.emoji} {s.name}</option>
                                ))}
                            </select>
                        </Field>

                        <Field label="Assigned to" error={form.errors.assigned_to}>
                            <select className={inputClass} value={form.data.assigned_to}
                                onChange={(e) => form.setData('assigned_to', (Number(e.target.value) || '') as number | '')}>
                                <option value="">Select assignee</option>
                                {members.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}
                            </select>
                        </Field>
                    </div>
                </Section>

                <div className="rounded-xl border border-border p-4">
                    <label className={`flex items-center gap-2.5 ${canCreateTask ? '' : 'opacity-50'}`}>
                        <input
                            type="checkbox"
                            className="size-4 accent-primary"
                            disabled={!canCreateTask}
                            checked={form.data.create_task}
                            onChange={(e) => form.setData('create_task', e.target.checked)}
                        />
                        <span className="font-semibold">Create a task</span>
                    </label>

                    {!canCreateTask && (
                        <p className="mt-1.5 pl-7 text-sm text-muted-foreground">
                            Pick a lead stage first — a task needs a lead to hang on.
                        </p>
                    )}

                    {canCreateTask && form.data.create_task && (
                        <div className="mt-4 space-y-4 border-t border-border pt-4">
                            <Field label="Note" error={form.errors.task_note}>
                                <textarea rows={3} className={inputClass} placeholder="Enter note"
                                    value={form.data.task_note}
                                    onChange={(e) => form.setData('task_note', e.target.value)} />
                            </Field>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label="Type" required error={form.errors.task_type}>
                                    <select className={inputClass} value={form.data.task_type}
                                        onChange={(e) => form.setData('task_type', e.target.value)}>
                                        <option value="">Select type</option>
                                        {options.todoTypes.map((t) => <option key={t} value={t}>{t}</option>)}
                                    </select>
                                </Field>

                                <Field label="Due date" required error={form.errors.task_due_at}>
                                    <input type="datetime-local" className={inputClass}
                                        value={form.data.task_due_at}
                                        onChange={(e) => form.setData('task_due_at', e.target.value)} />
                                </Field>
                            </div>
                        </div>
                    )}
                </div>

                <div className="flex justify-end gap-3 border-t border-border pt-5">
                    <Button type="button" variant="ghost" onClick={onClose}>Cancel</Button>
                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? 'Saving…' : 'Save'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <div className="space-y-4">
            <p className="eyebrow border-b border-border pb-2">{title}</p>
            {children}
        </div>
    );
}

/** A list that grows: one row per value, with a + to add another. */
function RepeatableList({
    legend,
    values,
    onChange,
    onAdd,
    onRemove,
    placeholder,
    prefix,
    type = 'text',
    required = false,
    error,
}: {
    legend: string;
    values: string[];
    onChange: (i: number, v: string) => void;
    onAdd: () => void;
    onRemove: (i: number) => void;
    placeholder: string;
    prefix?: string;
    type?: string;
    required?: boolean;
    error?: string;
}) {
    return (
        <fieldset>
            <legend className="mb-1.5 text-sm font-medium">
                {legend} {required && <span style={{ color: 'var(--bad)' }}>*</span>}
            </legend>

            <div className="space-y-2">
                {values.map((value, i) => (
                    <div key={i} className="flex items-center gap-2">
                        {prefix && (
                            <span className="grid h-11 shrink-0 place-items-center rounded-lg border border-input bg-muted px-3 text-sm text-muted-foreground">
                                {prefix}
                            </span>
                        )}

                        <input
                            type={type}
                            className={inputClass}
                            placeholder={i === 0 ? placeholder : `${placeholder} (additional)`}
                            value={value}
                            onChange={(e) => onChange(i, e.target.value)}
                        />

                        {i === 0 ? (
                            <button
                                type="button"
                                onClick={onAdd}
                                aria-label={`Add another ${legend.toLowerCase()}`}
                                className="grid size-11 shrink-0 place-items-center rounded-lg border border-input text-lg text-primary transition hover:bg-muted"
                            >
                                +
                            </button>
                        ) : (
                            <button
                                type="button"
                                onClick={() => onRemove(i)}
                                aria-label={`Remove this ${legend.toLowerCase()}`}
                                className="grid size-11 shrink-0 place-items-center rounded-lg border border-input text-lg text-muted-foreground transition hover:bg-muted"
                            >
                                ×
                            </button>
                        )}
                    </div>
                ))}
            </div>

            {error && <p className="mt-1.5 text-sm" style={{ color: 'var(--bad)' }}>{error}</p>}

            {values.length === 1 && (
                <p className="mt-1.5 text-sm text-muted-foreground">
                    The first is the primary. Extras still count when checking for duplicates.
                </p>
            )}
        </fieldset>
    );
}
