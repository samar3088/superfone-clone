<?php

namespace Tests\Feature\Crm;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\CrmSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The list filters, exercised through the query string the UI actually builds.
 *
 * The case that matters most is the last one: a member must not be able to
 * widen their own scope by hand-editing the URL.
 */
class TableFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $alice;

    private User $bob;

    private LeadStage $won;

    private LeadStage $newStage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->seed(CrmSettingsSeeder::class);

        $this->owner = User::factory()->owner()->create();
        $this->alice = User::factory()->member()->create(['name' => 'Alice']);
        $this->bob = User::factory()->member()->create(['name' => 'Bob']);

        $this->newStage = LeadStage::where('type', 'INITIAL')->firstOrFail();
        $this->won = LeadStage::where('type', 'FINAL_POSITIVE')->firstOrFail();

        $this->lead('Old Alice Lead', $this->alice, $this->newStage, '2026-01-10');
        $this->lead('New Alice Lead', $this->alice, $this->won, '2026-07-20');
        $this->lead('Bob Lead', $this->bob, $this->newStage, '2026-07-20');
        $this->lead('Nobody Lead', null, $this->newStage, '2026-07-20');
    }

    private function lead(string $name, ?User $owner, LeadStage $stage, string $date): Lead
    {
        static $n = 0;
        $n++;

        $customer = Customer::create([
            'name' => $name,
            'mobile' => '98765'.str_pad((string) $n, 5, '0', STR_PAD_LEFT),
            'last_activity_at' => $date,
        ]);

        $lead = Lead::create([
            'customer_id' => $customer->id,
            'name' => $name,
            'mobile' => $customer->mobile,
            'source' => 'facebook',
            'lead_stage_id' => $stage->id,
            'assigned_to' => $owner?->id,
        ]);

        $lead->forceFill(['created_at' => $date.' 10:00:00'])->save();
        $customer->forceFill(['created_at' => $date.' 10:00:00'])->save();

        return $lead;
    }

    /** @return array<int, string> lead names on the rendered page */
    private function leadNames(array $query = [], ?User $as = null): array
    {
        $response = $this->actingAs($as ?? $this->owner)->get('/leads?'.http_build_query($query));
        $response->assertOk();

        return collect($response->viewData('page')['props']['leads']['data'])
            ->pluck('name')->sort()->values()->all();
    }

    public function test_no_filter_shows_everything(): void
    {
        $this->assertCount(4, $this->leadNames());
    }

    public function test_filtering_by_member(): void
    {
        $this->assertSame(
            ['New Alice Lead', 'Old Alice Lead'],
            $this->leadNames(['member' => $this->alice->id]),
        );
    }

    public function test_filtering_by_unassigned(): void
    {
        $this->assertSame(['Nobody Lead'], $this->leadNames(['member' => 'unassigned']));
    }

    public function test_filtering_by_status(): void
    {
        $this->assertSame(['New Alice Lead'], $this->leadNames(['stage' => $this->won->id]));
    }

    public function test_filtering_by_date_range(): void
    {
        $this->assertSame(
            ['Old Alice Lead'],
            $this->leadNames(['date_from' => '2026-01-01', 'date_to' => '2026-01-31']),
        );
    }

    public function test_the_end_of_a_date_range_includes_that_whole_day(): void
    {
        // The lead sits at 10:00, so a naive "< 2026-07-20 00:00" would miss it.
        $this->assertContains(
            'Bob Lead',
            $this->leadNames(['date_from' => '2026-07-20', 'date_to' => '2026-07-20']),
        );
    }

    public function test_filters_combine_rather_than_replace_each_other(): void
    {
        $this->assertSame(
            ['New Alice Lead'],
            $this->leadNames([
                'member' => $this->alice->id,
                'stage' => $this->won->id,
                'date_from' => '2026-07-01',
            ]),
        );
    }

    public function test_a_backwards_date_range_is_rejected_rather_than_returning_nothing(): void
    {
        $this->actingAs($this->owner)
            ->get('/leads?date_from=2026-07-01&date_to=2026-01-01')
            ->assertSessionHasErrors('date_to');
    }

    public function test_a_nonsense_date_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->get('/leads?date_from=not-a-date')
            ->assertSessionHasErrors('date_from');
    }

    /* ── Export ───────────────────────────────────────── */

    public function test_the_export_returns_only_the_filtered_rows(): void
    {
        $response = $this->actingAs($this->owner)
            ->get('/leads/export?member='.$this->alice->id);

        $response->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('Old Alice Lead', $csv);
        $this->assertStringContainsString('New Alice Lead', $csv);
        $this->assertStringNotContainsString('Bob Lead', $csv);
        $this->assertStringNotContainsString('Nobody Lead', $csv);
    }

    public function test_the_customer_export_honours_filters_too(): void
    {
        $response = $this->actingAs($this->owner)
            ->get('/customers/export?date_from=2026-01-01&date_to=2026-01-31');

        $response->assertOk();
        $csv = $response->streamedContent();

        /*
         | The download carries FIRST NAME and LAST NAME so it can be imported
         | straight back. Nobody stated a split for these, so the whole name
         | sits in the first column and the second is empty.
         */
        $this->assertStringContainsString('"Old Alice Lead",', $csv);
        $this->assertStringNotContainsString('Bob Lead', $csv);
    }

    /* ── Scope ────────────────────────────────────────── */

    public function test_a_member_only_ever_sees_their_own_leads(): void
    {
        $this->assertSame(
            ['New Alice Lead', 'Old Alice Lead'],
            $this->leadNames([], $this->alice),
        );
    }

    public function test_a_member_cannot_widen_their_scope_through_the_member_filter(): void
    {
        // Alice asks for Bob's leads by hand-editing the URL.
        $this->assertSame(
            ['New Alice Lead', 'Old Alice Lead'],
            $this->leadNames(['member' => $this->bob->id], $this->alice),
        );
    }

    public function test_a_member_cannot_reach_unassigned_leads_either(): void
    {
        $this->assertSame(
            ['New Alice Lead', 'Old Alice Lead'],
            $this->leadNames(['member' => 'unassigned'], $this->alice),
        );
    }

    public function test_a_members_export_is_scoped_the_same_way(): void
    {
        $csv = $this->actingAs($this->alice)
            ->get('/leads/export?member='.$this->bob->id)
            ->streamedContent();

        $this->assertStringNotContainsString('Bob Lead', $csv);
        $this->assertStringContainsString('Old Alice Lead', $csv);
    }

    /* ── Customers ────────────────────────────────────── */

    public function test_customers_can_be_filtered_by_the_member_working_their_leads(): void
    {
        $response = $this->actingAs($this->owner)->get('/customers?member='.$this->bob->id);
        $response->assertOk();

        $names = collect($response->viewData('page')['props']['customers']['data'])->pluck('name');

        $this->assertSame(['Bob Lead'], $names->all());
    }

    /* ── Multi-select ─────────────────────────────────── */

    public function test_selecting_two_members_returns_both_of_their_leads(): void
    {
        $this->assertSame(
            ['Bob Lead', 'New Alice Lead', 'Old Alice Lead'],
            $this->leadNames(['member' => "{$this->alice->id},{$this->bob->id}"]),
        );
    }

    public function test_a_member_and_unassigned_can_be_selected_together(): void
    {
        $this->assertSame(
            ['New Alice Lead', 'Nobody Lead', 'Old Alice Lead'],
            $this->leadNames(['member' => "{$this->alice->id},unassigned"]),
        );
    }

    public function test_selecting_two_statuses_returns_both(): void
    {
        $this->assertCount(4, $this->leadNames(['stage' => "{$this->newStage->id},{$this->won->id}"]));
    }

    public function test_a_multi_select_still_narrows_rather_than_widens(): void
    {
        // Two members chosen, but only Alice's leads sit in the Won stage.
        $this->assertSame(
            ['New Alice Lead'],
            $this->leadNames([
                'member' => "{$this->alice->id},{$this->bob->id}",
                'stage' => (string) $this->won->id,
            ]),
        );
    }

    public function test_a_member_cannot_widen_their_scope_with_a_list_either(): void
    {
        $this->assertSame(
            ['New Alice Lead', 'Old Alice Lead'],
            $this->leadNames(['member' => "{$this->alice->id},{$this->bob->id},unassigned"], $this->alice),
        );
    }

    public function test_a_junk_entry_in_the_list_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->get('/leads?member=3,DROP%20TABLE')
            ->assertSessionHasErrors('member');
    }

    public function test_an_id_that_no_longer_exists_simply_matches_nothing(): void
    {
        // Not an error: a bookmark pointing at a deleted member should still load.
        $this->actingAs($this->owner)
            ->get('/leads?member=999999')
            ->assertOk()
            ->assertSessionHasNoErrors();
    }

    public function test_customers_can_be_filtered_by_several_members_at_once(): void
    {
        $response = $this->actingAs($this->owner)
            ->get('/customers?member='.$this->alice->id.','.$this->bob->id);

        $names = collect($response->viewData('page')['props']['customers']['data'])->pluck('name')->sort()->values();

        $this->assertSame(['Bob Lead', 'New Alice Lead', 'Old Alice Lead'], $names->all());
    }

    public function test_the_export_honours_a_multi_select(): void
    {
        $csv = $this->actingAs($this->owner)
            ->get('/leads/export?member='.$this->alice->id.','.$this->bob->id)
            ->streamedContent();

        $this->assertStringContainsString('Bob Lead', $csv);
        $this->assertStringContainsString('Old Alice Lead', $csv);
        $this->assertStringNotContainsString('Nobody Lead', $csv);
    }

    /* ── The dropdowns need data to render ────────────── */

    public function test_the_leads_screen_ships_the_options_its_dropdowns_need(): void
    {
        $props = $this->actingAs($this->owner)->get('/leads')->viewData('page')['props'];

        $this->assertNotEmpty($props['stages'], 'Status dropdown would be empty.');
        $this->assertContains(
            'Alice',
            collect($props['members'])->pluck('name')->all(),
            'Member dropdown would be missing members.',
        );
    }

    public function test_a_member_gets_no_member_dropdown_because_it_would_list_only_them(): void
    {
        $props = $this->actingAs($this->alice)->get('/leads')->viewData('page')['props'];

        $this->assertSame([], $props['members']);
    }

    public function test_the_customers_screen_ships_its_member_options(): void
    {
        $props = $this->actingAs($this->owner)->get('/customers')->viewData('page')['props'];

        $this->assertContains('Bob', collect($props['members'])->pluck('name')->all());
    }

    public function test_selected_filters_survive_back_to_the_screen_so_the_controls_stay_set(): void
    {
        $props = $this->actingAs($this->owner)
            ->get('/leads?member='.$this->alice->id.'&date_from=2026-01-01')
            ->viewData('page')['props'];

        $this->assertSame((string) $this->alice->id, (string) $props['filters']['member']);
        $this->assertSame('2026-01-01', $props['filters']['date_from']);
    }

    /* ── Customers: the advanced filter panel ─────────── */

    public function test_customers_can_be_filtered_by_tag(): void
    {
        $tag = Tag::create(['name' => 'VIP', 'color' => '#7c3aed']);
        $tagged = Customer::whereNotNull('id')->first();
        $tagged->tags()->attach($tag);

        $response = $this->actingAs($this->owner)->get('/customers?tags='.$tag->id);
        $names = collect($response->viewData('page')['props']['customers']['data'])->pluck('name');

        $this->assertSame([$tagged->name], $names->all());
    }

    public function test_customers_can_be_filtered_by_the_stage_of_their_leads(): void
    {
        $response = $this->actingAs($this->owner)->get('/customers?stage='.$this->won->id);
        $names = collect($response->viewData('page')['props']['customers']['data'])->pluck('name');

        // Only the customer behind the won lead.
        $this->assertSame(['New Alice Lead'], $names->all());
    }

    public function test_customers_can_be_filtered_by_who_added_them(): void
    {
        $mine = Customer::create([
            'name' => 'Typed In By Hand',
            'mobile' => '9000000077',
            'created_by' => $this->owner->id,
            'last_activity_at' => now(),
        ]);

        $response = $this->actingAs($this->owner)->get('/customers?creator='.$this->owner->id);
        $names = collect($response->viewData('page')['props']['customers']['data'])->pluck('name');

        $this->assertSame([$mine->name], $names->all());
    }

    public function test_the_panel_is_given_every_list_it_needs(): void
    {
        $props = $this->actingAs($this->owner)->get('/customers')->viewData('page')['props'];

        foreach (['teams', 'tags', 'creators', 'stages', 'groups'] as $key) {
            $this->assertArrayHasKey($key, $props['options'], "The panel needs {$key}.");
        }
    }

    public function test_a_nonsense_advanced_filter_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->get('/customers?tags=not-a-number')
            ->assertSessionHasErrors('tags');
    }

    public function test_customers_without_leads_can_be_isolated(): void
    {
        Customer::create(['name' => 'Walk In', 'mobile' => '9000000001', 'last_activity_at' => now()]);

        $response = $this->actingAs($this->owner)->get('/customers?leads=without');
        $names = collect($response->viewData('page')['props']['customers']['data'])->pluck('name');

        $this->assertSame(['Walk In'], $names->all());
    }
}
