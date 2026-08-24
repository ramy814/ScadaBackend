<?php

namespace App\Repositories;

use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MunicipalityVehicleRepository
{
    public function __construct(private readonly OracleReadiness $readiness) {}

    public function search(?string $term = null, int $limit = 25): Collection
    {
        if (! $this->readiness->available()) {
            return collect();
        }

        $table = $this->vehiclesTable();
        $columns = $this->vehicleColumns();

        try {
            return DB::connection('oracle')
                ->table($table)
                ->selectRaw(sprintf(
                    '%s as oracle_id, %s as plate_number, %s as name, %s as vehicle_type',
                    $columns['id'],
                    $columns['plate'],
                    $columns['name'],
                    $columns['type'],
                ))
                ->whereNotNull($columns['plate'])
                ->when($term, fn ($query) => $query->where($columns['plate'], 'like', "%{$term}%"))
                ->limit($limit)
                ->get()
                ->map(fn ($row): array => (array) $row);
        } catch (QueryException $e) {
            $this->logOracleReadFailure('search', $e);

            return collect();
        }
    }

    public function findByPlate(string $plate): ?array
    {
        if (! $this->readiness->available()) {
            return null;
        }

        $table = $this->vehiclesTable();
        $columns = $this->vehicleColumns();

        try {
            $row = DB::connection('oracle')
                ->table($table)
                ->selectRaw(sprintf(
                    '%s as oracle_id, %s as plate_number, %s as name, %s as vehicle_type',
                    $columns['id'],
                    $columns['plate'],
                    $columns['name'],
                    $columns['type'],
                ))
                ->where($columns['plate'], $plate)
                ->first();
        } catch (QueryException $e) {
            $this->logOracleReadFailure('find_by_plate', $e);

            return null;
        }

        return $row ? (array) $row : null;
    }

    public function findAssignedToDriver(
        int|string $oracleDriverId,
        ?string $driverMobile,
        string $plate,
    ): ?array {
        $vehicles = $this->findByDriverOracleId($oracleDriverId);

        if ($driverMobile) {
            $vehicles = $vehicles->merge($this->findByDriverMobile($driverMobile));
        }

        return $vehicles
            ->first(fn (array $vehicle): bool => (string) ($vehicle['plate_number'] ?? '') === $plate);
    }

    public function count(?string $term = null): int
    {
        if (! $this->readiness->available()) {
            return 0;
        }

        $table = $this->vehiclesTable();
        $columns = $this->vehicleColumns();

        try {
            return (int) DB::connection('oracle')
                ->table($table)
                ->whereNotNull($columns['plate'])
                ->when(filled($columns['active'] ?? null), fn ($query) => $query->where($columns['active'], 1))
                ->when($term, fn ($query) => $query->where($columns['plate'], 'like', "%{$term}%"))
                ->count();
        } catch (QueryException $e) {
            $this->logOracleReadFailure('count_active', $e);

            return 0;
        }
    }

    public function countActiveAssignedToDrivers(array $oracleDriverIds): int
    {
        $ids = collect($oracleDriverIds)
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): string => (string) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty() || ! $this->readiness->available()) {
            return 0;
        }

        $table = $this->vehiclesTable();
        $columns = $this->vehicleColumns();

        try {
            return (int) DB::connection('oracle')
                ->table($table)
                ->whereIn($columns['beneficiary_id'], $ids->all())
                ->where($columns['active'], 1)
                ->whereNotNull($columns['plate'])
                ->distinct()
                ->count($columns['id']);
        } catch (QueryException $e) {
            $this->logOracleReadFailure('count_active_tracking_app_vehicles', $e);

            return 0;
        }
    }

    public function findByDriverMobile(string $mobileNum): Collection
    {
        if (! $this->readiness->available()) {
            return collect();
        }
        $table = $this->vehiclesTable();
        $vehicleColumns = $this->vehicleColumns();
        $driversTable = $this->driversTable();
        $driverColumns = $this->driverColumns();
        $vehicleIdColumn = $vehicleColumns['id'] ?? 'OID';
        $vehiclePlateColumn = $vehicleColumns['plate'] ?? 'PLATE_NUM';
        $vehicleNameColumn = $vehicleColumns['name'] ?? 'VEHICLE_NUM';
        $vehicleTypeColumn = $vehicleColumns['type'] ?? null;
        $vehicleBeneficiaryColumn = $vehicleColumns['beneficiary_id'] ?? 'BENEFICIARY_OID';
        $driverPhoneColumn = $driverColumns['phone'] ?? 'MOBILE_NUM';
        $driverIdColumn = $driverColumns['id'] ?? 'OID';

        $digits = preg_replace('/\D+/', '', $mobileNum) ?? '';
        $last9 = strlen($digits) >= 9 ? substr($digits, -9) : $digits;

        // توليد الاحتمالات الشائعة لتسريع البحث باستخدام (IN) المدعوم بالفهارس (Indexes)
        $variants = collect([
            $mobileNum,
            $digits,
            ltrim($digits, '0'),
            str_starts_with($digits, '970') ? substr($digits, 3) : null,
            str_starts_with($digits, '972') ? substr($digits, 3) : null,
            str_starts_with($digits, '0') ? '970'.substr($digits, 1) : null,
            str_starts_with($digits, '0') ? '972'.substr($digits, 1) : null,
            $last9,
            '0'.$last9,
            '970'.$last9,
            '972'.$last9,
            '+970'.$last9,
            '+972'.$last9,
        ])
            ->filter()
            ->unique()
            ->values();

        try {
            return DB::connection('oracle')
                ->table($table.' as t')
                // استخدام INNER JOIN بدلاً من IN (subquery) يعطي خطة تنفيذ (Execution Plan) أفضل بكثير
                ->join($driversTable.' as d', 't.'.$vehicleBeneficiaryColumn, '=', 'd.'.$driverIdColumn)
                ->whereNotNull('t.'.$vehiclePlateColumn)
                ->where(function ($query) use ($driverPhoneColumn, $variants, $last9): void {
                    // البحث الدقيق الذي يستخدم الفهارس بشكل مباشر
                    $query->whereIn('d.'.$driverPhoneColumn, $variants->all());

                    if ($last9) {
                        // استبدال REGEXP_REPLACE و SUBSTR المكلفة جداً بـ LIKE الذي يعد أسرع بكثير
                        $query->orWhere('d.'.$driverPhoneColumn, 'LIKE', '%'.$last9.'%');
                    }
                })
                ->selectRaw(sprintf(
                    't.%s as oracle_id, t.%s as plate_number, t.%s as name%s',
                    $vehicleIdColumn,
                    $vehiclePlateColumn,
                    $vehicleNameColumn,
                    $vehicleTypeColumn ? ', t.'.$vehicleTypeColumn.' as vehicle_type' : ''
                ))
                ->get()
                ->map(fn ($row): array => [
                    'oracle_id' => $row->oracle_id ?? null,
                    'plate_number' => $row->plate_number,
                    'name' => $row->name ?? null,
                    'vehicle_type' => $row->vehicle_type ?? null,
                ]);
        } catch (QueryException $e) {
            $this->logOracleReadFailure('find_by_driver_mobile', $e);

            return collect();
        }
    }

    public function findByDriverOracleId(int|string $oracleDriverId): Collection
    {
        if (! $this->readiness->available()) {
            return collect();
        }
        $table = $this->vehiclesTable();
        $vehicleColumns = $this->vehicleColumns();
        $vehicleIdColumn = $vehicleColumns['id'] ?? 'OID';
        $vehiclePlateColumn = $vehicleColumns['plate'] ?? 'PLATE_NUM';
        $vehicleNameColumn = $vehicleColumns['name'] ?? 'VEHICLE_NUM';
        $vehicleTypeColumn = $vehicleColumns['type'] ?? null;
        $vehicleBeneficiaryColumn = $vehicleColumns['beneficiary_id'] ?? 'BENEFICIARY_OID';

        try {
            return DB::connection('oracle')
                ->table($table.' as t')
                ->whereNotNull('t.'.$vehiclePlateColumn)
                ->where('t.'.$vehicleBeneficiaryColumn, $oracleDriverId)
                ->selectRaw(sprintf(
                    't.%s as oracle_id, t.%s as plate_number, t.%s as name%s',
                    $vehicleIdColumn,
                    $vehiclePlateColumn,
                    $vehicleNameColumn,
                    $vehicleTypeColumn ? ', t.'.$vehicleTypeColumn.' as vehicle_type' : ''
                ))
                ->get()
                ->map(fn ($row): array => [
                    'oracle_id' => $row->oracle_id ?? null,
                    'plate_number' => $row->plate_number,
                    'name' => $row->name ?? null,
                    'vehicle_type' => $row->vehicle_type ?? null,
                ]);
        } catch (QueryException $e) {
            $this->logOracleReadFailure('find_by_driver_oracle_id', $e);

            return collect();
        }
    }

    public function findManyByDriverOracleIds(array $oracleDriverIds): Collection
    {
        $ids = collect($oracleDriverIds)
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): string => (string) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty() || ! $this->readiness->available()) {
            return collect();
        }

        $table = $this->vehiclesTable();
        $columns = $this->vehicleColumns();

        try {
            return DB::connection('oracle')
                ->table($table.' as t')
                ->whereIn('t.'.$columns['beneficiary_id'], $ids->all())
                ->whereNotNull('t.'.$columns['plate'])
                ->selectRaw(sprintf(
                    't.%s as oracle_driver_id, t.%s as oracle_id, t.%s as plate_number, t.%s as name%s',
                    $columns['beneficiary_id'],
                    $columns['id'],
                    $columns['plate'],
                    $columns['name'],
                    filled($columns['type'] ?? null)
                        ? ', t.'.$columns['type'].' as vehicle_type'
                        : '',
                ))
                ->get()
                ->map(fn ($row): array => (array) $row)
                ->groupBy(fn (array $vehicle): string => (string) $vehicle['oracle_driver_id']);
        } catch (QueryException $e) {
            $this->logOracleReadFailure('find_many_by_driver_oracle_ids', $e);

            return collect();
        }
    }

    private function logOracleReadFailure(string $operation, QueryException $e): void
    {
        Log::warning('Municipality vehicle Oracle read failed.', [
            'operation' => $operation,
            'vehicles_table' => $this->vehiclesTable(),
            'drivers_table' => $this->driversTable(),
            'error_code' => $e->getCode(),
            'error' => $e->getMessage(),
        ]);
    }

    private function vehiclesTable(): string
    {
        return 'g_store.VEHICLE';
    }

    private function driversTable(): string
    {
        return 'g_store.BENEFICIARIES';
    }

    private function vehicleColumns(): array
    {
        return [
            'id' => 'OID',
            'plate' => 'PLATE_NUM',
            'name' => 'VEHICLE_NUM',
            'type' => 'VEHICLE_TYPE_OID',
            'beneficiary_id' => 'BENEFICIARY_OID',
            'active' => 'IS_ACTIVE',
        ];
    }

    private function driverColumns(): array
    {
        return [
            'id' => 'OID',
            'phone' => 'MOBILE_NUM',
            'name' => 'FULL_NAME',
            'employee_number' => 'NATIONAL_ID',
            'active' => 'IS_ACTIVE',
        ];
    }
}
