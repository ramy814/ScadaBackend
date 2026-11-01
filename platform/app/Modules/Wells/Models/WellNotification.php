<?php

declare(strict_types=1);

namespace App\Modules\Wells\Models;

use App\Platform\Concerns\UppercaseKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'CLIENT_LOCAL_ID',
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

    public function media(): HasMany
    {
        return $this->hasMany(
            WellActivityMedia::class,
            'activity_oid',
            'oid',
        )->where('ACTIVITY_TYPE', 'notification');
    }
}
