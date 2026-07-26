<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\CrmSettingsSeeder;
use Database\Seeders\TeamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What a member can and cannot do.
 *
 * Written as evidence rather than assumption: a member must be able to work
 * everything assigned to them, and must not be able to delete anything at all.
 */
class MemberCapabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    private User $other;

    private Lead $mine;

    private Task $myTask;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->seed(CrmSettingsSeeder::class);
        $this->seed(TeamSeeder::class);

        $this->member = User::factory()->member()->create(['name' => 'Member']);
        $this->other = User::factory()->member()->create(['name' => 'Someone Else']);

        $customer = Customer::create([
            'name' => 'Asha Rao',
            'mobile' => '9876500001',
            'last_activity_at' => now(),
        ]);

        $this->mine = Lead::create([
            'customer_id' => $customer->id,
            'name' => 'Asha Rao',
            'mobile' => '9876500001',
            'source' => 'facebook',
            'lead_stage_id' => LeadStage::where('type', 'INITIAL')->value('id'),
            'assigned_to' => $this->member->id,
        ]);

        $this->myTask = Task::create([
            'lead_id' => $this->mine->id,
            'assigned_to' => $this->member->id,
            'type' => 'FIRST CALL',
            'title' => 'Call Asha',
            'due_at' => now()->addHour(),
        ]);
    }

    /* ── What a member must be able to do ─────────────── */

    public function test_a_member_can_open_the_screens_they_work_from(): void
    {
        foreach (['/dashboard', '/leads', '/todos', '/customers', '/profile'] as $path) {
            $this->actingAs($this->member)->get($path)->assertOk();
        }
    }

    public function test_a_member_can_move_their_own_lead_along(): void
    {
        $next = LeadStage::where('type', 'FINAL_POSITIVE')->firstOrFail();

        $this->actingAs($this->member)
            ->patch("/leads/{$this->mine->id}/status", [
                'version' => $this->mine->fresh()->version,
                'lead_stage_id' => $next->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($next->id, $this->mine->fresh()->lead_stage_id);
    }

    public function test_a_member_can_complete_and_reopen_their_own_task(): void
    {
        $this->actingAs($this->member)
            ->patch("/todos/{$this->myTask->id}/complete")
            ->assertSessionHasNoErrors();

        $this->assertNotNull($this->myTask->fresh()->completed_at);

        $this->actingAs($this->member)->patch("/todos/{$this->myTask->id}/reopen");

        $this->assertNull($this->myTask->fresh()->completed_at);
    }

    public function test_a_member_can_add_a_contact(): void
    {
        $this->actingAs($this->member)
            ->post('/customers', [
                'name' => 'Walk In',
                'phones' => ['9000000123'],
                'emails' => [],
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Customer::where('name', 'Walk In')->exists());
    }

    public function test_a_member_can_export_their_own_leads(): void
    {
        $this->actingAs($this->member)->get('/leads/export')->assertOk();
    }

    /* ── What a member must not be able to do ─────────── */

    public function test_a_member_cannot_delete_anything(): void
    {
        $tag = Tag::first() ?? Tag::create(['name' => 'X', 'color' => '#000']);
        $stage = LeadStage::first();
        $integration = Integration::create(['name' => 'I', 'provider' => 'facebook', 'status' => 'active']);

        $deletions = [
            'a team member' => ['delete', '/team/'.$this->other->id],
            'a tag' => ['delete', '/settings/tags/'.$tag->id],
            'a lead stage' => ['delete', '/settings/lead-stages/'.$stage->id],
            'an integration' => ['delete', '/settings/integrations/'.$integration->id],
            'the facebook token' => ['delete', '/settings/facebook-token'],
        ];

        foreach ($deletions as $what => [$verb, $path]) {
            $this->actingAs($this->member)
                ->{$verb}($path)
                ->assertForbidden("A member managed to delete {$what}.");
        }

        // Everything survived.
        $this->assertNotSoftDeleted($this->other);
        $this->assertTrue(Tag::whereKey($tag->id)->exists());
        $this->assertTrue(Integration::whereKey($integration->id)->exists());
    }

    public function test_a_member_cannot_merge_customers_away(): void
    {
        $target = Customer::create(['name' => 'A', 'mobile' => '9000000001', 'last_activity_at' => now()]);
        $dupe = Customer::create(['name' => 'B', 'mobile' => '9000000002', 'last_activity_at' => now()]);

        // Merging soft-deletes the loser, so it is a deletion in all but name.
        $this->actingAs($this->member)
            ->post("/customers/{$target->id}/merge", ['duplicate_ids' => [$dupe->id]])
            ->assertForbidden();

        $this->assertNotSoftDeleted($dupe);
    }

    public function test_a_member_cannot_reach_owner_only_screens(): void
    {
        foreach (['/settings', '/activity', '/teams'] as $path) {
            $this->actingAs($this->member)->get($path)->assertForbidden();
        }
    }

    public function test_a_member_cannot_touch_work_assigned_to_someone_else(): void
    {
        $theirs = Task::create([
            'lead_id' => $this->mine->id,
            'assigned_to' => $this->other->id,
            'type' => 'FIRST CALL',
            'title' => 'Not mine',
        ]);

        $this->actingAs($this->member)
            ->patch("/todos/{$theirs->id}/complete")
            ->assertForbidden();

        $this->assertNull($theirs->fresh()->completed_at);
    }

    public function test_a_member_cannot_add_or_change_team_members(): void
    {
        $this->actingAs($this->member)
            ->post('/team', [
                'name' => 'Sneaky', 'email' => 's@example.com',
                'mobile' => '9000000999', 'role' => 'owner',
            ])
            ->assertForbidden();

        $this->actingAs($this->member)
            ->patch('/team/'.$this->other->id, [
                'name' => 'Renamed', 'email' => $this->other->email,
                'mobile' => $this->other->mobile, 'role' => 'member',
            ])
            ->assertForbidden();
    }

    public function test_a_member_cannot_change_settings(): void
    {
        $this->actingAs($this->member)
            ->put('/settings/notifications', ['new_lead_email' => true])
            ->assertForbidden();

        $this->actingAs($this->member)
            ->post('/settings/tags', ['name' => 'Mine', 'color' => '#000000'])
            ->assertForbidden();
    }
}
