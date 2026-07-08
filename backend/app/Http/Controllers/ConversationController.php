<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function index(Request $request)
    {
        $query = Conversation::with('customer:id,business_name')
            ->withCount('messages');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        // Most recently active first; include a short preview of the last message.
        return $query->orderByDesc('last_message_at')
            ->get()
            ->map(function ($c) {
                $last = $c->messages()->latest('sent_at')->first();
                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'phone' => $c->phone,
                    'status' => $c->status,
                    'unread' => $c->unread,
                    'business' => $c->customer?->business_name,
                    'messages_count' => $c->messages_count,
                    'last_message_at' => $c->last_message_at,
                    'preview' => $last ? \Illuminate\Support\Str::limit($last->body, 48) : null,
                ];
            });
    }

    public function show(Conversation $conversation)
    {
        // Opening a thread clears its unread badge.
        if ($conversation->unread > 0) {
            $conversation->update(['unread' => 0]);
        }

        return response()->json([
            'conversation' => $conversation->load('customer:id,business_name'),
            'messages' => $conversation->messages()->get(),
        ]);
    }

    /**
     * Post an outbound reply into a conversation.
     */
    public function reply(Request $request, Conversation $conversation)
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:1024'],
        ]);

        $message = $conversation->messages()->create([
            'direction' => 'outbound',
            'body' => $data['body'],
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $conversation->update([
            'last_message_at' => now(),
            'status' => 'open',
        ]);

        return response()->json($message, 201);
    }

    public function close(Conversation $conversation)
    {
        $conversation->update(['status' => $conversation->status === 'open' ? 'closed' : 'open']);

        return $conversation;
    }
}
