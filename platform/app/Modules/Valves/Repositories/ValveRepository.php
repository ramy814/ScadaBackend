<?php

declare(strict_types=1);

namespace App\Modules\Valves\Repositories;

use App\Modules\Valves\Models\Valve;
use App\Modules\Valves\Models\ValveInspector;
use App\Modules\Valves\Models\ValveLog;
use App\Modules\Valves\Models\ValveMaintenanceReport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ValveRepository
{
    public function __construct(
        private readonly Valve $model,
    ) {}

    public function all(): Collection
    {
        return $this->model->orderBy('W4_GENERAL_ID')->get();
    }

    public function forMap(): Collection
    {
        return $this->attachWgs84Coordinates(
            $this->model->orderBy('W4_GENERAL_ID')->get(),
        );
    }

    public function assignedToInspector(?ValveInspector $inspector, array $filters = []): Collection
    {
        $query = $this->model->newQuery();

        if ($inspector) {
            $teams = $inspector->teams()->get(['valve_teams.oid', 'valve_teams.ASSIGN_ALL_VALVES']);
            $hasAllValvesAccess = $teams->contains(
                fn ($team): bool => (bool) ($team->assign_all_valves ?? $team->ASSIGN_ALL_VALVES),
            );

            if (! $hasAllValvesAccess) {
                $teamIds = $teams->pluck('oid');

                $query->whereHas('teams', function ($teamQuery) use ($teamIds): void {
                    $teamQuery->whereIn('valve_teams.oid', $teamIds);
                });
            }
        }

        if (! empty($filters['area'])) {
            $query->where('W4_SOURCE', $filters['area']);
        }

        if (! empty($filters['status'])) {
            $query->where('W4_STATUS', strtoupper((string) $filters['status']));
        }

        $valves = $query->with('latestLog')->orderBy('W4_GENERAL_ID')->get();

        return $this->attachWgs84Coordinates($valves);
    }

    public function findOrFail(int $id): Valve
    {
        return $this->model->findOrFail($id);
    }

    public function exists(int $id): bool
    {
        return $this->model->whereKey($id)->exists();
    }

    public function findActionByLocalId(string $localId, int $inspectorId): ?ValveLog
    {
        return ValveLog::query()
            ->where('CLIENT_LOCAL_ID', $localId)
            ->where('USER_OID', $inspectorId)
            ->first();
    }

    public function createAction(array $data): ValveLog
    {
        return ValveLog::query()->create($data);
    }

    public function createMaintenanceReport(array $data): ValveMaintenanceReport
    {
        return ValveMaintenanceReport::query()->create($data);
    }

    // ── Mobile API reads (legacy Api\DashboardController / Valves\ValveMaintenanceController) ──

    /** Latest logged action per valve, exactly as the legacy dashboard stats computed it. */
    public function latestActionsPerValve(): Collection
    {
        return ValveLog::query()->orderBy('ACTION_AT', 'desc')->get(['VALVE_OID', 'ACTION'])->unique('valve_oid');
    }

    public function count(): int
    {
        return $this->model->newQuery()->count();
    }

    public function recentActions(int $limit): Collection
    {
        return ValveLog::with('valve')->orderBy('ACTION_AT', 'desc')->take($limit)->get();
    }

    /** @return LengthAwarePaginator<ValveLog> */
    public function paginateLogs(Valve $valve, int $perPage): LengthAwarePaginator
    {
        return $valve->logs()->with('user')->paginate($perPage);
    }

    /** @return LengthAwarePaginator<ValveMaintenanceReport> */
    /** @param  array<string, mixed>  $filters  a filter applies whenever its key is present (legacy $request->has()) */
    public function paginateMaintenanceReports(array $filters, int $perPage): LengthAwarePaginator
    {
        return ValveMaintenanceReport::with(['reporter:oid,name,phone'])
            ->when(array_key_exists('status', $filters), fn ($q) => $q->where('STATUS', $filters['status']))
            ->when(array_key_exists('priority', $filters), fn ($q) => $q->where('PRIORITY', $filters['priority']))
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function updateMaintenanceReport(ValveMaintenanceReport $report, array $data): ValveMaintenanceReport
    {
        if ($data !== []) {
            $report->update($data);
        }

        return $report->load('reporter:oid,name');
    }

    /** Assignment check used by the legacy ValveController::authorizeInspectorValveAccess(). */
    public function inspectorCanAccess(ValveInspector $inspector, Valve $valve): bool
    {
        $teams = $inspector->teams()->get(['valve_teams.oid', 'valve_teams.ASSIGN_ALL_VALVES']);
        if ($teams->contains(fn ($team): bool => (bool) ($team->assign_all_valves ?? $team->ASSIGN_ALL_VALVES))) {
            return true;
        }

        return $valve->teams()->whereIn('valve_teams.oid', $teams->pluck('oid'))->exists();
    }

    private function attachWgs84Coordinates(Collection $valves): Collection
    {
        $coordinates = collect();

        foreach (array_chunk($valves->modelKeys(), 900) as $ids) {
            if ($ids === []) {
                continue;
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $connection = DB::connection($this->model->getConnectionName());
            // shortcut: SQLite fixture has no Oracle Spatial; it stores plain WGS84 columns. Production always takes the SDO path.
            $rows = $connection->getDriverName() === 'sqlite' ? $connection->select(
                "select W4_GENERAL_ID as VALVE_ID, FIXTURE_LONGITUDE as LONGITUDE, FIXTURE_LATITUDE as LATITUDE
                 from EME.WATER_VALVES where FIXTURE_LATITUDE is not null and W4_GENERAL_ID in ($placeholders)",
                $ids,
            ) : $connection->select(
                "select t.W4_GENERAL_ID as VALVE_ID, v.X as LONGITUDE, v.Y as LATITUDE
                 from EME.WATER_VALVES t,
                      table(SDO_UTIL.GETVERTICES(SDO_CS.TRANSFORM(t.GEOMETRY, 4326))) v
                 where t.GEOMETRY is not null
                   and t.W4_GENERAL_ID in ($placeholders)",
                $ids,
            );

            $coordinates->push(...$rows);
        }

        $byValve = $coordinates->keyBy(fn (object $row): int => (int) ($row->valve_id ?? $row->VALVE_ID));

        return $valves->each(function (Valve $valve) use ($byValve): void {
            $coordinate = $byValve->get((int) $valve->getKey());

            if (! $coordinate) {
                return;
            }

            $valve->setAttribute('map_latitude', $coordinate->latitude ?? $coordinate->LATITUDE);
            $valve->setAttribute('map_longitude', $coordinate->longitude ?? $coordinate->LONGITUDE);
        });
    }
}
