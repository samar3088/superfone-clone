<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Services\Support\DataTableService;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $leads = DataTableService::for(
            Lead::query()
                ->with('assignee:id,name')
                // Members only ever see the leads assigned to them.
                ->when(! $user->isOwner(), fn (Builder $q) => $q->where('assigned_to', $user->id))
        )
            ->select(['id', 'name', 'mobile', 'email', 'source', 'campaign', 'assigned_to', 'viewed_at', 'created_at'])
            ->searchable(['name', 'mobile', 'email', 'campaign'])
            ->sortable(['name', 'created_at', 'source'])
            ->filter('source', fn (Builder $q, $v) => $q->where('source', $v))
            ->filter('unread', fn (Builder $q) => $q->whereNull('viewed_at'))
            ->defaultSort('id', 'desc')
            ->paginate($request);

        return Inertia::render('leads/index', [
            'leads' => $leads,
            'filters' => $request->only(['search', 'source', 'unread', 'sort', 'direction']),
        ]);
    }

    /** Clear the unread badge for everything currently visible to this user. */
    public function markAllRead(Request $request): RedirectResponse
    {
        Lead::query()
            ->unreadFor($request->user())
            ->update(['viewed_at' => now()]);

        return back()->with('success', 'All leads marked as read.');
    }
}
