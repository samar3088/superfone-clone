<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What a brand-new install actually produces.
 *
 * Written after the seeders ran in an order that created every account before
 * any organisation existed, leaving the whole roster teamless — invisible
 * until someone filtered by team and got nothing back.
 */
class FreshInstallTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_full_seed_leaves_nobody_without_a_team(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThan(0, User::count(), 'The seed should create users.');
        $this->assertSame(
            0,
            User::whereNull('team_id')->count(),
            'Every seeded user must belong to an organisation.',
        );
    }

    public function test_a_full_seed_creates_exactly_one_organisation_named_after_the_business(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, Team::count());
        $this->assertSame(config('company.legal_name'), Team::sole()->name);
    }

    public function test_seeding_twice_does_not_duplicate_the_organisation_or_orphan_anyone(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, Team::count());
        $this->assertSame(0, User::whereNull('team_id')->count());
    }
}
