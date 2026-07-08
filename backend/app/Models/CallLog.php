<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallLog extends Model
{
    protected $fillable = [
        'customer_id',
        'agent_id',
        'virtual_number',
        'caller',
        'direction',
        'duration_sec',
        'status',
        'called_at',
    ];

    protected $casts = [
        'called_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'agent_id');
    }
}
