<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Repositories\MunicipalityVehicleRepository;
use App\Repositories\OracleReadiness;
use App\Repositories\SystemPermissionRepository;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class ManualAssistantAccessTest extends TestCase
{
    public function test_approval_routes_require_screen_access_and_oracle_review_access(): void
    {
        foreach (['admin.manual-trips.requests', 'admin.manual-trips.approve'] as $name) {
            $middleware = app('router')->getRoutes()->getByName($name)->gatherMiddleware();
            self::assertContains('auth:admin', $middleware);
            self::assertContains('action.permission:'.SystemPermissionRepository::CREATE_MANUAL_TRIP, $middleware);
            self::assertContains('action.permission:'.SystemPermissionRepository::APPROVE_MANUAL_TRIP, $middleware);
            self::assertNotContains('system.access:fleet', $middleware);
        }
    }

    public function test_screen_access_is_independent_from_dashboard_access_and_vehicle_operations(): void
    {
        config(['database.connections.oracle' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('oracle');
        $db = DB::connection('oracle');
        $db->statement("attach database ':memory:' as CONTROL_UNIT");
        $db->statement('create table CONTROL_UNIT.SYSTEM_PERMISSIONS (id integer primary key autoincrement, user_id integer, system_key text, granted_by text, granted_at text default current_timestamp)');
        $readiness = Mockery::mock(OracleReadiness::class);
        $readiness->shouldReceive('available')->andReturnTrue();
        $vehicles = Mockery::mock(MunicipalityVehicleRepository::class);
        $vehicles->shouldReceive('operationAccessForUsers')->with([140])
            ->andReturn(collect([140 => ['can_enter' => false, 'can_review' => false]]));
        $repository = new SystemPermissionRepository($readiness, $vehicles);

        $repository->replaceForUser(140, [SystemPermissionRepository::CREATE_MANUAL_TRIP], 'Test manager');

        self::assertTrue($repository->hasAnyAccess(140));
        self::assertTrue($repository->hasActionPermission(140, SystemPermissionRepository::CREATE_MANUAL_TRIP));
        self::assertFalse($repository->hasAccess(140, 'fleet'));
        self::assertFalse($repository->hasActionPermission(140, SystemPermissionRepository::ENTER_MANUAL_TRIP));
        self::assertFalse($repository->hasActionPermission(140, SystemPermissionRepository::REVIEW_TRIPS));

        $repository->revoke(140, SystemPermissionRepository::CREATE_MANUAL_TRIP);

        self::assertFalse($repository->hasAnyAccess(140));
        self::assertFalse($repository->hasActionPermission(140, SystemPermissionRepository::CREATE_MANUAL_TRIP));
    }

    public function test_assistant_can_enter_with_oracle_permission_without_dashboard_access(): void
    {
        $readiness = Mockery::mock(OracleReadiness::class);
        $readiness->shouldReceive('available')->andReturnTrue();
        $vehicles = Mockery::mock(MunicipalityVehicleRepository::class);
        $vehicles->shouldReceive('operationAccessForUsers')->with([140])->once()
            ->andReturn(collect([140 => ['can_enter' => true, 'can_review' => false]]));
        $repository = Mockery::mock(SystemPermissionRepository::class, [$readiness, $vehicles])->makePartial();
        $repository->shouldReceive('hasActionPermission')->with(140, SystemPermissionRepository::CREATE_MANUAL_TRIP)->andReturnTrue();
        $repository->shouldNotReceive('hasAccess');

        self::assertTrue($repository->hasActionPermission(140, SystemPermissionRepository::ENTER_MANUAL_TRIP));
    }

    public function test_assistant_can_approve_with_oracle_review_access_without_dashboard_access(): void
    {
        $readiness = Mockery::mock(OracleReadiness::class);
        $readiness->shouldReceive('available')->andReturnTrue();
        $vehicles = Mockery::mock(MunicipalityVehicleRepository::class);
        $vehicles->shouldReceive('operationAccessForUsers')->with([140])->once()
            ->andReturn(collect([140 => ['can_enter' => false, 'can_review' => true]]));
        $repository = Mockery::mock(SystemPermissionRepository::class, [$readiness, $vehicles])->makePartial();
        $repository->shouldReceive('hasActionPermission')->with(140, SystemPermissionRepository::CREATE_MANUAL_TRIP)->andReturnTrue();
        $repository->shouldNotReceive('hasAccess');

        self::assertTrue($repository->hasActionPermission(140, SystemPermissionRepository::APPROVE_MANUAL_TRIP));
    }
}
