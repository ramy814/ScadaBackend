<?php

declare(strict_types=1);

namespace App\Modules\Wells\Repositories;

use App\Modules\Wells\Models\DieselInventory;
use App\Modules\Wells\Models\Inspector;
use App\Modules\Wells\Models\NotificationType;
use App\Modules\Wells\Models\Well;
use App\Modules\Wells\Models\WellNotification;
use App\Modules\Wells\Models\WellReading;
use App\Modules\Wells\Services\ActivityMediaService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/** Queries the legacy wells mobile controllers ran inline (Api\Wells\*), unchanged. */
class WellsMobileRepository
{
    public function activeInspectorByPhone(string $phone): ?Inspector
    {
        return Inspector::active()->where('PHONE', $phone)->first();
    }

    public function activeNotificationTypes(): Collection
    {
        return NotificationType::active()->get();
    }

    /** Active wells reachable through the inspector's teams. */
    public function accessibleWellIds(Inspector $inspector): SupportCollection
    {
        return Well::active()
            ->whereHas('teams', fn ($query) => $query->whereIn('teams.oid', $inspector->teams()->pluck('teams.oid')))
            ->pluck('oid');
    }

    public function latestReadings(int $wellOid, int $limit): Collection
    {
        return WellReading::where('well_oid', $wellOid)->orderBy('reading_date', 'desc')->take($limit)->get();
    }

    public function latestDieselInventories(int $wellOid, int $limit): Collection
    {
        return DieselInventory::where('well_oid', $wellOid)->orderBy('inventory_date', 'desc')->take($limit)->get();
    }

    public function latestNotifications(int $wellOid, int $limit): Collection
    {
        return WellNotification::where('well_oid', $wellOid)->orderBy('created_at', 'desc')->take($limit)->get();
    }

    public function activityBelongsTo(string $activityType, int $activityId, int $inspectorId): bool
    {
        $query = match ($activityType) {
            ActivityMediaService::TYPE_READING => WellReading::query(),
            ActivityMediaService::TYPE_NOTIFICATION => WellNotification::query(),
        };

        return $query->where('OID', $activityId)->where('INSPECTOR_OID', $inspectorId)->exists();
    }
}
