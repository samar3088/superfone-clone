<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    protected $fillable = ['customer_id', 'name', 'color', 'hidden'];

    protected $casts = ['hidden' => 'boolean'];
}
