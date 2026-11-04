<?php

declare(strict_types=1);

namespace App\Models\Wells;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Wells\UppercaseKeys;

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
            'PRIORITY'  => 'integer',
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
