<?php

declare(strict_types=1);

namespace App\Models\Valves;

use App\Traits\Wells\UppercaseKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ValveTeam extends Model
{
    use UppercaseKeys;

    protected $table = 'valve_teams';

    protected $primaryKey = 'oid';

    protected $fillable = [
        'NAME',
        'IS_ACTIVE',
        'ASSIGN_ALL_VALVES',
    ];

    protected function casts(): array
    {
        return [
            'IS_ACTIVE' => 'boolean',
            'ASSIGN_ALL_VALVES' => 'boolean',
            'is_active' => 'boolean',
            'assign_all_valves' => 'boolean',
        ];
    }

    public function valves(): BelongsToMany
    {
        return $this->belongsToMany(Valve::class, 'valve_team_valves', 'team_oid', 'valve_oid')
            ->withPivot('created_at');
    }

    public function inspectors(): BelongsToMany
    {
        return $this->belongsToMany(ValveInspector::class, 'valve_inspector_teams', 'team_oid', 'inspector_oid')
            ->withPivot('created_at');
    }

    public function scopeActive($query)
    {
        return $query->where('IS_ACTIVE', true);
    }
}
