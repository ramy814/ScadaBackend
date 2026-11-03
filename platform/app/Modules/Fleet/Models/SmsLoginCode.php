<?php

namespace App\Modules\Fleet\Models;

use Illuminate\Database\Eloquent\Model;

class SmsLoginCode extends Model
{
    protected $fillable = [
        'phone',
        'code_hash',
        'expires_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}
