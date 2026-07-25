<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateFacebookTokenRequest;
use App\Http\Requests\Settings\UpdateNotificationSettingsRequest;
use App\Services\Support\SettingsService;
use App\Support\Permissions;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;

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
    public function updateFacebookToken(UpdateFacebookTokenRequest $request): RedirectResponse
    {
        $this->guard();

        $this->settings->set(Settings::FACEBOOK_TOKEN, $request->validated('token'));

        // Page tokens were derived from the old one and are now stale.
        cache()->forget('fb.pages');

        activity('settings')
            ->causedBy($request->user())
            ->log('Updated the Facebook access token');

        return back()->with('success', 'Facebook token saved. It is encrypted at rest.');
    }

    public function clearFacebookToken(): RedirectResponse
    {
        $this->guard();

        $this->settings->forget(Settings::FACEBOOK_TOKEN);
        cache()->forget('fb.pages');

        activity('settings')->causedBy(request()->user())->log('Removed the Facebook access token');

        return back()->with('success', 'Facebook token removed. Lead syncing will stop until a new one is saved.');
    }
}
