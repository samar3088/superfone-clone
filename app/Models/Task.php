<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    public const TRIGGER_NEW_LEAD = 'new_lead';

    public const TRIGGER_EXISTING_LEAD = 'existing_lead';

    public const TRIGGER_MANUAL = 'manual';

    protected $fillable = [
        'lead_id', 'assigned_to', 'integration_id', 'trigger',
        'type', 'title', 'due_at', 'completed_at', 'completed_by',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('completed_at');
    }

    /** Past its due time and still not done. */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()->whereNotNull('due_at')->where('due_at', '<', now());
    }

    public function isOverdue(): bool
    {
        return ! $this->completed_at && $this->due_at && $this->due_at->isPast();
    }
}
