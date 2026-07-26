<?php

namespace Tests\Feature\Crm;

use App\Models\Customer;
use App\Models\CustomerChannel;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Task;
use App\Models\User;
use App\Services\Crm\CustomerService;
use Database\Seeders\CrmSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Creating a contact by hand, and the thing that makes it worth doing: every
 * number and address counts when deciding whether we already know this person.
 */
class CreateContactTest extends TestCase
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

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Asha Rao',
            'phones' => ['9876500001'],
            'emails' => [],
        ], $overrides);
    }

    /**
     * assertSessionHasNoErrors alone is not enough: a 500 carries no session
     * errors either, so a crash reads as a pass and the assertions afterwards
     * fail somewhere confusing. This caught exactly that.
     */
    private function submit(array $overrides = [])
    {
        $response = $this->actingAs($this->owner)->post('/customers', $this->payload($overrides));

        $this->assertLessThan(500, $response->getStatusCode(), 'The request errored on the server.');

        return $response;
    }

    /* ── Creating ─────────────────────────────────────── */

    public function test_a_contact_is_created_with_its_first_number_as_primary(): void
    {
        $this->submit(['phones' => ['9876500001', '9876500002']])->assertSessionHasNoErrors();

        $customer = Customer::sole();

        $this->assertSame('Asha Rao', $customer->name);
        $this->assertSame('9876500001', $customer->mobile);
        $this->assertSame(2, $customer->phones()->count());
        $this->assertSame('9876500001', $customer->phones()->where('is_primary', true)->value('value'));
    }

    public function test_several_emails_are_kept_with_the_first_as_primary(): void
    {
        $this->submit(['emails' => ['work@example.com', 'home@example.com']])->assertSessionHasNoErrors();

        $customer = Customer::sole();

        $this->assertSame('work@example.com', $customer->email);
        $this->assertSame(2, $customer->emails()->count());
    }

    public function test_a_contact_needs_at_least_one_usable_number(): void
    {
        $this->submit(['phones' => ['12345']])->assertSessionHasErrors();

        $this->assertSame(0, Customer::count());
    }

    public function test_numbers_are_normalised_before_they_are_stored(): void
    {
        $this->submit(['phones' => ['+91 98765-00001']])->assertSessionHasNoErrors();

        $this->assertSame('9876500001', Customer::sole()->mobile);
    }

    public function test_emails_are_lower_cased(): void
    {
        $this->submit(['emails' => ['Asha.Rao@Example.COM']])->assertSessionHasNoErrors();

        $this->assertSame('asha.rao@example.com', Customer::sole()->email);
    }

    /* ── The point of it: duplicate detection across every channel ── */

    public function test_a_second_number_on_an_existing_contact_finds_them_rather_than_making_a_new_one(): void
    {
        $this->submit(['phones' => ['9876500001', '9876500002']]);

        // Someone re-enters them, this time leading with the alternate number.
        $this->submit(['name' => 'Asha R', 'phones' => ['9876500002']])->assertSessionHasNoErrors();

        $this->assertSame(1, Customer::count(), 'The alternate number should have matched the existing contact.');
    }

    public function test_a_matching_email_finds_them_even_when_the_number_is_new(): void
    {
        $this->submit(['emails' => ['asha@example.com']]);

        $this->submit(['phones' => ['9999900123'], 'emails' => ['asha@example.com']])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Customer::count());

        // The new number is recorded against them, so it matches next time too.
        $this->assertTrue(
            Customer::sole()->phones()->where('value', '9999900123')->exists(),
        );
    }

    public function test_an_arriving_campaign_lead_matches_on_a_secondary_number(): void
    {
        $this->submit(['phones' => ['9876500001', '9876500002']]);

        // A Facebook lead comes in on the second number.
        $resolved = app(CustomerService::class)->resolve('9876500002', null, 'Asha Rao');

        $this->assertSame(Customer::sole()->id, $resolved->id);
        $this->assertSame(1, Customer::count());
    }

    public function test_one_number_cannot_belong_to_two_contacts(): void
    {
        $this->submit(['phones' => ['9876500001']]);
        $this->submit(['name' => 'Someone Else', 'phones' => ['9876500001']]);

        $this->assertSame(1, Customer::count());
        $this->assertSame(1, CustomerChannel::where('value', '9876500001')->count());
    }

    public function test_re_entering_a_known_contact_fills_blanks_without_overwriting(): void
    {
        $this->submit(['city' => 'Bengaluru']);

        $this->submit(['city' => 'Mumbai', 'website' => 'vvt.example']);

        $customer = Customer::sole();

        $this->assertSame('Bengaluru', $customer->city, 'An existing value should not be overwritten.');
        $this->assertSame('vvt.example', $customer->website, 'A blank should be filled.');
    }

    public function test_merging_carries_the_numbers_across_so_they_keep_matching(): void
    {
        $this->submit(['phones' => ['9876500001']]);
        $this->submit(['name' => 'Asha Duplicate', 'phones' => ['9876500009']]);

        [$target, $duplicate] = Customer::orderBy('id')->get()->all();

        app(CustomerService::class)->merge($target, [$duplicate->id]);

        // The merged-away number must still find the survivor, or the next
        // enquiry from it would create a third record.
        $found = app(CustomerService::class)->findByChannel(CustomerChannel::PHONE, '9876500009');

        $this->assertNotNull($found);
        $this->assertSame($target->id, $found->id);
    }

    /* ── Lead and task ────────────────────────────────── */

    public function test_no_lead_is_opened_without_a_stage(): void
    {
        $this->submit()->assertSessionHasNoErrors();

        $this->assertSame(0, Lead::count());
    }

    public function test_choosing_a_stage_opens_a_lead(): void
    {
        $stage = LeadStage::where('type', 'INITIAL')->firstOrFail();

        $this->submit([
            'lead_stage_id' => $stage->id,
            'assigned_to' => $this->owner->id,
            'source' => 'Walk-in',
            'source_type' => 'Phone Contact',
            'deal_value' => 25000,
        ])->assertSessionHasNoErrors();

        $lead = Lead::sole();

        $this->assertSame($stage->id, $lead->lead_stage_id);
        $this->assertSame($this->owner->id, $lead->assigned_to);
        $this->assertSame('Phone Contact', $lead->source_type);
        $this->assertSame('25000.00', $lead->deal_value);
        // Typed in by hand, so it is not unread work.
        $this->assertNotNull($lead->viewed_at);
    }

    public function test_a_task_is_raised_when_asked_for(): void
    {
        $stage = LeadStage::where('type', 'INITIAL')->firstOrFail();

        $this->submit([
            'lead_stage_id' => $stage->id,
            'assigned_to' => $this->owner->id,
            'create_task' => true,
            'task_type' => 'FIRST CALL',
            'task_note' => 'Ring about Bangalore packages',
            'task_due_at' => now()->addDay()->toDateTimeString(),
        ])->assertSessionHasNoErrors();

        $task = Task::sole();

        $this->assertSame('FIRST CALL', $task->type);
        $this->assertSame('Ring about Bangalore packages', $task->title);
        $this->assertSame(Lead::sole()->id, $task->lead_id);
    }

    public function test_an_incomplete_task_is_refused(): void
    {
        $stage = LeadStage::where('type', 'INITIAL')->firstOrFail();

        $this->submit([
            'lead_stage_id' => $stage->id,
            'create_task' => true,
            // type and due date deliberately missing
        ])->assertSessionHasErrors(['task_type', 'task_due_at']);
    }

    public function test_a_task_without_a_lead_is_quietly_dropped_rather_than_failing(): void
    {
        // No stage, so no lead — the task has nothing to hang on.
        $this->submit([
            'create_task' => true,
            'task_type' => 'FIRST CALL',
            'task_due_at' => now()->addDay()->toDateTimeString(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Customer::count());
        $this->assertSame(0, Task::count());
    }

    public function test_a_guest_cannot_create_a_contact(): void
    {
        $this->post('/customers', $this->payload())->assertRedirect('/login');

        $this->assertSame(0, Customer::count());
    }
}
