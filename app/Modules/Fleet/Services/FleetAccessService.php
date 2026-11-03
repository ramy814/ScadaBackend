<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Services;

use App\Modules\Fleet\Models\Trip;
use App\Modules\Fleet\Repositories\MunicipalityVehicleRepository;
use App\Platform\Access\SystemPermissionRepository;

/** Ported verbatim from the legacy FleetVehicleAccessService (memoized per request). */
class FleetAccessService
{
    /** @var array<int, array<int, string>> */
    private array $vehicleIds = [];

    public function __construct(
        private readonly SystemPermissionRepository $permissions,
        private readonly MunicipalityVehicleRepository $vehicles,
    ) {}

    /** @return array<int, string> Vehicle scope (ACCESS_TYPE 2/3) for manual visibility and review. */
    public function allowedVehicleIds(int $userId): array
    {
        if (! isset($this->vehicleIds[$userId])) {
            $this->vehicleIds[$userId] = $this->permissions->hasActionPermission($userId, SystemPermissionRepository::REVIEW_TRIPS)
                ? $this->vehicles->vehicleIdsForReviewer($userId)->all()
                : [];
        }

        return $this->vehicleIds[$userId];
    }

    public function canReview(int $userId, Trip $trip): bool
    {
        if ($trip->entry_source === 'manual' && $trip->review_status === 'draft') {
            return false;
        }

        // Live Oracle check for every decision (legacy canAccessTrip()).
        return $this->permissions->hasActionPermission($userId, SystemPermissionRepository::REVIEW_TRIPS)
            && $this->vehicles->reviewerCanAccessVehicle($userId, $trip->oracle_vehicle_id);
    }

    /** List-row flag from the memoized scope; decisions still use canReview(). */
    public function inScope(int $userId, Trip $trip): bool
    {
        return in_array((string) $trip->oracle_vehicle_id, $this->allowedVehicleIds($userId), true);
    }

    public function canView(int $userId, Trip $trip): bool
    {
        if (in_array($trip->entry_source, [null, 'mobile'], true)) {
            return $this->permissions->hasAccess($userId, 'fleet');
        }

        return $this->canReview($userId, $trip);
    }
}
