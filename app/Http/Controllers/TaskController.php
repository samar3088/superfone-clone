<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Services\Crm\TaskService;
use App\Services\Support\DataTableService;
use App\Support\FilterList;
use App\Support\Roles;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    private const FILTER_KEYS = ['search', 'member', 'state', 'type'];

    public function __construct(private TaskService $tasks) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $tasks = DataTableService::for(
            Task::query()
                ->with(['lead:id,name,mobile,is_existing', 'assignee:id,name'])
                // Members see their own work, the same scoping as Leads.
                ->when(! $user->isOwner(), fn (Builder $q) => $q->where('assigned_to', $user->id))
        )
            ->select(['id', 'lead_id', 'assigned_to', 'trigger', 'type', 'title',
                'due_at', 'completed_at', 'created_at'])
            ->searchable(['title', 'type'])
            ->sortable(['due_at', 'created_at', 'type'])
            ->filter('member', fn (Builder $q, $v) => $user->isOwner()
                ? $q->whereIn('assigned_to', FilterList::ids($v))
                : $q)
            ->filter('type', fn (Builder $q, $v) => $q->whereIn('type', FilterList::parse($v)))
            ->filter('state', fn (Builder $q, $v) => match ($v) {
                'open' => $q->open(),
                'overdue' => $q->overdue(),
                'done' => $q->whereNotNull('completed_at'),
                default => $q,
            })
            // Open work first, soonest deadline at the top — the order someone
            // actually wants to work through.
            ->defaultSort('due_at', 'asc')
            ->paginate($request);

        return Inertia::render('tasks/index', [
            'tasks' => $tasks,
            'filters' => $request->only([...self::FILTER_KEYS, 'sort', 'direction', 'per_page']),
            'members' => $user->isOwner()
                ? User::role([Roles::OWNER, Roles::MEMBER])->orderBy('name')->get(['id', 'name'])
                : [],
            'types' => Task::query()->distinct()->orderBy('type')->pluck('type'),
            // Headline figures for the whole visible set, not the current page.
            'counts' => [
                'open' => $this->scoped($user)->open()->count(),
                'overdue' => $this->scoped($user)->overdue()->count(),
            ],
        ]);
    }

    /** Everything this user is allowed to see, before any filter. */
    private function scoped(User $user)
    {
        return Task::query()->when(
            ! $user->isOwner(),
            fn (Builder $q) => $q->where('assigned_to', $user->id),
        );
    }

    public function complete(Request $request, Task $task): RedirectResponse
    {
        $this->assertMine($request, $task);

        $this->tasks->complete($task, $request->user());

        return back()->with('success', 'Marked done.');
    }

    public function reopen(Request $request, Task $task): RedirectResponse
    {
        $this->assertMine($request, $task);

        $this->tasks->reopen($task, $request->user());

        return back()->with('success', 'Reopened.');
    }

    /** A member may only touch their own work; an owner may touch anyone's. */
    private function assertMine(Request $request, Task $task): void
    {
        abort_unless(
            $request->user()->isOwner() || $task->assigned_to === $request->user()->id,
            403,
        );
    }
}
