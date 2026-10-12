<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\OracleReadiness;
use App\Repositories\SystemPermissionRepository;
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

        $repository = new SystemPermissionRepository($readiness);

        self::assertFalse($repository->hasActionPermission(
            140,
            SystemPermissionRepository::DELETE_WELL_READING,
        ));
    }

    public function test_fleet_follow_to_permissions_have_the_required_oracle_values(): void
    {
        self::assertSame([
            SystemPermissionRepository::FLEET_VEHICLES_RENTED => 125,
            SystemPermissionRepository::FLEET_VEHICLES_MUNICIPALITY => 126,
            SystemPermissionRepository::FLEET_VEHICLES_INSTITUTIONS => 132,
            SystemPermissionRepository::FLEET_VEHICLES_MINISTRIES => 133,
        ], SystemPermissionRepository::FLEET_FOLLOW_TO_PERMISSIONS);
    }

    public function test_fleet_vehicle_scope_fails_closed_when_oracle_is_unavailable(): void
    {
        $readiness = Mockery::mock(OracleReadiness::class);
        $readiness->shouldReceive('available')->once()->andReturnFalse();

        self::assertSame([], (new SystemPermissionRepository($readiness))->fleetFollowToOids(140));
    }
}
