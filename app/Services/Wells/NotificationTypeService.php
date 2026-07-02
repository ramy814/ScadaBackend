<?php

declare(strict_types=1);

namespace App\Services\Wells;

use App\Models\Wells\NotificationType;
use App\Repositories\Wells\NotificationTypeRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NotificationTypeService
{
    public function __construct(
        private readonly NotificationTypeRepository $repository
    ) {
    }

    public function getAllTypes(): Collection
    {
        return $this->repository->all();
    }

    public function getPaginatedTypes(int $perPage = 20): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage);
    }

    public function getActiveTypes(): Collection
    {
        return $this->repository->getActive();
    }

    public function createType(array $data): NotificationType
    {
        return $this->repository->create($data);
    }

    public function updateType(int $id, array $data): NotificationType
    {
        return $this->repository->update($id, $data);
    }

    public function deleteType(int $id): bool
    {
        if ($this->repository->hasNotifications($id)) {
            throw new \RuntimeException('لا يمكن حذف هذا النوع لأنه مرتبط بإشعارات موجودة.');
        }

        return $this->repository->delete($id);
    }

    public function canModify(int $id): bool
    {
        return !$this->repository->hasNotifications($id);
    }
}
