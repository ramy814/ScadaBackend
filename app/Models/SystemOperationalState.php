<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemOperationalState extends Model
{
    protected $connection = 'oracle';

    protected $table = 'system_operational_states';

    public $timestamps = false;

    protected $fillable = [
        'scope',
        'is_suspended',
        'reason',
        'changed_by_user_id',
        'changed_by_name',
        'changed_at',
    ];

    protected $casts = [
        'is_suspended' => 'boolean',
        'changed_by_user_id' => 'integer',
        'changed_at' => 'datetime',
    ];
}
