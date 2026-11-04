<?php

declare(strict_types=1);

namespace App\Models\Valves;

use App\Traits\Wells\UppercaseKeys;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Valve extends Model
{
    use UppercaseKeys;

    protected $connection = 'oracle';
    protected $table = 'EME.WATER_VALVES';
    protected $primaryKey = 'w4_general_id';

    public $timestamps = false;

    protected $guarded = ['W4_GENERAL_ID'];

    protected $hidden = [
        'map_latitude',
        'map_longitude',
    ];

    protected $appends = [
        'oid',
        'code',
        'name',
        'location_description',
        'status',
        'area',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'W4_GENERAL_ID' => 'integer',
            'W4_STREET_NO' => 'integer',
            'W4_VALVE_DIAMETER' => 'float',
            'W4_PROJECT_NO' => 'integer',
            'W4_QUALITY' => 'float',
            'W4_VALVE_NO' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('without_geometry', function (Builder $builder): void {
            if ($builder->getQuery()->columns === null) {
                $builder->select([
                    'W4_GENERAL_ID',
                    'W4_STREET_NO',
                    'W4_VALVE_TYPE',
                    'W4_VALVE_DIAMETER',
                    'W4_MANUFACTURER',
                    'W4_OPERATION',
                    'W4_STATUS',
                    'W4_PROJECT_NO',
                    'W4_SOURCE',
                    'W4_QUALITY',
                    'W4_VALVE_NO',
                    'W4_VALVE_OPEN',
                ]);
            }
        });
    }

    public function getOidAttribute(): ?int
    {
        $value = $this->attributes['w4_general_id'] ?? $this->attributes['W4_GENERAL_ID'] ?? null;

        return $value === null ? null : (int) $value;
    }

    public function getCodeAttribute(): string
    {
        return 'WV-'.$this->oid;
    }

    public function getNameAttribute(): string
    {
        $type = trim((string) ($this->attributes['w4_valve_type'] ?? ''));
        $diameter = $this->attributes['w4_valve_diameter'] ?? null;

        return trim('محبس '.$this->oid.($type !== '' ? ' - '.$type : '').($diameter ? ' '.$diameter : ''));
    }

    public function getLocationDescriptionAttribute(): ?string
    {
        $streetNo = $this->attributes['w4_street_no'] ?? null;

        return $streetNo ? 'شارع رقم '.$streetNo : null;
    }

    public function getStatusAttribute(): ?string
    {
        $action = null;

        if ($this->relationLoaded('latestLog') && $this->latestLog) {
            $action = $this->latestLog->action ?? $this->latestLog->ACTION ?? null;
        } elseif ($this->relationLoaded('logs') && $this->logs->isNotEmpty()) {
            $action = $this->logs->first()->action ?? $this->logs->first()->ACTION ?? null;
        }

        if ($action) {
            $action = strtolower((string) $action);
            return $action === 'close' ? 'closed' : $action;
        }

        $status = $this->attributes['w4_valve_open'] ?? $this->attributes['w4_status'] ?? null;

        return $status === null ? null : strtolower((string) $status);
    }

    public function getAreaAttribute(): ?string
    {
        $source = trim((string) ($this->attributes['w4_source'] ?? ''));
        $streetNo = $this->attributes['w4_street_no'] ?? null;

        return $streetNo ? 'شارع '.$streetNo : ($source !== '' ? $source : null);
    }

    public function getLatitudeAttribute(): ?float
    {
        $value = $this->attributes['map_latitude'] ?? null;

        return $value === null ? null : (float) $value;
    }

    public function getLongitudeAttribute(): ?float
    {
        $value = $this->attributes['map_longitude'] ?? null;

        return $value === null ? null : (float) $value;
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ValveLog::class, 'VALVE_OID', 'w4_general_id')->orderBy('ACTION_AT', 'desc');
    }

    public function latestLog()
    {
        return $this->hasOne(ValveLog::class, 'VALVE_OID', 'w4_general_id')->latestOfMany('ACTION_AT');
    }

    public function maintenanceReports(): HasMany
    {
        return $this->hasMany(ValveMaintenanceReport::class, 'VALVE_OID', 'w4_general_id');
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(ValveTeam::class, 'valve_team_valves', 'valve_oid', 'team_oid')
            ->withPivot('created_at');
    }

    public function scopeOpen($query)
    {
        return $query->where('W4_VALVE_OPEN', 'OPEN');
    }

    public function scopeClosed($query)
    {
        return $query->where('W4_VALVE_OPEN', 'CLOSED');
    }

    public function scopeNeedsMaintenance($query)
    {
        return $query->where('W4_STATUS', 'MAINTENANCE');
    }

    public function scopeInArea($query, string $area)
    {
        return $query->where('W4_SOURCE', $area);
    }
}
