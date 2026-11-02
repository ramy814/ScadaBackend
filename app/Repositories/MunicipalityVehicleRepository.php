<?php

namespace App\Repositories;

use Illuminate\Database\Query\Builder;
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

    /**
     * Vehicles reachable through the authenticated assistant's stations.
     */
    public function forAdministrativeAssistant(int $ssoUserId): Collection
    {
        if (! $this->readiness->available()) {
            return collect();
        }

        try {
            return $this->administrativeAssistantVehiclesQuery($ssoUserId)
                ->orderBy('v.PLATE_NUM')
                ->get()
                ->map(fn ($row): array => (array) $row);
        } catch (QueryException $e) {
            $this->logOracleReadFailure('administrative_assistant_vehicles', $e);

            return collect();
        }
    }

    public function forManualReviewer(int $ssoUserId): Collection
    {
        if (! $this->readiness->available()) {
            return collect();
        }

        try {
            return $this->administrativeAssistantVehiclesQuery($ssoUserId, [2, 3])
                ->orderBy('v.PLATE_NUM')->get()->map(fn ($row): array => (array) $row);
        } catch (QueryException $e) {
            $this->logOracleReadFailure('manual_reviewer_vehicles', $e);

            return collect();
        }
    }

    public function findForAdministrativeAssistant(int $ssoUserId, int|string $vehicleId): ?array
    {
        if (! $this->readiness->available()) {
            return null;
        }

        try {
            $row = $this->administrativeAssistantVehiclesQuery($ssoUserId)
                ->where('v.OID', $vehicleId)
                ->first();
        } catch (QueryException $e) {
            $this->logOracleReadFailure('administrative_assistant_vehicle', $e);

            return null;
        }

        return $row ? (array) $row : null;
    }

    /** @return Collection<int, array{can_enter: bool, can_review: bool}> */
    public function operationAccessForUsers(array $ssoUserIds): Collection
    {
        $ids = collect($ssoUserIds)->map(fn ($id): int => (int) $id)->filter(fn (int $id): bool => $id > 0)->unique();
        if ($ids->isEmpty() || ! $this->readiness->available()) {
            return collect();
        }

        try {
            $access = collect();
            foreach ($ids->chunk(900) as $chunk) {
                $rows = DB::connection('oracle')
                    ->table('g_store.VEHICLE_STAGES as vs')
                    ->join('g_store.VEHICLE as v', 'v.OID', '=', 'vs.VEHICLE_OID')
                    ->join('tracking.USER_STATION as a', 'a.STAGE_OID', '=', 'vs.STAGE_OID')
                    ->join('mog_users.USERS_LIST as ul', 'ul.TRACKING_ID', '=', 'a.USER_OID')
                    ->whereNull('vs.REMOVED_USER')
                    ->whereIn('vs.ACCESS_TYPE', [1, 2, 3])
                    ->whereIn('ul.ID', $chunk->all())
                    ->selectRaw('ul.ID as user_id,
                        max(case when vs.ACCESS_TYPE in (1, 3) and v.PLATE_NUM is not null then 1 else 0 end) as can_enter,
                        max(case when vs.ACCESS_TYPE in (2, 3) then 1 else 0 end) as can_review')
                    ->groupBy('ul.ID')
                    ->get();

                foreach ($rows as $row) {
                    $access->put((int) $row->user_id, [
                        'can_enter' => (bool) $row->can_enter,
                        'can_review' => (bool) $row->can_review,
                    ]);
                }
            }

            return $access;
        } catch (QueryException $e) {
            $this->logOracleReadFailure('station_operation_access', $e);

            return collect();
        }
    }

    /** @return Collection<int, string> */
    public function vehicleIdsForReviewer(int $ssoUserId): Collection
    {
        if ($ssoUserId <= 0 || ! $this->readiness->available()) {
            return collect();
        }

        try {
            return $this->reviewerVehiclesQuery($ssoUserId)
                ->pluck('oracle_id')
                ->map(fn ($id): string => (string) $id)
                ->unique()
                ->values();
        } catch (QueryException $e) {
            $this->logOracleReadFailure('reviewer_vehicle_ids', $e);

            return collect();
        }
    }

    public function reviewerCanAccessVehicle(int $ssoUserId, int|string|null $vehicleId): bool
    {
        if ($ssoUserId <= 0 || ! filled($vehicleId) || ! $this->readiness->available()) {
            return false;
        }

        try {
            return $this->reviewerVehiclesQuery($ssoUserId)
                ->where('v.OID', $vehicleId)
                ->exists();
        } catch (QueryException $e) {
            $this->logOracleReadFailure('reviewer_vehicle_access', $e);

            return false;
        }
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

    public function countActiveAssignedToDrivers(
        array $oracleDriverIds,
        ?array $allowedVehicleIds = null,
    ): int {
        $ids = collect($oracleDriverIds)
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): string => (string) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()
            || ($allowedVehicleIds !== null && $allowedVehicleIds === [])
            || ! $this->readiness->available()
        ) {
            return 0;
        }

        $table = $this->vehiclesTable();
        $columns = $this->vehicleColumns();

        try {
            return (int) DB::connection('oracle')
                ->table($table)
                ->whereIn($columns['beneficiary_id'], $ids->all())
                ->when($allowedVehicleIds !== null, fn ($query) => $query
                    ->whereIn($columns['id'], $allowedVehicleIds))
                ->where($columns['active'], 1)
                ->whereNotNull($columns['plate'])
                ->distinct()
                ->count($columns['id']);
        } catch (QueryException $e) {
            $this->logOracleReadFailure('count_active_tracking_app_vehicles', $e);

            return 0;
        }
    }

    /** @return array<int, string> */
    public function activeDriverIdsAssignedToVehicles(
        array $oracleDriverIds,
        array $allowedVehicleIds,
    ): array {
        $driverIds = collect($oracleDriverIds)
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): string => (string) $id)
            ->unique()
            ->values();

        if ($driverIds->isEmpty() || $allowedVehicleIds === [] || ! $this->readiness->available()) {
            return [];
        }

        $columns = $this->vehicleColumns();

        try {
            return DB::connection('oracle')
                ->table($this->vehiclesTable())
                ->whereIn($columns['id'], $allowedVehicleIds)
                ->whereIn($columns['beneficiary_id'], $driverIds->all())
                ->where($columns['active'], 1)
                ->whereNotNull($columns['plate'])
                ->selectRaw($columns['beneficiary_id'].' as oracle_driver_id')
                ->distinct()
                ->pluck('oracle_driver_id')
                ->map(fn ($id): string => (string) $id)
                ->values()
                ->all();
        } catch (QueryException $e) {
            $this->logOracleReadFailure('active_drivers_for_review_vehicles', $e);

            return [];
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

    /**
     * Current hourly rental price (G_STORE.VEHICLE.HOURLY_PRICE) used for vehicle claims.
     * Returns null when the vehicle has no price or Oracle is unavailable.
     */
    public function hourlyPriceFor(int|string|null $vehicleId): ?float
    {
        if (! filled($vehicleId)) {
            return null;
        }

        return $this->hourlyPricesFor([$vehicleId])[(string) $vehicleId] ?? null;
    }

    /** @return array<string, float|null> Current hourly price keyed by vehicle OID. */
    public function hourlyPricesFor(array $vehicleIds): array
    {
        $ids = collect($vehicleIds)
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): string => (string) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty() || ! $this->readiness->available()) {
            return [];
        }

        $columns = $this->vehicleColumns();

        try {
            $prices = [];
            foreach ($ids->chunk(900) as $chunk) {
                DB::connection('oracle')
                    ->table($this->vehiclesTable())
                    ->whereIn($columns['id'], $chunk->all())
                    ->selectRaw(sprintf('%s as oracle_id, %s as hourly_price', $columns['id'], $columns['hourly_price']))
                    ->get()
                    ->each(function ($row) use (&$prices): void {
                        $prices[(string) $row->oracle_id] = is_numeric($row->hourly_price)
                            ? round((float) $row->hourly_price, 2)
                            : null;
                    });
            }

            return $prices;
        } catch (QueryException $e) {
            $this->logOracleReadFailure('vehicle_hourly_prices', $e);

            return [];
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

    private function administrativeAssistantVehiclesQuery(int $ssoUserId, array $accessTypes = [1, 3]): Builder
    {
        return DB::connection('oracle')
            ->table('g_store.VEHICLE as v')
            ->leftJoin('g_store.BENEFICIARIES as b', 'b.OID', '=', 'v.BENEFICIARY_OID')
            ->selectRaw(<<<'SQL'
                v.OID as oracle_id,
                v.PLATE_NUM as plate_number,
                v.VEHICLE_NUM as name,
                v.VEHICLE_TYPE_OID as vehicle_type,
                v.ODOMETER as odometer,
                b.OID as oracle_driver_id,
                b.FULL_NAME as driver_name,
                b.MOBILE_NUM as driver_phone,
                b.NATIONAL_ID as driver_employee_number
            SQL)
            ->whereNotNull('v.PLATE_NUM')
            ->whereExists(fn ($query) => $this->stationAccessQuery($query, $ssoUserId, $accessTypes));
    }

    private function reviewerVehiclesQuery(int $ssoUserId): Builder
    {
        return DB::connection('oracle')
            ->table('g_store.VEHICLE as v')
            ->selectRaw('v.OID as oracle_id')
            ->whereExists(fn ($query) => $this->stationAccessQuery($query, $ssoUserId, [2, 3]));
    }

    private function stationAccessQuery(Builder $query, int $ssoUserId, array $accessTypes): Builder
    {
        return $query->selectRaw('1')
            ->from('g_store.VEHICLE_STAGES as vs')
            ->join('tracking.USER_STATION as a', 'a.STAGE_OID', '=', 'vs.STAGE_OID')
            ->join('mog_users.USERS_LIST as ul', 'ul.TRACKING_ID', '=', 'a.USER_OID')
            ->whereColumn('vs.VEHICLE_OID', 'v.OID')
            ->whereNull('vs.REMOVED_USER')
            ->whereIn('vs.ACCESS_TYPE', $accessTypes)
            ->where('ul.ID', $ssoUserId);
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
            'hourly_price' => 'HOURLY_PRICE',
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
