<?php

declare(strict_types=1);

namespace App\Platform\Access;

use App\Modules\Fleet\Repositories\MunicipalityVehicleRepository;
use App\Platform\Support\OracleReadiness;

/**
 * Who can do what: SYSTEM_PERMISSIONS grants plus users whose only access is the
 * Oracle station scope (manual entry / approval). Read-only for station scope.
 */
class AccessDirectoryService
{
    public function __construct(
        private readonly SystemPermissionRepository $permissions,
        private readonly MunicipalityVehicleRepository $vehicles,
        private readonly OracleReadiness $readiness,
    ) {}

    public function oracleAvailable(): bool
    {
        return $this->readiness->available();
    }

    /** @return array<int, array<string, mixed>> */
    public function users(): array
    {
        $granted = $this->permissions->groupedByUser()->keyBy('user_id');
        $stationOnly = $this->vehicles->stationOperationUsers()->except($granted->keys()->all());
        $names = $this->permissions->usersById($stationOnly->keys());

        $users = $granted->all();
        foreach ($stationOnly as $userId => $scope) {
            $users[$userId] = [
                'user_id' => $userId,
                'name' => $names->get($userId)?->name,
                'fleet_access' => $scope,
                'systems' => [],
                'permissions' => [],
            ];
        }
        ksort($users);

        return array_values($users);
    }
}
