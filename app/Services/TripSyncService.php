<?php

namespace App\Services;

use App\Models\Beneficiary;
use App\Models\Trip;
use App\Repositories\MunicipalityVehicleRepository;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TripSyncService
{
    public function __construct(private readonly MunicipalityVehicleRepository $vehicles) {}

    public function storeTrip(Beneficiary $driver, array $data): Trip
    {
        return DB::transaction(function () use ($driver, $data): Trip {
            $oracleVehicle = $this->vehicles->findAssignedToDriver(
                (string) $driver->getAuthIdentifier(),
                $driver->phoneNumber(),
                $data['vehicle_plate'],
            );

            abort_unless($oracleVehicle, 422, 'المركبة غير مرتبطة بهذا المنتفع في Oracle.');

            $oracleDriverId = (string) $driver->getAuthIdentifier();
            $tripDriverId = $this->resolveDriverProfileId($driver);
            $vehicleId = $oracleVehicle['oracle_id'] ?? $oracleVehicle['id'] ?? null;
            $hasDriverIdColumn = $this->tripHasColumn('driver_id');
            $hasVehicleIdColumn = $this->tripHasColumn('vehicle_id');

            $tripValues = [
                'oracle_driver_id' => $oracleDriverId,
                'driver_name' => $driver->displayName() ?? 'سائق',
                'driver_phone' => $driver->phoneNumber(),
                'driver_employee_number' => $driver->employeeNumber(),
                'oracle_vehicle_id' => $vehicleId,
                'vehicle_plate' => $oracleVehicle['plate_number'] ?? $data['vehicle_plate'],
                'vehicle_name' => $oracleVehicle['name'] ?? null,
                'vehicle_type' => $oracleVehicle['vehicle_type'] ?? null,
                'status' => 'uploaded',
                'started_at' => $data['started_at'],
                'ended_at' => $data['ended_at'] ?? null,
                'origin_type' => $data['origin']['type'],
                'origin_id' => $data['origin']['id'] ?? null,
                'origin_text' => $data['origin']['text'],
                'destination_type' => $data['destination']['type'],
                'destination_id' => $data['destination']['id'] ?? null,
                'destination_text' => $data['destination']['text'],
                'mission_type' => $data['mission_type'] ?? null,
                'notes' => $data['notes'] ?? null,
                'wait_minutes' => isset($data['wait_minutes']) ? (int) $data['wait_minutes'] : null,
                'start_odometer' => $data['start_odometer'] ?? null,
                'end_odometer' => $data['end_odometer'] ?? null,
                'points_expected' => $data['points_expected'] ?? 0,
            ];

            if ($hasDriverIdColumn) {
                $tripValues['driver_id'] = $tripDriverId;
            }

            if ($hasVehicleIdColumn) {
                $tripValues['vehicle_id'] = $vehicleId;
            }

            Log::info('driver.trip_sync.store.prepare', [
                'local_id' => $data['local_id'],
                'driver_id' => $tripDriverId,
                'oracle_driver_id' => $oracleDriverId,
                'vehicle_plate_requested' => $data['vehicle_plate'],
                'oracle_vehicle' => Arr::only($oracleVehicle, [
                    'id',
                    'oracle_id',
                    'plate_number',
                    'name',
                    'vehicle_type',
                ]),
                'legacy_columns' => [
                    'driver_id' => $hasDriverIdColumn,
                    'vehicle_id' => $hasVehicleIdColumn,
                ],
                'trip_value_keys' => array_keys($tripValues),
                'trip_value_snapshot' => Arr::only($tripValues, [
                    'driver_id',
                    'vehicle_id',
                    'oracle_driver_id',
                    'oracle_vehicle_id',
                    'vehicle_plate',
                    'vehicle_type',
                    'points_expected',
                ]),
            ]);

            try {
                $trip = Trip::query()->updateOrCreate(
                    ['local_id' => $data['local_id']],
                    $tripValues,
                );
            } catch (QueryException $exception) {
                $this->logTripForeignKeyDiagnostics('TRIPS_DRIVER_FK', $tripDriverId, [
                    'oracle_driver_id' => $oracleDriverId,
                    'driver_phone' => $driver->phoneNumber(),
                    'driver_employee_number' => $driver->employeeNumber(),
                    'driver_name' => $driver->displayName(),
                ]);
                $this->logTripForeignKeyDiagnostics('TRIPS_VEHICLE_FK', $vehicleId, [
                    'vehicle_plate' => $data['vehicle_plate'],
                    'oracle_vehicle' => Arr::only($oracleVehicle, [
                        'id',
                        'oracle_id',
                        'plate_number',
                        'name',
                        'vehicle_type',
                    ]),
                ]);

                Log::error('driver.trip_sync.store.failed', [
                    'local_id' => $data['local_id'],
                    'driver_id' => $tripDriverId,
                    'oracle_driver_id' => $oracleDriverId,
                    'vehicle_id' => $vehicleId,
                    'legacy_columns' => [
                        'driver_id' => $hasDriverIdColumn,
                        'vehicle_id' => $hasVehicleIdColumn,
                    ],
                    'trip_value_keys' => array_keys($tripValues),
                    'error_code' => $exception->getCode(),
                    'error_message' => $exception->getMessage(),
                ]);

                throw $exception;
            }

            foreach ($data['passengers'] ?? [] as $passenger) {
                $trip->passengers()->updateOrCreate(
                    [
                        'employee_id' => $passenger['employee_id'] ?? null,
                        'name' => $passenger['name'],
                    ],
                    ['source' => $passenger['source']],
                );
            }

            foreach ($data['stops'] ?? [] as $stop) {
                $trip->stops()->updateOrCreate(
                    ['started_at' => $stop['started_at']],
                    Arr::only($stop, ['ended_at', 'reason', 'notes']),
                );
            }

            return $trip;
        });
    }

    private function tripHasColumn(string $column): bool
    {
        static $cache = [];

        $trip = new Trip();
        $table = $trip->getTable();
        $connection = $trip->getConnectionName() ?? config('database.default');
        $cacheKey = $connection.'.'.$table.'.'.$column;

        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        try {
            if (Schema::connection($connection)->hasColumn($table, $column)) {
                return $cache[$cacheKey] = true;
            }

            $row = DB::connection($connection)->selectOne(
                'select count(*) as column_count from user_tab_columns where table_name = ? and column_name = ?',
                [strtoupper($table), strtoupper($column)],
            );

            return $cache[$cacheKey] = ((int) ($row->column_count ?? 0)) > 0;
        } catch (\Throwable $exception) {
            Log::warning('driver.trip_sync.schema_check.failed', [
                'table' => $table,
                'column' => $column,
                'connection' => $connection,
                'error' => $exception->getMessage(),
            ]);

            return $cache[$cacheKey] = false;
        }
    }

    private function logTripForeignKeyDiagnostics(
        string $constraintName,
        int|string|null $candidateValue,
        array $context = [],
    ): void {
        if ($candidateValue === null || $candidateValue === '') {
            Log::warning('driver.trip_sync.fk_diagnostics.skipped', [
                'constraint' => $constraintName,
                'reason' => 'empty_candidate_value',
                'context' => $context,
            ]);

            return;
        }

        $connection = config('database.default');

        try {
            $rows = DB::connection($connection)->select(
                <<<'SQL'
select
    child_cols.column_name as child_column,
    parent_cols.table_name as parent_table,
    parent_cols.column_name as parent_column
from user_constraints child
join user_cons_columns child_cols
    on child.constraint_name = child_cols.constraint_name
join user_cons_columns parent_cols
    on child.r_constraint_name = parent_cols.constraint_name
    and child_cols.position = parent_cols.position
where child.constraint_name = ?
SQL,
                [strtoupper($constraintName)],
            );

            $details = [];
            foreach ($rows as $row) {
                $parentTable = (string) $row->parent_table;
                $parentColumn = (string) $row->parent_column;
                $exists = DB::connection($connection)
                    ->table($parentTable)
                    ->where($parentColumn, $candidateValue)
                    ->exists();

                $columns = DB::connection($connection)->select(
                    <<<'SQL'
select column_name
from user_tab_columns
where table_name = ?
order by column_id
SQL,
                    [strtoupper($parentTable)],
                );

                $details[] = [
                    'child_column' => $row->child_column,
                    'parent_table' => $parentTable,
                    'parent_column' => $parentColumn,
                    'candidate_value' => $candidateValue,
                    'candidate_exists' => $exists,
                    'parent_columns' => collect($columns)
                        ->pluck('column_name')
                        ->take(40)
                        ->values()
                        ->all(),
                ];
            }

            Log::info('driver.trip_sync.fk_diagnostics', [
                'constraint' => $constraintName,
                'details' => $details,
                'context' => $context,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('driver.trip_sync.fk_diagnostics.failed', [
                'constraint' => $constraintName,
                'candidate_value' => $candidateValue,
                'context' => $context,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function resolveDriverProfileId(Beneficiary $driver): string
    {
        $oracleDriverId = (string) $driver->getAuthIdentifier();
        $phone = $driver->phoneNumber();
        $employeeNumber = $driver->employeeNumber();
        $connection = config('database.default');
        $table = 'DRIVER_PROFILES';

        if (! $this->tableExists($table)) {
            return $oracleDriverId;
        }

        $query = DB::connection($connection)->table($table);
        $profile = (clone $query)
            ->where('ORACLE_DRIVER_ID', $oracleDriverId)
            ->when($phone, fn ($q) => $q->orWhere('PHONE', $phone))
            ->when($employeeNumber, fn ($q) => $q->orWhere('EMPLOYEE_NUMBER', $employeeNumber))
            ->first();

        if ($profile) {
            $profileId = (string) ($profile->id ?? $profile->ID);
            Log::info('driver.trip_sync.driver_profile.resolved', [
                'profile_id' => $profileId,
                'oracle_driver_id' => $oracleDriverId,
                'phone' => $phone,
                'employee_number' => $employeeNumber,
                'source' => 'existing',
            ]);

            return $profileId;
        }

        $now = now();
        $values = [
            'ORACLE_DRIVER_ID' => $oracleDriverId,
            'PHONE' => $phone,
            'NAME' => $driver->displayName() ?? 'سائق',
            'EMPLOYEE_NUMBER' => $employeeNumber,
            'ACTIVE' => 1,
            'CREATED_AT' => $now,
            'UPDATED_AT' => $now,
        ];

        $profileId = DB::connection($connection)
            ->table($table)
            ->insertGetId($values, 'ID');

        Log::info('driver.trip_sync.driver_profile.resolved', [
            'profile_id' => (string) $profileId,
            'oracle_driver_id' => $oracleDriverId,
            'phone' => $phone,
            'employee_number' => $employeeNumber,
            'source' => 'created',
        ]);

        return (string) $profileId;
    }

    private function tableExists(string $table): bool
    {
        $connection = config('database.default');

        try {
            if (Schema::connection($connection)->hasTable($table)) {
                return true;
            }
        } catch (\Throwable) {
        }

        $row = DB::connection($connection)->selectOne(
            'select count(*) as table_count from user_tables where table_name = ?',
            [strtoupper($table)],
        );

        return ((int) ($row->table_count ?? 0)) > 0;
    }

    public function uploadPoints(Trip $trip, array $points): int
    {
        foreach ($points as $point) {
            $trip->points()->updateOrCreate(
                ['sequence_number' => $point['sequence_number']],
                Arr::only($point, [
                    'latitude',
                    'longitude',
                    'accuracy',
                    'speed',
                    'heading',
                    'battery_level',
                    'provider',
                    'captured_at',
                ]),
            );
        }

        $count = $trip->points()->count();
        $trip->update(['points_received' => $count]);

        return $count;
    }

    public function confirm(Trip $trip, int $expected, string $checksum): Trip
    {
        $serverPoints = $trip->points()
            ->orderBy('sequence_number')
            ->get(['sequence_number', 'latitude', 'longitude', 'captured_at']);

        $pointsReceived = $serverPoints->count();

        // Rebuild server checksum using plain arrays (same format as client)
        $serverChecksumData = $serverPoints->map(fn ($p) => [
            'sequence_number' => (int) $p->sequence_number,
            'latitude'        => (float) $p->latitude,
            'longitude'       => (float) $p->longitude,
            'captured_at'     => $p->captured_at?->toIso8601String(),
        ])->values()->all();

        $serverChecksum = hash('sha256', json_encode($serverChecksumData));

        $trip->update([
            'points_expected' => $expected,
            'points_received' => $pointsReceived,
            'client_checksum' => $checksum,
            'server_checksum' => $serverChecksum,
        ]);

        // Consider complete if received >= expected (>= handles edge cases)
        // OR if expected is 0 (trip with no GPS data still gets confirmed)
        $isComplete = $expected === 0 || $pointsReceived >= $expected;

        $trip->update([
            'status'               => $isComplete ? 'confirmed' : 'uploaded',
            'confirmed_at'         => $isComplete ? now() : null,
            'virtual_fuel_liters'  => app(VirtualFuelService::class)->estimateLiters($trip),
        ]);

        Log::info('driver.trip_sync.confirm', [
            'trip_id'         => $trip->id,
            'expected'        => $expected,
            'received'        => $pointsReceived,
            'is_complete'     => $isComplete,
            'checksum_match'  => $checksum === $serverChecksum,
        ]);

        return $trip->fresh();
    }
}
