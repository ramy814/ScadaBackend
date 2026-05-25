<?php

namespace App\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MunicipalityVehicleRepository
{
    private const VEHICLES_TABLE = 'VEHICLE';

    private const DRIVERS_TABLE = 'BENEFICIARIES';

    private const VEHICLE_COLUMNS = [
        'id' => 'OID',
        'plate' => 'PLATE_NUM',
        'name' => 'VEHICLE_NUM',
        'type' => 'VEHICLE_TYPE_OID',
        'beneficiary_id' => 'BENEFICIARY_OID',
    ];

    private const DRIVER_COLUMNS = [
        'id' => 'OID',
        'phone' => 'MOBILE_NUM',
        'name' => 'FULL_NAME',
        'employee_number' => 'NATIONAL_ID',
    ];

    public function __construct(private readonly OracleReadiness $readiness) {}

    public function search(?string $term = null, int $limit = 25): Collection
    {
        if (! $this->readiness->available()) {
            return collect();
        }

        $table = self::VEHICLES_TABLE;
        $columns = self::VEHICLE_COLUMNS;

        return DB::connection('oracle')
            ->table($table)
            ->selectRaw(sprintf(
                '%s as oracle_id, %s as plate_number, %s as name, %s as vehicle_type',
                $columns['id'],
                $columns['plate'],
                $columns['name'],
                $columns['type'],
            ))
            ->when($term, fn ($query) => $query->where($columns['plate'], 'like', "%{$term}%"))
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => (array) $row);
    }

    public function findByPlate(string $plate): ?array
    {
        if (! $this->readiness->available()) {
            return null;
        }

        $table = self::VEHICLES_TABLE;
        $columns = self::VEHICLE_COLUMNS;

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
        return $this->search($term, 1000)->count();
    }

    public function findByDriverMobile(string $mobileNum): Collection
    {
        if (! $this->readiness->available()) {
            return collect();
        }
        $table = self::VEHICLES_TABLE;
        $vehicleColumns = self::VEHICLE_COLUMNS;
        $driversTable = self::DRIVERS_TABLE;
        $driverColumns = self::DRIVER_COLUMNS;
        $vehicleIdColumn = $vehicleColumns['id'] ?? 'OID';
        $vehiclePlateColumn = $vehicleColumns['plate'] ?? 'PLATE_NUM';
        $vehicleNameColumn = $vehicleColumns['name'] ?? 'VEHICLE_NUM';
        $vehicleTypeColumn = $vehicleColumns['type'] ?? null;
        $vehicleBeneficiaryColumn = $vehicleColumns['beneficiary_id'] ?? 'BENEFICIARY_OID';
        $driverIdColumn = $driverColumns['id'] ?? 'OID';
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

        return DB::connection('oracle')
            ->table($table.' as t')
            ->join($driversTable.' as b', 't.'.$vehicleBeneficiaryColumn, '=', 'b.'.$driverIdColumn)
            ->where(function ($query) use ($variants, $last9, $driverPhoneColumn): void {
                $query->whereIn('b.'.$driverPhoneColumn, $variants->all());
                if ($last9) {
                    $query->orWhereRaw(
                        "SUBSTR(REGEXP_REPLACE(b.$driverPhoneColumn, '[^0-9]', ''), -9) = ?",
                        [$last9]
                    );
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
    }

    public function findByDriverOracleId(int|string $oracleDriverId): Collection
    {
        if (! $this->readiness->available()) {
            return collect();
        }
        $table = self::VEHICLES_TABLE;
        $vehicleColumns = self::VEHICLE_COLUMNS;
        $vehicleIdColumn = $vehicleColumns['id'] ?? 'OID';
        $vehiclePlateColumn = $vehicleColumns['plate'] ?? 'PLATE_NUM';
        $vehicleNameColumn = $vehicleColumns['name'] ?? 'VEHICLE_NUM';
        $vehicleTypeColumn = $vehicleColumns['type'] ?? null;
        $vehicleBeneficiaryColumn = $vehicleColumns['beneficiary_id'] ?? 'BENEFICIARY_OID';

        return DB::connection('oracle')
            ->table($table.' as t')
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
    }
}
