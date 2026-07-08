<?php

namespace App\Http\Controllers;

use App\Models\VirtualNumber;
use Illuminate\Http\Request;

class VirtualNumberController extends Controller
{
    public function index(Request $request)
    {
        $query = VirtualNumber::with('customer:id,business_name');

        if ($search = $request->query('search')) {
            $query->where('number', 'like', "%{$search}%");
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        return $query->orderBy('number')->paginate(10)->withQueryString();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'number' => ['required', 'string', 'max:20', 'unique:virtual_numbers,number'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'type' => ['required', 'in:mobile,landline,tollfree'],
            'status' => ['required', 'in:available,assigned,porting,released'],
        ]);
        $data['assigned_at'] = $data['customer_id'] ? now() : null;

        return response()->json(VirtualNumber::create($data)->load('customer'), 201);
    }

    public function update(Request $request, VirtualNumber $virtual_number)
    {
        $data = $request->validate([
            'number' => ['required', 'string', 'max:20', 'unique:virtual_numbers,number,' . $virtual_number->id],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'type' => ['required', 'in:mobile,landline,tollfree'],
            'status' => ['required', 'in:available,assigned,porting,released'],
        ]);
        $data['assigned_at'] = $data['customer_id'] ? ($virtual_number->assigned_at ?? now()) : null;
        $virtual_number->update($data);

        return $virtual_number->load('customer');
    }

    public function destroy(VirtualNumber $virtual_number)
    {
        $virtual_number->delete();

        return response()->json(['message' => 'Number deleted']);
    }
}
