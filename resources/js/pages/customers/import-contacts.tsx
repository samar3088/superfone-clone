import { router } from '@inertiajs/react';
import { useState } from 'react';

import { Spinner } from '@/components/data-table';
import { Button, Field, Modal, inputClass } from '@/components/ui-kit';
import { ContactOptions } from './create-contact';

/** What step one learned about the file. */
interface Checked {
    token: string | null;
    valid: number;
    rows: number;
    errors: string[];
}

/** What step two did with it. */
interface Summary {
    created: number;
    matched: number;
    skipped: number;
    errors: string[];
}

const STEPS = ['Upload file', 'Contact settings', 'Complete'];

/**
 * Bulk contact import, in three steps.
 *
 * The split is the point: the file is checked and parked before anything is
 * written, so a bad file costs nothing, and the choices about what to do with
 * the rows are made once those rows are known to be good. Nothing lands in the
 * book until Submit on step two.
 */
export default function ImportContacts({
    options,
    members,
    onClose,
}: {
    options: ContactOptions;
    members: { id: number; name: string }[];
    onClose: () => void;
}) {
    const [step, setStep] = useState(0);

    // Step one
    const [teamId, setTeamId] = useState<string>(String(options.teams[0]?.id ?? ''));
    const [file, setFile] = useState<File | null>(null);
    const [skipPhoneCheck, setSkipPhoneCheck] = useState(false);
    const [updateExisting, setUpdateExisting] = useState(false);
    const [checking, setChecking] = useState(false);
    const [checked, setChecked] = useState<Checked | null>(null);
    const [uploadError, setUploadError] = useState<string | null>(null);

    // Step two
    const [leadGroupId, setLeadGroupId] = useState('');
    const [leadStageId, setLeadStageId] = useState('');
    const [tags, setTags] = useState<number[]>([]);
    const [assignTo, setAssignTo] = useState<number[]>([]);
    const [createTask, setCreateTask] = useState(false);
    const [taskTitle, setTaskTitle] = useState('');
    const [taskType, setTaskType] = useState(options.todoTypes[0] ?? '');
    const [taskDueAt, setTaskDueAt] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    // Step three
    const [summary, setSummary] = useState<Summary | null>(null);

    async function check(chosen: File) {
        setChecking(true);
        setUploadError(null);
        setChecked(null);

        const body = new FormData();
        body.append('file', chosen);
        body.append('skip_phone_check', skipPhoneCheck ? '1' : '0');

        if (teamId) body.append('team_id', teamId);

        try {
            const response = await fetch('/customers/import/check', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    // Laravel's CSRF guard reads this header; the cookie is set
                    // by the framework on every page load.
                    'X-XSRF-TOKEN': decodeURIComponent(
                        document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
                    ),
                },
                body,
            });

            const data = await response.json();

            if (!response.ok) {
                setUploadError(data.message ?? 'That file could not be read.');

                return;
            }

            setChecked(data);
        } catch {
            setUploadError('The upload did not go through. Try again.');
        } finally {
            setChecking(false);
        }
    }

    function submit() {
        if (!checked?.token) return;

        setSubmitting(true);
        setErrors({});

        router.post(
            '/customers/import',
            {
                token: checked.token,
                team_id: teamId || undefined,
                lead_group_id: leadGroupId || undefined,
                lead_stage_id: leadStageId || undefined,
                tags,
                assign_to: assignTo,
                update_existing: updateExisting,
                skip_phone_check: skipPhoneCheck,
                create_task: createTask,
                task_type: createTask ? taskType : undefined,
                task_title: createTask ? taskTitle : undefined,
                task_due_at: createTask ? taskDueAt : undefined,
            },
            {
                preserveScroll: true,
                onError: setErrors,
                onSuccess: (page) => {
                    const flash = (page.props as { flash?: { import?: Summary } }).flash;

                    setSummary(flash?.import ?? null);
                    setStep(2);
                },
                onFinish: () => setSubmitting(false),
            },
        );
    }

    return (
        <Modal open onClose={onClose} title="Import Contacts" wide>
            <ol className="mb-6 flex items-center gap-2">
                {STEPS.map((label, i) => (
                    <li key={label} className="flex flex-1 items-center gap-2">
                        <span
                            className={`size-2.5 shrink-0 rounded-full transition ${
                                i <= step ? 'bg-primary' : 'bg-border'
                            }`}
                        />
                        <span
                            className={`whitespace-nowrap text-sm ${
                                i === step ? 'font-semibold' : 'text-muted-foreground'
                            }`}
                        >
                            {label}
                        </span>
                        {i < STEPS.length - 1 && (
                            <span className={`h-0.5 flex-1 rounded ${i < step ? 'bg-primary' : 'bg-border'}`} />
                        )}
                    </li>
                ))}
            </ol>

            {step === 0 && (
                <div className="space-y-4">
                    <Field label="Organisation">
                        <select
                            value={teamId}
                            onChange={(e) => setTeamId(e.target.value)}
                            className={inputClass}
                        >
                            {options.teams.map((t) => (
                                <option key={t.id} value={t.id}>
                                    {t.virtual_number ? `${t.virtual_number}, ` : ''}
                                    {t.name}
                                </option>
                            ))}
                        </select>
                    </Field>

                    <label className="grid cursor-pointer place-items-center gap-2 rounded-xl border-2 border-dashed border-border px-6 py-10 text-center transition hover:border-primary/60 hover:bg-muted/40">
                        <svg viewBox="0 0 24 24" className="size-8 text-primary" fill="none" stroke="currentColor" strokeWidth="1.75" aria-hidden>
                            <path d="M4 14v4a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-4" strokeLinecap="round" />
                            <path d="M12 16V4m0 0 4 4m-4-4-4 4" strokeLinecap="round" strokeLinejoin="round" />
                        </svg>
                        <span className="font-semibold">{file ? file.name : 'Upload CSV file'}</span>
                        {checking && (
                            <span className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Spinner className="size-3.5" /> Checking…
                            </span>
                        )}
                        <input
                            type="file"
                            accept=".csv,text/csv"
                            className="sr-only"
                            onChange={(e) => {
                                const chosen = e.target.files?.[0] ?? null;
                                setFile(chosen);
                                if (chosen) check(chosen);
                            }}
                        />
                    </label>

                    <Check
                        checked={skipPhoneCheck}
                        onChange={(v) => {
                            setSkipPhoneCheck(v);
                            // The answer depends on this, so a file already
                            // checked has to be checked again.
                            if (file) check(file);
                        }}
                    >
                        Skip phone number format checking
                    </Check>

                    {uploadError && (
                        <p className="text-sm" style={{ color: 'var(--bad)' }}>{uploadError}</p>
                    )}

                    {checked && (
                        <div className="space-y-3">
                            {checked.valid > 0 ? (
                                <div
                                    className="rounded-lg px-3 py-2.5 text-sm"
                                    style={{ background: 'var(--good-soft)', color: 'var(--good)' }}
                                >
                                    <p className="font-semibold">
                                        {checked.valid} contact{checked.valid === 1 ? '' : 's'} validated
                                    </p>
                                    <p className="mt-0.5 opacity-90">
                                        A number already on file is matched to that contact rather
                                        than added twice.
                                    </p>
                                </div>
                            ) : (
                                <p className="rounded-lg px-3 py-2.5 text-sm" style={{ background: 'var(--bad-soft)', color: 'var(--bad)' }}>
                                    Nothing in that file can be imported yet.
                                </p>
                            )}

                            {checked.errors.length > 0 && (
                                <div className="max-h-36 overflow-y-auto rounded-lg border border-border p-3 text-sm">
                                    <p className="mb-1.5 font-semibold" style={{ color: 'var(--bad)' }}>
                                        {checked.errors.length} row
                                        {checked.errors.length === 1 ? '' : 's'} will be skipped
                                    </p>
                                    <ul className="space-y-1 text-muted-foreground">
                                        {checked.errors.map((e, i) => <li key={i}>{e}</li>)}
                                    </ul>
                                </div>
                            )}

                            <Check checked={updateExisting} onChange={setUpdateExisting}>
                                Update existing contact if found
                            </Check>
                        </div>
                    )}

                    <details className="rounded-lg border border-border p-3 text-sm">
                        <summary className="cursor-pointer font-semibold">Prepare your file for importing</summary>
                        <ul className="mt-2 list-disc space-y-1 pl-5 text-muted-foreground">
                            <li>Download the template and add your contacts to it.</li>
                            <li>Every contact needs a name and a phone number.</li>
                            <li>Save as CSV — an .xlsx file is not read directly.</li>
                            <li>Up to 2,000 rows at a time.</li>
                            <li>For date fields use YYYY-MM-DD, for example 1998-05-25.</li>
                        </ul>
                    </details>

                    <div className="flex items-center justify-between gap-3 border-t border-border pt-4">
                        <a
                            href="/customers/import/sample"
                            className="inline-flex h-9 items-center gap-1.5 rounded-lg px-3 text-sm font-semibold transition hover:opacity-85"
                            style={{ background: 'var(--info-soft)', color: 'var(--info)' }}
                        >
                            <svg viewBox="0 0 24 24" className="size-4" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
                                <path d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" strokeLinecap="round" strokeLinejoin="round" />
                            </svg>
                            Download template file
                        </a>

                        <div className="flex gap-2">
                            <Button type="button" variant="ghost" onClick={onClose}>Cancel</Button>
                            <Button
                                type="button"
                                disabled={!checked?.token || checked.valid === 0}
                                onClick={() => setStep(1)}
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                </div>
            )}

            {step === 1 && (
                <div className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Lead Group">
                            <select value={leadGroupId} onChange={(e) => setLeadGroupId(e.target.value)} className={inputClass}>
                                <option value="">Select Lead Group</option>
                                {options.groups.map((g) => (
                                    <option key={g.id} value={g.id}>{g.name}</option>
                                ))}
                            </select>
                        </Field>

                        <Field label="Lead Stage" error={errors.lead_stage_id}>
                            <select value={leadStageId} onChange={(e) => setLeadStageId(e.target.value)} className={inputClass}>
                                <option value="">Select Lead Stage</option>
                                {options.stages.map((s) => (
                                    <option key={s.id} value={s.id}>{s.emoji} {s.name}</option>
                                ))}
                            </select>
                        </Field>
                    </div>

                    <p className="text-xs text-muted-foreground">
                        A lead is only created when a stage is chosen. Without one the rows are
                        added as contacts and nothing else.
                    </p>

                    <Field label="Tags">
                        <TickList
                            empty="No tags yet"
                            options={options.tags.map((t) => ({
                                id: t.id,
                                label: `${t.emoji ?? ''} ${t.name}`.trim(),
                            }))}
                            selected={tags}
                            onToggle={(id) => setTags((s) => s.includes(id) ? s.filter((x) => x !== id) : [...s, id])}
                        />
                    </Field>

                    <Field label="Assign To">
                        <TickList
                            empty="No members yet"
                            options={members.map((m) => ({ id: m.id, label: m.name }))}
                            selected={assignTo}
                            onToggle={(id) => setAssignTo((s) => s.includes(id) ? s.filter((x) => x !== id) : [...s, id])}
                        />
                        <p className="mt-1 text-xs text-muted-foreground">
                            Contacts are shared out evenly when more than one is picked.
                        </p>
                    </Field>

                    <div className="rounded-lg border border-border p-3">
                        <Check checked={createTask} onChange={setCreateTask}>
                            Create a to-do against each lead
                        </Check>

                        {createTask && (
                            <div className="mt-3 grid gap-3 sm:grid-cols-3">
                                <Field label="Title">
                                    <input
                                        value={taskTitle}
                                        onChange={(e) => setTaskTitle(e.target.value)}
                                        placeholder="Enter title"
                                        className={inputClass}
                                    />
                                </Field>
                                <Field label="Type" required error={errors.task_type}>
                                    <select value={taskType} onChange={(e) => setTaskType(e.target.value)} className={inputClass}>
                                        {options.todoTypes.map((t) => (
                                            <option key={t} value={t}>{t}</option>
                                        ))}
                                    </select>
                                </Field>
                                <Field label="Due Date" required error={errors.task_due_at}>
                                    <input
                                        type="datetime-local"
                                        value={taskDueAt}
                                        onChange={(e) => setTaskDueAt(e.target.value)}
                                        className={inputClass}
                                    />
                                </Field>
                            </div>
                        )}
                    </div>

                    <div className="flex justify-end gap-2 border-t border-border pt-4">
                        <Button type="button" variant="ghost" onClick={() => setStep(0)}>Back</Button>
                        <Button type="button" onClick={submit} disabled={submitting}>
                            {submitting ? 'Importing…' : 'Submit'}
                        </Button>
                    </div>
                </div>
            )}

            {step === 2 && (
                <div className="space-y-4">
                    <div className="grid gap-3 sm:grid-cols-3">
                        <Tally label="Added" value={summary?.created ?? 0} tone="var(--good)" />
                        <Tally label="Already on file" value={summary?.matched ?? 0} tone="var(--info)" />
                        <Tally label="Skipped" value={summary?.skipped ?? 0} tone="var(--bad)" />
                    </div>

                    {(summary?.errors.length ?? 0) > 0 && (
                        <div className="max-h-44 overflow-y-auto rounded-lg border border-border p-3 text-sm">
                            <p className="mb-1.5 font-semibold" style={{ color: 'var(--bad)' }}>
                                Rows that were not imported
                            </p>
                            <ul className="space-y-1 text-muted-foreground">
                                {summary!.errors.map((e, i) => <li key={i}>{e}</li>)}
                            </ul>
                        </div>
                    )}

                    <div className="flex justify-end border-t border-border pt-4">
                        <Button type="button" onClick={onClose}>Done</Button>
                    </div>
                </div>
            )}
        </Modal>
    );
}

function Check({
    checked,
    onChange,
    children,
}: {
    checked: boolean;
    onChange: (v: boolean) => void;
    children: React.ReactNode;
}) {
    return (
        <label className="flex cursor-pointer items-center gap-2.5 text-sm">
            <input
                type="checkbox"
                className="size-4 accent-primary"
                checked={checked}
                onChange={(e) => onChange(e.target.checked)}
            />
            {children}
        </label>
    );
}

/** A short scrolling tick-list, for the two multi-choice fields on step two. */
function TickList({
    options,
    selected,
    onToggle,
    empty,
}: {
    options: { id: number; label: string }[];
    selected: number[];
    onToggle: (id: number) => void;
    empty: string;
}) {
    if (options.length === 0) {
        return (
            <p className="rounded-lg border border-border px-3 py-4 text-center text-sm text-muted-foreground">
                {empty}
            </p>
        );
    }

    return (
        <div className="max-h-32 overflow-y-auto rounded-lg border border-input p-1">
            {options.map((o) => (
                <label key={o.id} className="flex cursor-pointer items-center gap-2.5 rounded-md px-2 py-1.5 text-sm hover:bg-muted">
                    <input
                        type="checkbox"
                        className="size-4 accent-primary"
                        checked={selected.includes(o.id)}
                        onChange={() => onToggle(o.id)}
                    />
                    <span className="truncate">{o.label}</span>
                </label>
            ))}
        </div>
    );
}

function Tally({ label, value, tone }: { label: string; value: number; tone: string }) {
    return (
        <div className="rounded-lg border border-border p-3 text-center">
            <p className="tabular font-display text-2xl font-bold" style={{ color: tone }}>{value}</p>
            <p className="mt-0.5 text-xs text-muted-foreground">{label}</p>
        </div>
    );
}
