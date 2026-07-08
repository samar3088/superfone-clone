<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    protected $fillable = [
        'customer_id',
        'name',
        'phone',
        'email',
        'source',
        'stage',
        'value',
        'owner',
        'notes',
    ];

    protected $casts = [
        'value' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
