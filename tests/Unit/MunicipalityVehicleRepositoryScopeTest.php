<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\MunicipalityVehicleRepository;
use App\Repositories\OracleReadiness;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Facades\DB;
use Mockery;
use PDO;
use ReflectionMethod;
use Tests\TestCase;

class MunicipalityVehicleRepositoryScopeTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_assistant_vehicle_scope_excludes_removed_station_assignments(): void
    {
        $connection = new SQLiteConnection(new PDO('sqlite::memory:'));
        DB::shouldReceive('connection')->once()->with('oracle')->andReturn($connection);

        $repository = new MunicipalityVehicleRepository(Mockery::mock(OracleReadiness::class));
        $method = new ReflectionMethod($repository, 'administrativeAssistantVehiclesQuery');
        $query = $method->invoke($repository, 140);

        self::assertStringContainsString('"vs"."REMOVED_USER" is null', $query->toSql());
        self::assertSame([3, 140], $query->getBindings());
    }

    public function test_supervisor_vehicle_scope_uses_follow_to_oid(): void
    {
        $connection = new SQLiteConnection(new PDO('sqlite::memory:'));
        DB::shouldReceive('connection')->once()->with('oracle')->andReturn($connection);

        $repository = new MunicipalityVehicleRepository(Mockery::mock(OracleReadiness::class));
        $method = new ReflectionMethod($repository, 'followToVehiclesQuery');
        $query = $method->invoke($repository, [125, 132]);

        self::assertStringContainsString('"v"."FOLLOW_TO_OID" in (?, ?)', $query->toSql());
        self::assertSame([125, 132], $query->getBindings());
    }
}
