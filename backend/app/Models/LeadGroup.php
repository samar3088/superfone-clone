<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadGroup extends Model
{
    protected $fillable = ['customer_id', 'name', 'type', 'active'];

    protected $casts = ['active' => 'boolean'];
}
