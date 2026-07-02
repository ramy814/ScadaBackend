<?php

declare(strict_types=1);

namespace App\Models\Valves;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ValveInspectorTeam extends Pivot
{
    protected $table = 'valve_inspector_teams';

    protected $primaryKey = 'OID';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'INSPECTOR_OID',
        'TEAM_OID',
        'CREATED_AT',
    ];

    protected function casts(): array
    {
        return [
            'INSPECTOR_OID' => 'integer',
            'TEAM_OID' => 'integer',
            'CREATED_AT' => 'datetime',
        ];
    }
}
