<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasswordLoginRequest;
use App\Http\Requests\Auth\RequestOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Services\Auth\LoginService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Thin by design: validate via FormRequest, delegate to the service, redirect.
 */
class LoginController extends Controller
{
    public function __construct(private LoginService $login) {}

    public function show(): Response
    {
        return Inertia::render('auth/login', [
            'otpLength' => config('otp.length'),
            'resendCooldown' => config('otp.resend_cooldown'),
        ]);
    }

    public function requestOtp(RequestOtpRequest $request): RedirectResponse
    {
        $result = $this->login->requestOtp(
            $request->validated('mobile'),
            $request->ip()
        );

        return back()->with('otp', $result);
    }

    public function verifyOtp(VerifyOtpRequest $request): RedirectResponse
    {
        $this->login->loginWithOtp(
            $request,
            $request->validated('mobile'),
            $request->validated('code')
        );

        return redirect()->intended(route('dashboard'));
    }

    public function passwordLogin(PasswordLoginRequest $request): RedirectResponse
    {
        $this->login->loginWithPassword(
            $request,
            $request->validated('mobile'),
            $request->validated('password')
        );

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->login->logout($request);

        return redirect()->route('login');
    }
}
