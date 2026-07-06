<?php

declare(strict_types=1);

namespace App\Repositories\Wells;

use App\Models\Wells\Inspector;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

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
        // Verify the inspector exists
        $this->model->findOrFail($inspectorId);

        // Get current team IDs for this inspector
        $existing = DB::connection('oracle')
            ->table('inspector_teams')
            ->where('INSPECTOR_OID', $inspectorId)
            ->pluck('team_oid')
            ->map(fn ($id) => (int) $id)
            ->all();

        $newIds   = array_map('intval', $teamIds);
        $toDetach = array_diff($existing, $newIds);
        $toAttach = array_diff($newIds, $existing);

        // Remove de-associated teams
        if (!empty($toDetach)) {
            $placeholders = implode(',', array_fill(0, count($toDetach), '?'));
            DB::connection('oracle')->statement(
                "DELETE FROM inspector_teams WHERE INSPECTOR_OID = ? AND TEAM_OID IN ({$placeholders})",
                array_merge([$inspectorId], array_values($toDetach))
            );
        }

        // Insert newly associated teams
        foreach ($toAttach as $teamId) {
            DB::connection('oracle')->statement(
                'INSERT INTO inspector_teams (INSPECTOR_OID, TEAM_OID, CREATED_AT) VALUES (?, ?, ?)',
                [$inspectorId, $teamId, now()->toDateTimeString()]
            );
        }
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
