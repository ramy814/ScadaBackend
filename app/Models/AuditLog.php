<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['event_type', 'entity_type', 'entity_id', 'actor', 'payload'])]
class AuditLog extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}

