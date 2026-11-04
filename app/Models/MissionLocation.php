<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MissionLocation extends Model
{
    protected $fillable = ['name', 'category', 'sort_order', 'active'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
