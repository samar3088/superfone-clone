<?php

namespace App\Support;

/**
 * Providers offered in "Create a new integration".
 *
 * Deliberately short. A long catalogue of greyed-out logos reads as a broken
 * product rather than a roadmap, so only what is built (Facebook) and what is
 * next (Custom Webhook) are listed. Adding one back means a lead source class
 * plus an entry here.
 */
final class LeadProviders
{
    public const FACEBOOK = 'facebook';

    public const WEBHOOK = 'webhook';

    /** @return array<int, array{key: string, label: string, badge: string, color: string, available: bool}> */
    public static function all(): array
    {
        return [
            ['key' => self::FACEBOOK, 'label' => 'Facebook', 'badge' => 'f', 'color' => '#1877f2', 'available' => true],
            ['key' => self::WEBHOOK, 'label' => 'Custom Webhook', 'badge' => '⛓', 'color' => '#7c3aed', 'available' => false],
        ];
    }

    /** Providers shown as filter tabs above the integration list. */
    public static function tabs(): array
    {
        return [self::FACEBOOK, self::WEBHOOK];
    }

    public static function availableKeys(): array
    {
        return collect(self::all())->where('available', true)->pluck('key')->all();
    }

    /** "Where are these leads coming from?" */
    public static function sourceTypes(): array
    {
        return [
            'Facebook Integration',
            'Google Ads',
            'Website Form',
            'Referral',
            'Walk-in',
            'Other',
        ];
    }

    /** Task types offered by the new-lead To-Do rule. */
    public static function todoTypes(): array
    {
        return ['FIRST CALL', 'FOLLOW UP', 'SEND QUOTATION', 'SITE VISIT', 'OTHER'];
    }
}
