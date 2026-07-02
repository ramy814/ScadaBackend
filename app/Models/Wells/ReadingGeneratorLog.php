<?php

declare(strict_types=1);

namespace App\Models\Wells;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Traits\Wells\UppercaseKeys;

class ReadingGeneratorLog extends Model
{
    use UppercaseKeys;

    protected $table = 'reading_generator_logs';

    protected $primaryKey = 'oid';

    public $timestamps = false;

    protected $fillable = [
        'READING_OID',
        'GENERATOR_OID',
        'CURRENT_READING',
        'GALLONS',
        'CREATED_AT',
    ];

    protected function casts(): array
    {
        return [
            'READING_OID' => 'integer',
            'GENERATOR_OID' => 'integer',
            'CURRENT_READING' => 'decimal:2',
            'GALLONS' => 'decimal:2',
            'CREATED_AT' => 'datetime',
        ];
    }

    public function reading(): BelongsTo
    {
        return $this->belongsTo(WellReading::class, 'reading_oid', 'oid');
    }
}
