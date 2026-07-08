<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::with('plan:id,name')
            ->withCount('numbers');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('business_name', 'like', "%{$search}%")
                    ->orWhere('owner_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return $query->latest()->paginate(10)->withQueryString();
    }

    public function show(Customer $customer)
    {
        return $customer->load('plan', 'numbers');
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $customer = Customer::create($data);

        return response()->json($customer->load('plan'), 201);
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $this->validateData($request, $customer->id);
        $customer->update($data);

        return $customer->load('plan');
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();

        return response()->json(['message' => 'Customer deleted']);
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'business_name' => ['required', 'string', 'max:150'],
            'owner_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['required', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:80'],
            'plan_id' => ['nullable', 'exists:plans,id'],
            'status' => ['required', 'in:active,trial,suspended,churned'],
        ]);
    }
}
