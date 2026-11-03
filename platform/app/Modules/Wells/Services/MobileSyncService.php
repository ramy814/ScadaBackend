<?php

declare(strict_types=1);

namespace App\Modules\Wells\Services;

use App\Modules\Wells\Repositories\DieselInventoryRepository;
use App\Modules\Wells\Repositories\ReadingRepository;
use App\Modules\Wells\Repositories\WellNotificationRepository;
use App\Modules\Wells\Repositories\WellRepository;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MobileSyncService
{
    private const TRANSACTION_CHUNK_SIZE = 50;

    public function __construct(
        private readonly DieselFuelCalculator $fuelCalculator,
        private readonly ReadingRepository $readings,
        private readonly DieselInventoryRepository $inventories,
        private readonly WellNotificationRepository $notifications,
        private readonly WellRepository $wells,
    ) {}

    public function syncReadings(array $records, int $inspectorId): array
    {
        $syncedIds = [];
        $syncedRecords = [];

        foreach (array_chunk($records, self::TRANSACTION_CHUNK_SIZE) as $chunk) {
            DB::transaction(function () use ($chunk, $inspectorId, &$syncedIds, &$syncedRecords): void {
                $prepared = $this->prepare($chunk, 'reading', $inspectorId);
                $existing = $this->byIdempotencyKey(
                    $this->readings->findSyncedByLocalIdsIncludingDeleted(
                        $prepared->pluck('idempotency_key')->all(),
                        $inspectorId,
                    ),
                );

                foreach ($prepared as $item) {
                    $payload = $item['payload'];
                    $reading = $existing->get($item['idempotency_key']);

                    if ($reading === null) {
                        $generatorHours = $this->payloadValue($payload, [
                            'GENERATOR_HOURS_READING',
                            'generator_hours_reading',
                            'FLOW_RATE',
                            'flow_rate',
                        ]);

                        $reading = $this->readings->create([
                            'WELL_OID' => $this->payloadValue($payload, ['WELL_OID', 'well_oid']),
                            'INSPECTOR_OID' => $inspectorId,
                            'FLOW_RATE' => $generatorHours ?? 0,
                            'GENERATOR_HOURS_READING' => $generatorHours,
                            'PUMP_HOURS_READING' => $this->payloadValue($payload, [
                                'PUMP_HOURS_READING',
                                'pump_hours_reading',
                            ]),
                            'CHLORINE' => null,
                            'SALINITY' => null,
                            'QUALITY' => null,
                            'READING_DATE' => Carbon::parse($this->payloadValue($payload, [
                                'READING_DATE',
                                'reading_date',
                            ])),
                            'LATITUDE' => $this->payloadValue($payload, ['LATITUDE', 'latitude']),
                            'LONGITUDE' => $this->payloadValue($payload, ['LONGITUDE', 'longitude']),
                            'SYNCED_AT' => now(),
                            'CLIENT_LOCAL_ID' => $item['idempotency_key'],
                        ]);
                        $existing->put($item['idempotency_key'], $reading);
                    }

                    $this->acknowledge($item['client_local_id'], $reading, $syncedIds, $syncedRecords);
                }
            });
        }

        return [
            'synced_ids' => $syncedIds,
            'synced_records' => $syncedRecords,
        ];
    }

    public function syncDieselInventories(array $records, int $inspectorId): array
    {
        $syncedIds = [];

        foreach (array_chunk($records, self::TRANSACTION_CHUNK_SIZE) as $chunk) {
            DB::transaction(function () use ($chunk, $inspectorId, &$syncedIds): void {
                $prepared = $this->prepare($chunk, 'diesel', $inspectorId);
                $existing = $this->byIdempotencyKey(
                    $this->inventories->findSyncedByLocalIdsIncludingDeleted(
                        $prepared->pluck('idempotency_key')->all(),
                        $inspectorId,
                    ),
                );
                $missingWellIds = $prepared
                    ->reject(fn (array $item): bool => $existing->has($item['idempotency_key']))
                    ->map(fn (array $item): int => (int) $this->payloadValue(
                        $item['payload'],
                        ['WELL_OID', 'well_oid'],
                    ))
                    ->unique()
                    ->all();
                $wells = $missingWellIds === []
                    ? collect()
                    : $this->wells
                        ->findManyByIds($missingWellIds)
                        ->keyBy(fn (Model $well): int => (int) $well->getKey());

                foreach ($prepared as $item) {
                    $payload = $item['payload'];
                    $inventory = $existing->get($item['idempotency_key']);

                    if ($inventory === null) {
                        $wellId = (int) $this->payloadValue($payload, ['WELL_OID', 'well_oid']);
                        $dieselHeight = $this->payloadValue($payload, [
                            'DIESEL_HEIGHT_CM',
                            'diesel_height_cm',
                        ]);
                        $well = $wells->get($wellId);
                        $availableFuel = $well
                            ? $this->fuelCalculator->litersForWell($well, $dieselHeight)
                            : null;

                        $inventory = $this->inventories->create([
                            'WELL_OID' => $wellId,
                            'INSPECTOR_OID' => $inspectorId,
                            'GENERATOR_OID' => $this->payloadValue($payload, ['GENERATOR_OID', 'generator_oid']),
                            'INVENTORY_DATE' => Carbon::parse($this->payloadValue($payload, [
                                'INVENTORY_DATE',
                                'inventory_date',
                            ])),
                            'GENERATOR_HOURS_READING' => $this->payloadValue($payload, [
                                'GENERATOR_HOURS_READING',
                                'generator_hours_reading',
                            ]),
                            'DIESEL_HEIGHT_CM' => $dieselHeight,
                            'AVAILABLE_FUEL_LITERS' => $availableFuel ?? $this->payloadValue($payload, [
                                'AVAILABLE_FUEL_LITERS',
                                'available_fuel_liters',
                            ]),
                            'LATITUDE' => $this->payloadValue($payload, ['LATITUDE', 'latitude']),
                            'LONGITUDE' => $this->payloadValue($payload, ['LONGITUDE', 'longitude']),
                            'SYNCED_AT' => now(),
                            'CLIENT_LOCAL_ID' => $item['idempotency_key'],
                        ]);
                        $existing->put($item['idempotency_key'], $inventory);
                    }

                    if ($item['client_local_id'] !== null) {
                        $syncedIds[] = $item['client_local_id'];
                    }
                }
            });
        }

        return ['synced_ids' => $syncedIds];
    }

    public function syncNotifications(array $records, int $inspectorId): array
    {
        $syncedIds = [];
        $syncedRecords = [];

        foreach (array_chunk($records, self::TRANSACTION_CHUNK_SIZE) as $chunk) {
            DB::transaction(function () use ($chunk, $inspectorId, &$syncedIds, &$syncedRecords): void {
                $prepared = $this->prepare($chunk, 'notification', $inspectorId);
                $existing = $this->byIdempotencyKey(
                    $this->notifications->findSyncedByLocalIds(
                        $prepared->pluck('idempotency_key')->all(),
                        $inspectorId,
                    ),
                );

                foreach ($prepared as $item) {
                    $payload = $item['payload'];
                    $notification = $existing->get($item['idempotency_key']);

                    if ($notification === null) {
                        $notification = $this->notifications->create([
                            'WELL_OID' => $this->payloadValue($payload, ['WELL_OID', 'well_oid']),
                            'NOTIFICATION_TYPE_OID' => $this->payloadValue($payload, [
                                'NOTIFICATION_TYPE_OID',
                                'notification_type_oid',
                            ]),
                            'INSPECTOR_OID' => $inspectorId,
                            'MESSAGE' => $this->payloadValue($payload, ['MESSAGE', 'message']),
                            'IS_READ' => false,
                            'CLIENT_LOCAL_ID' => $item['idempotency_key'],
                        ]);
                        $existing->put($item['idempotency_key'], $notification);
                    }

                    $this->acknowledge(
                        $item['client_local_id'],
                        $notification,
                        $syncedIds,
                        $syncedRecords,
                    );
                }
            });
        }

        return [
            'synced_ids' => $syncedIds,
            'synced_records' => $syncedRecords,
        ];
    }

    private function prepare(array $records, string $type, int $inspectorId): Collection
    {
        return collect($records)->map(function (array $payload) use ($type, $inspectorId): array {
            $clientLocalId = $this->payloadValue($payload, ['LOCAL_ID', 'local_id']);

            return [
                'payload' => $payload,
                'client_local_id' => $clientLocalId,
                'idempotency_key' => $clientLocalId
                    ?? $this->legacyIdempotencyKey($type, $inspectorId, $payload),
            ];
        });
    }

    private function legacyIdempotencyKey(string $type, int $inspectorId, array $payload): string
    {
        $payload = array_change_key_case($payload, CASE_UPPER);
        unset($payload['LOCAL_ID'], $payload['INSPECTOR_OID']);
        ksort($payload);

        return hash('sha256', json_encode(
            [$type, $inspectorId, $payload],
            JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ));
    }

    private function byIdempotencyKey(Collection $records): Collection
    {
        return $records->keyBy(fn (Model $record): string => (string) (
            $record->getAttribute('client_local_id')
            ?? $record->getAttribute('CLIENT_LOCAL_ID')
        ));
    }

    private function acknowledge(
        ?string $clientLocalId,
        Model $record,
        array &$syncedIds,
        array &$syncedRecords,
    ): void {
        if ($clientLocalId === null) {
            return;
        }

        $syncedIds[] = $clientLocalId;
        $syncedRecords[] = [
            'local_id' => $clientLocalId,
            'oid' => $record->getKey(),
        ];
    }

    private function payloadValue(array $payload, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload)) {
                return $payload[$key];
            }
        }

        return null;
    }
}
