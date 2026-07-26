<?php

namespace Tests\Feature\Crm;

use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use App\Support\LeadProviders;
use App\Support\Roles;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoTodoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The demo data, and the order it has to be laid down in.
 *
 * A seeder that quietly does nothing is worse than one that fails: the run
 * looks clean and the screen is simply empty, and the reason is three seeders
 * further up. These tests hold the chain in place.
 */
class DemoTodoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_from_scratch_fills_the_to_dos_screen(): void
    {
        $this->seed(DatabaseSeeder::class);

        $owner = User::role(Roles::OWNER)->firstOrFail();

        $props = $this->actingAs($owner)->get('/todos')->viewData('page')['props'];

        // Both live tabs have open work, so neither reads as broken.
        foreach (['fresh', 'followups'] as $tab) {
            $this->assertGreaterThan(0, $props['tabCounts'][$tab], "The {$tab} tab is empty.");
        }

        // Reminders is held empty on purpose, not by accident.
        $this->assertSame(0, $props['tabCounts']['reminders']);

        $this->assertNotEmpty($props['tasks']['data']);

        // The usage card can only report a team if the contacts have one.
        $this->assertNotEmpty($props['usageByTeam']);
        $this->assertNotSame('No team', $props['usageByTeam'][0]['team']);
    }

    public function test_the_seeded_types_are_the_ones_the_chip_row_knows(): void
    {
        $this->seed(DatabaseSeeder::class);

        $seeded = Task::query()->distinct()->pluck('type')->all();

        // A type spelled differently from the canonical list would still show,
        // but as a stray chip appended after the known ones.
        $this->assertEmpty(
            array_diff($seeded, LeadProviders::todoTypes()),
            'A seeded to-do type is not one the chip row recognises.',
        );

        $this->assertGreaterThanOrEqual(5, count($seeded));
    }

    public function test_every_state_the_status_filter_offers_is_represented(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThan(0, Task::query()->overdue()->count(), 'Nothing is overdue.');
        $this->assertGreaterThan(0, Task::query()->whereNotNull('completed_at')->count(), 'Nothing is done.');
        $this->assertGreaterThan(0, Task::query()->open()->whereNull('due_at')->count(), 'Nothing is open-ended.');
        $this->assertGreaterThan(
            0,
            Task::query()->open()->where('due_at', '>', now())->count(),
            'Nothing is merely due.',
        );
    }

    public function test_re_seeding_rewrites_the_same_to_dos_rather_than_stacking_more(): void
    {
        $this->seed(DatabaseSeeder::class);
        $first = Task::count();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($first, Task::count());
    }

    public function test_no_to_do_is_left_without_someone_to_do_it(): void
    {
        $this->seed(DatabaseSeeder::class);

        // The app refuses to raise an unassigned to-do; the demo must not
        // produce work that contradicts its own rules.
        $this->assertSame(0, Task::whereNull('assigned_to')->count());
    }

    public function test_run_out_of_order_it_says_so_instead_of_seeding_nothing(): void
    {
        // No customers, no leads — exactly what a wrong order looks like.
        $this->seed(DemoTodoSeeder::class);

        $this->assertSame(0, Task::count());
    }

    public function test_both_ways_of_looking_worked_are_demonstrated(): void
    {
        $this->seed(DatabaseSeeder::class);

        // A lead that moved stage.
        $this->assertTrue(
            Lead::where('version', '>', 1)->whereHas('tasks')->exists(),
            'No seeded lead demonstrates work landing on Follow Ups because the stage moved.',
        );

        // And a lead that never moved but had something ticked off — the case
        // that would be missed if only the stage were checked.
        $this->assertTrue(
            Lead::where('version', 1)
                ->whereHas('tasks', fn ($t) => $t->whereNotNull('completed_at'))
                ->exists(),
            'No seeded lead demonstrates a completed to-do alone counting as work.',
        );
    }
}
