<?php

namespace Tests\Feature\Crm;

use App\Models\Customer;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\User;
use App\Services\Crm\DuplicateLeadService;
use App\Services\Crm\LeadService;
use App\Services\Support\SettingsService;
use App\Support\Settings;
use Database\Seeders\CrmSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The agreed repeat-enquiry rule, branch by branch.
 *
 *   same person + same campaign + inside the window   → repeat
 *   different campaign                                → fresh
 *   outside the window                                → fresh
 *   earlier enquiry already won or lost               → fresh
 */
class DuplicateLeadTest extends TestCase
{
    use RefreshDatabase;

    private Integration $campaignA;

    private Integration $campaignB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->seed(CrmSettingsSeeder::class);

        $member = User::factory()->member()->create();

        $this->campaignA = $this->campaign('Campaign A', $member);
        $this->campaignB = $this->campaign('Campaign B', $member);
    }

    private function campaign(string $name, User $member): Integration
    {
        $i = Integration::create([
            'name' => $name,
            'provider' => 'facebook',
            'status' => 'active',
            'external_page_id' => 'page_1',
            'external_form_id' => 'form_'.str($name)->slug(),
            'form_name' => $name,
            'source' => 'Facebook',
            'source_type' => 'Facebook Integration',
            'lead_stage_id' => LeadStage::where('type', 'INITIAL')->value('id'),
        ]);

        $i->members()->attach($member);

        return $i;
    }

    /** Same person every time: one mobile, one email. */
    private function arrive(Integration $on, string $at, string $externalId): Lead
    {
        return app(LeadService::class)->intake($on, [
            'external_id' => $externalId,
            'name' => 'Asha Rao',
            'mobile' => '9876500001',
            'email' => 'asha@example.com',
            'created_time' => $at,
        ], ['notify' => false]);
    }

    /* ── The four branches ────────────────────────────── */

    public function test_the_first_enquiry_is_always_fresh(): void
    {
        $lead = $this->arrive($this->campaignA, '2026-07-01T10:00:00+0000', 'a1');

        $this->assertFalse($lead->is_existing);
        $this->assertNull($lead->duplicate_of_id);
    }

    public function test_the_same_campaign_within_the_window_is_a_repeat(): void
    {
        $first = $this->arrive($this->campaignA, '2026-07-01T10:00:00+0000', 'a1');
        $second = $this->arrive($this->campaignA, '2026-07-02T10:00:00+0000', 'a2');

        $this->assertTrue($second->is_existing);
        $this->assertSame($first->id, $second->duplicate_of_id);
    }

    public function test_a_different_campaign_is_a_fresh_lead(): void
    {
        $this->arrive($this->campaignA, '2026-07-01T10:00:00+0000', 'a1');
        $other = $this->arrive($this->campaignB, '2026-07-01T11:00:00+0000', 'b1');

        $this->assertFalse($other->is_existing, 'A different campaign is new business.');
    }

    public function test_outside_the_window_is_a_fresh_lead(): void
    {
        $this->arrive($this->campaignA, '2026-07-01T10:00:00+0000', 'a1');

        // Three days later, against a two-day window.
        $later = $this->arrive($this->campaignA, '2026-07-04T10:00:00+0000', 'a2');

        $this->assertFalse($later->is_existing);
    }

    public function test_coming_back_after_a_won_sale_is_a_fresh_lead(): void
    {
        $first = $this->arrive($this->campaignA, '2026-07-01T10:00:00+0000', 'a1');
        $first->update(['lead_stage_id' => LeadStage::where('type', 'FINAL_POSITIVE')->value('id')]);

        $second = $this->arrive($this->campaignA, '2026-07-02T10:00:00+0000', 'a2');

        $this->assertFalse($second->is_existing, 'Finished business — this is a new opportunity.');
    }

    public function test_coming_back_after_a_lost_sale_is_a_fresh_lead(): void
    {
        $first = $this->arrive($this->campaignA, '2026-07-01T10:00:00+0000', 'a1');
        $first->update(['lead_stage_id' => LeadStage::where('type', 'FINAL_NEGATIVE')->value('id')]);

        $second = $this->arrive($this->campaignA, '2026-07-02T10:00:00+0000', 'a2');

        $this->assertFalse($second->is_existing);
    }

    /* ── Campaign identity ────────────────────────────── */

    public function test_two_campaigns_sharing_a_form_name_are_still_separate(): void
    {
        // The old rule keyed on the campaign *name*, which both of these share.
        $this->campaignB->update(['form_name' => 'Campaign A']);

        $this->arrive($this->campaignA, '2026-07-01T10:00:00+0000', 'a1');
        $other = $this->arrive($this->campaignB, '2026-07-01T11:00:00+0000', 'b1');

        $this->assertFalse($other->is_existing, 'Different integrations, however they are named.');
    }

    public function test_renaming_a_form_does_not_change_past_verdicts(): void
    {
        $first = $this->arrive($this->campaignA, '2026-07-01T10:00:00+0000', 'a1');

        $this->campaignA->update(['form_name' => 'Renamed On Facebook']);

        $second = $this->arrive($this->campaignA->fresh(), '2026-07-02T10:00:00+0000', 'a2');

        $this->assertTrue($second->is_existing, 'The integration is the same; only its label moved.');
        $this->assertSame($first->id, $second->duplicate_of_id);
    }

    /* ── The window is configurable ───────────────────── */

    public function test_the_window_defaults_to_two_days(): void
    {
        $this->assertSame(2, app(DuplicateLeadService::class)->windowDays());
    }

    public function test_widening_the_window_catches_a_later_return(): void
    {
        app(SettingsService::class)->set(Settings::DUPLICATE_WINDOW_DAYS, 30);

        $this->arrive($this->campaignA, '2026-07-01T10:00:00+0000', 'a1');
        $later = $this->arrive($this->campaignA, '2026-07-20T10:00:00+0000', 'a2');

        $this->assertTrue($later->is_existing);
    }

    public function test_a_zero_window_turns_repeat_detection_off(): void
    {
        app(SettingsService::class)->set(Settings::DUPLICATE_WINDOW_DAYS, 0);

        $this->arrive($this->campaignA, '2026-07-01T10:00:00+0000', 'a1');
        $second = $this->arrive($this->campaignA, '2026-07-01T10:05:00+0000', 'a2');

        $this->assertFalse($second->is_existing);
    }

    /* ── Out-of-order imports ─────────────────────────── */

    public function test_a_backfill_arriving_newest_first_is_put_right_by_the_repair(): void
    {
        // Graph hands leads back newest-first, so this is the real import order.
        $third = $this->arrive($this->campaignA, '2026-07-03T10:00:00+0000', 'a3');
        $second = $this->arrive($this->campaignA, '2026-07-02T10:00:00+0000', 'a2');
        $first = $this->arrive($this->campaignA, '2026-07-01T10:00:00+0000', 'a1');

        // Written blind, the newest looked fresh because nothing preceded it.
        $this->assertFalse($third->fresh()->is_existing);

        app(DuplicateLeadService::class)->repairFor([$first->customer_id]);

        $this->assertFalse($first->fresh()->is_existing, 'The oldest is the original.');
        $this->assertTrue($second->fresh()->is_existing);
        $this->assertTrue($third->fresh()->is_existing);
        $this->assertSame($second->id, $third->fresh()->duplicate_of_id);
    }

    public function test_the_repair_is_safe_to_run_twice(): void
    {
        $this->arrive($this->campaignA, '2026-07-01T10:00:00+0000', 'a1');
        $this->arrive($this->campaignA, '2026-07-02T10:00:00+0000', 'a2');

        $service = app(DuplicateLeadService::class);
        $customerId = Lead::first()->customer_id;

        $service->repairFor([$customerId]);

        // Nothing left to correct the second time.
        $this->assertSame(0, $service->repairFor([$customerId]));
    }

    /* ── Screen and export ────────────────────────────── */

    public function test_repeats_can_be_filtered_and_show_in_the_export(): void
    {
        $owner = User::factory()->owner()->create();

        $this->arrive($this->campaignA, '2026-07-01T10:00:00+0000', 'a1');
        $this->arrive($this->campaignA, '2026-07-02T10:00:00+0000', 'a2');

        $repeats = $this->actingAs($owner)->get('/leads?kind=existing')
            ->viewData('page')['props']['leads']['data'];
        $this->assertCount(1, $repeats);

        $fresh = $this->actingAs($owner)->get('/leads?kind=fresh')
            ->viewData('page')['props']['leads']['data'];
        $this->assertCount(1, $fresh);

        $csv = $this->actingAs($owner)->get('/leads/export')->streamedContent();
        $this->assertStringContainsString('Repeat', $csv);
        $this->assertStringContainsString('Fresh', $csv);
    }

    public function test_a_manual_lead_with_no_campaign_is_never_a_repeat(): void
    {
        $customer = Customer::create([
            'name' => 'Walk In', 'mobile' => '9000000001', 'last_activity_at' => now(),
        ]);

        $original = app(DuplicateLeadService::class)
            ->findOriginal($customer, null, Carbon::parse('2026-07-02 10:00'));

        $this->assertNull($original, 'A CSV row or phone call cannot repeat "the same form".');
    }
}
