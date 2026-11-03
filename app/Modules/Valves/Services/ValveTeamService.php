<?php

declare(strict_types=1);

namespace App\Modules\Valves\Services;

use App\Modules\Valves\Models\ValveTeam;
use App\Modules\Valves\Repositories\ValveTeamRepository;
use Illuminate\Database\Eloquent\Collection;

class ValveTeamService
{
    public function __construct(
        private readonly ValveTeamRepository $teamRepository,
    ) {}

    public function getAllTeamsWithRelations(): Collection
    {
        return $this->teamRepository->all();
    }

    public function getActiveTeams(): Collection
    {
        return $this->teamRepository->active();
    }

    public function createTeam(array $data, array $valveIds = []): ValveTeam
    {
        $team = $this->teamRepository->create($data);
        $assignAllValves = (bool) ($data['ASSIGN_ALL_VALVES'] ?? false);

        if ($assignAllValves || ! empty($valveIds)) {
            $this->teamRepository->syncValves((int) ($team->oid ?? $team->OID), $assignAllValves ? [] : $valveIds);
        }

        return $team->fresh(['valves', 'inspectors']);
    }

    public function updateTeam(int $id, array $data, ?array $valveIds = null): ValveTeam
    {
        $team = $this->teamRepository->update($id, $data);
        $assignAllValves = (bool) ($data['ASSIGN_ALL_VALVES'] ?? $team->ASSIGN_ALL_VALVES ?? false);

        if ($assignAllValves || $valveIds !== null) {
            $this->teamRepository->syncValves($id, $assignAllValves ? [] : ($valveIds ?? []));
        }

        return $team->fresh(['valves', 'inspectors']);
    }

    public function deleteTeam(int $id): bool
    {
        return $this->teamRepository->delete($id);
    }
}
