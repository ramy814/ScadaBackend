<?php

declare(strict_types=1);

namespace App\Services\Valves;

use App\Models\Valves\ValveInspector;
use App\Repositories\Valves\ValveInspectorRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;

class ValveInspectorService
{
    public function __construct(
        private readonly ValveInspectorRepository $inspectorRepository,
    ) {
    }

    public function getAllInspectors(): Collection
    {
        return $this->inspectorRepository->all();
    }

    public function createInspector(array $data, array $teamIds = []): ValveInspector
    {
        $inspector = $this->inspectorRepository->create($data);

        if (!empty($teamIds)) {
            $this->inspectorRepository->syncTeams((int) ($inspector->oid ?? $inspector->OID), $teamIds);
        }

        return $inspector->fresh(['teams']);
    }

    public function updateInspector(int $id, array $data, ?array $teamIds = null): ValveInspector
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

    public function authenticate(string $phone, string $password): ?ValveInspector
    {
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
}
