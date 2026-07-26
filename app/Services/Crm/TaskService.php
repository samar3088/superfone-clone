<?php

namespace App\Services\Crm;

use App\Models\Integration;
use App\Models\Lead;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Support\FilterList;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Turns an integration's to-do rules into actual work items.
 *
 * Each integration carries two rule sets — one for a first enquiry, one for a
 * repeat — and they usually differ: a first call versus a follow-up call. Which
 * one fires is decided by the lead's own repeat flag, so the rules and the
 * duplicate detection stay in step.
 */
class TaskService
{
    /** Work on a lead nobody has done anything to yet. */
    public const TAB_FRESH = 'fresh';

    /** Work on a lead somebody has already started. */
    public const TAB_FOLLOWUPS = 'followups';

    /** Held empty until the client says what belongs here. */
    public const TAB_REMINDERS = 'reminders';

    public const TABS = [self::TAB_FRESH, self::TAB_FOLLOWUPS, self::TAB_REMINDERS];

    /** The two task types that decide a tab on their own. */
    public const TYPE_FIRST_CALL = 'FIRST CALL';

    public const TYPE_REMINDER = 'REMINDER';

    /**
     * Narrow to one tab.
     *
     * Read in this order, because the order is what makes the three exhaustive
     * and non-overlapping — every to-do lands on exactly one:
     *
     *   Reminders   a REMINDER, whatever its lead is doing. Someone set it
     *               deliberately, so it belongs where reminders are read.
     *   Fresh Leads anything else that is new work: a first call, a lead still
     *               at its opening stage, or a lead nobody has touched.
     *   Follow Ups  everything left.
     *
     * Note what is *not* used: the trigger that raised the to-do. A campaign
     * rule change would otherwise move existing work between tabs overnight.
     */
    public function inTab(Builder $query, string $tab): Builder
    {
        return match ($tab) {
            self::TAB_REMINDERS => $query->where('type', self::TYPE_REMINDER),

            self::TAB_FRESH => $query
                ->where('type', '!=', self::TYPE_REMINDER)
                ->where(fn (Builder $q) => $this->isNewWork($q)),

            self::TAB_FOLLOWUPS => $query
                ->where('type', '!=', self::TYPE_REMINDER)
                ->whereNot(fn (Builder $q) => $this->isNewWork($q)),
        };
    }

    /**
     * The Fresh Leads condition, in one place so the two tabs that use it
     * cannot drift into overlapping or leaving a gap.
     *
     * Three ways in, any one of them enough:
     *
     *   - the to-do is a first call. Nothing is more clearly new work.
     *   - the lead is still at its opening stage. Keyed on the stage's INITIAL
     *     type, not the name "New Inquiry" — the client can rename stages in
     *     Settings, and a rule keyed on a name breaks silently when they do.
     *   - nobody has touched the lead: its version is still 1, so no stage or
     *     owner change, and nothing on it has been ticked off.
     */
    private function isNewWork(Builder $query): Builder
    {
        return $query
            ->where('type', self::TYPE_FIRST_CALL)
            ->orWhereHas('lead.stage', fn (Builder $s) => $s->where('type', 'INITIAL'))
            ->orWhere(fn (Builder $q) => $q
                ->whereHas('lead', fn (Builder $l) => $l->where('version', 1))
                ->whereNotIn('lead_id', $this->leadsWithCompletedWork()));
    }

    /**
     * Leads with at least one to-do already ticked off.
     *
     * Written as a plain subquery rather than a nested whereHas: the outer
     * query is also on tasks, and two tables of the same name in one condition
     * is a correlation waiting to be read wrong.
     */
    private function leadsWithCompletedWork(): callable
    {
        return fn ($q) => $q->from('tasks')
            ->select('lead_id')
            ->whereNotNull('completed_at');
    }

    /**
     * Everything this person may see, narrowed by the screen's filters.
     *
     * The tab is deliberately left out. The counts on the tabs have to be
     * measured against the same filters as the list beneath them, so both are
     * built from this one query and only the list adds the trigger.
     */
    public function filtered(User $user, array $f): Builder
    {
        $value = fn (string $key) => filled($f[$key] ?? null) ? $f[$key] : null;

        return Task::query()
            // A member sees their own work and nothing else — the same scoping
            // the Leads screen uses.
            ->when(! $user->isOwner(), fn (Builder $q) => $q->where('assigned_to', $user->id))

            // Only an owner has anyone else's work to filter down to.
            // Ternary, not `&&`: when() hands the closure the condition's own
            // value, and `isOwner() && $member` would arrive as a bare true.
            ->when($user->isOwner() ? $value('member') : null, fn (Builder $q, $v) => $q->whereIn(
                'assigned_to', FilterList::ids($v)
            ))
            ->when($value('type'), fn (Builder $q, $v) => $q->whereIn('type', FilterList::parse($v)))
            ->when($value('due_from'), fn (Builder $q, $v) => $q->whereDate('due_at', '>=', $v))
            ->when($value('due_to'), fn (Builder $q, $v) => $q->whereDate('due_at', '<=', $v))

            // A task has no team of its own; it belongs to whichever team owns
            // the contact behind the lead.
            ->when($value('team'), fn (Builder $q, $v) => $q->whereHas(
                'lead.customer', fn (Builder $c) => $c->whereIn('team_id', FilterList::ids($v))
            ))

            ->when($value('lead_from') || $value('lead_to'), fn (Builder $q) => $q->whereHas(
                'lead',
                function (Builder $l) use ($value): void {
                    // Qualified: "leads" is joined again by the team filter
                    // above, and a bare created_at would be ambiguous.
                    if ($from = $value('lead_from')) {
                        $l->whereDate('leads.created_at', '>=', $from);
                    }

                    if ($to = $value('lead_to')) {
                        $l->whereDate('leads.created_at', '<=', $to);
                    }
                }
            ))

            /*
             | How soon it falls due — the second-line filter on Reminders.
             |
             | Bucket n is "due within the next n days but not the n-1 before
             | it", so the buckets tile rather than nest: a to-do due tomorrow
             | afternoon is in "2 days" only, not in "1 day" as well. Counted
             | from now rather than from midnight, because "due in 1 day" means
             | twenty-four hours, not "sometime tomorrow".
             */
            ->when($value('due'), fn (Builder $q, $v) => match ((string) $v) {
                'overdue' => $q->overdue(),
                '1', '2', '3' => $q->open()->whereBetween('due_at', [
                    now()->addDays((int) $v - 1),
                    now()->addDays((int) $v),
                ]),
                // Everything further out, and everything with no deadline at
                // all — which would otherwise be unreachable from this row.
                'later' => $q->open()->where(fn (Builder $w) => $w
                    ->whereNull('due_at')
                    ->orWhere('due_at', '>', now()->addDays(3))),
                default => $q,
            })

            ->when($value('status'), fn (Builder $q, $v) => match ($v) {
                'open' => $q->open(),
                'overdue' => $q->overdue(),
                'done' => $q->whereNotNull('completed_at'),
                default => $q,
            });
    }

    /**
     * How much open work sits behind each tab.
     *
     * @return array<string, int>
     */
    public function tabCounts(Builder $base): array
    {
        /*
         | One count per tab rather than a single GROUP BY. The tabs are defined
         | by conditions across two tables, not by a column that could be
         | grouped on — and three cheap counts beat a query nobody can read.
         */
        return collect(self::TABS)
            ->mapWithKeys(fn (string $tab) => [
                $tab => $this->inTab((clone $base)->open(), $tab)->count(),
            ])
            ->all();
    }

    /**
     * Open work per team, for the summary card.
     *
     * The team is read with a scalar subquery rather than a join: the caller's
     * query already reaches into leads for its own filters, and a second table
     * of the same name in the outer query is a trap waiting for the next
     * person to add a where clause.
     *
     * @return array<int, array{team: string, total: int}>
     */
    public function usageByTeam(Builder $base): array
    {
        $counts = (clone $base)->open()
            ->selectRaw(
                '(select customers.team_id
                    from leads
                    join customers on customers.id = leads.customer_id
                   where leads.id = tasks.lead_id) as team_id,
                 count(*) as total'
            )
            ->groupBy('team_id')
            ->pluck('total', 'team_id');

        $names = Team::whereIn('id', $counts->keys()->filter())->pluck('name', 'id');

        return $counts
            ->map(fn ($total, $id) => [
                'team' => $names[$id] ?? 'No team',
                'total' => (int) $total,
            ])
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * Raise whatever the campaign's rules ask for on this lead.
     *
     * Returns null when the rule is switched off, incomplete, or the lead has
     * nobody to do the work — a task assigned to no one is a reminder nobody
     * sees.
     */
    public function createFromRules(Lead $lead, Integration $integration): ?Task
    {
        $prefix = $lead->is_existing ? 'existing_' : '';

        $parentOn = $lead->is_existing
            ? $integration->existing_lead_enabled
            : $integration->new_lead_enabled;

        if (! $parentOn || ! $integration->{"{$prefix}todo_enabled"}) {
            return null;
        }

        $type = $integration->{"{$prefix}todo_type"};
        $title = $integration->{"{$prefix}todo_title"};

        // A half-filled rule should do nothing rather than raise a blank task.
        if (blank($type) || blank($title)) {
            return null;
        }

        if (! $lead->assigned_to) {
            return null;
        }

        return Task::create([
            'lead_id' => $lead->id,
            'assigned_to' => $lead->assigned_to,
            'integration_id' => $integration->id,
            'trigger' => $lead->is_existing ? Task::TRIGGER_EXISTING_LEAD : Task::TRIGGER_NEW_LEAD,
            'type' => $type,
            'title' => $title,
            'due_at' => $this->dueAt(
                $lead->created_at,
                $integration->{"{$prefix}todo_due_value"},
                $integration->{"{$prefix}todo_due_unit"},
            ),
        ]);
    }

    /**
     * Due time, measured from when the enquiry arrived rather than from now.
     *
     * It matters for a backfill: a task on a lead from six months ago should be
     * overdue, not freshly due today.
     */
    public function dueAt(?Carbon $from, ?int $value, ?string $unit): ?Carbon
    {
        if ($value === null || blank($unit)) {
            return null;
        }

        $base = ($from ?? now())->copy();

        return match ($unit) {
            'seconds' => $base->addSeconds($value),
            'minutes' => $base->addMinutes($value),
            'hours' => $base->addHours($value),
            'days' => $base->addDays($value),
            default => null,
        };
    }

    public function complete(Task $task, User $actor): Task
    {
        if ($task->completed_at) {
            return $task;
        }

        $task->forceFill([
            'completed_at' => now(),
            'completed_by' => $actor->id,
        ])->save();

        activity('task')
            ->performedOn($task)
            ->causedBy($actor)
            ->log("Completed “{$task->title}”");

        return $task;
    }

    public function reopen(Task $task, User $actor): Task
    {
        $task->forceFill(['completed_at' => null, 'completed_by' => null])->save();

        activity('task')->performedOn($task)->causedBy($actor)->log("Reopened “{$task->title}”");

        return $task;
    }

    /** Someone hand-adding a task against a lead. */
    public function createManual(Lead $lead, array $data, User $actor): Task
    {
        if (! $lead->assigned_to && blank($data['assigned_to'] ?? null)) {
            throw ValidationException::withMessages([
                'assigned_to' => 'Choose who should do this — an unassigned task is a reminder nobody sees.',
            ]);
        }

        $task = Task::create([
            'lead_id' => $lead->id,
            'assigned_to' => $data['assigned_to'] ?? $lead->assigned_to,
            'integration_id' => $lead->integration_id,
            'trigger' => Task::TRIGGER_MANUAL,
            'type' => $data['type'],
            'title' => $data['title'],
            'due_at' => $data['due_at'] ?? null,
        ]);

        activity('task')->performedOn($task)->causedBy($actor)->log("Added “{$task->title}”");

        return $task;
    }
}
