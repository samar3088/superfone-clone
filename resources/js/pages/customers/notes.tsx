import { router, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useEffect, useState } from 'react';

import { Spinner } from '@/components/data-table';
import { Button, Modal, inputClass } from '@/components/ui-kit';

export interface Note {
    id: number;
    body: string;
    author: string | null;
    user_id: number | null;
    lead_id: number | null;
    /** The enquiry this note is about; null means it is about the contact. */
    lead: string | null;
    created_at: string | null;
}

export interface LeadChoice {
    id: number;
    label: string;
    stage: string | null;
    emoji: string | null;
    created_at: string | null;
}

/** Matches NoteService::ABOUT_CONTACT. */
const ABOUT_CONTACT = 'contact';

interface Auth {
    user: { id: number; is_owner: boolean } | null;
    permissions: string[];
}

function useAuth(): Auth {
    return usePage().props.auth as Auth;
}

/**
 * Notes for one contact — read and written in the same place.
 *
 * Opened from the Customers list, where the lead list is not already loaded,
 * so it fetches what it needs on open rather than making every row of the
 * table carry its contact's whole history.
 */
export default function NotesModal({
    customer,
    onClose,
}: {
    customer: { id: number; name: string };
    onClose: () => void;
}) {
    const [loading, setLoading] = useState(true);
    const [notes, setNotes] = useState<Note[]>([]);
    const [leads, setLeads] = useState<LeadChoice[]>([]);

    const load = () => {
        setLoading(true);

        fetch(`/customers/${customer.id}/notes`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((r) => r.json())
            .then((data) => {
                setNotes(data.notes);
                setLeads(data.leads);
            })
            .finally(() => setLoading(false));
    };

    useEffect(load, [customer.id]);

    return (
        <Modal open onClose={onClose} title={`Notes · ${customer.name}`}>
            {loading ? (
                <div className="grid place-items-center py-10 text-muted-foreground">
                    <Spinner className="size-6" />
                </div>
            ) : (
                <div className="space-y-5">
                    <NoteComposer customerId={customer.id} leads={leads} onSaved={load} />
                    <NoteList notes={notes} onChanged={load} />
                </div>
            )}
        </Modal>
    );
}

/**
 * Writing a note, and deciding what it is about.
 *
 * The picker only appears when there is something to pick between. With one
 * enquiry it is offered pre-selected, because that is almost always the answer
 * — but "about the contact" stays there for the note that is not about it.
 * With several, nothing is pre-selected and the server refuses a blank, so a
 * note can never be quietly filed against the wrong one.
 */
export function NoteComposer({
    customerId,
    leads,
    onSaved,
    autoFocus = false,
}: {
    customerId: number;
    leads: LeadChoice[];
    onSaved?: () => void;
    autoFocus?: boolean;
}) {
    const form = useForm({
        body: '',
        lead_id: leads.length === 1 ? String(leads[0].id) : '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();

        form.post(`/customers/${customerId}/notes`, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('body');
                onSaved?.();
            },
        });
    };

    return (
        <form onSubmit={submit} className="space-y-3">
            <textarea
                rows={3}
                autoFocus={autoFocus}
                value={form.data.body}
                onChange={(e) => form.setData('body', e.target.value)}
                placeholder={
                    leads.length === 0
                        ? 'What should the next person to speak to this contact know?'
                        : 'Write a note…'
                }
                className={inputClass}
            />
            {form.errors.body && <p className="text-sm text-[var(--bad)]">{form.errors.body}</p>}

            <div className="flex flex-wrap items-center justify-between gap-3">
                {leads.length === 0 ? (
                    <p className="text-xs text-muted-foreground">
                        This contact has no leads yet, so the note is saved against them.
                    </p>
                ) : (
                    <label className="flex min-w-0 flex-1 items-center gap-2 text-sm">
                        <span className="shrink-0 text-muted-foreground">About</span>
                        <select
                            value={form.data.lead_id}
                            onChange={(e) => form.setData('lead_id', e.target.value)}
                            className={`${inputClass} h-9 py-0`}
                        >
                            {/* Only offered as a blank when a choice is
                                genuinely required — with one lead it would just
                                be a second way of saying the same thing. */}
                            {leads.length > 1 && <option value="">Which lead is this about?</option>}
                            <option value={ABOUT_CONTACT}>This contact (not a specific lead)</option>
                            {leads.map((l) => (
                                <option key={l.id} value={l.id}>
                                    {l.emoji ? `${l.emoji} ` : ''}
                                    {l.label}
                                    {l.created_at ? ` · ${l.created_at}` : ''}
                                    {l.stage ? ` · ${l.stage}` : ''}
                                </option>
                            ))}
                        </select>
                    </label>
                )}

                <Button type="submit" disabled={form.processing || !form.data.body.trim()}>
                    Add note
                </Button>
            </div>

            {form.errors.lead_id && <p className="text-sm text-[var(--bad)]">{form.errors.lead_id}</p>}
        </form>
    );
}

export function NoteList({ notes, onChanged }: { notes: Note[]; onChanged?: () => void }) {
    if (notes.length === 0) {
        return (
            <p className="rounded-lg border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground">
                No notes yet.
            </p>
        );
    }

    return (
        <ul className="space-y-3">
            {notes.map((n) => (
                <NoteRow key={n.id} note={n} onChanged={onChanged} />
            ))}
        </ul>
    );
}

function NoteRow({ note, onChanged }: { note: Note; onChanged?: () => void }) {
    const { user, permissions } = useAuth();
    const [editing, setEditing] = useState(false);
    const [body, setBody] = useState(note.body);

    /*
     | Anyone may write a note. Only its author corrects their own words — an
     | owner can too, because someone has to be able to fix a note left behind
     | by a member who has gone. Deleting is owner-only, like every other
     | deletion in the app. The server enforces all three; this only decides
     | which buttons are worth showing.
     */
    const canEdit = !!user && (note.user_id === user.id || user.is_owner);
    const canDelete = permissions.includes('note.delete');

    const save = () => {
        router.patch(`/notes/${note.id}`, { body }, {
            preserveScroll: true,
            onSuccess: () => {
                setEditing(false);
                onChanged?.();
            },
        });
    };

    return (
        <li className="rounded-lg border border-border p-3">
            <div className="mb-1.5 flex flex-wrap items-center gap-2 text-xs">
                <span className="font-semibold">{note.author ?? 'Someone who has left'}</span>
                {note.created_at && (
                    <span className="data text-muted-foreground">
                        {note.created_at.slice(0, 16).replace('T', ' ')}
                    </span>
                )}
                <span
                    className="rounded-full px-2 py-0.5 font-semibold"
                    style={
                        note.lead
                            ? { background: 'var(--info-soft)', color: 'var(--info)' }
                            : { background: 'var(--muted)', color: 'var(--muted-foreground)' }
                    }
                >
                    {note.lead ?? 'About this contact'}
                </span>
            </div>

            {editing ? (
                <div className="space-y-2">
                    <textarea
                        rows={3}
                        autoFocus
                        value={body}
                        onChange={(e) => setBody(e.target.value)}
                        className={inputClass}
                    />
                    <div className="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            className="px-2.5 py-1"
                            onClick={() => {
                                setBody(note.body);
                                setEditing(false);
                            }}
                        >
                            Cancel
                        </Button>
                        <Button type="button" className="px-2.5 py-1" onClick={save} disabled={!body.trim()}>
                            Save
                        </Button>
                    </div>
                </div>
            ) : (
                <>
                    <p className="whitespace-pre-wrap text-sm">{note.body}</p>

                    {(canEdit || canDelete) && (
                        <div className="mt-2 flex gap-3 text-xs">
                            {canEdit && (
                                <button
                                    type="button"
                                    onClick={() => setEditing(true)}
                                    className="font-semibold text-muted-foreground transition hover:text-foreground"
                                >
                                    Edit
                                </button>
                            )}
                            {canDelete && (
                                <button
                                    type="button"
                                    onClick={() =>
                                        router.delete(`/notes/${note.id}`, {
                                            preserveScroll: true,
                                            onSuccess: onChanged,
                                        })
                                    }
                                    className="font-semibold transition hover:opacity-70"
                                    style={{ color: 'var(--bad)' }}
                                >
                                    Delete
                                </button>
                            )}
                        </div>
                    )}
                </>
            )}
        </li>
    );
}
