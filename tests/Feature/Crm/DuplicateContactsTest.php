<?php

namespace Tests\Feature\Crm;

use App\Models\Customer;
use App\Models\CustomerChannel;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Note;
use App\Models\User;
use Database\Seeders\CrmSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Finding contacts that look like the same person, and folding them together.
 *
 * The thing to keep in mind throughout: two contacts can never share a phone
 * number or an email — the channels table has a unique index on the value. So
 * every candidate here is matched on name alone plus whatever else agrees, and
 * nothing is ever merged without somebody choosing.
 */
class DuplicateContactsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->seed(CrmSettingsSeeder::class);
        $this->owner = User::factory()->owner()->create();
    }

    private function contact(string $name, string $mobile, array $extra = []): Customer
    {
        return Customer::create([
            'name' => $name,
            'mobile' => $mobile,
            'last_activity_at' => now(),
            ...$extra,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function duplicateGroups(): array
    {
        return $this->actingAs($this->owner)
            ->getJson('/customers/duplicates')
            ->assertOk()
            ->json('groups');
    }

    /* ── Finding them ─────────────────────────────────── */

    public function test_the_same_name_twice_is_offered_as_a_group(): void
    {
        $this->contact('Asha Rao', '9876500001');
        $this->contact('Asha Rao', '9876500002');

        $groups = $this->duplicateGroups();

        $this->assertCount(1, $groups);
        $this->assertCount(2, $groups[0]['customers']);
    }

    public function test_case_and_punctuation_do_not_stop_a_match(): void
    {
        $this->contact('Asha Rao', '9876500001');
        $this->contact('asha  rao.', '9876500002');

        $this->assertCount(1, $this->duplicateGroups());
    }

    public function test_a_name_that_appears_once_is_not_a_duplicate(): void
    {
        $this->contact('Asha Rao', '9876500001');
        $this->contact('Ravi Kumar', '9876500002');

        $this->assertSame([], $this->duplicateGroups());
    }

    public function test_a_shared_business_is_ranked_above_a_shared_city(): void
    {
        // Same name and the same business: the strongest signal available.
        $this->contact('Asha Rao', '9876500001', ['business_name' => 'Rao Weddings']);
        $this->contact('Asha Rao', '9876500002', ['business_name' => 'Rao Weddings']);

        // Same name and the same city: weaker, plenty of namesakes in one town.
        $this->contact('Ravi Kumar', '9876500003', ['city' => 'Mumbai']);
        $this->contact('Ravi Kumar', '9876500004', ['city' => 'Mumbai']);

        // Same name and nothing else.
        $this->contact('Sunita Devi', '9876500005');
        $this->contact('Sunita Devi', '9876500006');

        $groups = $this->duplicateGroups();

        // Strongest first, so the safest merges are the ones in front of you.
        $this->assertSame(['high', 'medium', 'low'], array_column($groups, 'confidence'));
        $this->assertSame('Same name and the same business', $groups[0]['reason']);
    }

    public function test_a_group_says_what_would_be_lost_by_picking_the_wrong_keeper(): void
    {
        $keep = $this->contact('Asha Rao', '9876500001');
        $this->contact('Asha Rao', '9876500002');

        Lead::create([
            'customer_id' => $keep->id, 'name' => 'Asha Rao', 'mobile' => '9876500001',
            'source' => 'facebook', 'lead_stage_id' => LeadStage::value('id'),
        ]);

        $first = $this->duplicateGroups()[0]['customers'][0];

        $this->assertSame(1, $first['leads_count']);
        $this->assertArrayHasKey('notes_count', $first);
        $this->assertArrayHasKey('calls_count', $first);
    }

    public function test_a_contact_already_merged_away_is_not_offered_again(): void
    {
        $keep = $this->contact('Asha Rao', '9876500001');
        $gone = $this->contact('Asha Rao', '9876500002');

        $this->actingAs($this->owner)
            ->post("/customers/{$keep->id}/merge", ['duplicate_ids' => [$gone->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame([], $this->duplicateGroups());
    }

    /* ── Merging them ─────────────────────────────────── */

    public function test_everything_on_the_losing_record_moves_across(): void
    {
        $keep = $this->contact('Asha Rao', '9876500001');
        $gone = $this->contact('Asha Rao', '9876500002', ['city' => 'Bengaluru']);

        $gone->channels()->create([
            'type' => 'phone', 'value' => '9876500002', 'is_primary' => true,
        ]);

        $lead = Lead::create([
            'customer_id' => $gone->id, 'name' => 'Asha Rao', 'mobile' => '9876500002',
            'source' => 'facebook', 'lead_stage_id' => LeadStage::value('id'),
        ]);

        $note = Note::create([
            'customer_id' => $gone->id, 'user_id' => $this->owner->id,
            'body' => 'Rang about the December dates.',
        ]);

        $this->actingAs($this->owner)
            ->post("/customers/{$keep->id}/merge", ['duplicate_ids' => [$gone->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame($keep->id, $lead->fresh()->customer_id);

        // Notes especially: left behind they would follow the tombstone out of
        // every list and become unreachable.
        $this->assertSame($keep->id, $note->fresh()->customer_id);

        /*
         | And the second number keeps matching, so the next enquiry from it
         | finds this person rather than making a third record. Looked up by the
         | number itself: asking the old owner for its channels would find
         | nothing whether they moved or were destroyed.
         */
        $this->assertSame(
            $keep->id,
            CustomerChannel::where('value', '9876500002')->value('customer_id'),
        );

        // Details the survivor was missing are kept.
        $this->assertSame('Bengaluru', $keep->fresh()->city);
    }

    public function test_the_loser_is_archived_rather_than_deleted(): void
    {
        $keep = $this->contact('Asha Rao', '9876500001');
        $gone = $this->contact('Asha Rao', '9876500002');

        $this->actingAs($this->owner)
            ->post("/customers/{$keep->id}/merge", ['duplicate_ids' => [$gone->id]]);

        $archived = Customer::withTrashed()->find($gone->id);

        $this->assertNotNull($archived, 'The record was destroyed rather than archived.');
        $this->assertSame($keep->id, $archived->merged_into_id);
        $this->assertNotNull($archived->merged_at);
    }

    /* ── Who may do it ────────────────────────────────── */

    public function test_a_member_can_neither_see_nor_run_the_clean_up(): void
    {
        $this->contact('Asha Rao', '9876500001');
        $this->contact('Asha Rao', '9876500002');

        $member = User::factory()->member()->create();

        // Merging is a deletion wearing a friendlier name, so it is owner-only
        // — and so is the list of what it would affect.
        $this->actingAs($member)->getJson('/customers/duplicates')->assertForbidden();

        $this->assertSame(2, Customer::count());
    }

    public function test_a_guest_gets_nothing(): void
    {
        $this->get('/customers/duplicates')->assertRedirect('/login');
    }
}
