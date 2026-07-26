<?php

namespace Tests\Feature\Crm;

use App\Mail\NewLeadMail;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\Crm\LeadService;
use App\Services\Support\SettingsService;
use App\Support\Settings;
use Database\Seeders\CrmSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The to-do and notification rules on an integration, finally doing something.
 *
 * A first enquiry and a repeat carry separate rule sets — usually a first call
 * versus a follow-up — and which fires is decided by the lead's repeat flag.
 */
class LeadTodoTest extends TestCase
{
    use RefreshDatabase;

    private Integration $campaign;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->seed(CrmSettingsSeeder::class);
        Mail::fake();

        $this->member = User::factory()->member()->create();

        $this->campaign = Integration::create([
            'name' => 'Campaign',
            'provider' => 'facebook',
            'status' => 'active',
            'external_page_id' => 'page_1',
            'external_form_id' => 'form_1',
            'form_name' => 'Campaign',
            'source' => 'Facebook',
            'source_type' => 'Facebook Integration',
            'lead_stage_id' => LeadStage::where('type', 'INITIAL')->value('id'),
        ]);

        $this->campaign->members()->attach($this->member);
    }

    private function withRules(array $rules): void
    {
        $this->campaign->update($rules);
    }

    private function arrive(string $at = '2026-07-01T10:00:00+0000', string $id = 'x1'): Lead
    {
        return app(LeadService::class)->intake($this->campaign->fresh(), [
            'external_id' => $id,
            'name' => 'Asha Rao',
            'mobile' => '9876500001',
            'created_time' => $at,
        ]);
    }

    /* ── New lead rules ───────────────────────────────── */

    public function test_a_new_lead_raises_the_to_do_its_campaign_asks_for(): void
    {
        $this->withRules([
            'new_lead_enabled' => true,
            'todo_enabled' => true,
            'todo_type' => 'FIRST CALL',
            'todo_title' => 'Bangalore Packages',
            'todo_due_value' => 30,
            'todo_due_unit' => 'minutes',
        ]);

        $lead = $this->arrive();
        $task = Task::sole();

        $this->assertSame('FIRST CALL', $task->type);
        $this->assertSame('Bangalore Packages', $task->title);
        $this->assertSame(Task::TRIGGER_NEW_LEAD, $task->trigger);
        $this->assertSame($this->member->id, $task->assigned_to);
        $this->assertSame($lead->id, $task->lead_id);

        // Due measured from when the enquiry arrived, not from the import.
        $this->assertSame(
            $lead->created_at->copy()->addMinutes(30)->toDateTimeString(),
            $task->due_at->toDateTimeString(),
        );
    }

    public function test_no_to_do_is_raised_when_the_rule_is_off(): void
    {
        $this->withRules(['new_lead_enabled' => true, 'todo_enabled' => false]);

        $this->arrive();

        $this->assertSame(0, Task::count());
    }

    public function test_a_half_filled_rule_raises_nothing_rather_than_a_blank_to_do(): void
    {
        $this->withRules([
            'new_lead_enabled' => true,
            'todo_enabled' => true,
            'todo_type' => 'FIRST CALL',
            'todo_title' => null,
        ]);

        $this->arrive();

        $this->assertSame(0, Task::count());
    }

    /* ── Existing lead rules ──────────────────────────── */

    public function test_a_repeat_enquiry_raises_the_existing_lead_to_do_instead(): void
    {
        $this->withRules([
            'new_lead_enabled' => true,
            'todo_enabled' => true,
            'todo_type' => 'FIRST CALL',
            'todo_title' => 'First call',
            'todo_due_value' => 30,
            'todo_due_unit' => 'minutes',
            'existing_lead_enabled' => true,
            'existing_todo_enabled' => true,
            'existing_todo_type' => 'FOLLOW-UP CALL',
            'existing_todo_title' => 'Follow up',
            'existing_todo_due_value' => 2,
            'existing_todo_due_unit' => 'hours',
        ]);

        $this->arrive('2026-07-01T10:00:00+0000', 'x1');
        $repeat = $this->arrive('2026-07-02T10:00:00+0000', 'x2');

        $this->assertTrue($repeat->is_existing);

        $task = Task::where('lead_id', $repeat->id)->sole();

        $this->assertSame('FOLLOW-UP CALL', $task->type);
        $this->assertSame(Task::TRIGGER_EXISTING_LEAD, $task->trigger);
        $this->assertSame(
            $repeat->created_at->copy()->addHours(2)->toDateTimeString(),
            $task->due_at->toDateTimeString(),
        );
    }

    public function test_a_repeat_raises_nothing_when_only_the_new_lead_rule_is_set(): void
    {
        $this->withRules([
            'new_lead_enabled' => true,
            'todo_enabled' => true,
            'todo_type' => 'FIRST CALL',
            'todo_title' => 'First call',
            'existing_lead_enabled' => false,
        ]);

        $this->arrive('2026-07-01T10:00:00+0000', 'x1');
        $repeat = $this->arrive('2026-07-02T10:00:00+0000', 'x2');

        $this->assertSame(0, Task::where('lead_id', $repeat->id)->count());
    }

    /* ── Notification timing ──────────────────────────── */

    public function test_a_delayed_notification_is_scheduled_rather_than_sent_at_once(): void
    {
        app(SettingsService::class)->set(Settings::NEW_LEAD_EMAIL, true);

        $this->withRules([
            'new_lead_enabled' => true,
            'notify_enabled' => true,
            'notify_value' => 30,
            'notify_unit' => 'minutes',
        ]);

        $lead = $this->arrive(now()->toIso8601String());

        Mail::assertNotQueued(NewLeadMail::class);
        $this->assertNotNull($lead->notify_at);
        $this->assertTrue($lead->notify_at->isFuture());
        $this->assertNull($lead->notified_at);
    }

    public function test_the_scheduled_command_sends_it_once_it_comes_due(): void
    {
        app(SettingsService::class)->set(Settings::NEW_LEAD_EMAIL, true);

        $this->withRules([
            'new_lead_enabled' => true,
            'notify_enabled' => true,
            'notify_value' => 30,
            'notify_unit' => 'minutes',
        ]);

        $lead = $this->arrive(now()->toIso8601String());

        $this->travel(31)->minutes();
        $this->artisan('leads:notify')->assertSuccessful();

        Mail::assertQueued(NewLeadMail::class);
        $this->assertNotNull($lead->fresh()->notified_at);
    }

    public function test_a_lead_is_never_notified_about_twice(): void
    {
        app(SettingsService::class)->set(Settings::NEW_LEAD_EMAIL, true);

        $this->withRules([
            'new_lead_enabled' => true,
            'notify_enabled' => true,
            'notify_value' => 0,
            'notify_unit' => 'minutes',
        ]);

        $this->arrive(now()->toIso8601String());

        // Sent at intake because there was no delay; the sweep must not repeat it.
        $this->artisan('leads:notify');
        $this->artisan('leads:notify');

        Mail::assertQueuedCount(1);
    }

    public function test_a_backfilled_lead_is_never_notified_about(): void
    {
        app(SettingsService::class)->set(Settings::NEW_LEAD_EMAIL, true);

        $this->withRules([
            'new_lead_enabled' => true,
            'notify_enabled' => true,
            'notify_value' => 0,
            'notify_unit' => 'minutes',
        ]);

        app(LeadService::class)->intake($this->campaign->fresh(), [
            'external_id' => 'old',
            'name' => 'Asha Rao',
            'mobile' => '9876500001',
            'created_time' => '2025-01-01T10:00:00+0000',
        ], ['notify' => false]);

        $this->artisan('leads:notify');

        Mail::assertNothingQueued();
        $this->assertNull(Lead::sole()->notify_at);
    }

    public function test_a_member_deactivated_during_the_delay_is_not_emailed(): void
    {
        app(SettingsService::class)->set(Settings::NEW_LEAD_EMAIL, true);

        $this->withRules([
            'new_lead_enabled' => true,
            'notify_enabled' => true,
            'notify_value' => 30,
            'notify_unit' => 'minutes',
        ]);

        $this->arrive(now()->toIso8601String());

        // They left in the half hour between arrival and the alert falling due.
        $this->member->update(['is_active' => false]);

        $this->travel(31)->minutes();
        $this->artisan('leads:notify');

        Mail::assertNothingQueued();
    }

    /* ── The screen ───────────────────────────────────── */

    public function test_a_member_sees_only_their_own_to_dos(): void
    {
        $this->withRules([
            'new_lead_enabled' => true, 'todo_enabled' => true,
            'todo_type' => 'FIRST CALL', 'todo_title' => 'Call',
        ]);

        $this->arrive();

        $other = User::factory()->member()->create();

        $mine = $this->actingAs($this->member)->get('/todos')
            ->viewData('page')['props']['tasks']['data'];
        $theirs = $this->actingAs($other)->get('/todos')
            ->viewData('page')['props']['tasks']['data'];

        $this->assertCount(1, $mine);
        $this->assertCount(0, $theirs);
    }

    public function test_completing_and_reopening_a_to_do(): void
    {
        $this->withRules([
            'new_lead_enabled' => true, 'todo_enabled' => true,
            'todo_type' => 'FIRST CALL', 'todo_title' => 'Call',
        ]);

        $this->arrive();
        $task = Task::sole();

        $this->actingAs($this->member)->patch("/todos/{$task->id}/complete")->assertSessionHasNoErrors();
        $this->assertNotNull($task->fresh()->completed_at);
        $this->assertSame($this->member->id, $task->fresh()->completed_by);

        $this->actingAs($this->member)->patch("/todos/{$task->id}/reopen");
        $this->assertNull($task->fresh()->completed_at);
    }

    public function test_a_member_cannot_complete_someone_elses_to_do(): void
    {
        $this->withRules([
            'new_lead_enabled' => true, 'todo_enabled' => true,
            'todo_type' => 'FIRST CALL', 'todo_title' => 'Call',
        ]);

        $this->arrive();

        $this->actingAs(User::factory()->member()->create())
            ->patch('/todos/'.Task::sole()->id.'/complete')
            ->assertForbidden();
    }

    public function test_an_owner_can_complete_anyones_to_do(): void
    {
        $this->withRules([
            'new_lead_enabled' => true, 'todo_enabled' => true,
            'todo_type' => 'FIRST CALL', 'todo_title' => 'Call',
        ]);

        $this->arrive();

        $this->actingAs(User::factory()->owner()->create())
            ->patch('/todos/'.Task::sole()->id.'/complete')
            ->assertSessionHasNoErrors();

        $this->assertNotNull(Task::sole()->completed_at);
    }

    public function test_overdue_work_can_be_isolated(): void
    {
        $this->withRules([
            'new_lead_enabled' => true, 'todo_enabled' => true,
            'todo_type' => 'FIRST CALL', 'todo_title' => 'Call',
            'todo_due_value' => 30, 'todo_due_unit' => 'minutes',
        ]);

        // A lead from last year: its 30-minute deadline is long gone.
        $this->arrive('2025-01-01T10:00:00+0000');

        $props = $this->actingAs($this->member)->get('/todos?status=overdue')
            ->viewData('page')['props'];

        $this->assertCount(1, $props['tasks']['data']);

        // The tab counts are measured against the same filter as the list, so
        // the number on the tab and the rows beneath it always agree.
        $this->assertSame(1, $props['tabCounts']['fresh']);
        $this->assertSame(0, $props['tabCounts']['followups']);
    }

    /* ── The tabs ─────────────────────────────────────── */

    public function test_untouched_work_starts_on_fresh_leads(): void
    {
        $this->withRules([
            'new_lead_enabled' => true, 'todo_enabled' => true,
            'todo_type' => 'FIRST CALL', 'todo_title' => 'Call',
        ]);

        $this->arrive();

        $props = fn (string $query = '') => $this->actingAs($this->member)
            ->get("/todos{$query}")->viewData('page')['props'];

        // Nobody has done anything to the lead, so it is Fresh — and Fresh is
        // where you land without asking.
        $this->assertSame('fresh', $props()['tab']);
        $this->assertCount(1, $props()['tasks']['data']);
        $this->assertCount(0, $props('?tab=followups')['tasks']['data']);

        // A tab name nobody offers falls back rather than erroring.
        $this->assertSame('fresh', $props('?tab=nonsense')['tab']);
    }

    public function test_moving_the_lead_along_moves_its_work_to_follow_ups(): void
    {
        $this->withRules([
            'new_lead_enabled' => true, 'todo_enabled' => true,
            'todo_type' => 'FIRST CALL', 'todo_title' => 'Call',
        ]);

        $lead = $this->arrive();

        $this->actingAs($this->member)->patch("/leads/{$lead->id}/status", [
            'version' => $lead->fresh()->version,
            'lead_stage_id' => LeadStage::where('type', 'FINAL_POSITIVE')->value('id'),
        ])->assertSessionHasNoErrors();

        $props = fn (string $query = '') => $this->actingAs($this->member)
            ->get("/todos{$query}")->viewData('page')['props'];

        $this->assertCount(0, $props()['tasks']['data']);
        $this->assertCount(1, $props('?tab=followups')['tasks']['data']);
        $this->assertSame(0, $props()['tabCounts']['fresh']);
        $this->assertSame(1, $props()['tabCounts']['followups']);
    }

    public function test_ticking_one_to_do_off_moves_the_rest_of_that_leads_work(): void
    {
        $this->withRules([
            'new_lead_enabled' => true, 'todo_enabled' => true,
            'todo_type' => 'FIRST CALL', 'todo_title' => 'Call',
        ]);

        $lead = $this->arrive();

        // A second job on the same lead, still outstanding.
        $open = Task::create([
            'lead_id' => $lead->id,
            'assigned_to' => $this->member->id,
            'type' => 'FOLLOW-UP CALL',
            'title' => 'Send the quote',
        ]);

        /*
         | Completing the first one is enough on its own — the stage has not
         | moved, but somebody has clearly picked this lead up, so the work
         | still outstanding on it is a follow-up rather than a fresh start.
         */
        $this->actingAs($this->member)->patch('/todos/'.Task::whereKeyNot($open->id)->value('id').'/complete');

        $props = fn (string $query) => $this->actingAs($this->member)
            ->get("/todos{$query}")->viewData('page')['props'];

        // Narrowed to open work, because the completed one lands on Follow Ups
        // as well — its own lead has been picked up, which is the whole point.
        $rows = $props('?tab=followups&status=open')['tasks']['data'];

        $this->assertCount(1, $rows);
        $this->assertSame($open->id, $rows[0]['id']);
        $this->assertCount(0, $props('?status=open')['tasks']['data']);
    }

    public function test_reminders_is_held_empty_and_hides_nothing(): void
    {
        $this->withRules([
            'new_lead_enabled' => true, 'todo_enabled' => true,
            'todo_type' => 'FIRST CALL', 'todo_title' => 'Call',
        ]);

        $this->arrive();

        $props = $this->actingAs($this->member)->get('/todos?tab=reminders')
            ->viewData('page')['props'];

        $this->assertCount(0, $props['tasks']['data']);
        $this->assertSame(0, $props['tabCounts']['reminders']);

        // Nothing falls down the gap: every to-do is on one of the other two.
        $this->assertSame(
            Task::query()->open()->count(),
            $props['tabCounts']['fresh'] + $props['tabCounts']['followups'],
        );
    }

    public function test_the_team_card_counts_open_work_against_the_contacts_team(): void
    {
        $this->withRules([
            'new_lead_enabled' => true, 'todo_enabled' => true,
            'todo_type' => 'FIRST CALL', 'todo_title' => 'Call',
        ]);

        $this->arrive();

        $usage = $this->actingAs($this->member)->get('/todos')
            ->viewData('page')['props']['usageByTeam'];

        $this->assertCount(1, $usage);
        $this->assertSame(1, $usage[0]['total']);
    }

    public function test_the_team_and_lead_date_filters_can_be_used_together(): void
    {
        $this->withRules([
            'new_lead_enabled' => true, 'todo_enabled' => true,
            'todo_type' => 'FIRST CALL', 'todo_title' => 'Call',
        ]);

        $lead = $this->arrive();

        $team = Team::create(['name' => 'Sales', 'status' => 'active']);
        $lead->customer->update(['team_id' => $team->id]);

        $rows = fn (string $query) => $this->actingAs($this->member)
            ->get("/todos?{$query}")->viewData('page')['props']['tasks']['data'];

        /*
         | Both filters reach into leads — one for the contact's team, one for
         | when the enquiry came in. Used together they used to be able to leave
         | an unqualified created_at ambiguous, so this asks for both at once.
         */
        $this->assertCount(1, $rows("team={$team->id}&lead_from=2026-06-01&lead_to=2026-12-31"));

        // The same query with the enquiry outside the range finds nothing.
        $this->assertCount(0, $rows("team={$team->id}&lead_from=2026-08-01"));

        // And a different team excludes it.
        $this->assertCount(0, $rows('team='.($team->id + 99)));

        // "Add more filters" sends a comma-joined list, since the panel ticks
        // several teams at once where the old single select could not.
        $this->assertCount(1, $rows('team='.($team->id + 99).",{$team->id}"));
    }
}
