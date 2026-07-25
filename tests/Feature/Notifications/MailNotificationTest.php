<?php

namespace Tests\Feature\Notifications;

use App\Mail\MemberWelcomeMail;
use App\Mail\NewLeadMail;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\User;
use App\Services\Crm\LeadService;
use App\Services\Support\SettingsService;
use App\Services\Team\MemberService;
use App\Support\Roles;
use App\Support\Settings;
use Database\Seeders\CrmSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Integration $integration;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CrmSettingsSeeder::class);
        $this->seedRoles();

        Mail::fake();

        $this->member = User::factory()->member()->create();

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

        $this->integration->members()->attach($this->member);
    }

    private function intake(array $options = []): void
    {
        app(LeadService::class)->intake($this->integration, [
            'external_id' => 'ext_1',
            'name' => 'Asha Rao',
            'mobile' => '9876500001',
            'email' => 'asha@example.com',
        ], $options);
    }

    /* ── New member welcome ───────────────────────────── */

    public function test_creating_a_member_queues_a_welcome_email(): void
    {
        $owner = User::factory()->owner()->create();

        $member = app(MemberService::class)->create([
            'name' => 'Ravi Kumar',
            'email' => 'ravi@example.com',
            'mobile' => '9876500002',
            'role' => Roles::MEMBER,
        ], $owner);

        Mail::assertQueued(MemberWelcomeMail::class, function (MemberWelcomeMail $mail) use ($member, $owner) {
            return $mail->hasTo('ravi@example.com')
                && $mail->member->is($member)
                && $mail->invitedBy === $owner->name;
        });
    }

    public function test_the_emailed_password_actually_works_and_is_not_stored_in_the_clear(): void
    {
        $member = app(MemberService::class)->create([
            'name' => 'Ravi Kumar',
            'email' => 'ravi@example.com',
            'mobile' => '9876500002',
        ]);

        $sent = null;
        Mail::assertQueued(MemberWelcomeMail::class, function (MemberWelcomeMail $mail) use (&$sent) {
            $sent = $mail->temporaryPassword;

            return true;
        });

        $this->assertNotEmpty($sent);
        $this->assertTrue(Hash::check($sent, $member->fresh()->password));
        $this->assertNotSame($sent, $member->fresh()->password);
    }

    public function test_a_member_with_a_temporary_password_is_held_until_they_change_it(): void
    {
        $member = User::factory()->member()->create(['must_reset_password' => true]);

        $this->actingAs($member)->get('/dashboard')->assertRedirect('/profile');

        // The profile screen itself must stay reachable, or it is a redirect loop.
        $this->actingAs($member)->get('/profile')->assertOk();
    }

    public function test_choosing_a_password_lifts_the_hold(): void
    {
        $member = User::factory()->member()->create([
            'must_reset_password' => true,
            'password' => Hash::make('Emailed-temp-1'),
        ]);

        // The member supplies the emailed password as their current one.
        $this->actingAs($member)->put('/profile/password', [
            'current_password' => 'Emailed-temp-1',
            'password' => 'Chosen-pass-1',
            'password_confirmation' => 'Chosen-pass-1',
        ])->assertSessionHasNoErrors();

        $this->assertFalse($member->fresh()->must_reset_password);
        $this->actingAs($member->fresh())->get('/dashboard')->assertOk();
    }

    /* ── New lead alerts ──────────────────────────────── */

    public function test_no_lead_email_is_sent_while_the_setting_is_off(): void
    {
        $this->intake();

        Mail::assertNotQueued(NewLeadMail::class);
    }

    public function test_the_setting_defaults_to_off(): void
    {
        $this->assertFalse(app(SettingsService::class)->boolean(Settings::NEW_LEAD_EMAIL));
    }

    public function test_a_lead_email_goes_to_the_assignee_once_switched_on(): void
    {
        app(SettingsService::class)->set(Settings::NEW_LEAD_EMAIL, true);

        $this->intake();

        Mail::assertQueued(NewLeadMail::class, fn (NewLeadMail $mail) => $mail->hasTo($this->member->email));
    }

    public function test_backfilled_leads_never_email_even_with_the_setting_on(): void
    {
        app(SettingsService::class)->set(Settings::NEW_LEAD_EMAIL, true);

        // Exactly what leads:backfill passes.
        $this->intake(['notify' => false, 'assign' => false, 'mark_read' => true]);

        Mail::assertNotQueued(NewLeadMail::class);
    }

    public function test_an_unassigned_lead_emails_nobody(): void
    {
        app(SettingsService::class)->set(Settings::NEW_LEAD_EMAIL, true);

        $this->intake(['assign' => false]);

        Mail::assertNotQueued(NewLeadMail::class);
    }

    public function test_a_deactivated_member_is_not_emailed(): void
    {
        app(SettingsService::class)->set(Settings::NEW_LEAD_EMAIL, true);
        $this->member->update(['is_active' => false]);

        $this->intake();

        Mail::assertNotQueued(NewLeadMail::class);
    }

    /* ── Both mails must render ───────────────────────── */

    public function test_the_welcome_email_renders_and_shows_the_credentials(): void
    {
        $member = User::factory()->member()->create(['name' => 'Ravi Kumar', 'mobile' => '9876500003']);

        $html = (new MemberWelcomeMail($member, 'Temp-Pass-99', 'Shank'))->render();

        $this->assertStringContainsString('Temp-Pass-99', $html);
        $this->assertStringContainsString('9876500003', $html);
        $this->assertStringContainsString('Ravi', $html);
    }

    public function test_the_lead_email_renders_with_the_lead_details(): void
    {
        $this->intake();

        $html = (new NewLeadMail(Lead::sole()))->render();

        $this->assertStringContainsString('Asha Rao', $html);
        $this->assertStringContainsString('9876500001', $html);
    }
}
