<?php

declare(strict_types=1);

namespace App\Repositories\Wells;

use App\Models\Wells\Well;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class WellRepository
{
    public function __construct(
        private readonly Well $model
    ) {
    }

    public function all(): Collection
    {
        return $this->model->orderBy('NAME')->get();
    }

    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->with('teams')->withCount(['readings', 'dieselInventories'])->orderBy('NAME');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('NAME', 'like', '%' . $search . '%')
                  ->orWhere('WELL_NUM', 'like', '%' . $search . '%');
            });
        }

        if (!empty($filters['quarter_oid'])) {
            $query->where('QUARTER_OID', $filters['quarter_oid']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('IS_ACTIVE', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function find(int $id): ?Well
    {
        return $this->model->with(['teams', 'generators'])->find($id);
    }

    public function findOrFail(int $id): Well
    {
        return $this->model->with(['teams', 'generators'])->findOrFail($id);
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
        return $this->model->whereHas('teams', function ($query) use ($teamId) {
            $query->where('teams.OID', $teamId);
        })->get();
    }

    public function findWithGenerators(int $id): Well
    {
        return $this->model->with('generators')->findOrFail($id);
    }

    public function getActiveWells(): Collection
    {
        return $this->model->active()->orderBy('NAME')->get();
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
        return $this->model->where('POWER_SOURCE', $source)->get();
    }
}
