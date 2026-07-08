<?php

namespace App\Http\Controllers;

use App\Models\TeamMember;
use Illuminate\Http\Request;

class TeamMemberController extends Controller
{
    public function index(Request $request)
    {
        $query = TeamMember::with('customer:id,business_name');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($customerId = $request->query('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        return $query->orderBy('name')->paginate(10)->withQueryString();
    }

    public function store(Request $request)
    {
        $member = TeamMember::create($this->validateData($request));

        return response()->json($member->load('customer:id,business_name'), 201);
    }

    public function update(Request $request, TeamMember $teamMember)
    {
        $teamMember->update($this->validateData($request));

        return $teamMember->load('customer:id,business_name');
    }

    public function destroy(TeamMember $teamMember)
    {
        $teamMember->delete();

        return response()->json(['message' => 'Team member removed']);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', 'in:owner,admin,member'],
            'status' => ['required', 'in:active,invited,inactive'],
        ]);
    }
}
