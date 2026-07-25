<?php

namespace App\Support;

/**
 * Providers offered in "Create a new integration".
 *
 * Only Facebook is wired up today; the rest are listed so the catalogue
 * matches the product, and each becomes available by adding a lead source
 * class plus flipping `available`.
 */
final class LeadProviders
{
    public const FACEBOOK = 'facebook';

    /** @return array<int, array{key: string, label: string, badge: string, color: string, available: bool}> */
    public static function all(): array
    {
        return [
            ['key' => self::FACEBOOK, 'label' => 'Facebook', 'badge' => 'f', 'color' => '#1877f2', 'available' => true],
            ['key' => 'indiamart', 'label' => 'IndiaMart', 'badge' => 'M', 'color' => '#b91c1c', 'available' => false],
            ['key' => 'webhook', 'label' => 'Custom Webhook', 'badge' => '⛓', 'color' => '#7c3aed', 'available' => false],
            ['key' => 'housing', 'label' => 'Housing.com', 'badge' => '⌂', 'color' => '#f59e0b', 'available' => false],
            ['key' => '99acres', 'label' => '99acres', 'badge' => '99', 'color' => '#2563eb', 'available' => false],
            ['key' => 'magicbricks', 'label' => 'MagicBricks', 'badge' => 'mb', 'color' => '#dc2626', 'available' => false],
            ['key' => 'ninjaforms', 'label' => 'Ninja Forms', 'badge' => 'N', 'color' => '#111827', 'available' => false],
            ['key' => 'wix', 'label' => 'Wix', 'badge' => 'W', 'color' => '#111827', 'available' => false],
            ['key' => 'email', 'label' => 'Email Leads', 'badge' => '✉', 'color' => '#6b7280', 'available' => false],
            ['key' => 'razorpay', 'label' => 'Razorpay', 'badge' => 'R', 'color' => '#0e4bad', 'available' => false],
            ['key' => 'forminator', 'label' => 'Forminator', 'badge' => 'F', 'color' => '#374151', 'available' => false],
            ['key' => 'shopify', 'label' => 'Shopify', 'badge' => 'S', 'color' => '#16a34a', 'available' => false],
            ['key' => 'woocommerce', 'label' => 'Woo commerce', 'badge' => 'W', 'color' => '#7f54b3', 'available' => false],
            ['key' => 'googlesheet', 'label' => 'Google sheet', 'badge' => '▦', 'color' => '#16a34a', 'available' => false],
            ['key' => 'tradeindia', 'label' => 'TradeIndia', 'badge' => 'ti', 'color' => '#ea580c', 'available' => false],
        ];
    }

    /** Providers shown as filter tabs above the integration list. */
    public static function tabs(): array
    {
        return ['facebook', 'indiamart', 'housing', '99acres', 'magicbricks', 'webhook'];
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
