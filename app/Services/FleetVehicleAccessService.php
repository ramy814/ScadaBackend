<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Trip;
use App\Repositories\MunicipalityVehicleRepository;
use App\Repositories\SystemPermissionRepository;

class FleetVehicleAccessService
{
    public function __construct(
        private readonly SystemPermissionRepository $permissions,
        private readonly MunicipalityVehicleRepository $vehicles,
    ) {}

    /** @return array<int, string> Vehicle scope for manual visibility and review actions. */
    public function allowedVehicleIds(int $userId): array
    {
        if (! $this->permissions->hasActionPermission($userId, SystemPermissionRepository::REVIEW_TRIPS)) {
            return [];
        }

        return $this->vehicles
            ->vehicleIdsForReviewer($userId)
            ->all();
    }

    public function canAccessTrip(int $userId, Trip $trip): bool
    {
        return $this->permissions->hasActionPermission($userId, SystemPermissionRepository::REVIEW_TRIPS)
            && $this->vehicles->reviewerCanAccessVehicle($userId, $trip->oracle_vehicle_id);
    }

    public function canViewTrip(int $userId, Trip $trip): bool
    {
        if (in_array($trip->entry_source, [null, 'mobile'], true)) {
            return $this->permissions->hasAccess($userId, 'fleet');
        }

        return $this->canAccessTrip($userId, $trip);
    }
}
