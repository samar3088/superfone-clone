<?php

namespace Tests\Feature\Crm;

use App\Models\Customer;
use App\Models\Integration;
use App\Models\LeadStage;
use App\Models\User;
use App\Services\Crm\LeadService;
use Database\Seeders\CrmSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * A contact's name, held three ways that must never disagree.
 *
 * Every screen, export and email reads `name`. The client's import template
 * reads FIRST NAME and LAST NAME. Both are stored, and a saving hook on the
 * model keeps them in step whichever side a caller writes — so no path can set
 * one and forget the others.
 */
class ContactNameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->seed(CrmSettingsSeeder::class);
    }

    /* ── Whichever side is written ────────────────────── */

    public function test_a_whole_name_goes_wholly_into_the_first_name(): void
    {
        $customer = Customer::create([
            'name' => 'Asha Rao', 'mobile' => '9876500001', 'last_activity_at' => now(),
        ]);

        /*
         | Not split. A whole name is not a split, and there is no rule that
         | finds one — so the last name stays empty until something actually
         | states it, rather than carrying a guess that reads as fact.
         */
        $this->assertSame('Asha Rao', $customer->first_name);
        $this->assertNull($customer->last_name);
        $this->assertSame('Asha Rao', $customer->name);
    }

    public function test_two_halves_build_the_whole_name(): void
    {
        $customer = Customer::create([
            'first_name' => 'Asha', 'last_name' => 'Rao',
            'mobile' => '9876500001', 'last_activity_at' => now(),
        ]);

        $this->assertSame('Asha Rao', $customer->name);
    }

    public function test_a_three_part_name_is_not_carved_up(): void
    {
        $customer = Customer::create([
            'name' => 'Asha Devi Rao', 'mobile' => '9876500001', 'last_activity_at' => now(),
        ]);

        // Is Devi a middle name or half the surname? Unknowable, so unguessed.
        $this->assertSame('Asha Devi Rao', $customer->first_name);
        $this->assertNull($customer->last_name);
    }

    public function test_a_one_word_name_leaves_the_last_half_empty(): void
    {
        $customer = Customer::create([
            'name' => 'Sunita', 'mobile' => '9876500001', 'last_activity_at' => now(),
        ]);

        // Truthful rather than inventing a surname.
        $this->assertSame('Sunita', $customer->first_name);
        $this->assertNull($customer->last_name);
        $this->assertSame('Sunita', $customer->name);
    }

    public function test_editing_either_side_keeps_the_other_in_step(): void
    {
        $customer = Customer::create([
            'first_name' => 'Asha', 'last_name' => 'Rao',
            'mobile' => '9876500001', 'last_activity_at' => now(),
        ]);

        $customer->update(['last_name' => 'Sharma']);
        $this->assertSame('Asha Sharma', $customer->fresh()->name);

        // Writing the whole name replaces both halves — the stated split is
        // gone, so keeping half of it would leave the record self-contradictory.
        $customer->update(['name' => 'Ravi Kumar']);
        $this->assertSame('Ravi Kumar', $customer->fresh()->first_name);
        $this->assertNull($customer->fresh()->last_name);
    }

    public function test_a_split_that_does_not_round_trip_is_kept_as_given(): void
    {
        /*
         | "Rao" / "Asha Devi" joins to "Rao Asha Devi", which splitting would
         | read as "Rao Asha" / "Devi". Storing both halves is what lets an
         | unusual split survive — deriving it on the way out could not.
         */
        $customer = Customer::create([
            'first_name' => 'Rao', 'last_name' => 'Asha Devi',
            'mobile' => '9876500001', 'last_activity_at' => now(),
        ]);

        $this->assertSame('Rao', $customer->fresh()->first_name);
        $this->assertSame('Asha Devi', $customer->fresh()->last_name);
        $this->assertSame('Rao Asha Devi', $customer->fresh()->name);
    }

    public function test_a_contact_is_never_left_nameless_by_a_half_written_pair(): void
    {
        $customer = Customer::create([
            'name' => 'Asha Rao', 'mobile' => '9876500001', 'last_activity_at' => now(),
        ]);

        // Breaking the write-them-as-a-pair rule must not blank the record.
        $customer->update(['first_name' => '', 'last_name' => '']);

        $this->assertNotSame('', $customer->fresh()->name);
    }

    /* ── The sources that write names ─────────────────── */

    public function test_a_facebook_form_that_sends_both_halves_keeps_them(): void
    {
        Mail::fake();

        $lead = app(LeadService::class)->intake($this->campaign(), [
            'external_id' => 'x1',
            'name' => 'Asha Devi Rao',
            'first_name' => 'Asha',
            'last_name' => 'Devi Rao',
            'mobile' => '9876500001',
            'created_time' => now()->toIso8601String(),
        ]);

        // The form said where the split is, so we do not second-guess it.
        $this->assertSame('Asha', $lead->customer->first_name);
        $this->assertSame('Devi Rao', $lead->customer->last_name);
    }

    public function test_a_facebook_form_that_sends_only_a_whole_name_leaves_the_surname_empty(): void
    {
        Mail::fake();

        $lead = app(LeadService::class)->intake($this->campaign(), [
            'external_id' => 'x2',
            'name' => 'Ravi Kumar',
            'mobile' => '9876500002',
            'created_time' => now()->toIso8601String(),
        ]);

        // The form gave one field, so we record one field.
        $this->assertSame('Ravi Kumar', $lead->customer->first_name);
        $this->assertNull($lead->customer->last_name);
    }

    public function test_the_create_contact_form_writes_both_halves(): void
    {
        $this->actingAs(User::factory()->owner()->create())
            ->post('/customers', [
                'first_name' => 'Asha',
                'last_name' => 'Rao',
                'phones' => ['9876500001'],
                'emails' => [],
            ])
            ->assertSessionHasNoErrors();

        $customer = Customer::sole();

        $this->assertSame('Asha', $customer->first_name);
        $this->assertSame('Rao', $customer->last_name);
        $this->assertSame('Asha Rao', $customer->name);
    }

    public function test_a_contact_needs_a_first_name_but_not_a_last(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->post('/customers', ['first_name' => '', 'phones' => ['9876500001'], 'emails' => []])
            ->assertSessionHasErrors('first_name');

        // Plenty of contacts are one word — a business, a mononym.
        $this->actingAs($owner)
            ->post('/customers', ['first_name' => 'Sunita', 'phones' => ['9876500001'], 'emails' => []])
            ->assertSessionHasNoErrors();

        $this->assertSame('Sunita', Customer::sole()->name);
    }

    private function campaign(): Integration
    {
        return Integration::create([
            'name' => 'Campaign',
            'provider' => 'facebook',
            'status' => 'active',
            'external_page_id' => 'page_1',
            'external_form_id' => 'form_1',
            'form_name' => 'Campaign',
            'source' => 'Facebook',
            'lead_stage_id' => LeadStage::where('type', 'INITIAL')->value('id'),
        ]);
    }
}
