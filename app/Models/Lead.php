<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Lead extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        // external_id must stay fillable — the sync dedupes on it, and mass
        // assignment would otherwise drop it silently and re-import every run.
        'external_id',
        'customer_id', 'name', 'mobile', 'email', 'source', 'campaign',
        'integration_id', 'lead_stage_id', 'lead_group_id', 'custom_data',
        'assigned_to', 'viewed_at', 'version', 'last_updated_by',
        'is_existing', 'duplicate_of_id',
    ];

    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
            'notify_at' => 'datetime',
            'notified_at' => 'datetime',
            'custom_data' => 'array',
            'is_existing' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** The earlier enquiry this one repeats, when it is a repeat. */
    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(LeadStage::class, 'lead_stage_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(LeadGroup::class, 'lead_group_id');
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    public function lastEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }

    /** Leads the given user hasn't seen — owners see every unread lead. */
    public function scopeUnreadFor(Builder $query, User $user): Builder
    {
        return $query
            ->whereNull('viewed_at')
            ->when(! $user->isOwner(), fn (Builder $q) => $q->where('assigned_to', $user->id));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'mobile', 'assigned_to', 'lead_stage_id', 'lead_group_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('lead');
    }
}
