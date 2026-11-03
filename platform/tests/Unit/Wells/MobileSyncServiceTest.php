<?php

declare(strict_types=1);

namespace Tests\Unit\Wells;

use App\Modules\Wells\Models\DieselInventory;
use App\Modules\Wells\Models\WellNotification;
use App\Modules\Wells\Models\WellReading;
use App\Modules\Wells\Repositories\DieselInventoryRepository;
use App\Modules\Wells\Repositories\ReadingRepository;
use App\Modules\Wells\Repositories\WellNotificationRepository;
use App\Modules\Wells\Repositories\WellRepository;
use App\Modules\Wells\Services\DieselFuelCalculator;
use App\Modules\Wells\Services\MobileSyncService;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use Tests\TestCase;

class MobileSyncServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_readings_are_processed_in_transactions_of_at_most_50_records(): void
    {
        $records = [];
        $existing = [];

        for ($index = 1; $index <= 51; $index++) {
            $localId = sprintf('reading-%03d', $index);
            $records[] = [
                'LOCAL_ID' => $localId,
                'WELL_OID' => 1,
                'READING_DATE' => '2026-08-26 10:00:00',
                'GENERATOR_HOURS_READING' => 120.5,
            ];
            $model = new WellReading;
            $model->forceFill(['oid' => $index, 'CLIENT_LOCAL_ID' => $localId]);
            $existing[] = $model;
        }

        $readings = Mockery::mock(ReadingRepository::class);
        $readings->shouldReceive('findSyncedByLocalIdsIncludingDeleted')
            ->twice()
            ->withArgs(fn (array $ids, int $inspectorId): bool => count($ids) <= 50
                && $inspectorId === 9)
            ->andReturn(
                new Collection(array_slice($existing, 0, 50)),
                new Collection(array_slice($existing, 50)),
            );
        $readings->shouldNotReceive('create');

        $result = $this->service($readings)->syncReadings($records, 9);

        self::assertCount(51, $result['synced_ids']);
        self::assertCount(51, $result['synced_records']);
        self::assertSame(1, $result['synced_records'][0]['oid']);
        self::assertSame(51, $result['synced_records'][50]['oid']);
    }

    public function test_retry_returns_the_existing_reading_without_creating_a_duplicate(): void
    {
        $existing = new WellReading;
        $existing->forceFill(['oid' => 77, 'CLIENT_LOCAL_ID' => 'reading-retry']);
        $readings = Mockery::mock(ReadingRepository::class);
        $readings->shouldReceive('findSyncedByLocalIdsIncludingDeleted')
            ->once()
            ->with(['reading-retry'], 9)
            ->andReturn(new Collection([$existing]));
        $readings->shouldNotReceive('create');

        $result = $this->service($readings)->syncReadings([[
            'LOCAL_ID' => 'reading-retry',
            'WELL_OID' => 1,
            'READING_DATE' => '2026-08-26 10:00:00',
            'GENERATOR_HOURS_READING' => 120.5,
        ]], 9);

        self::assertSame(['reading-retry'], $result['synced_ids']);
        self::assertSame([
            ['local_id' => 'reading-retry', 'oid' => 77],
        ], $result['synced_records']);
    }

    public function test_retry_returns_existing_diesel_without_queries_or_duplicate_writes(): void
    {
        $existing = new DieselInventory;
        $existing->forceFill(['oid' => 81, 'CLIENT_LOCAL_ID' => 'diesel-retry']);
        $readings = Mockery::mock(ReadingRepository::class);
        $inventories = Mockery::mock(DieselInventoryRepository::class);
        $inventories->shouldReceive('findSyncedByLocalIdsIncludingDeleted')
            ->once()
            ->with(['diesel-retry'], 9)
            ->andReturn(new Collection([$existing]));
        $inventories->shouldNotReceive('create');
        $wells = Mockery::mock(WellRepository::class);
        $wells->shouldNotReceive('findManyByIds');

        $result = $this->service(
            $readings,
            inventories: $inventories,
            wells: $wells,
        )->syncDieselInventories([[
            'LOCAL_ID' => 'diesel-retry',
            'WELL_OID' => 1,
            'INVENTORY_DATE' => '2026-08-26 10:00:00',
            'GENERATOR_HOURS_READING' => 120.5,
            'DIESEL_HEIGHT_CM' => 40,
        ]], 9);

        self::assertSame(['diesel-retry'], $result['synced_ids']);
    }

    public function test_retry_returns_existing_notification_without_creating_a_duplicate(): void
    {
        $existing = new WellNotification;
        $existing->forceFill(['oid' => 82, 'CLIENT_LOCAL_ID' => 'notification-retry']);
        $readings = Mockery::mock(ReadingRepository::class);
        $notifications = Mockery::mock(WellNotificationRepository::class);
        $notifications->shouldReceive('findSyncedByLocalIds')
            ->once()
            ->with(['notification-retry'], 9)
            ->andReturn(new Collection([$existing]));
        $notifications->shouldNotReceive('create');

        $result = $this->service(
            $readings,
            notifications: $notifications,
        )->syncNotifications([[
            'LOCAL_ID' => 'notification-retry',
            'WELL_OID' => 1,
            'NOTIFICATION_TYPE_OID' => 2,
            'MESSAGE' => 'signal',
        ]], 9);

        self::assertSame(['notification-retry'], $result['synced_ids']);
        self::assertSame([
            ['local_id' => 'notification-retry', 'oid' => 82],
        ], $result['synced_records']);
    }

    private function service(
        ReadingRepository $readings,
        ?DieselInventoryRepository $inventories = null,
        ?WellNotificationRepository $notifications = null,
        ?WellRepository $wells = null,
    ): MobileSyncService {
        return new MobileSyncService(
            Mockery::mock(DieselFuelCalculator::class),
            $readings,
            $inventories ?? Mockery::mock(DieselInventoryRepository::class),
            $notifications ?? Mockery::mock(WellNotificationRepository::class),
            $wells ?? Mockery::mock(WellRepository::class),
        );
    }
}
