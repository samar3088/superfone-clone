<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IvrOption extends Model
{
    protected $fillable = [
        'ivr_flow_id',
        'key_press',
        'label',
        'action',
        'destination',
    ];

    public function flow(): BelongsTo
    {
        return $this->belongsTo(IvrFlow::class, 'ivr_flow_id');
    }
}
