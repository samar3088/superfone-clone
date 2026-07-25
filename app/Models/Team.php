<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Team extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name', 'status',
        'virtual_number', 'number_provider', 'number_assigned_at',
        'staff_limit', 'lead_limit', 'plan_renews_at',
    ];

    protected function casts(): array
    {
        return [
            'number_assigned_at' => 'datetime',
            'plan_renews_at' => 'date',
        ];
    }

    public function hasNumber(): bool
    {
        return filled($this->virtual_number);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'status', 'virtual_number', 'staff_limit', 'lead_limit', 'plan_renews_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
