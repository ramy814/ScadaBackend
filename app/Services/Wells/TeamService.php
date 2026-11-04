<?php

declare(strict_types=1);

namespace App\Services\Wells;

use App\Models\Wells\Team;
use App\Repositories\Wells\TeamRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use RuntimeException;

class TeamService
{
    public function __construct(
        private readonly TeamRepository $teamRepository,
    ) {}

    public function getAllTeams(): Collection
    {
        return $this->teamRepository->all();
    }

    public function getPaginatedTeams(int $perPage = 15): LengthAwarePaginator
    {
        $teams = $this->teamRepository->paginate($perPage);
        $teams->getCollection()
            ->each(fn (Team $team): Team => $this->applyDeletionState($team));

        return $teams;
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

        if (! empty($wellIds)) {
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
        $team = $this->teamRepository->findForDeletion($id);
        $blockers = $this->deletionBlockers($team);

        if ($blockers !== []) {
            throw new RuntimeException(
                'لا يمكن حذف الفريق لوجود سجلات مرتبطة به: '.implode('، ', $blockers).'. أزل الارتباطات أو عطّل الفريق.'
            );
        }

        try {
            return $this->teamRepository->delete($id);
        } catch (QueryException $exception) {
            if (! str_contains($exception->getMessage(), 'ORA-02292')) {
                throw $exception;
            }

            throw new RuntimeException(
                'لا يمكن حذف الفريق لأن قاعدة البيانات وجدت سجلات مرتبطة به.',
                previous: $exception,
            );
        }
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
        return $this->teamRepository->getAllWithCounts()
            ->each(fn (Team $team): Team => $this->applyDeletionState($team));
    }

    private function applyDeletionState(Team $team): Team
    {
        $blockers = $this->deletionBlockers($team);
        $team->setAttribute('CAN_DELETE', $blockers === []);
        $team->setAttribute('DELETE_BLOCKERS', $blockers);

        return $team;
    }

    /** @return array<int, string> */
    private function deletionBlockers(Team $team): array
    {
        $relations = [
            'wells_count' => 'آبار مسندة',
            'inspectors_count' => 'مفتشون',
        ];

        $blockers = [];
        foreach ($relations as $attribute => $label) {
            $count = (int) ($team->getAttribute($attribute) ?? 0);
            if ($count > 0) {
                $blockers[] = "{$label} ({$count})";
            }
        }

        return $blockers;
    }
}
