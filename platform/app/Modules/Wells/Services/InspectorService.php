<?php

declare(strict_types=1);

namespace App\Modules\Wells\Services;

use App\Modules\Wells\Models\Inspector;
use App\Modules\Wells\Repositories\InspectorRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class InspectorService
{
    public function __construct(
        private readonly InspectorRepository $inspectorRepository,
    ) {}

    public function getAllInspectors(): Collection
    {
        return $this->inspectorRepository->all()
            ->each(fn (Inspector $inspector): Inspector => $this->applyDeletionState($inspector));
    }

    public function getPaginatedInspectors(int $perPage = 15): LengthAwarePaginator
    {
        $inspectors = $this->inspectorRepository->paginate($perPage);
        $inspectors->getCollection()
            ->each(fn (Inspector $inspector): Inspector => $this->applyDeletionState($inspector));

        return $inspectors;
    }

    public function getInspector(int $id): Inspector
    {
        return $this->inspectorRepository->findOrFail($id);
    }

    public function createInspector(array $data, array $teamIds = []): Inspector
    {
        $inspector = $this->inspectorRepository->create($data);

        if (! empty($teamIds)) {
            $this->inspectorRepository->syncTeams((int) $inspector->getKey(), $teamIds);
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
        $inspector = $this->inspectorRepository->findForDeletion($id);
        $blockers = $this->deletionBlockers($inspector);

        if ($blockers !== []) {
            throw new RuntimeException(
                'لا يمكن حذف المفتش لوجود سجلات مرتبطة به: '.implode('، ', $blockers).'. عطّل الحساب بدلاً من الحذف.'
            );
        }

        try {
            return $this->inspectorRepository->delete($id);
        } catch (QueryException $exception) {
            if (! str_contains($exception->getMessage(), 'ORA-02292')) {
                throw $exception;
            }

            throw new RuntimeException(
                'لا يمكن حذف المفتش لأن قاعدة البيانات وجدت سجلات مرتبطة به.',
                previous: $exception,
            );
        }
    }

    public function authenticate(string $phone, string $password): ?Inspector
    {
        // Search by either PHONE or phone (depending on driver/PDO case)
        $inspector = $this->inspectorRepository->findByPhone($phone);

        if (! $inspector || empty($inspector->is_active ?? $inspector->IS_ACTIVE)) {
            return null;
        }

        $hash = $inspector->password ?? $inspector->PASSWORD;
        if (! Hash::check($password, $hash)) {
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

    private function applyDeletionState(Inspector $inspector): Inspector
    {
        $blockers = $this->deletionBlockers($inspector);
        $inspector->setAttribute('CAN_DELETE', $blockers === []);
        $inspector->setAttribute('DELETE_BLOCKERS', $blockers);

        return $inspector;
    }

    /** @return array<int, string> */
    private function deletionBlockers(Inspector $inspector): array
    {
        $relations = [
            'teams_count' => 'فرق ميدانية',
            'readings_count' => 'قراءات آبار',
            'diesel_inventories_count' => 'سجلات جرد سولار',
            'notifications_count' => 'إشعارات',
            'tokens_count' => 'جلسات دخول',
        ];

        $blockers = [];
        foreach ($relations as $attribute => $label) {
            $count = (int) ($inspector->getAttribute($attribute) ?? 0);
            if ($count > 0) {
                $blockers[] = "{$label} ({$count})";
            }
        }

        return $blockers;
    }
}
