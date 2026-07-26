<?php

namespace Tests\Feature\Crm;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Note;
use App\Models\User;
use App\Services\Crm\NoteService;
use Database\Seeders\CrmSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Notes on a contact, and the rule for which enquiry they belong to.
 *
 * The rule exists so nothing is left hanging: a note always lands on the
 * person, and it is only tied to an enquiry when there is no doubt which one.
 */
class ContactNoteTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->seed(CrmSettingsSeeder::class);

        $this->member = User::factory()->member()->create();

        $this->customer = Customer::create([
            'name' => 'Asha Rao',
            'mobile' => '9876500001',
            'last_activity_at' => now()->subMonth(),
        ]);
    }

    private function addLead(string $campaign): Lead
    {
        return Lead::create([
            'customer_id' => $this->customer->id,
            'name' => $this->customer->name,
            'mobile' => $this->customer->mobile,
            'source' => 'facebook',
            'campaign' => $campaign,
            'lead_stage_id' => LeadStage::where('type', 'INITIAL')->value('id'),
            'assigned_to' => $this->member->id,
        ]);
    }

    private function write(array $payload, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->member)
            ->post("/customers/{$this->customer->id}/notes", $payload);
    }

    /* ── Where a note lands ───────────────────────────── */

    public function test_with_no_leads_a_note_is_saved_against_the_contact(): void
    {
        $this->write(['body' => 'Rang in, asked for a callback.'])->assertSessionHasNoErrors();

        $note = Note::sole();

        $this->assertSame($this->customer->id, $note->customer_id);
        $this->assertNull($note->lead_id);
        $this->assertSame($this->member->id, $note->user_id);
    }

    public function test_with_one_lead_a_note_defaults_to_that_lead(): void
    {
        $lead = $this->addLead('Bangalore Packages');

        // No lead named: with only one enquiry there is nothing to ask about.
        $this->write(['body' => 'Wants a quote.'])->assertSessionHasNoErrors();

        $this->assertSame($lead->id, Note::sole()->lead_id);
    }

    public function test_with_one_lead_a_note_can_still_be_left_against_the_contact(): void
    {
        $this->addLead('Bangalore Packages');

        $this->write([
            'body' => 'Changed their number.',
            'lead_id' => NoteService::ABOUT_CONTACT,
        ])->assertSessionHasNoErrors();

        $this->assertNull(Note::sole()->lead_id);
    }

    public function test_with_several_leads_the_note_must_say_which_one(): void
    {
        $this->addLead('Bangalore Packages');
        $this->addLead('Goa Packages');

        // Guessing here would file the note where nobody looks for it.
        $this->write(['body' => 'Following up.'])->assertSessionHasErrors('lead_id');

        $this->assertSame(0, Note::count());
    }

    public function test_with_several_leads_a_note_can_still_be_general(): void
    {
        $this->addLead('Bangalore Packages');
        $this->addLead('Goa Packages');

        $this->write([
            'body' => 'Prefers WhatsApp.',
            'lead_id' => NoteService::ABOUT_CONTACT,
        ])->assertSessionHasNoErrors();

        $this->assertNull(Note::sole()->lead_id);
    }

    public function test_a_note_cannot_be_filed_against_someone_elses_lead(): void
    {
        $other = Customer::create([
            'name' => 'Someone Else', 'mobile' => '9876500002', 'last_activity_at' => now(),
        ]);

        $theirs = Lead::create([
            'customer_id' => $other->id,
            'name' => $other->name,
            'mobile' => $other->mobile,
            'source' => 'facebook',
            'lead_stage_id' => LeadStage::where('type', 'INITIAL')->value('id'),
        ]);

        $this->addLead('Bangalore Packages');

        $this->write(['body' => 'Nope.', 'lead_id' => (string) $theirs->id])
            ->assertSessionHasErrors('lead_id');

        $this->assertSame(0, Note::count());
    }

    public function test_an_empty_note_is_refused(): void
    {
        $this->write(['body' => '   '])->assertSessionHasErrors('body');

        $this->assertSame(0, Note::count());
    }

    /* ── What a note does to the record ───────────────── */

    public function test_writing_a_note_counts_as_activity_on_the_contact(): void
    {
        // Otherwise a well-worked contact looks abandoned on the list.
        $this->write(['body' => 'Spoke at length.']);

        $this->assertTrue($this->customer->fresh()->last_activity_at->isToday());
    }

    public function test_a_note_survives_its_lead_being_removed(): void
    {
        $lead = $this->addLead('Bangalore Packages');

        $this->write(['body' => 'Wants a quote.']);
        $lead->delete();

        /*
         | Leads are archived rather than erased, so the note keeps its lead_id
         | and simply stops finding a lead to name. It falls back to reading as
         | a note about the contact — still there, still attributed, still on
         | the page. That is the point of filing every note under the person.
         */
        $props = $this->actingAs($this->member)
            ->get("/customers/{$this->customer->id}")
            ->viewData('page')['props'];

        $this->assertCount(1, $props['notes']);
        $this->assertNull($props['notes'][0]['lead']);
        $this->assertSame($this->customer->id, Note::sole()->customer_id);
    }

    /* ── Reading ──────────────────────────────────────── */

    public function test_the_contact_page_carries_its_notes(): void
    {
        $lead = $this->addLead('Bangalore Packages');
        $this->write(['body' => 'Wants a quote.']);

        $props = $this->actingAs($this->member)
            ->get("/customers/{$this->customer->id}")
            ->viewData('page')['props'];

        $this->assertCount(1, $props['notes']);
        $this->assertSame('Bangalore Packages', $props['notes'][0]['lead']);
        $this->assertSame($this->member->name, $props['notes'][0]['author']);
        $this->assertSame(1, $props['customer']['notes_count']);

        $this->assertNotNull($lead->fresh());
    }

    public function test_the_composer_is_told_which_leads_it_may_choose_from(): void
    {
        $this->addLead('Bangalore Packages');
        $this->addLead('Goa Packages');

        $body = $this->actingAs($this->member)
            ->getJson("/customers/{$this->customer->id}/notes")
            ->assertOk()
            ->json();

        $this->assertCount(2, $body['leads']);
        $this->assertSame('Goa Packages', $body['leads'][0]['label']);
    }

    /* ── Who may do what ──────────────────────────────── */

    public function test_a_member_can_edit_their_own_note_but_not_anothers(): void
    {
        $this->write(['body' => 'Mine.']);
        $note = Note::sole();

        $this->actingAs($this->member)
            ->patch("/notes/{$note->id}", ['body' => 'Mine, corrected.'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Mine, corrected.', $note->fresh()->body);

        $this->actingAs(User::factory()->member()->create())
            ->patch("/notes/{$note->id}", ['body' => 'Not theirs to change.'])
            ->assertForbidden();

        $this->assertSame('Mine, corrected.', $note->fresh()->body);
    }

    public function test_a_member_cannot_delete_a_note_and_an_owner_can(): void
    {
        $this->write(['body' => 'Keep me.']);
        $note = Note::sole();

        // Members must not be able to delete anything.
        $this->actingAs($this->member)->delete("/notes/{$note->id}")->assertForbidden();
        $this->assertSame(1, Note::count());

        $this->actingAs(User::factory()->owner()->create())
            ->delete("/notes/{$note->id}")
            ->assertSessionHasNoErrors();

        $this->assertSame(0, Note::count());
    }

    public function test_a_guest_cannot_read_or_write_notes(): void
    {
        $this->get("/customers/{$this->customer->id}/notes")->assertRedirect('/login');
        $this->post("/customers/{$this->customer->id}/notes", ['body' => 'Hi'])->assertRedirect('/login');

        $this->assertSame(0, Note::count());
    }
}
