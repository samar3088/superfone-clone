<?php

namespace App\Support;

/**
 * Multi-select filters travel as one comma-joined query parameter —
 * `?member=3,7,unassigned` — which keeps a shared URL short and readable
 * instead of repeating the key for every choice.
 */
final class FilterList
{
    /** @return array<int, string> */
    public static function parse(mixed $value): array
    {
        if (is_array($value)) {
            $parts = $value;
        } else {
            $parts = explode(',', (string) $value);
        }

        return collect($parts)
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** Numeric ids only, for whereIn on a key column. */
    public static function ids(mixed $value): array
    {
        return collect(self::parse($value))
            ->filter(fn (string $v) => ctype_digit($v))
            ->map(fn (string $v) => (int) $v)
            ->all();
    }

    /** Every entry is either a positive integer or one of the allowed words. */
    public static function isValid(mixed $value, array $allowedWords = []): bool
    {
        $parts = self::parse($value);

        if ($parts === []) {
            return true;
        }

        foreach ($parts as $part) {
            if (! ctype_digit($part) && ! in_array($part, $allowedWords, true)) {
                return false;
            }
        }

        return true;
    }
}
