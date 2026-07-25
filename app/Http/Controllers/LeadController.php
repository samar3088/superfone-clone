<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadGroup;
use App\Models\LeadStage;
use App\Services\Crm\LeadService;
use App\Services\Support\DataTableService;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    public function __construct(private LeadService $leads) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $leads = DataTableService::for(
            Lead::query()
                ->with(['assignee:id,name', 'stage:id,name,type,emoji', 'customer:id,name'])
                // Members only ever see the leads assigned to them.
                ->when(! $user->isOwner(), fn (Builder $q) => $q->where('assigned_to', $user->id))
        )
            ->select(['id', 'customer_id', 'name', 'mobile', 'email', 'source', 'campaign',
                'lead_stage_id', 'assigned_to', 'viewed_at', 'version', 'created_at'])
            ->searchable(['name', 'mobile', 'email', 'campaign'])
            ->sortable(['name', 'created_at', 'source'])
            ->filter('source', fn (Builder $q, $v) => $q->where('source', $v))
            ->filter('stage', fn (Builder $q, $v) => $q->where('lead_stage_id', $v))
            ->filter('unread', fn (Builder $q) => $q->whereNull('viewed_at'))
            ->defaultSort('id', 'desc')
            ->paginate($request);

        return Inertia::render('leads/index', [
            'leads' => $leads,
            'filters' => $request->only(['search', 'source', 'stage', 'unread', 'sort', 'direction']),
            'stages' => LeadStage::where('is_active', true)->orderBy('sequence')->get(['id', 'name', 'emoji', 'type']),
            'groups' => LeadGroup::where('is_active', true)->get(['id', 'name']),
        ]);
    }

    /**
     * Change a lead's stage/owner. Guarded by an optimistic-lock version so
     * two members working the same lead can't overwrite each other.
     */
    public function updateStatus(Request $request, Lead $lead): RedirectResponse
    {
        $data = $request->validate([
            'version' => ['required', 'integer'],
            'lead_stage_id' => ['nullable', 'exists:lead_stages,id'],
            'lead_group_id' => ['nullable', 'exists:lead_groups,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        $this->leads->updateStatus($lead, $data, $request->user());
        $this->leads->markViewed($lead);

        return back()->with('success', 'Lead updated.');
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
