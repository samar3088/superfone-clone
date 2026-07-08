<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrgSetting extends Model
{
    protected $fillable = [
        'customer_id',
        'sticky_agent',
        'sticky_fallback',
        'ivr_enabled',
        'recording_enabled',
        'priority_fields',
    ];

    protected $casts = [
        'sticky_agent' => 'boolean',
        'ivr_enabled' => 'boolean',
        'recording_enabled' => 'boolean',
        'priority_fields' => 'array',
    ];

    /** Settings row for an org, created with defaults on first access. */
    public static function forOrg(int $customerId): self
    {
        return static::firstOrCreate(['customer_id' => $customerId]);
    }
}
