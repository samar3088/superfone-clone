<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    /**
     * Returns leads grouped by pipeline stage for the board view,
     * plus per-stage totals.
     */
    public function index(Request $request)
    {
        $query = Lead::with('customer:id,business_name');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('owner', 'like', "%{$search}%");
            });
        }

        if ($customerId = $request->query('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        $leads = $query->latest()->get();
        $stages = ['new', 'contacted', 'qualified', 'won', 'lost'];

        $board = [];
        foreach ($stages as $stage) {
            $items = $leads->where('stage', $stage)->values();
            $board[$stage] = [
                'leads' => $items,
                'count' => $items->count(),
                'value' => (float) $items->sum('value'),
            ];
        }

        return response()->json([
            'board' => $board,
            'total' => $leads->count(),
            'pipeline_value' => (float) $leads->whereNotIn('stage', ['lost'])->sum('value'),
        ]);
    }

    public function store(Request $request)
    {
        $lead = Lead::create($this->validateData($request));

        return response()->json($lead->load('customer:id,business_name'), 201);
    }

    public function update(Request $request, Lead $lead)
    {
        $lead->update($this->validateData($request));

        return $lead->load('customer:id,business_name');
    }

    /**
     * Lightweight stage-only update for drag/move on the board.
     */
    public function move(Request $request, Lead $lead)
    {
        $data = $request->validate([
            'stage' => ['required', 'in:new,contacted,qualified,won,lost'],
        ]);
        $lead->update($data);

        return $lead;
    }

    public function destroy(Lead $lead)
    {
        $lead->delete();

        return response()->json(['message' => 'Lead deleted']);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'source' => ['required', 'in:referral,website,ads,walkin,other'],
            'stage' => ['required', 'in:new,contacted,qualified,won,lost'],
            'value' => ['required', 'numeric', 'min:0'],
            'owner' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
