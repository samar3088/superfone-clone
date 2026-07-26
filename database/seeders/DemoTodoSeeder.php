<?php

namespace Database\Seeders;

use App\Models\Lead;
use App\Models\Task;
use Illuminate\Database\Seeder;

/**
 * Demo to-dos, built to exercise every part of the To-Dos screen.
 *
 * Not just "some rows": the set is chosen so that each control on the page has
 * something to do. All three tabs are populated, every task type appears so the
 * chip row is complete, and the states cover overdue, due later, no deadline and
 * already done — which is what the status filter and the red overdue line need
 * in order to be checked at all.
 *
 * Runs last. A to-do hangs off a lead, and the lead carries the assignee and the
 * contact whose team the usage card counts, so nothing here can be seeded until
 * customers and leads exist.
 */
class DemoTodoSeeder extends Seeder
{
    /**
     * What to raise, per lead taken in turn.
     *
     * [trigger, type, title, hours from now (negative is overdue, null is no
     * deadline), already done]
     *
     * Types are spelled exactly as LeadProviders::todoTypes() spells them, so
     * the chips come out in the canonical order rather than as strays.
     */
    private const PLAN = [
        // Fresh Leads — raised by a first enquiry.
        [Task::TRIGGER_NEW_LEAD, 'FIRST CALL', 'Call back about the Bengaluru package', -26, false],
        [Task::TRIGGER_NEW_LEAD, 'FOLLOW-UP CALL', 'Share the quote by email', 3, false],
        [Task::TRIGGER_NEW_LEAD, 'CALLBACK REQUEST', 'Asked to be rung after 6pm', null, false],
        [Task::TRIGGER_NEW_LEAD, 'SITE VISIT', 'Showed them the Whitefield venue', -72, true],

        // Follow Ups — raised by a repeat enquiry from someone already on file.
        [Task::TRIGGER_EXISTING_LEAD, 'FOLLOW-UP CALL', 'Enquired again — check what changed', -4, false],
        [Task::TRIGGER_EXISTING_LEAD, 'BOOKING/ APPOINTMENT/ DEMO', 'Hold the date pending payment', 30, false],
        [Task::TRIGGER_EXISTING_LEAD, 'REMINDER', 'Confirmed the revised headcount', -100, true],

        // Reminders — added by hand rather than by a campaign rule.
        [Task::TRIGGER_MANUAL, 'REMINDER', 'Send the decorator shortlist', -2, false],
        [Task::TRIGGER_MANUAL, 'CALLBACK REQUEST', 'Wants to speak to the planner directly', 8, false],
        [Task::TRIGGER_MANUAL, 'SITE VISIT', 'Second viewing, bringing family', 54, false],
    ];

    public function run(): void
    {
        // Only leads with an owner: an unassigned to-do is a reminder nobody
        // sees, and the app refuses to raise one, so the demo should not either.
        $leads = Lead::whereNotNull('assigned_to')->orderBy('id')->get();

        if ($leads->isEmpty()) {
            /*
             | Said out loud rather than returned quietly. A seeder that does
             | nothing in silence is how an ordering mistake survives — the run
             | looks clean and the screen is simply empty.
             */
            $this->command?->warn(
                'DemoTodoSeeder skipped: no assigned leads yet. It must run after DemoCrmDataSeeder.'
            );

            return;
        }

        foreach (self::PLAN as $i => [$trigger, $type, $title, $hours, $done]) {
            $lead = $leads[$i % $leads->count()];

            Task::updateOrCreate(
                // Keyed so re-seeding rewrites the same ten rows instead of
                // stacking another ten on top.
                ['lead_id' => $lead->id, 'trigger' => $trigger, 'type' => $type],
                [
                    'assigned_to' => $lead->assigned_to,
                    'integration_id' => $lead->integration_id,
                    'title' => $title,
                    'due_at' => $hours === null ? null : now()->addHours($hours),
                    'completed_at' => $done ? now()->subHours(6) : null,
                    'completed_by' => $done ? $lead->assigned_to : null,
                ]
            );
        }

        $this->command?->info('Demo to-dos seeded: '.count(self::PLAN).' across all three tabs');
    }
}
