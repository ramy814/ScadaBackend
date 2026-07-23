<?php

declare(strict_types=1);

namespace App\Models\Wells;

use App\Traits\Wells\UppercaseKeys;
use Illuminate\Database\Eloquent\Model;

class WellActivityMedia extends Model
{
    use UppercaseKeys;

    protected $table = 'well_activity_media';

    protected $primaryKey = 'oid';

    protected $fillable = [
        'ACTIVITY_TYPE',
        'ACTIVITY_OID',
        'CLIENT_LOCAL_ID',
        'FILE_PATH',
        'ORIGINAL_NAME',
        'MIME_TYPE',
        'FILE_SIZE',
    ];

    protected $appends = ['url'];

    protected function casts(): array
    {
        return [
            'ACTIVITY_OID' => 'integer',
            'FILE_SIZE' => 'integer',
        ];
    }

    public function getUrlAttribute(): string
    {
        return route(
            'wells.activity-media.show',
            ['media' => $this->getKey()],
            false,
        );
    }
}
