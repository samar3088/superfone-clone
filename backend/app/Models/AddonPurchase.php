<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AddonPurchase extends Model
{
    protected $fillable = ['customer_id', 'addon_id', 'quantity', 'amount'];

    protected $casts = ['amount' => 'decimal:2'];

    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
