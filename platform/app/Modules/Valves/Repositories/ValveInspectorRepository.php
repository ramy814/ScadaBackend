<?php

declare(strict_types=1);

namespace App\Modules\Valves\Repositories;

use App\Modules\Valves\Models\ValveInspector;
use Illuminate\Database\Eloquent\Collection;

class ValveInspectorRepository
{
    public function __construct(
        private readonly ValveInspector $model,
    ) {}

    public function all(): Collection
    {
        return $this->model->with('teams')->orderBy('NAME')->get();
    }

    public function findOrFail(int $id): ValveInspector
    {
        return $this->model->with('teams')->findOrFail($id);
    }

    public function create(array $data): ValveInspector
    {
        return $this->model->create($data);
    }

    public function update(int $id, array $data): ValveInspector
    {
        $inspector = $this->model->findOrFail($id);
        $inspector->update($data);

        return $inspector->fresh(['teams']);
    }

    public function delete(int $id): bool
    {
        return (bool) $this->model->findOrFail($id)->delete();
    }

    public function findByPhone(string $phone): ?ValveInspector
    {
        return $this->model->where('PHONE', $phone)->first();
    }

    public function syncTeams(int $inspectorId, array $teamIds): void
    {
        $inspector = $this->model->findOrFail($inspectorId);
        $inspector->teams()->sync($teamIds);
    }
}
