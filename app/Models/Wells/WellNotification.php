<?php

declare(strict_types=1);

namespace App\Models\Wells;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Wells\UppercaseKeys;

class WellNotification extends Model
{
    use UppercaseKeys;

    protected $connection = 'oracle';

    protected $table = 'well_notifications';
    protected $primaryKey = 'oid';

    protected $fillable = [
        'WELL_OID',
        'NOTIFICATION_TYPE_OID',
        'INSPECTOR_OID',
        'MESSAGE',
        'IS_READ',
        'READ_AT',
    ];

    protected function casts(): array
    {
        return [
            'IS_READ' => 'boolean',
            'READ_AT' => 'datetime',
        ];
    }

    public function well(): BelongsTo
    {
        return $this->belongsTo(Well::class, 'well_oid', 'oid');
    }

    public function notificationType(): BelongsTo
    {
        return $this->belongsTo(NotificationType::class, 'notification_type_oid', 'oid');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(Inspector::class, 'inspector_oid', 'oid');
    }
}
