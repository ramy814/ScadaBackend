<?php

declare(strict_types=1);

namespace App\Services\Wells;

use App\Models\Wells\Team;
use App\Repositories\Wells\TeamRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TeamService
{
    public function __construct(
        private readonly TeamRepository $teamRepository,
    ) {
    }

    public function getAllTeams(): Collection
    {
        return $this->teamRepository->all();
    }

    public function getPaginatedTeams(int $perPage = 15): LengthAwarePaginator
    {
        return $this->teamRepository->paginate($perPage);
    }

    public function getTeam(int $id): Team
    {
        return $this->teamRepository->findOrFail($id);
    }

    public function getTeamWithAll(int $id): Team
    {
        return $this->teamRepository->findWithAll($id);
    }

    public function createTeam(array $data, array $wellIds = []): Team
    {
        $team = $this->teamRepository->create($data);

        if (!empty($wellIds)) {
            $this->teamRepository->syncWells((int) $team->getKey(), $wellIds);
        }

        return $team->fresh(['wells', 'inspectors']);
    }

    public function updateTeam(int $id, array $data, ?array $wellIds = null): Team
    {
        $team = $this->teamRepository->update($id, $data);

        if ($wellIds !== null) {
            $this->teamRepository->syncWells($id, $wellIds);
        }

        return $team->fresh(['wells', 'inspectors']);
    }

    public function deleteTeam(int $id): bool
    {
        return $this->teamRepository->delete($id);
    }

    public function assignWells(int $teamId, array $wellIds): void
    {
        $this->teamRepository->syncWells($teamId, $wellIds);
    }

    public function getActiveTeams(): Collection
    {
        return $this->teamRepository->getActiveTeams();
    }

    public function getAllTeamsWithCounts(): Collection
    {
        return $this->teamRepository->getAllWithCounts();
    }
}
