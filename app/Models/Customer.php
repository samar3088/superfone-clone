<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Customer extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'team_id', 'created_by',
        'name', 'mobile', 'email', 'city', 'notes', 'last_activity_at',
        'website', 'business_name', 'house_no', 'address_1', 'address_2',
        'additional_info', 'merged_into_id', 'merged_at',
    ];

    protected function casts(): array
    {
        return [
            'last_activity_at' => 'datetime',
            'merged_at' => 'datetime',
        ];
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function calls(): HasMany
    {
        return $this->hasMany(Call::class);
    }

    /** The organisation whose book this contact sits in. */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** Whoever typed this contact in; null for anyone the sync brought in. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Labels on the person, as against the campaign that found them. */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'customer_tag');
    }

    /** Every phone number and email address this customer can be reached on. */
    public function channels(): HasMany
    {
        return $this->hasMany(CustomerChannel::class);
    }

    public function phones(): HasMany
    {
        return $this->channels()->where('type', CustomerChannel::PHONE)->orderByDesc('is_primary');
    }

    public function emails(): HasMany
    {
        return $this->channels()->where('type', CustomerChannel::EMAIL)->orderByDesc('is_primary');
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    /** Records that were folded into this one. */
    public function mergedFrom(): HasMany
    {
        return $this->hasMany(self::class, 'merged_into_id');
    }

    /** Exclude records that have been merged away. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('merged_into_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'mobile', 'email', 'merged_into_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('customer');
    }
}
