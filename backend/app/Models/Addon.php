<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Addon extends Model
{
    protected $fillable = ['name', 'icon', 'price', 'unit', 'quantity_based'];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity_based' => 'boolean',
    ];
}
