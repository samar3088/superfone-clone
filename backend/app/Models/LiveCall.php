<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveCall extends Model
{
    protected $fillable = [
        'provider',
        'provider_call_id',
        'customer_id',
        'virtual_number',
        'caller',
        'sticky_agent_id',
        'ring_all',
        'answered_by',
        'status',
        'started_at',
        'answered_at',
        'ended_at',
    ];

    protected $casts = [
        'ring_all' => 'boolean',
        'started_at' => 'datetime',
        'answered_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function stickyAgent(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'sticky_agent_id');
    }

    public function answeredBy(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'answered_by');
    }
}
