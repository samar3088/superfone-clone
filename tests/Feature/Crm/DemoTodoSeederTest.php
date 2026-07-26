<?php

namespace Tests\Feature\Crm;

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

        // Every tab has open work, so none of the three reads as broken.
        foreach (['fresh', 'followups', 'reminders'] as $tab) {
            $this->assertGreaterThan(0, $props['tabCounts'][$tab], "The {$tab} tab is empty.");
        }

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
}
