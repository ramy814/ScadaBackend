<?php

namespace App\Modules\Valves\Models;

use App\Platform\Concerns\UppercaseKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ValveLog extends Model
{
    use UppercaseKeys;

    protected $table = 'valve_logs';

    protected $primaryKey = 'oid';

    protected $fillable = [
        'VALVE_OID',
        'USER_OID',
        'ACTION',
        'NOTE',
        'SMS_RAW',
        'ACTION_AT',
        'CLIENT_LOCAL_ID',
    ];

    protected function casts(): array
    {
        return [
            'ACTION_AT' => 'datetime',
            'action_at' => 'datetime',
        ];
    }

    public function valve(): BelongsTo
    {
        return $this->belongsTo(Valve::class, 'valve_oid', 'w4_general_id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(ValveInspector::class, 'user_oid', 'oid');
    }
}
