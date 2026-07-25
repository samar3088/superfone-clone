<?php

namespace App\Support;

/** Keys used in the app_settings store. */
class Settings
{
    /** Facebook user access token, held encrypted. */
    public const FACEBOOK_TOKEN = 'facebook.access_token';

    /**
     * Whether an assigned member is emailed when a lead arrives.
     *
     * Deliberately off by default: a busy campaign produces thousands of leads,
     * and switching this on without meaning to would flood inboxes and put the
     * sending domain at risk.
     */
    public const NEW_LEAD_EMAIL = 'notifications.new_lead_email';

    /** Encrypted at rest — never rendered back to the browser. */
    public const ENCRYPTED = [
        self::FACEBOOK_TOKEN,
    ];

    public static function isEncrypted(string $key): bool
    {
        return in_array($key, self::ENCRYPTED, true);
    }
}
