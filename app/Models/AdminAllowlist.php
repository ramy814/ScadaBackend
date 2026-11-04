<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Represents an entry in the Oracle FLEET_ADMIN_ALLOWLIST table.
 * Only employees whose EMP_ID appears in this table may access the admin dashboard.
 *
 * @property int    $id
 * @property string $emp_no
 * @property string|null $label
 * @property string|null $added_by
 */
class AdminAllowlist extends Model
{
    protected $connection = 'oracle';

    protected $table = 'FLEET_ADMIN_ALLOWLIST';

    protected $fillable = [
        'emp_no',
        'label',
        'added_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
