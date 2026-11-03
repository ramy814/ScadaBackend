<?php

declare(strict_types=1);

namespace App\Modules\Valves\Repositories;

use App\Modules\Valves\Models\Valve;
use App\Modules\Valves\Models\ValveInspector;
use App\Modules\Valves\Models\ValveLog;
use App\Modules\Valves\Models\ValveMaintenanceReport;
use App\Modules\Valves\Models\ValveTeam;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ValvesRepository
{
    public function totalValves(): int
    {
        return Valve::query()->toBase()->count();
    }

    /**
     * Latest recorded action per valve, aggregated in the database. The legacy
     * dashboard loaded every VALVE_LOGS row into PHP to compute the same numbers.
     *
     * @return array<string, int>
     */
    public function latestActionDistribution(): array
    {
        $ranked = DB::connection('oracle')->table('valve_logs')
            ->selectRaw('ACTION AS action, ROW_NUMBER() OVER (PARTITION BY VALVE_OID ORDER BY ACTION_AT DESC, OID DESC) AS rn');

        return DB::connection('oracle')->query()->fromSub($ranked, 'latest_action')
            ->where('rn', 1)
            ->groupBy('action')
            ->selectRaw('action, COUNT(*) AS valves')
            ->pluck('valves', 'action')
            ->map(fn ($count): int => (int) $count)
            ->all();
    }

    public function actionsOn(CarbonImmutable $day): int
    {
        return ValveLog::query()->whereBetween('ACTION_AT', [$day->startOfDay(), $day->endOfDay()])->count();
    }

    /** @return array<string, int> */
    public function maintenanceCounts(): array
    {
        return ValveMaintenanceReport::query()->toBase()
            ->selectRaw('STATUS AS status, COUNT(*) AS reports')->groupBy('STATUS')
            ->pluck('reports', 'status')->map(fn ($c): int => (int) $c)->all();
    }

    public function paginateMaintenance(?string $status): LengthAwarePaginator
    {
        return ValveMaintenanceReport::query()->with(['valve', 'reporter:oid,name'])
            ->when($status !== null && $status !== 'all', fn (Builder $q) => $q->where('STATUS', $status))
            ->orderByRaw("CASE WHEN STATUS = 'pending' THEN 0 WHEN STATUS = 'in_progress' THEN 1 ELSE 2 END")
            ->orderByDesc('CREATED_AT')
            ->paginate(20)->withQueryString();
    }

    /** Filters ported from the legacy ValveReportService::getLogs(). */
    public function paginateActions(array $filters): LengthAwarePaginator
    {
        $valveId = (int) str_replace('WV-', '', strtoupper((string) ($filters['valve'] ?? '')));

        return ValveLog::query()->with(['inspector:oid,name', 'valve'])
            ->when(filled($filters['inspector'] ?? null), fn (Builder $q) => $q->where('USER_OID', $filters['inspector']))
            ->when(filled($filters['team'] ?? null), fn (Builder $q) => $q->whereHas('inspector.teams', fn (Builder $t) => $t->where('valve_teams.oid', $filters['team'])))
            ->when(filled($filters['action'] ?? null), fn (Builder $q) => $q->where('ACTION', $filters['action']))
            ->when($valveId > 0, fn (Builder $q) => $q->where('VALVE_OID', $valveId))
            ->when(filled($filters['date_from'] ?? null), fn (Builder $q) => $q->where('ACTION_AT', '>=', CarbonImmutable::parse($filters['date_from'])->startOfDay()))
            ->when(filled($filters['date_to'] ?? null), fn (Builder $q) => $q->where('ACTION_AT', '<=', CarbonImmutable::parse($filters['date_to'])->endOfDay()))
            ->orderByDesc('ACTION_AT')
            ->paginate(25)->withQueryString();
    }

    public function teams(): Collection
    {
        return ValveTeam::query()->withCount(['valves', 'inspectors'])->orderBy('NAME')->get();
    }

    public function inspectors(): Collection
    {
        return ValveInspector::query()->with('teams:oid,name')
            ->addSelect(['logs_count' => ValveLog::query()->selectRaw('COUNT(*)')->whereColumn('USER_OID', 'valve_inspectors.OID')])
            ->orderBy('NAME')->get();
    }
}
