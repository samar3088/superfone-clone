<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IvrFlow extends Model
{
    protected $fillable = [
        'customer_id',
        'name',
        'greeting',
        'status',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(IvrOption::class)->orderBy('key_press');
    }
}
