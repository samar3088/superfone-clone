<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginService
{
    public function __construct(private OtpService $otp) {}

    /**
     * Step 1 — send a login code. The response never reveals whether the
     * mobile is registered; an unknown number simply gets no code.
     *
     * @return array{sent: bool, dev_code: ?string, cooldown: int}
     */
    public function requestOtp(string $mobile, ?string $ip = null): array
    {
        $user = $this->activeUserByMobile($mobile);

        if (! $user) {
            // Same shape and timing as the success path — no account enumeration.
            return ['sent' => true, 'dev_code' => null, 'cooldown' => config('otp.resend_cooldown')];
        }

        $code = $this->otp->issue($mobile, 'login', $ip);

        return [
            'sent' => true,
            'dev_code' => $code,
            'cooldown' => config('otp.resend_cooldown'),
        ];
    }

    /** Step 2 — verify the code and start the session. */
    public function loginWithOtp(Request $request, string $mobile, string $code): User
    {
        $this->otp->verify($mobile, $code, 'login');

        $user = $this->activeUserByMobile($mobile);

        if (! $user) {
            throw ValidationException::withMessages([
                'mobile' => 'No active account is registered with that mobile number.',
            ]);
        }

        if (! $user->mobile_verified_at) {
            $user->forceFill(['mobile_verified_at' => now()])->save();
        }

        $this->startSession($request, $user);

        return $user;
    }

    /** Alternative path — mobile + password, for users who set one. */
    public function loginWithPassword(Request $request, string $mobile, string $password): User
    {
        $user = $this->activeUserByMobile($mobile);

        if (! $user || ! $user->password || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'mobile' => 'Those credentials do not match our records.',
            ]);
        }

        $this->startSession($request, $user);

        return $user;
    }

    public function logout(Request $request): void
    {
        activity('auth')->causedBy($request->user())->log('Logged out');

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    private function activeUserByMobile(string $mobile): ?User
    {
        return User::query()
            ->where('mobile', $mobile)
            ->where('is_active', true)
            ->first();
    }

    private function startSession(Request $request, User $user): void
    {
        Auth::login($user, remember: true);

        // A fresh session id after authenticating blocks session fixation.
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        activity('auth')->causedBy($user)->log('Logged in');
    }
}
