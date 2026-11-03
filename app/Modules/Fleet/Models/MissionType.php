<?php

namespace App\Modules\Fleet\Models;

use Illuminate\Database\Eloquent\Model;

class MissionType extends Model
{
    protected $fillable = ['name', 'sort_order', 'active'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'active' => 'boolean',
        ];
    }
}
