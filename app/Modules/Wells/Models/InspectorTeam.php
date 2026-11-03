<?php

declare(strict_types=1);

namespace App\Modules\Wells\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class InspectorTeam extends Pivot
{
    protected $table = 'inspector_teams';

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
