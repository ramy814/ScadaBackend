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

    public function test_fleet_access_allows_viewing_mobile_and_legacy_trips_without_manual_station_assignments(): void
    {
        $permissions = Mockery::mock(SystemPermissionRepository::class);
        $permissions->shouldReceive('hasAccess')->twice()->with(166, 'fleet')->andReturnTrue();
        $permissions->shouldNotReceive('hasActionPermission');
        $vehicles = Mockery::mock(MunicipalityVehicleRepository::class);
        $vehicles->shouldNotReceive('reviewerCanAccessVehicle');
        $service = new FleetVehicleAccessService($permissions, $vehicles);
        foreach (['mobile', null] as $source) {
            $trip = new Trip;
            $trip->entry_source = $source;
            self::assertTrue($service->canViewTrip(166, $trip));
        }
    }

    public function test_screen_access_alone_does_not_expose_mobile_trips_without_fleet_access(): void
    {
        $permissions = Mockery::mock(SystemPermissionRepository::class);
        $permissions->shouldReceive('hasAccess')->once()->with(166, 'fleet')->andReturnFalse();
        $trip = new Trip;
        $trip->entry_source = 'mobile';
        self::assertFalse((new FleetVehicleAccessService($permissions, Mockery::mock(MunicipalityVehicleRepository::class)))->canViewTrip(166, $trip));
    }

    public function test_viewing_a_manual_trip_still_requires_its_review_station_scope(): void
    {
        $permissions = Mockery::mock(SystemPermissionRepository::class);
        $permissions->shouldReceive('hasActionPermission')->once()->with(166, SystemPermissionRepository::REVIEW_TRIPS)->andReturnFalse();
        $trip = new Trip;
        $trip->entry_source = 'manual';
        self::assertFalse((new FleetVehicleAccessService($permissions, Mockery::mock(MunicipalityVehicleRepository::class)))->canViewTrip(166, $trip));
    }
}
