<?php

declare(strict_types=1);

namespace App\Repositories\Wells;

use App\Models\Wells\Inspector;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InspectorRepository
{
    public function __construct(
        private readonly Inspector $model
    ) {
    }

    public function all(): Collection
    {
        return $this->model->with('teams')->orderBy('NAME')->get();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->with('teams')->orderBy('NAME')->paginate($perPage);
    }

    public function find(int $id): ?Inspector
    {
        return $this->model->with('teams')->find($id);
    }

    public function findOrFail(int $id): Inspector
    {
        return $this->model->with('teams')->findOrFail($id);
    }

    public function create(array $data): Inspector
    {
        return $this->model->create($data);
    }

    public function update(int $id, array $data): Inspector
    {
        $inspector = $this->model->findOrFail($id);
        $inspector->update($data);

        return $inspector->fresh(['teams']);
    }

    public function delete(int $id): bool
    {
        return (bool) $this->model->findOrFail($id)->delete();
    }

    public function findByPhone(string $phone): ?Inspector
    {
        return $this->model->where('PHONE', $phone)->first();
    }

    public function findByTeam(int $teamId): Collection
    {
        return $this->model->whereHas('teams', function ($query) use ($teamId) {
            $query->where('teams.OID', $teamId);
        })->get();
    }

    public function syncTeams(int $inspectorId, array $teamIds): void
    {
        $inspector = $this->model->findOrFail($inspectorId);
        $inspector->teams()->sync($teamIds);
    }

    public function getActiveCount(): int
    {
        return $this->model->active()->count();
    }

    public function getActiveInspectors(): Collection
    {
        return $this->model->active()->with('teams')->get();
    }
}
