<?php

namespace Tests\Feature\Settings;

use App\Models\AppSetting;
use App\Models\User;
use App\Services\Support\SettingsService;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->owner = User::factory()->owner()->create();
    }

    public function test_the_facebook_token_is_ciphertext_in_the_database(): void
    {
        $token = 'EAAtest'.str_repeat('x', 60);

        app(SettingsService::class)->set(Settings::FACEBOOK_TOKEN, $token);

        $stored = AppSetting::where('key', Settings::FACEBOOK_TOKEN)->sole();

        $this->assertNotSame($token, $stored->value);
        $this->assertStringNotContainsString($token, $stored->value);
        $this->assertTrue($stored->is_encrypted);
        $this->assertSame($token, app(SettingsService::class)->get(Settings::FACEBOOK_TOKEN));
    }

    public function test_the_settings_screen_never_sends_the_token_to_the_browser(): void
    {
        $token = 'EAAsecret'.str_repeat('y', 60);
        app(SettingsService::class)->set(Settings::FACEBOOK_TOKEN, $token);

        $response = $this->actingAs($this->owner)->get('/settings');

        $response->assertOk();
        $response->assertDontSee($token);
        // It may only report that one exists.
        $response->assertSee('facebook_token_set', escape: false);
    }

    public function test_an_owner_can_rotate_the_token(): void
    {
        $new = 'EAArotated'.str_repeat('z', 60);

        $this->actingAs($this->owner)
            ->put('/settings/facebook-token', ['token' => $new])
            ->assertSessionHasNoErrors();

        $this->assertSame($new, app(SettingsService::class)->get(Settings::FACEBOOK_TOKEN));
    }

    public function test_a_truncated_or_whitespace_damaged_token_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->put('/settings/facebook-token', ['token' => 'EAAshort'])
            ->assertSessionHasErrors('token');

        $this->actingAs($this->owner)
            ->put('/settings/facebook-token', ['token' => 'EAA with spaces'.str_repeat('x', 60)])
            ->assertSessionHasErrors('token');
    }

    public function test_a_member_cannot_read_or_change_settings(): void
    {
        $member = User::factory()->member()->create();

        $this->actingAs($member)->get('/settings')->assertForbidden();

        $this->actingAs($member)
            ->put('/settings/facebook-token', ['token' => str_repeat('a', 60)])
            ->assertForbidden();

        $this->actingAs($member)
            ->put('/settings/notifications', ['new_lead_email' => true])
            ->assertForbidden();
    }

    public function test_an_owner_can_toggle_lead_emails(): void
    {
        $settings = app(SettingsService::class);
        $this->assertFalse($settings->boolean(Settings::NEW_LEAD_EMAIL));

        $this->actingAs($this->owner)
            ->put('/settings/notifications', ['new_lead_email' => true])
            ->assertSessionHasNoErrors();

        $this->assertTrue($settings->boolean(Settings::NEW_LEAD_EMAIL));

        $this->actingAs($this->owner)->put('/settings/notifications', ['new_lead_email' => false]);

        $this->assertFalse($settings->boolean(Settings::NEW_LEAD_EMAIL));
    }

    public function test_removing_the_token_leaves_nothing_behind(): void
    {
        app(SettingsService::class)->set(Settings::FACEBOOK_TOKEN, str_repeat('q', 60));

        $this->actingAs($this->owner)->delete('/settings/facebook-token');

        $this->assertFalse(app(SettingsService::class)->has(Settings::FACEBOOK_TOKEN));
        $this->assertDatabaseMissing('app_settings', ['key' => Settings::FACEBOOK_TOKEN]);
    }

    public function test_a_write_invalidates_the_cache_so_the_next_read_is_fresh(): void
    {
        $settings = app(SettingsService::class);

        $settings->set(Settings::NEW_LEAD_EMAIL, false);
        $this->assertFalse($settings->boolean(Settings::NEW_LEAD_EMAIL));

        $settings->set(Settings::NEW_LEAD_EMAIL, true);
        $this->assertTrue($settings->boolean(Settings::NEW_LEAD_EMAIL));
    }
}
