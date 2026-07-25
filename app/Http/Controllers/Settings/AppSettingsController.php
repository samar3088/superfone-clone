<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateFacebookTokenRequest;
use App\Http\Requests\Settings\UpdateNotificationSettingsRequest;
use App\LeadSources\FacebookLeadSource;
use App\Services\Support\SettingsService;
use App\Support\Permissions;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class AppSettingsController extends Controller
{
    public function __construct(private SettingsService $settings) {}

    private function guard(): void
    {
        abort_unless(request()->user()?->can(Permissions::SETTINGS_MANAGE), 403);
    }

    public function updateNotifications(UpdateNotificationSettingsRequest $request): RedirectResponse
    {
        $enabled = $request->boolean('new_lead_email');

        $this->settings->set(Settings::NEW_LEAD_EMAIL, $enabled);

        activity('settings')
            ->causedBy($request->user())
            ->log($enabled ? 'Enabled new-lead emails' : 'Disabled new-lead emails');

        return back()->with('success', $enabled
            ? 'Members will now be emailed when a lead is assigned to them.'
            : 'New-lead emails are off.');
    }

    /**
     * Save or rotate the Facebook token.
     *
     * Write-only by design — the value is never sent back to the browser, so
     * the screen can show that a token exists but not what it is.
     */
    public function updateFacebookToken(UpdateFacebookTokenRequest $request, FacebookLeadSource $facebook): RedirectResponse
    {
        $this->guard();

        $token = $request->validated('token');

        /*
         | Prove it works before storing it. Otherwise a mistyped or expired
         | token saves cleanly and lead syncing stops silently — the failure
         | only shows up on the next scheduled run, hours later.
         */
        try {
            $account = $facebook->verifyToken($token);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'token' => 'Facebook rejected that token: '.$e->getMessage(),
            ]);
        }

        $this->settings->set(Settings::FACEBOOK_TOKEN, $token);

        // Page tokens and profiles were derived from the old token.
        $facebook->forgetCaches();

        activity('settings')
            ->causedBy($request->user())
            ->log("Reconnected Facebook as {$account['name']}");

        return back()->with(
            'success',
            "Reconnected as {$account['name']} — {$account['pages']} page"
                .($account['pages'] === 1 ? '' : 's').' available.'
        );
    }

    public function clearFacebookToken(FacebookLeadSource $facebook): RedirectResponse
    {
        $this->guard();

        $this->settings->forget(Settings::FACEBOOK_TOKEN);
        $facebook->forgetCaches();

        activity('settings')->causedBy(request()->user())->log('Removed the Facebook access token');

        return back()->with('success', 'Facebook token removed. Lead syncing will stop until a new one is saved.');
    }
}
