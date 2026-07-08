<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $query = Contact::with('customer:id,business_name');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%");
            });
        }

        if ($tag = $request->query('tag')) {
            $query->where('tag', $tag);
        }

        if ($customerId = $request->query('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        return $query->latest()->paginate(10)->withQueryString();
    }

    public function store(Request $request)
    {
        $contact = Contact::create($this->validateData($request));

        return response()->json($contact->load('customer:id,business_name'), 201);
    }

    public function update(Request $request, Contact $contact)
    {
        $contact->update($this->validateData($request));

        return $contact->load('customer:id,business_name');
    }

    public function destroy(Contact $contact)
    {
        $contact->delete();

        return response()->json(['message' => 'Contact deleted']);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'company' => ['nullable', 'string', 'max:150'],
            'tag' => ['required', 'in:customer,prospect,vendor,other'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
