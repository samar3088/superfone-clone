<?php

namespace Tests\Feature\Settings;

use App\Models\Integration;
use App\Models\LeadStage;
use App\Models\User;
use App\Services\Support\SettingsService;
use App\Support\Settings;
use Database\Seeders\CrmSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntegrationSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Integration $integration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->seed(CrmSettingsSeeder::class);

        $this->owner = User::factory()->owner()->create();

        // Lives in the encrypted store, not the environment.
        app(SettingsService::class)->set(Settings::FACEBOOK_TOKEN, str_repeat('t', 60));

        $this->integration = Integration::create([
            'name' => 'Test Integration',
            'provider' => 'facebook',
            'status' => 'active',
            'external_page_id' => 'page_1',
            'external_form_id' => 'form_1',
            'form_name' => 'B2B_META_FORM',
            'page_name' => 'Planawedding.in',
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Integration',
            'source_type' => 'Facebook Integration',
            'source' => 'Facebook',
            'lead_stage_id' => LeadStage::where('type', 'INITIAL')->value('id'),
            'member_ids' => [$this->owner->id],
            'new_lead_enabled' => false,
            'existing_lead_enabled' => false,
        ], $overrides);
    }

    /* ── Setup state ──────────────────────────────────── */

    public function test_a_fresh_integration_needs_both_mapping_and_settings(): void
    {
        $this->assertTrue($this->integration->needsSettings());
        $this->assertTrue($this->integration->needsMapping());
        $this->assertFalse($this->integration->isConfigured());
    }

    public function test_routing_rules_without_members_still_needs_mapping(): void
    {
        $this->integration->update([
            'source_type' => 'Facebook Integration',
            'source' => 'Facebook',
            'lead_stage_id' => LeadStage::where('type', 'INITIAL')->value('id'),
        ]);

        $fresh = $this->integration->fresh();

        $this->assertFalse($fresh->needsSettings());
        $this->assertTrue($fresh->needsMapping(), 'Nobody is mapped, so leads would land unassigned.');
        $this->assertFalse($fresh->isConfigured());
    }

    public function test_both_states_reach_the_settings_screen(): void
    {
        $props = $this->actingAs($this->owner)->get('/settings')->viewData('page')['props'];
        $row = collect($props['integrations'])->firstWhere('id', $this->integration->id);

        $this->assertTrue($row['needs_settings']);
        $this->assertTrue($row['needs_mapping']);
        $this->assertNotEmpty($row['created_ago']);
        $this->assertSame(config('company.legal_name'), $row['company']);
    }

    /* ── Saving the rules ─────────────────────────────── */

    public function test_an_owner_can_save_both_lead_rule_blocks(): void
    {
        $this->actingAs($this->owner)
            ->patch("/settings/integrations/{$this->integration->id}/settings", $this->validPayload([
                'new_lead_enabled' => true,
                'todo_enabled' => true,
                'todo_type' => 'FIRST CALL',
                'todo_title' => 'Call the new enquiry',
                'todo_due_value' => 30,
                'todo_due_unit' => 'minutes',
                'notify_enabled' => true,
                'notify_value' => 0,
                'notify_unit' => 'minutes',
                'existing_lead_enabled' => true,
                'existing_todo_enabled' => true,
                'existing_todo_type' => 'FOLLOW UP',
                'existing_todo_title' => 'Follow up with them again',
                'existing_todo_due_value' => 2,
                'existing_todo_due_unit' => 'hours',
                'existing_notify_enabled' => true,
                'existing_notify_value' => 5,
                'existing_notify_unit' => 'minutes',
            ]))
            ->assertSessionHasNoErrors();

        $i = $this->integration->fresh();

        $this->assertSame('FIRST CALL', $i->todo_type);
        $this->assertSame(30, $i->todo_due_value);
        $this->assertSame('FOLLOW UP', $i->existing_todo_type);
        $this->assertSame(2, $i->existing_todo_due_value);
        $this->assertSame('hours', $i->existing_todo_due_unit);
        $this->assertSame(5, $i->existing_notify_value);
        $this->assertTrue($i->isConfigured());
    }

    public function test_an_enabled_todo_must_be_filled_in(): void
    {
        $this->actingAs($this->owner)
            ->patch("/settings/integrations/{$this->integration->id}/settings", $this->validPayload([
                'existing_lead_enabled' => true,
                'existing_todo_enabled' => true,
                // type, title and due deliberately missing
            ]))
            ->assertSessionHasErrors(['existing_todo_type', 'existing_todo_title', 'existing_todo_due_value']);
    }

    public function test_switching_a_rule_off_clears_its_stale_detail(): void
    {
        $this->integration->update([
            'existing_lead_enabled' => true,
            'existing_todo_enabled' => true,
            'existing_todo_type' => 'FOLLOW UP',
            'existing_todo_title' => 'Old title',
            'existing_todo_due_value' => 9,
            'existing_todo_due_unit' => 'days',
        ]);

        $this->actingAs($this->owner)
            ->patch("/settings/integrations/{$this->integration->id}/settings", $this->validPayload([
                'existing_lead_enabled' => false,
            ]))
            ->assertSessionHasNoErrors();

        $i = $this->integration->fresh();

        // Left behind, these would fire again the moment the box was re-ticked.
        $this->assertFalse($i->existing_todo_enabled);
        $this->assertNull($i->existing_todo_type);
        $this->assertNull($i->existing_todo_title);
        $this->assertNull($i->existing_todo_due_value);
    }

    public function test_a_member_cannot_change_integration_settings(): void
    {
        $member = User::factory()->member()->create();

        $this->actingAs($member)
            ->patch("/settings/integrations/{$this->integration->id}/settings", $this->validPayload())
            ->assertForbidden();
    }

    /* ── Preview ──────────────────────────────────────── */

    public function test_the_preview_returns_the_page_profile(): void
    {
        Http::fake([
            '*/me/accounts*' => Http::response(['data' => [
                ['id' => 'page_1', 'name' => 'Planawedding.in', 'access_token' => 'tok'],
            ]]),
            '*' => Http::response([
                'name' => 'Planawedding.in',
                'about' => 'India\'s only wedding planning platform.',
                'cover' => ['source' => 'https://scontent.example/cover.jpg'],
            ]),
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson("/settings/integrations/{$this->integration->id}/preview");

        $response->assertOk();
        $response->assertJsonPath('profile.cover', 'https://scontent.example/cover.jpg');
        $response->assertJsonPath('form_name', 'B2B_META_FORM');
        // The stable avatar endpoint, not an expiring CDN url.
        $this->assertStringContainsString('/page_1/picture', $response->json('profile.picture'));
    }

    public function test_a_dead_token_leaves_the_preview_empty_rather_than_erroring(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'Invalid token']], 400)]);

        $this->actingAs($this->owner)
            ->getJson("/settings/integrations/{$this->integration->id}/preview")
            ->assertOk()
            ->assertJsonPath('profile', null);
    }

    public function test_a_member_cannot_read_the_preview(): void
    {
        $member = User::factory()->member()->create();

        $this->actingAs($member)
            ->getJson("/settings/integrations/{$this->integration->id}/preview")
            ->assertForbidden();
    }
}
