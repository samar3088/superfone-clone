<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FieldPriority extends Model
{
    protected $fillable = ['section', 'field_key', 'label', 'is_selected', 'sequence'];

    protected function casts(): array
    {
        return ['is_selected' => 'boolean'];
    }
}
