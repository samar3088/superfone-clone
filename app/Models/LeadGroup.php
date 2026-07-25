<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class LeadGroup extends Model
{
    use LogsActivity;

    protected $fillable = ['name', 'type', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'type', 'is_active'])
            ->logOnlyDirty()->dontSubmitEmptyLogs()->useLogName('lead_group');
    }
}
