<?php

declare(strict_types=1);

namespace App\Models\Wells;

use App\Traits\Wells\UppercaseKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WellMedia extends Model
{
    use UppercaseKeys;

    protected $table = 'well_media';

    protected $primaryKey = 'oid';

    protected $fillable = [
        'WELL_OID',
        'CATEGORY',
        'LABEL',
        'FILE_PATH',
        'ORIGINAL_NAME',
        'MIME_TYPE',
    ];

    protected $appends = ['url'];

    public function well(): BelongsTo
    {
        return $this->belongsTo(Well::class, 'well_oid', 'oid');
    }

    public function getUrlAttribute(): string
    {
        return route('wells.media.show', ['media' => $this->getKey()]);
    }
}
