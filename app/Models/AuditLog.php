<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = ['event_type', 'entity_type', 'entity_id', 'actor', 'payload'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
