<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Note;
use App\Services\Crm\NoteService;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    public function __construct(private NoteService $notes) {}

    /**
     * The contact's notes and the enquiries one can be filed against.
     *
     * Fetched by the composer rather than shipped with every row of the
     * Customers table — a note count per row is cheap, but every contact's
     * whole lead list is not.
     */
    public function index(Customer $customer): JsonResponse
    {
        return response()->json([
            'customer' => $customer->only(['id', 'name', 'mobile']),
            'leads' => $this->notes->choices($customer)->map(fn ($lead) => [
                'id' => $lead->id,
                'label' => $this->notes->label($lead),
                'stage' => $lead->stage?->name,
                'emoji' => $lead->stage?->emoji,
                'created_at' => $lead->created_at?->toDateString(),
            ]),
            'notes' => $this->notes->timeline($customer),
        ]);
    }

    public function store(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            // The value is checked against this contact's own leads in the
            // service; a rule here could only repeat that query.
            'lead_id' => ['nullable', 'string', 'max:20'],
        ], [
            'body.required' => 'Write the note before saving it.',
        ]);

        $this->notes->write($customer, $data['lead_id'] ?? null, $data['body'], $request->user());

        return back()->with('success', 'Note added.');
    }

    public function update(Request $request, Note $note): RedirectResponse
    {
        // Editing someone else's words is not a correction, it is a rewrite.
        abort_unless(
            $request->user()->isOwner() || $note->user_id === $request->user()->id,
            403,
        );

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $this->notes->update($note, $data['body'], $request->user());

        return back()->with('success', 'Note updated.');
    }

    public function destroy(Request $request, Note $note): RedirectResponse
    {
        abort_unless($request->user()?->can(Permissions::NOTE_DELETE), 403);

        $this->notes->delete($note, $request->user());

        return back()->with('success', 'Note deleted.');
    }
}
