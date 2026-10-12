<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Trip;
use App\Repositories\MunicipalityVehicleRepository;
use App\Repositories\SystemPermissionRepository;
use App\Services\FleetVehicleAccessService;
use Mockery;
use Tests\TestCase;

class FleetVehicleAccessServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_resolves_vehicle_ids_and_authorizes_trips_from_follow_to_permissions(): void
    {
        $permissions = Mockery::mock(SystemPermissionRepository::class);
        $permissions->shouldReceive('fleetFollowToOids')
            ->twice()
            ->with(140)
            ->andReturn([125, 132]);

        $vehicles = Mockery::mock(MunicipalityVehicleRepository::class);
        $vehicles->shouldReceive('vehicleIdsForFollowToOids')
            ->once()
            ->with([125, 132])
            ->andReturn(collect(['10', '20']));
        $vehicles->shouldReceive('vehicleMatchesFollowToOids')
            ->once()
            ->with('20', [125, 132])
            ->andReturnTrue();

        $service = new FleetVehicleAccessService($permissions, $vehicles);
        $trip = new Trip;
        $trip->oracle_vehicle_id = '20';

        self::assertSame(['10', '20'], $service->allowedVehicleIds(140));
        self::assertTrue($service->canAccessTrip(140, $trip));
    }
}
