<?php

namespace App\Services;

use App\Models\Beneficiary;
use App\Models\Trip;
use App\Models\TripPoint;
use App\Repositories\MunicipalityVehicleRepository;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TripSyncService
{
    private const POINT_UPSERT_CHUNK_SIZE = 100;

    public function __construct(
        private readonly MunicipalityVehicleRepository $vehicles,
        private readonly VirtualFuelService $fuel,
    ) {}

    public function storeTrip(Beneficiary $driver, array $data): Trip
    {
        return DB::transaction(function () use ($driver, $data): Trip {
            $existing = Trip::query()->where('local_id', $data['local_id'])->first();
            if ($existing) {
                abort_unless(
                    (string) $existing->oracle_driver_id === (string) $driver->getAuthIdentifier(),
                    409,
                    'معرف الرحلة مستخدم من سائق آخر.',
                );
                if ($existing->status === 'confirmed') {
                    return $existing;
                }
            }

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
                'quality_version' => $data['quality_version'] ?? null,
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
                $identity = filled($stop['local_id'] ?? null)
                    ? ['local_id' => $stop['local_id']]
                    : ['started_at' => $stop['started_at']];
                $trip->stops()->updateOrCreate(
                    $identity,
                    Arr::only($stop, [
                        'started_at',
                        'ended_at',
                        'latitude',
                        'longitude',
                        'source',
                        'reason',
                        'notes',
                    ]),
                );
            }

            foreach ($data['tracking_events'] ?? [] as $event) {
                $trip->trackingEvents()->updateOrCreate(
                    ['local_id' => $event['local_id']],
                    Arr::only($event, [
                        'event_type',
                        'detected_at',
                        'latitude',
                        'longitude',
                        'details_json',
                    ]),
                );
            }

            if (! empty($data['stops'])) {
                $automaticWaitSeconds = $trip->stops()
                    ->where('source', 'automatic')
                    ->get(['started_at', 'ended_at'])
                    ->sum(function ($stop): int {
                        if (! $stop->started_at || ! $stop->ended_at) {
                            return 0;
                        }

                        return max(0, (int) $stop->started_at->diffInSeconds($stop->ended_at, false));
                    });
                $trip->update([
                    'wait_minutes' => array_key_exists('manual_wait_minutes', $data)
                        ? (int) $data['manual_wait_minutes'] + intdiv($automaticWaitSeconds, 60)
                        : max(
                            (int) ($data['wait_minutes'] ?? 0),
                            intdiv($automaticWaitSeconds, 60),
                        ),
                ]);
            }

            return $trip;
        });
    }

    private function tripHasColumn(string $column): bool
    {
        static $cache = [];

        $trip = new Trip;
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

            if (! in_array(DB::connection($connection)->getDriverName(), ['oci', 'oci8', 'oracle'], true)) {
                return $cache[$cacheKey] = false;
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

        if (! in_array(DB::connection($connection)->getDriverName(), ['oci', 'oci8', 'oracle'], true)) {
            return false;
        }

        try {
            $row = DB::connection($connection)->selectOne(
                'select count(*) as table_count from user_tables where table_name = ?',
                [strtoupper($table)],
            );
        } catch (\Throwable) {
            return false;
        }

        return ((int) ($row->table_count ?? 0)) > 0;
    }

    public function uploadPoints(Trip $trip, array $points): int
    {
        return DB::transaction(function () use ($trip, $points): int {
            $lockedTrip = Trip::query()
                ->whereKey($trip->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedTrip->status === 'confirmed') {
                return $lockedTrip->points()->count();
            }

            $rowsByShape = collect($points)
                ->map(function (array $point) use ($lockedTrip): array {
                    $model = new TripPoint;
                    $model->setConnection($lockedTrip->getConnectionName());
                    $model->forceFill([
                        'trip_id' => $lockedTrip->getKey(),
                        'sequence_number' => (int) $point['sequence_number'],
                        ...Arr::only($point, [
                            'latitude',
                            'longitude',
                            'accuracy',
                            'speed',
                            'heading',
                            'battery_level',
                            'provider',
                            'captured_at',
                            'recorded_at',
                            'client_is_valid',
                            'quality_reason',
                        ]),
                    ]);

                    $attributes = $model->getAttributes();
                    ksort($attributes);

                    return $attributes;
                })
                ->groupBy(fn (array $row): string => implode('|', array_keys($row)));

            foreach ($rowsByShape as $rows) {
                $firstRow = $rows->first();
                $updateColumns = array_values(array_diff(
                    array_keys($firstRow),
                    ['trip_id', 'sequence_number'],
                ));

                foreach (array_chunk($rows->all(), self::POINT_UPSERT_CHUNK_SIZE) as $chunk) {
                    $lockedTrip->points()->upsert(
                        $chunk,
                        ['trip_id', 'sequence_number'],
                        $updateColumns,
                    );
                }
            }

            $count = $lockedTrip->points()->count();
            $lockedTrip->update(['points_received' => $count]);

            return $count;
        });
    }

    public function confirm(Trip $trip, int $expected, string $checksum): Trip
    {
        return DB::transaction(function () use ($trip, $expected, $checksum): Trip {
            $lockedTrip = Trip::query()
                ->whereKey($trip->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedTrip->status === 'confirmed') {
                return $lockedTrip;
            }

            $pointsReceived = $lockedTrip->points()->count();
            $serverPoints = $lockedTrip->points()
                ->orderBy('sequence_number')
                ->cursor([
                    'sequence_number',
                    'latitude',
                    'longitude',
                    'accuracy',
                    'speed',
                    'captured_at',
                    'recorded_at',
                    'client_is_valid',
                    'quality_reason',
                ]);

            $serverChecksum = $this->checksumForPoints($serverPoints);
            $isComplete = $this->isCompleteUpload(
                $expected,
                $pointsReceived,
                $checksum,
                $serverChecksum,
            );
            $metrics = $isComplete ? $this->fuel->metrics($lockedTrip) : null;
            $usesFilteredDistance = $metrics !== null && (
                (int) ($lockedTrip->quality_version ?? 0) >= 2
                || $this->fuel->hasSufficientQualityData($metrics)
            );

            $lockedTrip->update([
                'points_expected' => $expected,
                'points_received' => $pointsReceived,
                'client_checksum' => $checksum,
                'server_checksum' => $serverChecksum,
                'status' => $isComplete ? 'confirmed' : 'uploaded',
                'confirmed_at' => $isComplete ? now() : null,
                'distance_km' => $isComplete
                    ? ($usesFilteredDistance
                        ? $metrics['distance_km']
                        : $metrics['raw_distance_km'])
                    : null,
                'duration_minutes' => $isComplete
                    ? $metrics['duration_minutes']
                    : null,
                'virtual_fuel_liters' => $isComplete && $usesFilteredDistance
                    ? $this->fuel->estimateFromMetrics($lockedTrip, $metrics)
                    : ($isComplete ? $this->fuel->estimateLegacyLiters($lockedTrip, $metrics) : null),
            ]);

            Log::info('driver.trip_sync.confirm', [
                'trip_id' => $lockedTrip->id,
                'expected' => $expected,
                'received' => $pointsReceived,
                'is_complete' => $isComplete,
                'checksum_match' => $checksum === $serverChecksum,
            ]);

            return $lockedTrip->fresh();
        });
    }

    public function assertOwnedBy(Trip $trip, Beneficiary $driver): void
    {
        abort_unless(
            (string) $trip->oracle_driver_id === (string) $driver->getAuthIdentifier(),
            403,
            'غير مسموح.',
        );
    }

    public function isCompleteUpload(
        int $expected,
        int $received,
        string $clientChecksum,
        string $serverChecksum,
    ): bool {
        return $expected === $received
            && hash_equals($serverChecksum, strtolower($clientChecksum));
    }

    public function checksumForPoints(iterable $points): string
    {
        $hash = hash_init('sha256');
        hash_update($hash, '[');
        $first = true;
        foreach ($points as $point) {
            $value = static fn (string $key): mixed => is_array($point)
                ? ($point[$key] ?? null)
                : ($point->{$key} ?? null);
            $capturedAt = $value('captured_at');
            $date = $capturedAt instanceof DateTimeInterface
                ? DateTimeImmutable::createFromInterface($capturedAt)
                : new DateTimeImmutable((string) $capturedAt);
            $date = $date->setTimezone(new DateTimeZone('UTC'));
            $clientIsValid = $value('client_is_valid');

            $data = [
                'sequence_number' => (int) $value('sequence_number'),
                'latitude' => number_format((float) $value('latitude'), 7, '.', ''),
                'longitude' => number_format((float) $value('longitude'), 7, '.', ''),
                'accuracy' => $value('accuracy') === null
                    ? null
                    : number_format((float) $value('accuracy'), 2, '.', ''),
                'speed' => $value('speed') === null
                    ? null
                    : number_format((float) $value('speed'), 2, '.', ''),
                'captured_at' => $date->format('Y-m-d\TH:i:s\Z'),
                'recorded_at' => $this->canonicalOptionalTimestamp(
                    $value('recorded_at'),
                ),
                'client_is_valid' => $clientIsValid === null
                    ? true
                    : (bool) $clientIsValid,
                'quality_reason' => $value('quality_reason'),
            ];
            if (! $first) {
                hash_update($hash, ',');
            }
            $first = false;
            hash_update($hash, json_encode(
                $data,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ));
        }
        hash_update($hash, ']');

        return hash_final($hash);
    }

    private function canonicalOptionalTimestamp(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $date = $value instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($value)
            : new DateTimeImmutable((string) $value);

        return $date
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s\Z');
    }
}
