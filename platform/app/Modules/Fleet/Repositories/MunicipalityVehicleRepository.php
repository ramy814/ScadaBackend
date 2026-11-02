<?php

namespace App\Modules\Fleet\Repositories;

use App\Platform\Support\OracleReadiness;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Oracle station/vehicle scope, ported from the legacy repository. Only the review
 * scope, assistant entry scope and driver-vehicle lookups the platform uses are
 * kept; driver-app lookups remain in the legacy backend.
 */
class MunicipalityVehicleRepository
{
    public function __construct(private readonly OracleReadiness $readiness) {}

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
                $access = $access->union($this->operationAccessRows($this->operationAccessQuery()->whereIn('ul.ID', $chunk->all())));
            }

            return $access;
        } catch (QueryException $e) {
            $this->logOracleReadFailure('station_operation_access', $e);

            return collect();
        }
    }

    /**
     * Every SSO user with an active station link (manual entry or approval scope),
     * so the access directory can list assistants that have no SYSTEM_PERMISSIONS row.
     *
     * @return Collection<int, array{can_enter: bool, can_review: bool}>
     */
    public function stationOperationUsers(): Collection
    {
        if (! $this->readiness->available()) {
            return collect();
        }

        try {
            return $this->operationAccessRows($this->operationAccessQuery());
        } catch (QueryException $e) {
            $this->logOracleReadFailure('station_operation_users', $e);

            return collect();
        }
    }

    private function operationAccessQuery(): Builder
    {
        return DB::connection('oracle')
            ->table('g_store.VEHICLE_STAGES as vs')
            ->join('g_store.VEHICLE as v', 'v.OID', '=', 'vs.VEHICLE_OID')
            ->join('tracking.USER_STATION as a', 'a.STAGE_OID', '=', 'vs.STAGE_OID')
            ->join('mog_users.USERS_LIST as ul', 'ul.TRACKING_ID', '=', 'a.USER_OID')
            ->whereNull('vs.REMOVED_USER')
            ->whereIn('vs.ACCESS_TYPE', [1, 2, 3])
            ->selectRaw('ul.ID as user_id,
                max(case when vs.ACCESS_TYPE in (1, 3) and v.PLATE_NUM is not null then 1 else 0 end) as can_enter,
                max(case when vs.ACCESS_TYPE in (2, 3) then 1 else 0 end) as can_review')
            ->groupBy('ul.ID');
    }

    /** @return Collection<int, array{can_enter: bool, can_review: bool}> */
    private function operationAccessRows(Builder $query): Collection
    {
        return $query->get()->mapWithKeys(fn ($row): array => [(int) $row->user_id => [
            'can_enter' => (bool) $row->can_enter,
            'can_review' => (bool) $row->can_review,
        ]]);
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

    /** @return array<int, string> */
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
        ];
    }

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
}
