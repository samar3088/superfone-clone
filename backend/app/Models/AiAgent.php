<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAgent extends Model
{
    protected $fillable = [
        'customer_id',
        'name',
        'type',
        'channel',
        'status',
        'persona',
        'handled',
        'resolution_rate',
    ];

    protected $casts = [
        'resolution_rate' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
