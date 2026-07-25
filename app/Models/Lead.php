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
        'name', 'mobile', 'email', 'source', 'campaign', 'assigned_to', 'viewed_at',
    ];

    protected function casts(): array
    {
        return ['viewed_at' => 'datetime'];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** Leads the given user hasn't seen yet — owners see every unread lead. */
    public function scopeUnreadFor(Builder $query, User $user): Builder
    {
        return $query
            ->whereNull('viewed_at')
            ->when(! $user->isOwner(), fn (Builder $q) => $q->where('assigned_to', $user->id));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'mobile', 'assigned_to'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('lead');
    }
}
