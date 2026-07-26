<?php

namespace App\Services\Crm;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\Note;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Notes against a contact, and the rule for which enquiry they belong to.
 *
 * Every note is filed under the person. Whether it is also filed under one of
 * their enquiries depends on how many they have:
 *
 *   none  — saved against the contact, no question asked; there is nothing to
 *           choose between and a picker with no options is a dead end.
 *   one   — that enquiry is offered as the default, and "about the contact"
 *           stays available for anything that is not about it.
 *   many  — a choice is required. Guessing here is worse than asking: a note
 *           filed against the wrong enquiry is read by the wrong person at the
 *           wrong moment, and nobody ever notices it moved.
 *
 * The rule lives here rather than in the form so the browser cannot talk its
 * way past it.
 */
class NoteService
{
    /** No lead chosen, said explicitly rather than left blank. */
    public const ABOUT_CONTACT = 'contact';

    /**
     * The enquiries a note may be filed against, newest first.
     *
     * @return Collection<int, Lead>
     */
    public function choices(Customer $customer): Collection
    {
        return $customer->leads()
            ->with('stage:id,name,emoji')
            ->latest('id')
            ->get(['id', 'customer_id', 'name', 'campaign', 'source', 'lead_stage_id', 'created_at']);
    }

    /**
     * The contact's notes, newest first, in the shape the screen renders.
     *
     * Shared by the contact page and the composer so the same note never reads
     * two different ways depending on where it is opened from.
     *
     * @return array<int, array<string, mixed>>
     */
    public function timeline(Customer $customer): array
    {
        return $customer->notes()
            ->with(['author:id,name', 'lead:id,campaign,source'])
            ->latest('id')
            ->get()
            ->map(fn (Note $note) => [
                'id' => $note->id,
                'body' => $note->body,
                'author' => $note->author?->name,
                'user_id' => $note->user_id,
                'lead_id' => $note->lead_id,
                // Null reads as "about the contact", so a general note never
                // looks like one that lost its lead.
                'lead' => $note->lead ? $this->label($note->lead) : null,
                'created_at' => $note->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /** How an enquiry is named in a picker or a chip. */
    public function label(Lead $lead): string
    {
        return $lead->campaign ?: ($lead->source ?: 'Lead');
    }

    /**
     * Write a note.
     *
     * $leadId is the raw choice from the form: a lead id, the ABOUT_CONTACT
     * marker, or nothing at all.
     */
    public function write(Customer $customer, ?string $leadId, string $body, User $author): Note
    {
        $note = Note::create([
            'customer_id' => $customer->id,
            'lead_id' => $this->resolveLead($customer, $leadId),
            'user_id' => $author->id,
            'body' => trim($body),
        ]);

        // Writing a note is contact with the customer, so the record should
        // say so — otherwise a well-worked contact looks abandoned on the list.
        $customer->forceFill(['last_activity_at' => now()])->save();

        activity('note')
            ->performedOn($note)
            ->causedBy($author)
            ->log("Added a note on {$customer->name}");

        return $note;
    }

    public function update(Note $note, string $body, User $author): Note
    {
        $note->update(['body' => trim($body)]);

        activity('note')->performedOn($note)->causedBy($author)->log('Edited a note');

        return $note;
    }

    public function delete(Note $note, User $author): void
    {
        activity('note')->performedOn($note)->causedBy($author)->log('Deleted a note');

        $note->delete();
    }

    /**
     * Turn the form's choice into a lead id, refusing anything that would file
     * the note somewhere it does not belong.
     */
    private function resolveLead(Customer $customer, ?string $choice): ?int
    {
        $leads = $customer->leads()->pluck('id');

        // Nothing to choose between — the note is about the person.
        if ($leads->isEmpty()) {
            return null;
        }

        if ($choice === self::ABOUT_CONTACT) {
            return null;
        }

        if (blank($choice)) {
            // With one enquiry, silence means that enquiry. With several it
            // means the question was skipped, and that has to be asked again.
            if ($leads->count() === 1) {
                return (int) $leads->first();
            }

            throw ValidationException::withMessages([
                'lead_id' => 'This contact has more than one lead — choose which one this note is about.',
            ]);
        }

        // Someone else's lead id, whether typed or stale. Never file it there.
        if (! $leads->contains((int) $choice)) {
            throw ValidationException::withMessages([
                'lead_id' => 'That lead does not belong to this contact.',
            ]);
        }

        return (int) $choice;
    }
}
