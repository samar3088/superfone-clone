<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index()
    {
        return Plan::withCount('customers')->orderBy('price')->get();
    }

    public function store(Request $request)
    {
        $plan = Plan::create($this->validateData($request));

        return response()->json($plan, 201);
    }

    public function update(Request $request, Plan $plan)
    {
        $plan->update($this->validateData($request));

        return $plan;
    }

    public function destroy(Plan $plan)
    {
        $plan->delete();

        return response()->json(['message' => 'Plan deleted']);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'price' => ['required', 'numeric', 'min:0'],
            'validity_days' => ['required', 'integer', 'min:1'],
            'users_included' => ['required', 'integer', 'min:1'],
            'features' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);
    }
}
