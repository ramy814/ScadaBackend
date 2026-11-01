<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Repositories;

use App\Modules\Fleet\Models\MissionLocation;
use App\Modules\Fleet\Models\MissionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** Locations and mission types consumed by the driver app's lookup endpoints. */
class CatalogRepository
{
    public const TYPES = ['locations' => MissionLocation::class, 'mission-types' => MissionType::class];

    public function all(string $type): Collection
    {
        return (self::TYPES[$type])::query()->orderBy('sort_order')->orderBy('name')->get();
    }

    public function save(string $type, ?int $id, array $attributes): Model
    {
        $model = self::TYPES[$type];
        $item = $id === null ? new $model : $model::query()->findOrFail($id);
        $item->fill($attributes)->save();

        return $item;
    }
}
