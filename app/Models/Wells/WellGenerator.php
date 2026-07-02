<?php

declare(strict_types=1);

namespace App\Models\Wells;

use App\Traits\Wells\UppercaseKeys;
use Illuminate\Database\Eloquent\Relations\Pivot;

class WellGenerator extends Pivot
{
    use UppercaseKeys;

    protected $table = 'well_generators';

    protected $primaryKey = 'oid';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'WELL_OID',
        'GENERATOR_OID',
        'CREATED_AT',
    ];

    protected function casts(): array
    {
        return [
            'WELL_OID' => 'integer',
            'GENERATOR_OID' => 'integer',
            'CREATED_AT' => 'datetime',
        ];
    }
}
