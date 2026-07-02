<?php

declare(strict_types=1);

namespace App\Models\Wells;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Wells\UppercaseKeys;

class WellReading extends Model
{
    use UppercaseKeys;

    protected $table = 'well_readings';

    protected $primaryKey = 'oid';

    protected $fillable = [
        'WELL_OID',
        'INSPECTOR_OID',
        'FLOW_RATE',
        'CHLORINE',
        'SALINITY',
        'QUALITY',
        'READING_DATE',
        'LATITUDE',
        'LONGITUDE',
        'SYNCED_AT',
    ];

    protected function casts(): array
    {
        return [
            'WELL_OID' => 'integer',
            'INSPECTOR_OID' => 'integer',
            'FLOW_RATE' => 'decimal:2',
            'CHLORINE' => 'decimal:2',
            'SALINITY' => 'decimal:2',
            'LATITUDE' => 'decimal:8',
            'LONGITUDE' => 'decimal:8',
            'READING_DATE' => 'datetime',
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

    public function generatorLogs(): HasMany
    {
        return $this->hasMany(ReadingGeneratorLog::class, 'reading_oid', 'oid');
    }
}
