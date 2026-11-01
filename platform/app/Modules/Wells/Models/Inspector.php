<?php

declare(strict_types=1);

namespace App\Modules\Wells\Models;

use App\Platform\Concerns\UppercaseKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inspector extends Model
{
    use UppercaseKeys;

    protected $table = 'inspectors';

    protected $primaryKey = 'oid';

    protected $fillable = [
        'NAME',
        'PHONE',
        'PASSWORD',
        'IS_ACTIVE',
    ];

    protected $hidden = [
        'PASSWORD',
    ];

    protected function casts(): array
    {
        return [
            'IS_ACTIVE' => 'boolean',
            'PASSWORD' => 'hashed',
        ];
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'inspector_teams', 'inspector_oid', 'team_oid');
    }

    public function readings(): HasMany
    {
        return $this->hasMany(WellReading::class, 'INSPECTOR_OID', 'OID');
    }

    public function dieselInventories(): HasMany
    {
        return $this->hasMany(DieselInventory::class, 'INSPECTOR_OID', 'OID');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(WellNotification::class, 'inspector_oid', 'oid');
    }

    public function scopeActive($query)
    {
        return $query->where('IS_ACTIVE', true);
    }
}
