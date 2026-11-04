<?php

declare(strict_types=1);

namespace Tests\Unit\Valves;

use App\Models\Valves\Valve;
use App\Models\Valves\ValveInspector;
use App\Models\Valves\ValveLog;
use App\Models\Valves\ValveMaintenanceReport;
use App\Repositories\Valves\ValveRepository;
use App\Services\Valves\ValveActionService;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ValveActionServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_retry_reuses_the_existing_action_without_duplicate_side_effects(): void
    {
        $existing = new ValveLog;
        $existing->forceFill([
            'oid' => 90,
            'VALVE_OID' => 12,
            'USER_OID' => 7,
            'ACTION' => 'maintenance',
            'NOTE' => 'leak',
            'CLIENT_LOCAL_ID' => 'pending-123',
        ]);
        $repository = Mockery::mock(ValveRepository::class);
        $repository->shouldReceive('findActionByLocalId')
            ->once()
            ->with('pending-123', 7)
            ->andReturn($existing);
        $repository->shouldNotReceive('createAction');
        $repository->shouldNotReceive('createMaintenanceReport');

        $result = (new ValveActionService($repository))->record(
            $this->inspector(7),
            $this->valve(12),
            ['local_id' => 'pending-123', 'action' => 'maintenance', 'note' => 'leak'],
        );

        self::assertSame($existing, $result);
    }

    public function test_reusing_an_id_for_different_action_is_rejected(): void
    {
        $existing = new ValveLog;
        $existing->forceFill([
            'VALVE_OID' => 12,
            'USER_OID' => 7,
            'ACTION' => 'open',
            'CLIENT_LOCAL_ID' => 'pending-123',
        ]);
        $repository = Mockery::mock(ValveRepository::class);
        $repository->shouldReceive('findActionByLocalId')->once()->with('pending-123', 7)->andReturn($existing);
        $repository->shouldNotReceive('createAction');

        try {
            (new ValveActionService($repository))->record(
                $this->inspector(7),
                $this->valve(12),
                ['local_id' => 'pending-123', 'action' => 'close'],
            );
            self::fail('Reused idempotency key was accepted for a different action.');
        } catch (HttpException $exception) {
            self::assertSame(409, $exception->getStatusCode());
        }
    }

    public function test_maintenance_action_and_report_are_created_atomically(): void
    {
        $created = new ValveLog;
        $repository = Mockery::mock(ValveRepository::class);
        $repository->shouldReceive('findActionByLocalId')->once()->with('pending-456', 7)->andReturnNull();
        $repository->shouldReceive('createAction')
            ->once()
            ->withArgs(fn (array $data): bool => $data['CLIENT_LOCAL_ID'] === 'pending-456'
                && $data['ACTION'] === 'maintenance')
            ->andReturn($created);
        $repository->shouldReceive('createMaintenanceReport')
            ->once()
            ->withArgs(fn (array $data): bool => $data['VALVE_OID'] === 12
                && $data['REPORTED_BY'] === 7
                && $data['STATUS'] === 'pending')
            ->andReturn(new ValveMaintenanceReport);

        $result = (new ValveActionService($repository))->record(
            $this->inspector(7),
            $this->valve(12),
            ['local_id' => 'pending-456', 'action' => 'maintenance'],
        );

        self::assertSame($created, $result);
    }

    public function test_idempotency_index_is_scoped_per_inspector(): void
    {
        Schema::create('valve_logs', function (Blueprint $table): void {
            $table->id('OID');
            $table->unsignedBigInteger('USER_OID');
        });
        $migration = require database_path('migrations/2026_08_26_000026_add_idempotency_to_valve_logs.php');

        try {
            $migration->up();
            DB::table('valve_logs')->insert([
                ['USER_OID' => 7, 'CLIENT_LOCAL_ID' => 'pending-123'],
                ['USER_OID' => 8, 'CLIENT_LOCAL_ID' => 'pending-123'],
            ]);

            try {
                DB::table('valve_logs')->insert([
                    'USER_OID' => 7,
                    'CLIENT_LOCAL_ID' => 'pending-123',
                ]);
                self::fail('Duplicate action ID was accepted for the same inspector.');
            } catch (QueryException) {
                self::assertSame(2, DB::table('valve_logs')->count());
            }

            $migration->down();
        } finally {
            Schema::dropIfExists('valve_logs');
        }
    }

    private function valve(int $id): Valve
    {
        $valve = new Valve;
        $valve->forceFill(['W4_GENERAL_ID' => $id]);

        return $valve;
    }

    private function inspector(int $id): ValveInspector
    {
        $inspector = new ValveInspector;
        $inspector->forceFill(['oid' => $id]);

        return $inspector;
    }
}
