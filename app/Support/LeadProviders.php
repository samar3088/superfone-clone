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

    /**
     * "Where are these leads coming from?"
     *
     * Describes how a lead reached us, which is not the same as the integration
     * that carried it — a lead can arrive from a spreadsheet or a phone call
     * with no integration at all.
     */
    public static function sourceTypes(): array
    {
        return [
            'CSV Upload',
            'Facebook Integration',
            'Phone Contact',
            'Whatsapp Message',
            'Whatsapp Integration',
            'Pabbly',
            'Others',
        ];
    }

    /** Task types offered by the New Lead and Existing Lead to-do rules. */
    public static function todoTypes(): array
    {
        return [
            'FOLLOW-UP CALL',
            'REMINDER',
            'FIRST CALL',
            'CALLBACK REQUEST',
            'SITE VISIT',
            'BOOKING/ APPOINTMENT/ DEMO',
        ];
    }
}
