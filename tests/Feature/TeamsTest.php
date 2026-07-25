<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\Team;
use App\Models\User;
use App\Services\Team\MemberService;
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

    /* ── Members belong to a team ─────────────────────── */

    public function test_a_new_member_joins_the_only_organisation_without_being_told_to(): void
    {
        $this->seed(TeamSeeder::class);

        $member = app(MemberService::class)->create([
            'name' => 'Ravi Kumar',
            'email' => 'ravi@example.com',
            'mobile' => '9876500055',
        ]);

        $this->assertSame(Team::sole()->id, $member->fresh()->team_id);
    }

    public function test_members_can_be_filtered_by_team(): void
    {
        $this->seed(TeamSeeder::class);

        $vvt = Team::sole();
        $other = Team::create(['name' => 'Second Org', 'status' => 'active']);

        User::factory()->member()->create(['name' => 'In VVT', 'team_id' => $vvt->id]);
        User::factory()->member()->create(['name' => 'In Other', 'team_id' => $other->id]);

        $response = $this->actingAs($this->owner)->get('/team?team='.$other->id);
        $names = collect($response->viewData('page')['props']['members']['data'])->pluck('name');

        $this->assertSame(['In Other'], $names->all());
    }

    public function test_the_team_filter_takes_several_teams_at_once(): void
    {
        $this->seed(TeamSeeder::class);

        $vvt = Team::sole();
        $other = Team::create(['name' => 'Second Org', 'status' => 'active']);

        User::factory()->member()->create(['name' => 'In VVT', 'team_id' => $vvt->id]);
        User::factory()->member()->create(['name' => 'In Other', 'team_id' => $other->id]);

        $response = $this->actingAs($this->owner)->get("/team?team={$vvt->id},{$other->id}");
        $names = collect($response->viewData('page')['props']['members']['data'])->pluck('name');

        $this->assertContains('In VVT', $names);
        $this->assertContains('In Other', $names);
    }

    public function test_the_members_screen_ships_the_team_options_and_each_row_carries_its_team(): void
    {
        $this->seed(TeamSeeder::class);
        $member = User::factory()->member()->create();

        $props = $this->actingAs($this->owner)->get('/team')->viewData('page')['props'];

        $this->assertNotEmpty($props['teams']);
        $this->assertSame(
            Team::sole()->name,
            collect($props['members']['data'])->firstWhere('id', $member->id)['team']['name'],
        );
    }

    public function test_a_user_created_before_any_team_exists_still_gets_one_later(): void
    {
        // The owner in setUp predates the seeder, which is the shape a fresh
        // install had before TeamSeeder was moved ahead of the user seeders.
        $this->assertNull($this->owner->fresh()->team_id);

        $this->seed(TeamSeeder::class);

        $this->assertSame(Team::sole()->id, User::factory()->member()->create()->team_id);
    }

    public function test_the_member_export_carries_the_team_column(): void
    {
        $this->seed(TeamSeeder::class);

        User::factory()->member()->create();

        $csv = $this->actingAs($this->owner)->get('/team/export')->streamedContent();

        $this->assertStringContainsString('Team', $csv);
        $this->assertStringContainsString(Team::sole()->name, $csv);
    }

    public function test_removing_a_team_does_not_delete_the_people_in_it(): void
    {
        $this->seed(TeamSeeder::class);

        $team = Team::create(['name' => 'Doomed Org', 'status' => 'active']);
        $member = User::factory()->member()->create(['team_id' => $team->id]);

        $team->delete();

        $this->assertNotNull($member->fresh(), 'The member should survive their team.');
        $this->assertNull($member->fresh()->team_id);
    }

    public function test_teams_and_team_members_stay_separate_screens(): void
    {
        $this->seed(TeamSeeder::class);

        // One letter apart in the URL; they must not resolve to each other.
        $this->actingAs($this->owner)->get('/teams')->assertOk()->assertSee('teams', escape: false);
        $this->actingAs($this->owner)->get('/team')->assertOk()->assertSee('members', escape: false);
    }
}
