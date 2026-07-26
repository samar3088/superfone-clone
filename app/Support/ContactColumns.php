<?php

namespace App\Support;

use App\Models\Customer;

/**
 * The columns a contact download can carry.
 *
 * The first ten are the import template, spelled identically and in the same
 * order. That is deliberate: a download of everything can be edited and fed
 * straight back in, which is how most people actually do a bulk update. The
 * rest are ours — things the importer has no column for and would ignore.
 */
final class ContactColumns
{
    /**
     * Column key => heading.
     *
     * @var array<string, string>
     */
    public const CHOICES = [
        // Round-trips with ContactImportService::COLUMNS.
        'first_name' => 'FIRST NAME',
        'last_name' => 'LAST NAME',
        'primary_phone' => 'PRIMARY PHONE',
        'secondary_phone' => 'SECONDARY PHONE',
        'business_name' => 'BUSINESS NAME',
        'city' => 'CITY',
        'address' => 'ADDRESS',
        'email' => 'EMAIL',
        'website_url' => 'WEBSITE URL',
        'additional_info' => 'ADDITIONAL INFO',

        // Read-only on the way out: the importer has no column for these.
        'tags' => 'TAGS',
        'lead_stage' => 'LEAD STAGE',
        'lead_owner' => 'LEAD OWNER',
        'team' => 'TEAM NAME',
        'leads' => 'LEADS',
        'calls' => 'CALLS',
        'last_activity' => 'LAST ACTIVITY',
        'created_at' => 'DATE CREATED',
    ];

    /** Ticked when the dialog first opens. */
    public const DEFAULTS = [
        'first_name', 'last_name', 'primary_phone', 'secondary_phone',
        'business_name', 'city', 'email',
    ];

    /**
     * Whatever was asked for, in the canonical order, with anything
     * unrecognised dropped. Falls back to the defaults rather than producing a
     * file with no columns at all.
     *
     * @param  array<int, string>  $asked
     * @return array<int, string>
     */
    public static function resolve(array $asked): array
    {
        $known = array_values(array_intersect(array_keys(self::CHOICES), $asked));

        return $known ?: self::DEFAULTS;
    }

    /** @param array<int, string> $keys */
    public static function headings(array $keys): array
    {
        return array_map(fn (string $k) => self::CHOICES[$k], $keys);
    }

    /**
     * One row, in the order the headings were written.
     *
     * @param  array<int, string>  $keys
     * @return array<int, string>
     */
    public static function row(Customer $customer, array $keys): array
    {
        return array_map(fn (string $key) => self::value($customer, $key), $keys);
    }

    private static function value(Customer $customer, string $key): string
    {
        return match ($key) {
            /*
             | The book holds one name, the template wants two. Split on the
             | last space so "Asha Rao" gives Asha / Rao and a three-part name
             | keeps its middle with the first — the alternative loses it.
             */
            'first_name' => self::splitName($customer->name)[0],
            'last_name' => self::splitName($customer->name)[1],

            'primary_phone' => (string) $customer->mobile,
            'secondary_phone' => self::extraChannel($customer, 'phone'),
            'email' => (string) ($customer->email ?? ''),

            'business_name' => (string) ($customer->business_name ?? ''),
            'city' => (string) ($customer->city ?? ''),
            'address' => trim(implode(' ', array_filter([
                $customer->house_no, $customer->address_1, $customer->address_2,
            ]))),
            'website_url' => (string) ($customer->website ?? ''),
            'additional_info' => (string) ($customer->additional_info ?? ''),

            'tags' => $customer->relationLoaded('tags')
                ? $customer->tags->pluck('name')->implode(', ')
                : '',
            'lead_stage' => (string) ($customer->latestLead?->stage?->name ?? ''),
            'lead_owner' => (string) ($customer->latestLead?->assignee?->name ?? ''),
            'team' => (string) ($customer->team?->name ?? ''),
            'leads' => (string) ($customer->leads_count ?? 0),
            'calls' => (string) ($customer->calls_count ?? 0),
            'last_activity' => (string) ($customer->last_activity_at?->toDateTimeString() ?? ''),
            'created_at' => (string) ($customer->created_at?->toDateTimeString() ?? ''),

            default => '',
        };
    }

    /** @return array{0: string, 1: string} */
    private static function splitName(?string $name): array
    {
        $name = trim((string) $name);
        $cut = mb_strrpos($name, ' ');

        return $cut === false
            ? [$name, '']
            : [mb_substr($name, 0, $cut), mb_substr($name, $cut + 1)];
    }

    /**
     * The first channel of this kind that is not the primary one.
     *
     * Blank rather than wrong when the relation was not loaded — a download
     * that silently invented a second number would be worse than an empty cell.
     */
    private static function extraChannel(Customer $customer, string $type): string
    {
        if (! $customer->relationLoaded('channels')) {
            return '';
        }

        return (string) ($customer->channels
            ->where('type', $type)
            ->where('is_primary', false)
            ->pluck('value')
            ->first() ?? '');
    }
}
