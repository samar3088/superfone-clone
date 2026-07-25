<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Integration extends Model
{
    use LogsActivity;

    protected $fillable = [
        'provider', 'name', 'external_page_id', 'page_name', 'external_form_id',
        'form_name', 'connected_account', 'status', 'last_synced_at', 'created_by',
    ];

    protected function casts(): array
    {
        return ['last_synced_at' => 'datetime'];
    }

    /** Team members who receive leads from this campaign. */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'integration_user')->withTimestamps();
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'page_name', 'form_name', 'status'])
            ->logOnlyDirty()->dontSubmitEmptyLogs()->useLogName('integration');
    }
}
