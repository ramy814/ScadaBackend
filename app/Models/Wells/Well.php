<?php

declare(strict_types=1);

namespace App\Models\Wells;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Wells\UppercaseKeys;

class Well extends Model
{
    use UppercaseKeys;
    protected $table = 'wells';

    protected $primaryKey = 'oid';

    protected $fillable = [
        // البيانات العامة
        'WELL_NUM',
        'NAME',
        'QUARTER_OID',
        'DAMAGE_STATUS',
        'OPERATIONAL_STATUS',
        'INSTALLATION_DATE',
        'LAST_MAINTENANCE_DATE',
        // بيانات البئر والمضخة
        'WELL_DEPTH',
        'PUMP_TYPE',
        'CAPACITY',
        'SALINITY',
        // الطاقة والموقع
        'POWER_SOURCE',
        'LATITUDE',
        'LONGITUDE',
        'IS_ACTIVE',
        // بيانات خزان الوقود
        'TANK_SHAPE',
        'TANK_MATERIAL',
        'TANK_DIAMETER',
        'TANK_HEIGHT',
        // نظام الكلورة
        'CHLORINATION_SYSTEM',
        'CHLORINE_TANK_CAPACITY',
        'CHLORINE_TANK_TYPE',
        'CHLORINE_TANK_MATERIAL',
        'CHLORINE_PUMP_MODEL',
        'CHLORINE_PUMP_TYPE',
        // خزان التبريد
        'COOLING_TANK_CAPACITY',
        'COOLING_TANK_TYPE',
    ];

    protected function casts(): array
    {
        return [
            'QUARTER_OID'            => 'integer',
            'INSTALLATION_DATE'      => 'date',
            'LAST_MAINTENANCE_DATE'  => 'date',
            'WELL_DEPTH'             => 'decimal:2',
            'CAPACITY'               => 'decimal:2',
            'SALINITY'               => 'decimal:2',
            'TANK_DIAMETER'          => 'decimal:2',
            'TANK_HEIGHT'            => 'decimal:2',
            'CHLORINE_TANK_CAPACITY' => 'decimal:2',
            'COOLING_TANK_CAPACITY'  => 'decimal:2',
            'LATITUDE'               => 'decimal:8',
            'LONGITUDE'              => 'decimal:8',
            'IS_ACTIVE'              => 'boolean',
        ];
    }

    public function readings(): HasMany
    {
        return $this->hasMany(WellReading::class, 'well_oid', 'oid');
    }

    public function dieselInventories(): HasMany
    {
        return $this->hasMany(DieselInventory::class, 'well_oid', 'oid');
    }

    public function generators(): HasMany
    {
        return $this->hasMany(WellGenerator::class, 'well_oid', 'oid');
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_wells', 'well_oid', 'team_oid');
    }

    protected $appends = ['has_transactions'];

    public function getHasTransactionsAttribute(): bool
    {
        // Check if the counts were eager loaded to avoid N+1
        if (isset($this->attributes['readings_count']) || isset($this->attributes['diesel_inventories_count'])) {
            return ($this->readings_count ?? 0) > 0 || ($this->diesel_inventories_count ?? 0) > 0;
        }

        // Fallback to checking exists()
        return $this->readings()->exists() || $this->dieselInventories()->exists();
    }

    public function scopeActive($query)
    {
        return $query->where('IS_ACTIVE', true);
    }
}
