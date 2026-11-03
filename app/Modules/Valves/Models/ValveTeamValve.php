<?php

declare(strict_types=1);

namespace App\Modules\Valves\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ValveTeamValve extends Pivot
{
    protected $table = 'valve_team_valves';

    protected $primaryKey = 'OID';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'TEAM_OID',
        'VALVE_OID',
        'CREATED_AT',
    ];

    protected function casts(): array
    {
        return [
            'TEAM_OID' => 'integer',
            'VALVE_OID' => 'integer',
            'CREATED_AT' => 'datetime',
        ];
    }
}
