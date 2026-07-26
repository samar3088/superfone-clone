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
    /**
     * The three lists the To-Dos screen is split into.
     *
     * They are the three triggers under friendlier names: work raised by a
     * first enquiry, work raised by a repeat one, and anything somebody added
     * by hand. Splitting by trigger rather than by task type means a rule
     * change never silently moves work between tabs.
     */
    public const TABS = [
        'fresh' => Task::TRIGGER_NEW_LEAD,
        'followups' => Task::TRIGGER_EXISTING_LEAD,
        'reminders' => Task::TRIGGER_MANUAL,
    ];

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
        $totals = (clone $base)->open()
            // Selected through the builder rather than raw SQL: "trigger" is a
            // reserved word in MariaDB and needs quoting.
            ->select('trigger')
            ->selectRaw('count(*) as total')
            ->groupBy('trigger')
            ->pluck('total', 'trigger');

        return collect(self::TABS)
            ->map(fn (string $trigger) => (int) ($totals[$trigger] ?? 0))
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
