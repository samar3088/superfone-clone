<?php

namespace App\Http\Controllers;

use App\Models\Reminder;
use Illuminate\Http\Request;

class ReminderController extends Controller
{
    public function index(Request $request)
    {
        $query = Reminder::with(['customer:id,business_name', 'contact:id,name']);

        if ($search = $request->query('search')) {
            $query->where('title', 'like', "%{$search}%");
        }

        $filter = $request->query('filter');
        if ($filter === 'pending') {
            $query->where('status', 'pending');
        } elseif ($filter === 'done') {
            $query->where('status', 'done');
        } elseif ($filter === 'overdue') {
            $query->where('status', 'pending')->where('due_at', '<', now());
        } elseif ($filter === 'today') {
            $query->where('status', 'pending')->whereDate('due_at', today());
        }

        return $query->orderBy('due_at')->paginate(12)->withQueryString();
    }

    public function store(Request $request)
    {
        $reminder = Reminder::create($this->validateData($request));

        return response()->json($reminder->load('customer:id,business_name', 'contact:id,name'), 201);
    }

    public function update(Request $request, Reminder $reminder)
    {
        $reminder->update($this->validateData($request));

        return $reminder->load('customer:id,business_name', 'contact:id,name');
    }

    /**
     * Toggle pending/done quickly from the list.
     */
    public function toggle(Reminder $reminder)
    {
        $reminder->update([
            'status' => $reminder->status === 'done' ? 'pending' : 'done',
        ]);

        return $reminder;
    }

    public function destroy(Reminder $reminder)
    {
        $reminder->delete();

        return response()->json(['message' => 'Reminder deleted']);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'title' => ['required', 'string', 'max:200'],
            'notes' => ['nullable', 'string'],
            'due_at' => ['required', 'date'],
            'status' => ['required', 'in:pending,done'],
        ]);
    }
}
