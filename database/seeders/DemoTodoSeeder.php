<?php

namespace Database\Seeders;

use App\Models\Lead;
use App\Models\Task;
use Illuminate\Database\Seeder;

/**
 * Demo to-dos, built to exercise every part of the To-Dos screen.
 *
 * Not just "some rows": the set is chosen so that each control on the page has
 * something to do. Both live tabs are populated, every task type appears so the
 * chip row is complete, and the states cover overdue, due later, no deadline and
 * already done — which is what the status filter and the red overdue line need
 * in order to be checked at all.
 *
 * Which tab a to-do lands on is decided by its lead, not by the to-do: Fresh
 * Leads holds work on leads nobody has touched, Follow Ups holds the rest. So
 * this seeder also has to *make* some leads look worked, in both of the ways
 * that counts — a stage that has moved, and a to-do already ticked off.
 *
 * Runs last. A to-do hangs off a lead, and the lead carries the assignee and the
 * contact whose team the usage card counts, so nothing here can be seeded until
 * customers and leads exist.
 */
class DemoTodoSeeder extends Seeder
{
    /**
     * Work on leads nobody has touched — the Fresh Leads tab.
     *
     * [type, title, hours from now (negative is overdue, null is no deadline)]
     *
     * Types are spelled exactly as LeadProviders::todoTypes() spells them, so
     * the chips come out in the canonical order rather than as strays.
     */
    private const UNTOUCHED = [
        ['FIRST CALL', 'Call back about the Bengaluru package', -26],
        ['FOLLOW-UP CALL', 'Share the quote by email', 3],
        ['CALLBACK REQUEST', 'Asked to be rung after 6pm', null],
    ];

    /** Work on leads whose stage has already moved — the Follow Ups tab. */
    private const MOVED_ON = [
        ['FOLLOW-UP CALL', 'Enquired again — check what changed', -4],
        ['BOOKING/ APPOINTMENT/ DEMO', 'Hold the date pending payment', 30],
        ['SITE VISIT', 'Second viewing, bringing family', 54],
    ];

    public function run(): void
    {
        // Only leads with an owner: an unassigned to-do is a reminder nobody
        // sees, and the app refuses to raise one, so the demo should not either.
        $leads = Lead::whereNotNull('assigned_to')->orderBy('id')->get();

        if ($leads->count() < 7) {
            /*
             | Said out loud rather than returned quietly. A seeder that does
             | nothing in silence is how an ordering mistake survives — the run
             | looks clean and the screen is simply empty.
             */
            $this->command?->warn(
                'DemoTodoSeeder skipped: needs at least 7 assigned leads. It must run after DemoCrmDataSeeder.'
            );

            return;
        }

        $count = 0;

        // Left at version 1 with nothing ticked off, so they read as untouched.
        foreach (self::UNTOUCHED as $i => [$type, $title, $hours]) {
            $this->raise($leads[$i], $type, $title, $hours);
            $count++;
        }

        // Moved off the stage they arrived in — the first of the two signals.
        foreach (self::MOVED_ON as $i => [$type, $title, $hours]) {
            $lead = $leads[3 + $i];
            $lead->forceFill(['version' => 2, 'viewed_at' => now()->subDay()])->save();

            $this->raise($lead, $type, $title, $hours);
            $count++;
        }

        /*
         | The second signal, on its own. This lead has never moved stage — a
         | first call was made and nothing was agreed yet — but the completed
         | to-do is enough to say somebody has picked it up, so its open work
         | belongs under Follow Ups rather than back in the untouched pile.
         */
        $worked = $leads[6];

        $this->raise($worked, 'FIRST CALL', 'Rang and left a message', -30, done: true);
        $this->raise($worked, 'REMINDER', 'Send the decorator shortlist', -2);
        $count += 2;

        $this->command?->info("Demo to-dos seeded: {$count} across Fresh Leads and Follow Ups");
    }

    private function raise(Lead $lead, string $type, string $title, ?int $hours, bool $done = false): void
    {
        Task::updateOrCreate(
            // Keyed so re-seeding rewrites the same rows instead of stacking
            // another set on top.
            ['lead_id' => $lead->id, 'type' => $type],
            [
                'assigned_to' => $lead->assigned_to,
                'integration_id' => $lead->integration_id,
                // Still recorded honestly even though it no longer decides the
                // tab: it says which rule raised the work.
                'trigger' => $lead->is_existing ? Task::TRIGGER_EXISTING_LEAD : Task::TRIGGER_NEW_LEAD,
                'title' => $title,
                'due_at' => $hours === null ? null : now()->addHours($hours),
                'completed_at' => $done ? now()->subHours(6) : null,
                'completed_by' => $done ? $lead->assigned_to : null,
            ]
        );
    }
}
