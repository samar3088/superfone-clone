<?php

namespace App\Http\Controllers;

use App\Models\MessageTemplate;
use Illuminate\Http\Request;

class MessageTemplateController extends Controller
{
    public function index(Request $request)
    {
        $query = MessageTemplate::withCount('campaigns');

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return $query->latest()->paginate(10)->withQueryString();
    }

    public function store(Request $request)
    {
        return response()->json(MessageTemplate::create($this->validateData($request)), 201);
    }

    public function update(Request $request, MessageTemplate $messageTemplate)
    {
        $messageTemplate->update($this->validateData($request));

        return $messageTemplate;
    }

    public function destroy(MessageTemplate $messageTemplate)
    {
        $messageTemplate->delete();

        return response()->json(['message' => 'Template deleted']);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', 'in:marketing,utility,authentication'],
            'body' => ['required', 'string', 'max:1024'],
            'status' => ['required', 'in:approved,pending,rejected'],
        ]);
    }
}
