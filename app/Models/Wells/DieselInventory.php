<?php

declare(strict_types=1);

namespace App\Models\Wells;

use App\Traits\Wells\UppercaseKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DieselInventory extends Model
{
    use UppercaseKeys;

    protected $table = 'diesel_inventories';

    protected $primaryKey = 'oid';

    protected $fillable = [
        'WELL_OID',
        'INSPECTOR_OID',
        'GENERATOR_OID',
        'INVENTORY_DATE',
        'GENERATOR_HOURS_READING',
        'DIESEL_HEIGHT_CM',
        'AVAILABLE_FUEL_LITERS',
        'LATITUDE',
        'LONGITUDE',
        'NOTES',
        'SYNCED_AT',
        'CLIENT_LOCAL_ID',
    ];

    protected function casts(): array
    {
        return [
            'WELL_OID' => 'integer',
            'INSPECTOR_OID' => 'integer',
            'GENERATOR_OID' => 'integer',
            'INVENTORY_DATE' => 'datetime',
            'GENERATOR_HOURS_READING' => 'decimal:2',
            'DIESEL_HEIGHT_CM' => 'decimal:2',
            'AVAILABLE_FUEL_LITERS' => 'decimal:2',
            'LATITUDE' => 'decimal:8',
            'LONGITUDE' => 'decimal:8',
            'SYNCED_AT' => 'datetime',
        ];
    }

    public function well(): BelongsTo
    {
        return $this->belongsTo(Well::class, 'well_oid', 'oid');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(Inspector::class, 'inspector_oid', 'oid');
    }
}
