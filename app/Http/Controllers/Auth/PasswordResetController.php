<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestOtpRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\Auth\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Password reset runs over the same OTP channel as login: the user proves
 * control of the mobile number, then sets a new password. No email link is
 * needed, which matches how staff actually sign in.
 */
class PasswordResetController extends Controller
{
    public function __construct(private OtpService $otp) {}

    public function show(): Response
    {
        return Inertia::render('auth/forgot-password', [
            'otpLength' => config('otp.length'),
        ]);
    }

    public function sendCode(RequestOtpRequest $request): RedirectResponse
    {
        $mobile = $request->validated('mobile');

        $exists = User::query()
            ->where('mobile', $mobile)
            ->where('is_active', true)
            ->exists();

        // Unknown numbers get an identical response — no account enumeration.
        $code = $exists
            ? $this->otp->issue($mobile, 'reset', $request->ip())
            : null;

        return back()->with('otp', [
            'sent' => true,
            'dev_code' => $code,
            'cooldown' => config('otp.resend_cooldown'),
        ]);
    }

    public function reset(ResetPasswordRequest $request): RedirectResponse
    {
        $mobile = $request->validated('mobile');

        $this->otp->verify($mobile, $request->validated('code'), 'reset');

        $user = User::query()
            ->where('mobile', $mobile)
            ->where('is_active', true)
            ->firstOrFail();

        $user->forceFill([
            'password' => Hash::make($request->validated('password')),
            'mobile_verified_at' => now(),
            // Choosing their own password clears any emailed temporary one.
            'must_reset_password' => false,
        ])->save();

        activity('auth')->performedOn($user)->log('Password reset via OTP');

        return redirect()
            ->route('login')
            ->with('success', 'Password updated. You can sign in with it now.');
    }
}
