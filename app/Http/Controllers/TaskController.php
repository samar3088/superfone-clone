<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\Crm\TaskService;
use App\Services\Support\DataTableService;
use App\Support\LeadProviders;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    /** Everything the filter row owns, and everything Reset clears. */
    private const FILTER_KEYS = [
        'search', 'member', 'status', 'type', 'team',
        'due_from', 'due_to', 'lead_from', 'lead_to',
    ];

    public function __construct(private TaskService $tasks) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        // One filtered query feeds three things: the list, the tab counts and
        // the team card. Built once so they can never disagree.
        $base = $this->tasks->filtered($user, $request->all());

        $tab = $this->tab($request);

        $tasks = DataTableService::for(
            $this->tasks->inTab(clone $base, $tab)
                ->with([
                    'lead:id,customer_id,name,mobile,is_existing,lead_stage_id',
                    'lead.stage:id,name,emoji,type',
                    'assignee:id,name',
                ])
        )
            ->select(['id', 'lead_id', 'assigned_to', 'trigger', 'type', 'title',
                'due_at', 'completed_at', 'created_at'])
            ->searchable(['title', 'type'])
            ->sortable(['due_at', 'created_at', 'type'])
            // Open work first, soonest deadline at the top — the order someone
            // actually wants to work through.
            ->defaultSort('due_at', 'asc')
            ->paginate($request);

        return Inertia::render('tasks/index', [
            'tasks' => $tasks,
            'tab' => $tab,
            'filters' => $request->only([...self::FILTER_KEYS, 'sort', 'direction', 'per_page']),
            'members' => $user->isOwner()
                ? User::role([Roles::OWNER, Roles::MEMBER])->orderBy('name')->get(['id', 'name'])
                : [],
            'teams' => Team::orderBy('name')->get(['id', 'name']),
            // Only types that actually exist, so no chip leads to an empty list.
            'types' => $this->chipTypes(),
            'tabCounts' => $this->tasks->tabCounts($base),
            'usageByTeam' => $this->tasks->usageByTeam($base),
        ]);
    }

    /** The chosen tab, or the first one — never a name from the query string. */
    private function tab(Request $request): string
    {
        $asked = (string) $request->string('tab');

        return in_array($asked, TaskService::TABS, true)
            ? $asked
            : TaskService::TABS[0];
    }

    /**
     * Task types offered as chips.
     *
     * Ordered by the canonical list so the row reads the same way every time,
     * with anything unexpected — a type typed by hand into a rule — appended
     * rather than hidden.
     */
    private function chipTypes(): array
    {
        $present = Task::query()->distinct()->pluck('type')->all();
        $known = LeadProviders::todoTypes();

        return [
            ...array_values(array_intersect($known, $present)),
            ...array_values(array_diff($present, $known)),
        ];
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
