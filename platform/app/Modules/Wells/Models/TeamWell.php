<?php

declare(strict_types=1);

namespace App\Modules\Wells\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class TeamWell extends Pivot
{
    protected $table = 'team_wells';

    protected $primaryKey = 'OID';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'TEAM_OID',
        'WELL_OID',
        'CREATED_AT',
    ];

    protected function casts(): array
    {
        return [
            'TEAM_OID' => 'integer',
            'WELL_OID' => 'integer',
            'CREATED_AT' => 'datetime',
        ];
    }
}
