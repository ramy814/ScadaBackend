<?php

declare(strict_types=1);

namespace App\Repositories\Wells;

use App\Models\Wells\Well;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class WellRepository
{
    public function __construct(
        private readonly Well $model
    ) {}

    public function all(): Collection
    {
        return $this->withSourceWellCode($this->model->newQuery())
            ->orderBy('wells.NAME')
            ->get();
    }

    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = $this->withSourceWellCode($this->model->newQuery())
            ->with(['teams', 'generators'])
            ->withCount([
                'readings',
                'dieselInventories',
                'notifications',
                'teams',
                'generators',
                'media',
            ])
            ->orderByDesc('wells.IS_ACTIVE')
            ->orderBy('wells.NAME');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('wells.NAME', 'like', '%'.$search.'%')
                    ->orWhere('wells.WELL_NUM', 'like', '%'.$search.'%')
                    ->orWhere('source_wells.SOURCE_WELL_CODE', 'like', '%'.$search.'%');
            });
        }

        if (! empty($filters['damage_status'])) {
            $query->where('wells.DAMAGE_STATUS', $filters['damage_status']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('wells.IS_ACTIVE', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function find(int $id): ?Well
    {
        return $this->withSourceWellCode($this->model->newQuery())
            ->with(['teams', 'generators', 'media'])
            ->find($id);
    }

    public function findOrFail(int $id): Well
    {
        return $this->withSourceWellCode($this->model->newQuery())
            ->with(['teams', 'generators', 'media'])
            ->findOrFail($id);
    }

    public function findForDeletion(int $id): Well
    {
        return $this->model
            ->withCount([
                'readings',
                'dieselInventories',
                'notifications',
                'teams',
                'generators',
                'media',
            ])
            ->findOrFail($id);
    }

    public function create(array $data): Well
    {
        return $this->model->create($data);
    }

    public function update(int $id, array $data): Well
    {
        $well = $this->model->findOrFail($id);
        $well->update($data);

        return $well->fresh();
    }

    public function delete(int $id): bool
    {
        return (bool) $this->model->findOrFail($id)->delete();
    }

    public function findByTeam(int $teamId): Collection
    {
        return $this->withSourceWellCode($this->model->newQuery())
            ->whereHas('teams', function ($query) use ($teamId) {
                $query->where('teams.OID', $teamId);
            })
            ->get();
    }

    public function findWithGenerators(int $id): Well
    {
        return $this->withSourceWellCode($this->model->newQuery())
            ->with('generators')
            ->findOrFail($id);
    }

    public function getActiveWells(): Collection
    {
        return $this->withSourceWellCode($this->model->newQuery())
            ->active()
            ->orderBy('wells.NAME')
            ->get();
    }

    public function getWellsForMap(): Collection
    {
        return $this->withSourceWellCode($this->model->newQuery())
            ->with('generators')
            ->orderByDesc('wells.IS_ACTIVE')
            ->orderBy('wells.NAME')
            ->get();
    }

    public function getActiveForInspector(int $inspectorId): Collection
    {
        return $this->model->newQuery()
            ->active()
            ->whereHas('teams', function ($query) use ($inspectorId): void {
                $query->whereIn('teams.OID', function ($query) use ($inspectorId): void {
                    $query->select('TEAM_OID')
                        ->from('INSPECTOR_TEAMS')
                        ->where('INSPECTOR_OID', $inspectorId);
                });
            })
            ->orderBy('wells.NAME')
            ->get();
    }

    public function getActiveCount(): int
    {
        return $this->model->active()->count();
    }

    public function getTotalCount(): int
    {
        return $this->model->count();
    }

    public function getByPowerSource(string $source): Collection
    {
        return $this->withSourceWellCode($this->model->newQuery())
            ->where('wells.POWER_SOURCE', $source)
            ->get();
    }

    public function getQuarters(): array
    {
        $quarters = DB::connection('oracle')->select('
            SELECT q.OId, q.NAME
            FROM GAZADATA.QUARTERS q
        ');

        return array_map(
            fn (object $row): object => (object) array_change_key_case((array) $row, CASE_UPPER),
            $quarters,
        );
    }

    public function withSourceWellCode(Builder $query): Builder
    {
        return $query
            ->select('wells.*', 'source_wells.SOURCE_WELL_CODE', 'quarters.NAME as QUARTER_NAME')
            ->leftJoin(
                DB::raw('DSS_CORE.WTR_WELLS source_wells'),
                'source_wells.WELL_ID',
                '=',
                'wells.WELL_NUM'
            )
            ->leftJoin(
                DB::raw('GAZADATA.QUARTERS quarters'),
                'quarters.OID',
                '=',
                'wells.QUARTER_OID'
            );
    }
}
