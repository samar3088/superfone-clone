<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdatePasswordRequest;
use App\Http\Requests\Account\UpdateProfileRequest;
use App\Services\Account\ProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(private ProfileService $profiles) {}

    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('account/profile', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'mobile_verified' => (bool) $user->mobile_verified_at,
                'has_password' => (bool) $user->password,
                'last_login_at' => $user->last_login_at?->toDayDateTimeString(),
            ],
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $this->profiles->update($request->user(), $request->validated());

        return back()->with('success', 'Your profile has been updated.');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $this->profiles->changePassword($request->user(), $request->validated('password'));

        return back()->with('success', 'Your password has been changed.');
    }
}
