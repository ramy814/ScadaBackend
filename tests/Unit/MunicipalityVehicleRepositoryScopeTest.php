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
        self::assertStringContainsString('"vs"."ACCESS_TYPE" in (?, ?)', $query->toSql());
        self::assertSame([1, 3, 140], $query->getBindings());
    }

    public function test_supervisor_vehicle_scope_uses_vehicle_stage_access_type(): void
    {
        $connection = new SQLiteConnection(new PDO('sqlite::memory:'));
        DB::shouldReceive('connection')->once()->with('oracle')->andReturn($connection);

        $repository = new MunicipalityVehicleRepository(Mockery::mock(OracleReadiness::class));
        $method = new ReflectionMethod($repository, 'reviewerVehiclesQuery');
        $query = $method->invoke($repository, 140);

        self::assertStringContainsString('"vs"."ACCESS_TYPE" in (?, ?)', $query->toSql());
        self::assertStringContainsString('"vs"."REMOVED_USER" is null', $query->toSql());
        self::assertStringNotContainsString('FOLLOW_TO_OID', $query->toSql());
        self::assertSame([2, 3, 140], $query->getBindings());
    }

    public function test_entry_and_review_return_only_eligible_active_vehicles_in_the_users_station(): void
    {
        $connection = new SQLiteConnection(new PDO('sqlite::memory:'));
        foreach (['g_store', 'tracking', 'mog_users'] as $schema) {
            $connection->statement("attach database ':memory:' as {$schema}");
        }
        $connection->statement('create table g_store.vehicle (oid integer, plate_num text, vehicle_num text, vehicle_type_oid integer, odometer integer, beneficiary_oid integer)');
        $connection->statement('create table g_store.beneficiaries (oid integer, full_name text, mobile_num text, national_id text)');
        $connection->statement('create table g_store.vehicle_stages (vehicle_oid integer, stage_oid integer, access_type integer, removed_user integer)');
        $connection->statement('create table tracking.user_station (stage_oid integer, user_oid integer, access_type integer)');
        $connection->statement('create table mog_users.users_list (id integer, tracking_id integer)');
        $connection->table('mog_users.users_list')->insert(['id' => 140, 'tracking_id' => 7]);
        $connection->table('tracking.user_station')->insert(['stage_oid' => 10, 'user_oid' => 7, 'access_type' => 1]);
        foreach ([1, 2, 3, 4, 5, 6, 7] as $id) {
            $connection->table('g_store.vehicle')->insert(['oid' => $id, 'plate_num' => 'PLATE-'.$id]);
            $connection->table('g_store.vehicle_stages')->insert([
                'vehicle_oid' => $id,
                'stage_oid' => $id === 5 ? 99 : 10,
                'access_type' => match ($id) {
                    1 => 1, 2 => 2, 6 => null, 7 => 4, default => 3
                },
                'removed_user' => $id === 4 ? 42 : null,
            ]);
        }
        // Repeated active links must not duplicate vehicles.
        $connection->table('g_store.vehicle_stages')->insert(['vehicle_oid' => 3, 'stage_oid' => 10, 'access_type' => 3]);
        foreach ([141 => 1, 142 => 2, 143 => 3] as $userId => $accessType) {
            $connection->table('mog_users.users_list')->insert(['id' => $userId, 'tracking_id' => $userId]);
            $connection->table('tracking.user_station')->insert(['stage_oid' => $userId, 'user_oid' => $userId, 'access_type' => 4]);
            $connection->table('g_store.vehicle')->insert(['oid' => $userId, 'plate_num' => 'PLATE-'.$userId]);
            $connection->table('g_store.vehicle_stages')->insert([
                'vehicle_oid' => $userId,
                'stage_oid' => $userId,
                'access_type' => $accessType,
                'removed_user' => $userId === 143 ? 42 : null,
            ]);
        }
        DB::shouldReceive('connection')->with('oracle')->andReturn($connection);
        $readiness = Mockery::mock(OracleReadiness::class);
        $readiness->shouldReceive('available')->andReturnTrue();
        $repository = new MunicipalityVehicleRepository($readiness);

        self::assertSame([1, 3], $repository->forAdministrativeAssistant(140)->pluck('oracle_id')->all());
        self::assertSame(['2', '3'], $repository->vehicleIdsForReviewer(140)->all());
        self::assertNull($repository->findForAdministrativeAssistant(140, 2));
        self::assertFalse($repository->reviewerCanAccessVehicle(140, 1));
        self::assertFalse($repository->reviewerCanAccessVehicle(140, 4));
        self::assertFalse($repository->reviewerCanAccessVehicle(140, 5));
        self::assertFalse($repository->reviewerCanAccessVehicle(140, 6));
        self::assertFalse($repository->reviewerCanAccessVehicle(140, 7));
        self::assertTrue($repository->reviewerCanAccessVehicle(140, 3));
        self::assertSame([], $repository->vehicleIdsForReviewer(999)->all());
        self::assertSame([
            140 => ['can_enter' => true, 'can_review' => true],
            141 => ['can_enter' => true, 'can_review' => false],
            142 => ['can_enter' => false, 'can_review' => true],
        ], $repository->operationAccessForUsers([140, 141, 142, 143, 999])->all());

        $connection->table('g_store.vehicle_stages')->where('vehicle_oid', 141)->update(['access_type' => 2]);
        self::assertSame(['can_enter' => false, 'can_review' => true], $repository->operationAccessForUsers([141])->get(141));
    }

    public function test_station_access_fails_closed_when_oracle_is_unavailable(): void
    {
        $readiness = Mockery::mock(OracleReadiness::class);
        $readiness->shouldReceive('available')->andReturnFalse();
        $repository = new MunicipalityVehicleRepository($readiness);

        self::assertTrue($repository->forAdministrativeAssistant(140)->isEmpty());
        self::assertTrue($repository->vehicleIdsForReviewer(140)->isEmpty());
        self::assertFalse($repository->reviewerCanAccessVehicle(140, 3));
        self::assertTrue($repository->operationAccessForUsers([140])->isEmpty());
    }
}
