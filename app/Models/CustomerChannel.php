<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One phone number or email address belonging to a customer. */
class CustomerChannel extends Model
{
    public const PHONE = 'phone';

    public const EMAIL = 'email';

    protected $fillable = ['customer_id', 'type', 'value', 'is_primary'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** Phones keep the last 10 digits; emails are lower-cased and trimmed. */
    public static function normalise(string $type, string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if ($type === self::EMAIL) {
            $value = mb_strtolower($value);

            return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (strlen($digits) > 10) {
            $digits = substr($digits, -10);
        }

        return preg_match('/^[6-9]\d{9}$/', $digits) === 1 ? $digits : null;
    }
}
