<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\TeamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->owner = User::factory()->owner()->create();
    }

    private function teamProps(): array
    {
        return $this->actingAs($this->owner)->get('/teams')->viewData('page')['props']['teams'][0];
    }

    public function test_seeding_fills_in_the_registered_company_name(): void
    {
        $this->seed(TeamSeeder::class);

        $this->assertSame(config('company.legal_name'), Team::sole()->name);
    }

    public function test_reseeding_renames_rather_than_adding_a_second_organisation(): void
    {
        $this->seed(TeamSeeder::class);

        config(['company.legal_name' => 'Renamed Holdings Private Limited']);
        $this->seed(TeamSeeder::class);

        $this->assertSame(1, Team::count());
        $this->assertSame('Renamed Holdings Private Limited', Team::sole()->name);
    }

    public function test_reseeding_leaves_an_assigned_number_and_plan_alone(): void
    {
        $this->seed(TeamSeeder::class);

        Team::sole()->update([
            'virtual_number' => '+919403890373',
            'staff_limit' => 10,
            'lead_limit' => 10000,
        ]);

        $this->seed(TeamSeeder::class);

        $team = Team::sole();

        // These are earned, not seeded — a reseed must never wipe them.
        $this->assertSame('+919403890373', $team->virtual_number);
        $this->assertSame(10, $team->staff_limit);
        $this->assertSame(10000, $team->lead_limit);
    }

    public function test_the_number_reads_as_unassigned_until_one_is_provisioned(): void
    {
        $this->seed(TeamSeeder::class);

        $this->assertFalse($this->teamProps()['has_number']);

        Team::sole()->update(['virtual_number' => '+919403890373']);

        $props = $this->teamProps();
        $this->assertTrue($props['has_number']);
        $this->assertSame('+919403890373', $props['virtual_number']);
    }

    public function test_staff_and_lead_counts_are_live_rather_than_stored(): void
    {
        $this->seed(TeamSeeder::class);

        User::factory()->member()->count(3)->create();
        User::factory()->member()->create(['is_active' => false]);

        $customer = Customer::create(['name' => 'A', 'mobile' => '9876500001', 'last_activity_at' => now()]);
        Lead::create(['customer_id' => $customer->id, 'name' => 'A', 'mobile' => '9876500001', 'source' => 'facebook']);

        $props = $this->teamProps();

        // Owner + 3 active members; the deactivated one does not count.
        $this->assertSame(4, $props['staff_count']);
        $this->assertSame(1, $props['leads_count']);
    }

    public function test_a_member_cannot_see_the_organisation_screen(): void
    {
        $this->seed(TeamSeeder::class);

        $this->actingAs(User::factory()->member()->create())
            ->get('/teams')
            ->assertForbidden();
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/teams')->assertRedirect('/login');
    }

    public function test_teams_and_team_members_stay_separate_screens(): void
    {
        $this->seed(TeamSeeder::class);

        // One letter apart in the URL; they must not resolve to each other.
        $this->actingAs($this->owner)->get('/teams')->assertOk()->assertSee('teams', escape: false);
        $this->actingAs($this->owner)->get('/team')->assertOk()->assertSee('members', escape: false);
    }
}
