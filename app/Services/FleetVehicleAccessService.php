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

    /** @return array<int, string> */
    public function allowedVehicleIds(int $userId): array
    {
        return $this->vehicles
            ->vehicleIdsForFollowToOids($this->permissions->fleetFollowToOids($userId))
            ->all();
    }

    public function canAccessTrip(int $userId, Trip $trip): bool
    {
        return $this->vehicles->vehicleMatchesFollowToOids(
            $trip->oracle_vehicle_id,
            $this->permissions->fleetFollowToOids($userId),
        );
    }
}
