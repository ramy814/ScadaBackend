<?php

declare(strict_types=1);

namespace App\Repositories\Wells;

use App\Models\Wells\WellNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class WellNotificationRepository
{
    public function __construct(
        private readonly WellNotification $model
    ) {
    }

    public function getUnreadCount(): int
    {
        return $this->model->where('IS_READ', false)->count();
    }

    public function paginate(int $perPage = 20, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model
            ->with(['well', 'notificationType', 'inspector'])
            ->orderBy('CREATED_AT', 'desc');

        if (isset($filters['is_read']) && $filters['is_read'] !== '') {
            $query->where('IS_READ', filter_var($filters['is_read'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function create(array $data): WellNotification
    {
        return $this->model->create($data);
    }

    public function findOrFail(int $id): WellNotification
    {
        return $this->model->findOrFail($id);
    }

    public function markAsRead(int $id): WellNotification
    {
        $notification = $this->model->findOrFail($id);
        $notification->update([
            'IS_READ' => true,
            'READ_AT' => now(),
        ]);
        return $notification->fresh();
    }

    public function markAllAsRead(): int
    {
        return $this->model
            ->where('IS_READ', false)
            ->update([
                'IS_READ' => true,
                'READ_AT' => now(),
            ]);
    }

    public function getLatestUnread(int $limit = 10): Collection
    {
        return $this->model
            ->with(['well', 'notificationType', 'inspector'])
            ->where('IS_READ', false)
            ->orderBy('CREATED_AT', 'desc')
            ->limit($limit)
            ->get();
    }
}
