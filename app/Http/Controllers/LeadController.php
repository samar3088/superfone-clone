<?php

namespace App\Http\Controllers;

use App\Http\Requests\Crm\LeadFilterRequest;
use App\Models\Lead;
use App\Models\LeadGroup;
use App\Models\LeadStage;
use App\Models\User;
use App\Services\Crm\LeadService;
use App\Services\Support\DataTableService;
use App\Services\Support\ExportService;
use App\Support\FilterList;
use App\Support\Roles;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadController extends Controller
{
    /** Filter keys the screen owns; the export reads the same set. */
    private const FILTER_KEYS = ['search', 'member', 'stage', 'source', 'date_from', 'date_to', 'unread'];

    public function __construct(
        private LeadService $leads,
        private ExportService $exports,
    ) {}

    public function index(LeadFilterRequest $request): Response
    {
        return Inertia::render('leads/index', [
            'leads' => $this->table($request)->paginate($request),
            'filters' => $request->only([...self::FILTER_KEYS, 'sort', 'direction', 'per_page']),
            'stages' => LeadStage::where('is_active', true)->orderBy('sequence')
                ->get(['id', 'name', 'emoji', 'type']),
            'groups' => LeadGroup::where('is_active', true)->get(['id', 'name']),
            // Members only ever see their own leads, so the picker would be a
            // list of one — it is owner-only.
            'members' => $request->user()->isOwner()
                ? User::role([Roles::OWNER, Roles::MEMBER])->orderBy('name')->get(['id', 'name'])
                : [],
        ]);
    }

    public function export(LeadFilterRequest $request): StreamedResponse
    {
        return $this->exports->streamCsv(
            $this->table($request)->query($request),
            ['Lead', 'Mobile', 'Email', 'Source', 'Campaign', 'Status', 'Assigned to', 'Read', 'Received'],
            fn (Lead $l) => [
                $l->name,
                $l->mobile,
                $l->email ?? '',
                $l->source,
                $l->campaign ?? '',
                $l->stage?->name ?? '',
                $l->assignee?->name ?? 'Unassigned',
                $l->viewed_at ? 'Yes' : 'No',
                $l->created_at->toDateTimeString(),
            ],
            'leads-'.now()->format('Y-m-d-Hi').'.csv',
        );
    }

    private function table(Request $request): DataTableService
    {
        $user = $request->user();

        return DataTableService::for(
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
            ->filter('stage', fn (Builder $q, $v) => $q->whereIn('lead_stage_id', FilterList::ids($v)))
            ->filter('unread', fn (Builder $q) => $q->whereNull('viewed_at'))
            /*
             | Ignored for anyone but an owner — members are already scoped to
             | their own leads, and honouring this would let a hand-edited URL
             | widen that. "unassigned" is a real state, so it rides alongside
             | the ids as a word and is OR-ed in.
             */
            ->filter('member', function (Builder $q, $v) use ($user) {
                if (! $user->isOwner()) {
                    return $q;
                }

                $ids = FilterList::ids($v);
                $wantsUnassigned = in_array('unassigned', FilterList::parse($v), true);

                return $q->where(function (Builder $group) use ($ids, $wantsUnassigned) {
                    if ($ids !== []) {
                        $group->whereIn('assigned_to', $ids);
                    }

                    if ($wantsUnassigned) {
                        $group->orWhereNull('assigned_to');
                    }
                });
            })
            // whereDate on the end so a lead created at 18:40 still falls
            // inside a range ending on its own date.
            ->filter('date_from', fn (Builder $q, $v) => $q->whereDate('created_at', '>=', $v))
            ->filter('date_to', fn (Builder $q, $v) => $q->whereDate('created_at', '<=', $v))
            ->defaultSort('id', 'desc');
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
