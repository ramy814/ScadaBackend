<?php

declare(strict_types=1);

namespace App\Modules\Wells\Models;

use App\Platform\Concerns\UppercaseKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationType extends Model
{
    use UppercaseKeys;

    protected $connection = 'oracle';

    protected $table = 'notification_types';

    protected $primaryKey = 'oid';

    protected $fillable = [
        'NAME',
        'ICON',
        'COLOR',
        'EMOJI',
        'PRIORITY',
        'IS_ACTIVE',
    ];

    protected function casts(): array
    {
        return [
            'IS_ACTIVE' => 'boolean',
            'PRIORITY' => 'integer',
        ];
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(WellNotification::class, 'notification_type_oid', 'oid');
    }

    public function hasBeenUsed(): bool
    {
        return $this->notifications()->exists();
    }

    public function scopeActive($query)
    {
        return $query->where('IS_ACTIVE', true);
    }
}
