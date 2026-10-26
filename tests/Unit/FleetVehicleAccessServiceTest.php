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

    public function test_it_resolves_vehicle_ids_and_authorizes_trips_from_review_station_scope(): void
    {
        $permissions = Mockery::mock(SystemPermissionRepository::class);
        $permissions->shouldReceive('hasActionPermission')
            ->twice()
            ->with(140, SystemPermissionRepository::REVIEW_TRIPS)
            ->andReturnTrue();

        $vehicles = Mockery::mock(MunicipalityVehicleRepository::class);
        $vehicles->shouldReceive('vehicleIdsForReviewer')
            ->once()
            ->with(140)
            ->andReturn(collect(['10', '20']));
        $vehicles->shouldReceive('reviewerCanAccessVehicle')
            ->once()
            ->with(140, '20')
            ->andReturnTrue();

        $service = new FleetVehicleAccessService($permissions, $vehicles);
        $trip = new Trip;
        $trip->oracle_vehicle_id = '20';

        self::assertSame(['10', '20'], $service->allowedVehicleIds(140));
        self::assertTrue($service->canAccessTrip(140, $trip));
    }

    public function test_entry_only_users_cannot_list_or_review_supervisor_trips(): void
    {
        $permissions = Mockery::mock(SystemPermissionRepository::class);
        $permissions->shouldReceive('hasActionPermission')->twice()
            ->with(140, SystemPermissionRepository::REVIEW_TRIPS)->andReturnFalse();
        $vehicles = Mockery::mock(MunicipalityVehicleRepository::class);
        $vehicles->shouldNotReceive('vehicleIdsForReviewer');
        $vehicles->shouldNotReceive('reviewerCanAccessVehicle');
        $service = new FleetVehicleAccessService($permissions, $vehicles);

        self::assertSame([], $service->allowedVehicleIds(140));
        self::assertFalse($service->canAccessTrip(140, new Trip));
    }

    public function test_review_role_does_not_grant_access_to_a_vehicle_outside_the_station_scope(): void
    {
        $permissions = Mockery::mock(SystemPermissionRepository::class);
        $permissions->shouldReceive('hasActionPermission')->once()
            ->with(140, SystemPermissionRepository::REVIEW_TRIPS)->andReturnTrue();
        $vehicles = Mockery::mock(MunicipalityVehicleRepository::class);
        $vehicles->shouldReceive('reviewerCanAccessVehicle')->once()->with(140, '99')->andReturnFalse();
        $trip = new Trip;
        $trip->oracle_vehicle_id = '99';

        self::assertFalse((new FleetVehicleAccessService($permissions, $vehicles))->canAccessTrip(140, $trip));
    }
}
