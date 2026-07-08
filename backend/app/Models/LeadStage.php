<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadStage extends Model
{
    protected $fillable = ['customer_id', 'sequence', 'name', 'type', 'contacts_attached', 'active'];

    protected $casts = ['active' => 'boolean'];
}
