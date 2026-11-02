<?php

declare(strict_types=1);

namespace App\Modules\Wells\Repositories;

use App\Modules\Wells\Models\Team;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TeamRepository
{
    public function __construct(
        private readonly Team $model
    ) {}

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

    public function findForDeletion(int $id): Team
    {
        return $this->model
            ->withCount(['wells', 'inspectors'])
            ->findOrFail($id);
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
        // Verify the team exists
        $this->model->findOrFail($teamId);

        // Get current well IDs for this team
        $existing = DB::connection('oracle')
            ->table('team_wells')
            ->where('TEAM_OID', $teamId)
            ->pluck('well_oid')
            ->map(fn ($id) => (int) $id)
            ->all();

        $newIds = array_map('intval', $wellIds);
        $toDetach = array_diff($existing, $newIds);
        $toAttach = array_diff($newIds, $existing);

        // Remove de-associated wells
        if (! empty($toDetach)) {
            $placeholders = implode(',', array_fill(0, count($toDetach), '?'));
            DB::connection('oracle')->statement(
                "DELETE FROM team_wells WHERE TEAM_OID = ? AND WELL_OID IN ({$placeholders})",
                array_merge([$teamId], array_values($toDetach))
            );
        }

        // Insert newly associated wells
        foreach ($toAttach as $wellId) {
            DB::connection('oracle')->statement(
                'INSERT INTO team_wells (TEAM_OID, WELL_OID, CREATED_AT) VALUES (?, ?, ?)',
                [$teamId, $wellId, now()->toDateTimeString()]
            );
        }
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
