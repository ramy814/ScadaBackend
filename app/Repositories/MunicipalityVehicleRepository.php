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

        $digits = preg_replace('/\D+/', '', $mobileNum) ?? '';
        $variants = collect([
            $mobileNum,
            $digits,
            ltrim($digits, '0'),
            str_starts_with($digits, '970') ? substr($digits, 3) : null,
            str_starts_with($digits, '0') ? '970'.substr($digits, 1) : null,
        ])
            ->filter()
            ->unique()
            ->values();

        $last9 = strlen($digits) >= 9 ? substr($digits, -9) : null;

        try {
            return DB::connection('oracle')
                ->table($table.' as t')
                ->whereNotNull('t.'.$vehiclePlateColumn)
                ->whereIn('t.'.$vehicleBeneficiaryColumn, function ($query) use (
                    $driversTable,
                    $driverColumns,
                    $driverPhoneColumn,
                    $variants,
                    $last9,
                ): void {
                    $query
                        ->select($driverColumns['id'] ?? 'OID')
                        ->from($driversTable)
                        ->where(function ($nested) use ($variants, $last9, $driverPhoneColumn): void {
                            $nested->whereIn($driverPhoneColumn, $variants->all());
                            if ($last9) {
                                $nested->orWhereRaw(
                                    "SUBSTR(REGEXP_REPLACE($driverPhoneColumn, '[^0-9]', ''), -9) = ?",
                                    [$last9]
                                );
                            }
                        });
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
