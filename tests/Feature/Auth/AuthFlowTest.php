<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

/**
 * End-to-end cover for the three ways in and the way out.
 *
 * Written when the Laravel starter-kit auth controllers were removed: this app
 * signs in with a mobile number and a one-time code, never with an email and
 * password, and nothing should be able to quietly reintroduce that.
 */
class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();

        $this->user = User::factory()->owner()->create([
            'mobile' => '9876500111',
            'password' => Hash::make('Known-pass-1'),
            'is_active' => true,
        ]);
    }

    /* ── OTP sign-in ──────────────────────────────────── */

    public function test_a_user_can_sign_in_with_a_one_time_code(): void
    {
        $this->post('/login/otp', ['mobile' => '9876500111'])->assertSessionHasNoErrors();

        $code = $this->issuedCode();

        $this->post('/login/verify', ['mobile' => '9876500111', 'code' => $code])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($this->user);
    }

    public function test_a_wrong_code_does_not_sign_anyone_in(): void
    {
        $this->post('/login/otp', ['mobile' => '9876500111']);

        $this->post('/login/verify', ['mobile' => '9876500111', 'code' => '000000'])
            ->assertSessionHasErrors();

        $this->assertGuest();
    }

    public function test_an_unknown_mobile_does_not_reveal_whether_the_account_exists(): void
    {
        $known = $this->post('/login/otp', ['mobile' => '9876500111']);
        $unknown = $this->post('/login/otp', ['mobile' => '9876509999']);

        $this->assertSame($known->getStatusCode(), $unknown->getStatusCode());
        $unknown->assertSessionHasNoErrors();
    }

    public function test_a_deactivated_user_cannot_sign_in(): void
    {
        $this->post('/login/otp', ['mobile' => '9876500111']);
        $code = $this->issuedCode();

        $this->user->update(['is_active' => false]);

        $this->post('/login/verify', ['mobile' => '9876500111', 'code' => $code])
            ->assertSessionHasErrors();

        $this->assertGuest();
    }

    /* ── Password sign-in ─────────────────────────────── */

    public function test_a_user_can_sign_in_with_mobile_and_password(): void
    {
        $this->post('/login/password', ['mobile' => '9876500111', 'password' => 'Known-pass-1'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($this->user);
    }

    public function test_a_wrong_password_is_refused(): void
    {
        $this->post('/login/password', ['mobile' => '9876500111', 'password' => 'not-it'])
            ->assertSessionHasErrors();

        $this->assertGuest();
    }

    /* ── Forgotten password ───────────────────────────── */

    public function test_a_password_can_be_reset_over_otp(): void
    {
        $this->get('/forgot-password')->assertOk();

        $this->post('/forgot-password', ['mobile' => '9876500111'])->assertSessionHasNoErrors();

        $this->post('/reset-password', [
            'mobile' => '9876500111',
            'code' => $this->issuedCode(),
            'password' => 'Brand-new-pass-2',
            'password_confirmation' => 'Brand-new-pass-2',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('Brand-new-pass-2', $this->user->fresh()->password));

        $this->post('/login/password', ['mobile' => '9876500111', 'password' => 'Brand-new-pass-2'])
            ->assertRedirect('/dashboard');
    }

    /* ── Sign out, and the doors that must stay shut ──── */

    public function test_a_user_can_sign_out(): void
    {
        $this->actingAs($this->user)->post('/logout')->assertRedirect();

        $this->assertGuest();
    }

    public function test_the_login_screen_renders_for_guests(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_guests_are_kept_out_of_the_console(): void
    {
        foreach (['/dashboard', '/leads', '/customers', '/team', '/settings', '/profile'] as $path) {
            $this->get($path)->assertRedirect('/login');
        }
    }

    /**
     * The starter kit's email/password routes were deleted along with their
     * controllers. If any of these ever answer again, something reintroduced
     * an authentication path this app does not want.
     */
    public function test_the_starter_kit_auth_routes_no_longer_exist(): void
    {
        foreach (['/register', '/verify-email', '/confirm-password'] as $path) {
            $this->get($path)->assertNotFound();
        }

        $this->post('/register', [
            'name' => 'Intruder',
            'email' => 'intruder@example.com',
            'password' => 'password123',
        ])->assertNotFound();
    }

    /**
     * The controllers flash the issued code to the session while the "log" OTP
     * driver is active, which is how the dev UI shows it without an SMS.
     */
    private function issuedCode(): string
    {
        return session('otp.dev_code') ?? throw new RuntimeException('No OTP code was issued.');
    }
}
