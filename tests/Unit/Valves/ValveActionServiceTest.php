<?php

declare(strict_types=1);

namespace Tests\Unit\Valves;

use App\Modules\Valves\Models\Valve;
use App\Modules\Valves\Models\ValveInspector;
use App\Modules\Valves\Models\ValveLog;
use App\Modules\Valves\Models\ValveMaintenanceReport;
use App\Modules\Valves\Repositories\ValveRepository;
use App\Modules\Valves\Services\ValveActionService;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Ported from the legacy tests/Unit/Valves/ValveActionServiceTest.php. The migration-index
 * test is not ported: the platform owns no migrations (the index is created by the legacy
 * migration 2026_08_26_000026 on Oracle).
 */
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
