<?php

namespace App\Models\Valves;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Wells\UppercaseKeys;

class ValveMaintenanceReport extends Model
{
    use UppercaseKeys;

    protected $table = 'valve_maintenance_reports';
    protected $primaryKey = 'oid';

    protected $fillable = [
        'VALVE_OID',
        'REPORTED_BY',
        'DESCRIPTION',
        'PRIORITY',
        'STATUS',
        'RESOLVED_BY',
        'RESOLVED_AT',
    ];

    protected function casts(): array
    {
        return [
            'RESOLVED_AT' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function valve(): BelongsTo
    {
        return $this->belongsTo(Valve::class, 'valve_oid', 'w4_general_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(ValveInspector::class, 'reported_by', 'oid');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(ValveInspector::class, 'resolved_by', 'oid');
    }
}
