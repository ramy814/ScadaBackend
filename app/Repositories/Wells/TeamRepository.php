<?php

declare(strict_types=1);

namespace App\Repositories\Wells;

use App\Models\Wells\Team;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TeamRepository
{
    public function __construct(
        private readonly Team $model
    ) {
    }

    public function all(): Collection
    {
        return $this->model->orderBy('NAME')->get();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->withCount(['wells', 'inspectors'])->orderBy('NAME')->paginate($perPage);
    }

    public function find(int $id): ?Team
    {
        return $this->model->find($id);
    }

    public function findOrFail(int $id): Team
    {
        return $this->model->findOrFail($id);
    }

    public function findWithWells(int $id): Team
    {
        return $this->model->with('wells')->findOrFail($id);
    }

    public function findWithInspectors(int $id): Team
    {
        return $this->model->with('inspectors')->findOrFail($id);
    }

    public function findWithAll(int $id): Team
    {
        return $this->model->with(['wells', 'inspectors'])->findOrFail($id);
    }

    public function create(array $data): Team
    {
        return $this->model->create($data);
    }

    public function update(int $id, array $data): Team
    {
        $team = $this->model->findOrFail($id);
        $team->update($data);

        return $team->fresh();
    }

    public function delete(int $id): bool
    {
        return (bool) $this->model->findOrFail($id)->delete();
    }

    public function syncWells(int $teamId, array $wellIds): void
    {
        $team = $this->model->findOrFail($teamId);
        $team->wells()->sync($wellIds);
    }

    public function getActiveTeams(): Collection
    {
        return $this->model->active()->withCount(['wells', 'inspectors'])->get();
    }

    public function getActiveCount(): int
    {
        return $this->model->active()->count();
    }

    public function getAllWithCounts(): Collection
    {
        return $this->model
            ->with(['wells', 'inspectors'])
            ->withCount(['wells', 'inspectors'])
            ->orderBy('NAME')
            ->get();
    }
}
