<?php

namespace App\Services\Crm;

use App\Models\Integration;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
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
