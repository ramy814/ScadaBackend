<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Represents an entry in the Oracle SYSTEM_PERMISSIONS table.
 * Controls which systems (fleet, wells, valves) a given SSO user may access.
 *
 * @property int         $id
 * @property int         $user_id      — references mog_users.users_list.id
 * @property string      $system_key   — 'fleet' | 'wells' | 'valves'
 * @property string|null $granted_by
 * @property \Carbon\Carbon|null $granted_at
 */
class SystemPermission extends Model
{
    protected $connection = 'oracle';

    protected $table = 'CONTROL_UNIT.SYSTEM_PERMISSIONS';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'system_key',
        'granted_by',
    ];

    protected $casts = [
        'granted_at' => 'datetime',
        'user_id'    => 'integer',
    ];
}
