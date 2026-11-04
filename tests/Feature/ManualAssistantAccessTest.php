<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MogSsoUser;
use App\Repositories\MunicipalityVehicleRepository;
use App\Repositories\OracleReadiness;
use App\Repositories\SystemPermissionRepository;
use App\Services\ManualTripService;
use App\Services\SystemOperationalStateService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class ManualAssistantAccessTest extends TestCase
{
    public function test_submission_requires_authenticated_assistant_screen_and_entry_access(): void
    {
        $middleware = app('router')->getRoutes()->getByName('admin.manual-trips.submit')->gatherMiddleware();
        self::assertContains('auth:admin', $middleware);
        self::assertContains('action.permission:'.SystemPermissionRepository::CREATE_MANUAL_TRIP, $middleware);
        self::assertContains('action.permission:'.SystemPermissionRepository::ENTER_MANUAL_TRIP, $middleware);
    }

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

    public function test_oracle_operations_admit_assistants_without_granting_any_system(): void
    {
        config(['database.connections.oracle' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('oracle');
        $db = DB::connection('oracle');
        $db->statement("attach database ':memory:' as CONTROL_UNIT");
        $db->statement('create table CONTROL_UNIT.SYSTEM_PERMISSIONS (id integer primary key autoincrement, user_id integer, system_key text, granted_by text, granted_at text default current_timestamp)');
        $readiness = Mockery::mock(OracleReadiness::class);
        $readiness->shouldReceive('available')->andReturnTrue();
        $operational = Mockery::mock(SystemOperationalStateService::class);
        $operational->shouldReceive('current')->andReturn(['shutdown_enabled' => false]);
        $this->app->instance(SystemOperationalStateService::class, $operational);
        $manualTrips = Mockery::mock(ManualTripService::class);
        $manualTrips->shouldReceive('indexOptions')->andReturn(['trips' => ['data' => [], 'total' => 0], 'vehicles' => []]);
        $manualTrips->shouldReceive('approvalOptions')->andReturn(['trips' => ['data' => [], 'total' => 0], 'vehicles' => []]);
        $this->app->instance(ManualTripService::class, $manualTrips);
        $currentAccess = [];
        $vehicles = Mockery::mock(MunicipalityVehicleRepository::class);
        $vehicles->shouldReceive('operationAccessForUsers')->with([140])
            ->andReturnUsing(function () use (&$currentAccess) {
                return collect([140 => $currentAccess]);
            });
        $repository = new SystemPermissionRepository($readiness, $vehicles);
        $this->app->instance(SystemPermissionRepository::class, $repository);
        foreach ([[true, false], [false, true], [true, true], [false, false]] as [$enter, $review]) {
            $currentAccess = ['can_enter' => $enter, 'can_review' => $review];
            self::assertSame($enter || $review, $repository->hasAnyAccess(140));
            self::assertSame($enter || $review, $repository->hasActionPermission(140, SystemPermissionRepository::CREATE_MANUAL_TRIP));
            self::assertSame($enter, $repository->hasActionPermission(140, SystemPermissionRepository::ENTER_MANUAL_TRIP));
            self::assertSame($review, $repository->hasActionPermission(140, SystemPermissionRepository::APPROVE_MANUAL_TRIP));
            self::assertSame([], $repository->accessibleSystems(140)->all());
            foreach (['fleet', 'wells', 'valves'] as $system) {
                self::assertFalse($repository->hasAccess(140, $system));
            }
            self::assertFalse($repository->hasActionPermission(140, SystemPermissionRepository::REVIEW_TRIPS));

            if ($enter || $review) {
                $this->actingAs((new MogSsoUser)->forceFill(['id' => 140, 'full_name' => 'Assistant']), 'admin');
                $this->get('/')->assertRedirect(route($enter ? 'admin.manual-trips.index' : 'admin.manual-trips.requests'));
                $this->get('/admin/manual-trips')->assertOk();
                foreach (['/admin/dashboard', '/wells', '/valves'] as $url) {
                    $this->get($url)->assertRedirect(route('portal'));
                }
                if (! $enter) {
                    $this->get('/admin/manual-trips/create')->assertForbidden();
                    $this->post('/admin/manual-trips/80/submit')->assertForbidden();
                }
                if ($review) {
                    $this->get('/admin/manual-trips/requests')->assertOk();
                } else {
                    $this->get('/admin/manual-trips/requests')->assertForbidden();
                    $this->post('/admin/manual-trips/80/approve')->assertForbidden();
                }
            }
        }
        self::assertSame(0, $db->table('CONTROL_UNIT.SYSTEM_PERMISSIONS')->count());

        // Old dashboard grants alone must no longer authorize assistant access.
        $db->table('CONTROL_UNIT.SYSTEM_PERMISSIONS')->insert(['user_id' => 140, 'system_key' => SystemPermissionRepository::CREATE_MANUAL_TRIP]);
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
        $repository->shouldNotReceive('hasAccess');

        self::assertTrue($repository->hasActionPermission(140, SystemPermissionRepository::APPROVE_MANUAL_TRIP));
    }
}
