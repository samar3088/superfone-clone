<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Note extends Model
{
    protected $fillable = ['customer_id', 'lead_id', 'user_id', 'body'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** Null when the note is about the contact rather than one enquiry. */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
