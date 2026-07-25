<?php

namespace Tests\Feature\Crm;

use App\LeadSources\FacebookLeadSource;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\User;
use App\Services\Crm\FacebookSyncService;
use Database\Seeders\CrmSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Throwable;

/*
 | These lock down the sync's data-safety guarantees. Every case here maps to
 | something that actually went wrong during the build: leads silently dropped
 | past the first 100, a re-run importing everything twice, and a backfill
 | stamping years of history as arriving today.
 */
class FacebookSyncTest extends TestCase
{
    use RefreshDatabase;

    private Integration $integration;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CrmSettingsSeeder::class);
        $this->seedRoles();

        $this->integration = Integration::create([
            'name' => 'Test form',
            'provider' => 'facebook',
            'status' => 'active',
            'external_page_id' => 'page_1',
            'external_form_id' => 'form_1',
            'form_name' => 'Test form',
            'source' => 'Facebook',
            'source_type' => 'facebook',
            'lead_stage_id' => LeadStage::where('type', 'INITIAL')->value('id'),
        ]);

        // isConfigured() requires at least one member to route leads to.
        $this->member = User::factory()->member()->create();
        $this->integration->members()->attach($this->member);

        Http::preventStrayRequests();

        // Http::fake() merges stubs rather than replacing them, so this must
        // stay narrower than the lead stub each test adds — a catch-all here
        // would answer the lead requests too.
        Http::fake(['*/me/accounts*' => $this->accountsResponse()]);
    }

    private function accountsResponse()
    {
        return Http::response([
            'data' => [['id' => 'page_1', 'name' => 'Page One', 'access_token' => 'page-token']],
        ]);
    }

    /** A Graph-shaped lead row. */
    private function fbLead(int $n, string $created = '2026-01-15T10:00:00+0000'): array
    {
        return [
            'id' => "ext_{$n}",
            'created_time' => $created,
            'field_data' => [
                ['name' => 'full_name', 'values' => ["Person {$n}"]],
                ['name' => 'phone_number', 'values' => ['+9198765'.str_pad((string) $n, 5, '0', STR_PAD_LEFT)]],
            ],
        ];
    }

    private function leadRange(int $from, int $to): array
    {
        return array_map(fn ($n) => $this->fbLead($n), range($from, $to));
    }

    /**
     * Fake the lead endpoint as a cursor-linked series of pages. Every page but
     * the last advertises a "next" url, which is what the sync must follow.
     *
     * The sequence is scoped to the leads endpoint rather than "*" on purpose:
     * Laravel evaluates every registered stub before picking the first match,
     * so a catch-all sequence gets drained by the /me/accounts call as well.
     *
     * Each argument is one sync run's worth of pages.
     *
     * @param  array<int, array>  ...$runs
     */
    private function fakeGraph(array ...$runs): void
    {
        $sequence = Http::sequence();

        foreach ($runs as $r => $pages) {
            foreach ($pages as $i => $rows) {
                $sequence->push([
                    'data' => $rows,
                    'paging' => $i === array_key_last($pages)
                        ? []
                        : ['next' => "https://graph.facebook.com/v21.0/form_1/leads?after=r{$r}c{$i}"],
                ]);
            }
        }

        Http::fake(['*/leads*' => $sequence]);
    }

    private function sync(array $options = []): array
    {
        return app(FacebookSyncService::class)->sync($this->integration, $options + ['since' => null]);
    }

    public function test_it_follows_paging_cursors_instead_of_stopping_at_the_first_page(): void
    {
        $this->fakeGraph([$this->leadRange(1, 100), $this->leadRange(101, 150)]);

        $result = $this->sync();

        $this->assertSame(150, $result['imported']);
        $this->assertTrue($result['complete']);
        $this->assertSame(150, Lead::count());
    }

    public function test_it_does_not_advance_the_watermark_when_the_page_limit_cuts_a_run_short(): void
    {
        $this->fakeGraph([$this->leadRange(1, 100), $this->leadRange(101, 200)]);

        $result = $this->sync(['max_pages' => 1]);

        $this->assertFalse($result['complete']);
        $this->assertNull($this->integration->fresh()->last_synced_at);
    }

    public function test_it_skips_leads_it_has_already_imported(): void
    {
        // Both rounds go in one sequence — stubs merge, so re-faking would
        // leave the first, now-exhausted sequence still matching.
        $this->fakeGraph([$this->leadRange(1, 10)], [$this->leadRange(1, 10)]);

        $this->sync();
        $second = $this->sync();

        $this->assertSame(0, $second['imported']);
        $this->assertSame(10, $second['skipped']);
        $this->assertSame(10, Lead::count());
    }

    public function test_it_stores_the_external_id_so_the_dedupe_has_something_to_match_on(): void
    {
        $this->fakeGraph([[$this->fbLead(1)]]);

        $this->sync();

        $this->assertSame('ext_1', Lead::sole()->external_id);
    }

    public function test_it_dates_a_lead_when_the_enquiry_was_made_not_when_it_was_imported(): void
    {
        $this->fakeGraph([[$this->fbLead(1, '2025-03-04T08:30:00+0000')]]);

        $this->sync();

        $this->assertSame('2025-03-04', Lead::sole()->created_at->toDateString());
    }

    public function test_backfilled_leads_are_unassigned_read_and_in_the_chosen_stage(): void
    {
        // What a go-live backfill would park already-dealt-with enquiries in.
        $closed = LeadStage::where('type', 'FINAL_POSITIVE')->firstOrFail();

        $this->fakeGraph([[$this->fbLead(1)]]);

        $this->sync(['assign' => false, 'mark_read' => true, 'stage_id' => $closed->id]);

        $lead = Lead::sole();

        $this->assertNull($lead->assigned_to);
        $this->assertNotNull($lead->viewed_at);
        $this->assertSame($closed->id, $lead->lead_stage_id);
    }

    public function test_it_drops_rows_with_no_usable_mobile_rather_than_storing_junk(): void
    {
        $this->fakeGraph([[
            $this->fbLead(1),
            ['id' => 'ext_bad', 'created_time' => '2026-01-01T00:00:00+0000', 'field_data' => [
                ['name' => 'full_name', 'values' => ['No Phone']],
            ]],
        ]]);

        $this->sync();

        $this->assertSame(1, Lead::count());
    }

    public function test_it_records_a_failure_on_the_logs_tab_and_rethrows(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'Invalid OAuth access token']], 400)]);

        try {
            $this->sync();
            $this->fail('A Graph failure must not be swallowed.');
        } catch (Throwable) {
            // expected
        }

        $this->assertSame('error', $this->integration->logs()->latest('id')->first()->status);
    }

    public function test_a_paused_integration_is_left_alone(): void
    {
        $this->integration->update(['status' => 'paused']);
        $this->fakeGraph([$this->leadRange(1, 5)]);

        $result = $this->sync();

        $this->assertSame(0, $result['imported']);
        $this->assertSame(0, Lead::count());
    }

    public function test_it_never_lets_a_page_access_token_reach_the_browser(): void
    {
        $pages = app(FacebookLeadSource::class)->pages();

        $this->assertArrayNotHasKey('token', $pages[0]);
    }
}
