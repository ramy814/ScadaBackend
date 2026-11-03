<?php

declare(strict_types=1);

namespace App\Modules\Valves\Models;

use App\Platform\Concerns\UppercaseKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Sanctum\HasApiTokens;

class ValveInspector extends Model
{
    use HasApiTokens, UppercaseKeys;

    protected $table = 'valve_inspectors';

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
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(ValveTeam::class, 'valve_inspector_teams', 'inspector_oid', 'team_oid')
            ->withPivot('created_at');
    }

    public function scopeActive($query)
    {
        return $query->where('IS_ACTIVE', true);
    }
}
