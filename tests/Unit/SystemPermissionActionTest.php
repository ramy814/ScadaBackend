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

    public function test_destructive_action_permissions_fail_closed_when_oracle_is_unavailable(): void
    {
        $readiness = Mockery::mock(OracleReadiness::class);
        $readiness->shouldReceive('available')->once()->andReturnFalse();

        $repository = new SystemPermissionRepository($readiness);

        self::assertFalse($repository->hasActionPermission(
            140,
            SystemPermissionRepository::DELETE_WELL_READING,
        ));
    }
}
