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
        'provider', 'name', 'external_page_id', 'page_name', 'page_picture',
        'page_description', 'external_form_id', 'form_name', 'connected_account',
        'source_type', 'source', 'lead_stage_id', 'lead_group_id',
        'new_lead_enabled', 'todo_enabled', 'todo_type', 'todo_title',
        'todo_due_value', 'todo_due_unit',
        'notify_enabled', 'notify_value', 'notify_unit',
        'existing_lead_enabled', 'existing_todo_enabled', 'existing_todo_type',
        'existing_todo_title', 'existing_todo_due_value', 'existing_todo_due_unit',
        'existing_notify_enabled', 'existing_notify_value', 'existing_notify_unit',
        'status', 'last_synced_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
            'new_lead_enabled' => 'boolean',
            'todo_enabled' => 'boolean',
            'notify_enabled' => 'boolean',
            'existing_lead_enabled' => 'boolean',
            'existing_todo_enabled' => 'boolean',
            'existing_notify_enabled' => 'boolean',
        ];
    }

    /** Team members who receive leads from this campaign. */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'integration_user')->withTimestamps();
    }

    /** Labels stamped on every lead this campaign produces. */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'integration_tag')->withTimestamps();
    }

    public function leadStage(): BelongsTo
    {
        return $this->belongsTo(LeadStage::class, 'lead_stage_id');
    }

    public function leadGroup(): BelongsTo
    {
        return $this->belongsTo(LeadGroup::class, 'lead_group_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(IntegrationLog::class)->latest('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Configuration is only "complete" once the rules needed to route an
     * incoming lead are all in place.
     */
    public function isConfigured(): bool
    {
        return ! $this->needsSettings() && ! $this->needsMapping();
    }

    /**
     * The routing rules are incomplete — an arriving lead would have no source
     * to record and no stage to sit in.
     */
    public function needsSettings(): bool
    {
        return blank($this->source_type)
            || blank($this->source)
            || $this->lead_stage_id === null;
    }

    /** Nobody is mapped to this campaign, so its leads would land unassigned. */
    public function needsMapping(): bool
    {
        return ! $this->members()->exists();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'page_name', 'form_name', 'status', 'source', 'source_type', 'lead_stage_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('integration');
    }
}
