<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Represents an entry in the Oracle SYSTEM_PERMISSIONS table.
 * Controls system access and sensitive action permissions for an SSO user.
 *
 * @property int         $id
 * @property int         $user_id      — references mog_users.users_list.id
 * @property string      $system_key   — system or action permission key
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
