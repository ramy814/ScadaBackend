<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Requests\SystemPermissionRequest;
use App\Repositories\MunicipalityVehicleRepository;
use App\Repositories\OracleReadiness;
use App\Repositories\SystemPermissionRepository;
use Illuminate\Support\Facades\Validator;
use Mockery;
use Tests\TestCase;

class SystemPermissionActionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_standalone_action_permissions_fail_closed_when_oracle_is_unavailable(): void
    {
        $readiness = Mockery::mock(OracleReadiness::class);
        $readiness->shouldReceive('available')->once()->andReturnFalse();

        $repository = new SystemPermissionRepository($readiness, Mockery::mock(MunicipalityVehicleRepository::class));

        self::assertFalse($repository->hasActionPermission(
            140,
            SystemPermissionRepository::DELETE_WELL_READING,
        ));
    }

    public function test_legacy_vehicle_category_permissions_are_no_longer_accepted(): void
    {
        foreach ([125, 126, 132, 133] as $category) {
            self::assertNotContains('fleet.vehicles.follow-to.'.$category, SystemPermissionRepository::PERMISSION_KEYS);
        }
    }

    public function test_review_permission_fails_closed_when_oracle_is_unavailable(): void
    {
        $readiness = Mockery::mock(OracleReadiness::class);
        $readiness->shouldReceive('available')->once()->andReturnFalse();

        self::assertFalse((new SystemPermissionRepository($readiness, Mockery::mock(MunicipalityVehicleRepository::class)))->hasActionPermission(
            140,
            SystemPermissionRepository::REVIEW_TRIPS,
        ));
    }

    public function test_system_access_can_be_saved_without_selecting_a_fleet_role(): void
    {
        foreach ([
            ['fleet'],
            ['fleet', 'wells'],
            ['fleet', 'wells', SystemPermissionRepository::DELETE_WELL_READING],
        ] as $systems) {
            $request = SystemPermissionRequest::create('/permissions', 'POST', ['user_id' => 140, 'systems' => $systems]);
            $validator = Validator::make($request->all(), $request->rules());
            $request->withValidator($validator);
            self::assertTrue($validator->passes());
        }
    }

    public function test_automatic_fleet_operations_cannot_be_granted_through_the_permission_form(): void
    {
        foreach ([
            [SystemPermissionRepository::CREATE_MANUAL_TRIP],
            [SystemPermissionRepository::REVIEW_TRIPS],
            ['fleet', SystemPermissionRepository::ENTER_MANUAL_TRIP],
            [SystemPermissionRepository::CREATE_MANUAL_TRIP, SystemPermissionRepository::APPROVE_MANUAL_TRIP],
            ['fleet', SystemPermissionRepository::REVIEW_TRIPS],
            ['fleet', 'fleet.vehicles.follow-to.125'],
        ] as $systems) {
            $request = SystemPermissionRequest::create('/permissions', 'POST', ['user_id' => 140, 'systems' => $systems]);
            $validator = Validator::make($request->all(), $request->rules());
            $request->withValidator($validator);
            self::assertTrue($validator->fails());
        }
    }

    public function test_fleet_operations_are_derived_from_oracle_without_stored_action_grants(): void
    {
        $readiness = Mockery::mock(OracleReadiness::class);
        $readiness->shouldReceive('available')->andReturnTrue();
        $vehicles = Mockery::mock(MunicipalityVehicleRepository::class);
        $repository = Mockery::mock(SystemPermissionRepository::class, [$readiness, $vehicles])->makePartial();
        $repository->shouldReceive('hasAccess')->with(140, 'fleet')->andReturnTrue();

        foreach ([
            ['can_enter' => true, 'can_review' => false],
            ['can_enter' => false, 'can_review' => true],
            ['can_enter' => true, 'can_review' => true],
            ['can_enter' => false, 'can_review' => false],
        ] as $access) {
            $vehicles->shouldReceive('operationAccessForUsers')->times(4)->with([140])->andReturn(collect([140 => $access]));
            self::assertSame($access['can_enter'] || $access['can_review'], $repository->hasActionPermission(140, SystemPermissionRepository::CREATE_MANUAL_TRIP));
            self::assertSame($access['can_enter'], $repository->hasActionPermission(140, SystemPermissionRepository::ENTER_MANUAL_TRIP));
            self::assertSame($access['can_review'], $repository->hasActionPermission(140, SystemPermissionRepository::APPROVE_MANUAL_TRIP));
            self::assertSame($access['can_review'], $repository->hasActionPermission(140, SystemPermissionRepository::REVIEW_TRIPS));
        }
    }

    public function test_oracle_assistant_access_does_not_grant_fleet_dashboard_access(): void
    {
        $readiness = Mockery::mock(OracleReadiness::class);
        $readiness->shouldReceive('available')->andReturnTrue();
        $vehicles = Mockery::mock(MunicipalityVehicleRepository::class);
        $vehicles->shouldReceive('operationAccessForUsers')->times(3)->with([140])
            ->andReturn(collect([140 => ['can_enter' => true, 'can_review' => true]]));
        $repository = Mockery::mock(SystemPermissionRepository::class, [$readiness, $vehicles])->makePartial();
        $repository->shouldReceive('hasAccess')->with(140, 'fleet')->andReturnFalse();

        self::assertTrue($repository->hasActionPermission(140, SystemPermissionRepository::CREATE_MANUAL_TRIP));
        self::assertTrue($repository->hasActionPermission(140, SystemPermissionRepository::ENTER_MANUAL_TRIP));
        self::assertTrue($repository->hasActionPermission(140, SystemPermissionRepository::APPROVE_MANUAL_TRIP));
        self::assertFalse($repository->hasActionPermission(140, SystemPermissionRepository::REVIEW_TRIPS));
    }
}
