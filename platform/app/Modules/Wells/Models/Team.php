<?php

declare(strict_types=1);

namespace App\Modules\Wells\Models;

use App\Platform\Concerns\UppercaseKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Team extends Model
{
    use UppercaseKeys;

    protected $table = 'teams';

    protected $primaryKey = 'oid';

    protected $fillable = [
        'NAME',
        'IS_ACTIVE',
    ];

    protected function casts(): array
    {
        return [
            'IS_ACTIVE' => 'boolean',
        ];
    }

    public function wells(): BelongsToMany
    {
        return $this->belongsToMany(Well::class, 'team_wells', 'team_oid', 'well_oid');
    }

    public function inspectors(): BelongsToMany
    {
        return $this->belongsToMany(Inspector::class, 'inspector_teams', 'team_oid', 'inspector_oid');
    }

    public function scopeActive($query)
    {
        return $query->where('IS_ACTIVE', true);
    }
}
