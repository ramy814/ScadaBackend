<?php

declare(strict_types=1);

namespace App\Services\Wells;

use App\Models\Wells\WellNotification;
use App\Repositories\Wells\WellNotificationRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class WellNotificationService
{
    public function __construct(
        private readonly WellNotificationRepository $repository
    ) {
    }

    public function getUnreadCount(): int
    {
        return $this->repository->getUnreadCount();
    }

    public function getPaginatedNotifications(int $perPage = 20, array $filters = []): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage, $filters);
    }

    public function createNotification(array $data): WellNotification
    {
        return $this->repository->create($data);
    }

    public function markAsRead(int $id): WellNotification
    {
        return $this->repository->markAsRead($id);
    }

    public function markAllAsRead(): int
    {
        return $this->repository->markAllAsRead();
    }

    public function getLatestUnread(int $limit = 10): Collection
    {
        return $this->repository->getLatestUnread($limit);
    }
}
