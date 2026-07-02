<?php

declare(strict_types=1);

namespace App\Services\Wells;

use App\Models\Wells\Inspector;
use App\Repositories\Wells\InspectorRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class InspectorService
{
    public function __construct(
        private readonly InspectorRepository $inspectorRepository,
    ) {
    }

    public function getAllInspectors(): Collection
    {
        return $this->inspectorRepository->all();
    }

    public function getPaginatedInspectors(int $perPage = 15): LengthAwarePaginator
    {
        return $this->inspectorRepository->paginate($perPage);
    }

    public function getInspector(int $id): Inspector
    {
        return $this->inspectorRepository->findOrFail($id);
    }

    public function createInspector(array $data, array $teamIds = []): Inspector
    {
        $inspector = $this->inspectorRepository->create($data);

        if (!empty($teamIds)) {
            $this->inspectorRepository->syncTeams($inspector->OID, $teamIds);
        }

        return $inspector->fresh(['teams']);
    }

    public function updateInspector(int $id, array $data, ?array $teamIds = null): Inspector
    {
        if (array_key_exists('PASSWORD', $data) && empty($data['PASSWORD'])) {
            unset($data['PASSWORD']);
        }

        $inspector = $this->inspectorRepository->update($id, $data);

        if ($teamIds !== null) {
            $this->inspectorRepository->syncTeams($id, $teamIds);
        }

        return $inspector->fresh(['teams']);
    }

    public function deleteInspector(int $id): bool
    {
        return $this->inspectorRepository->delete($id);
    }

    public function authenticate(string $phone, string $password): ?Inspector
    {
        // Search by either PHONE or phone (depending on driver/PDO case)
        $inspector = $this->inspectorRepository->findByPhone($phone);

        if (!$inspector || empty($inspector->is_active ?? $inspector->IS_ACTIVE)) {
            return null;
        }

        $hash = $inspector->password ?? $inspector->PASSWORD;
        if (!Hash::check($password, $hash)) {
            return null;
        }

        return $inspector;
    }

    public function assignToTeams(int $inspectorId, array $teamIds): void
    {
        $this->inspectorRepository->syncTeams($inspectorId, $teamIds);
    }

    public function getInspectorsByTeam(int $teamId): Collection
    {
        return $this->inspectorRepository->findByTeam($teamId);
    }
}
