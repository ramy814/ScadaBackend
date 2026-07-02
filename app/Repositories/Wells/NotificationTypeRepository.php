<?php

declare(strict_types=1);

namespace App\Repositories\Wells;

use App\Models\Wells\NotificationType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class NotificationTypeRepository
{
    public function __construct(
        private readonly NotificationType $model
    ) {
    }

    public function all(): Collection
    {
        return $this->model->newQuery()->orderBy('PRIORITY')->get();
    }

    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return $this->model->newQuery()->orderBy('PRIORITY')->paginate($perPage);
    }

    public function findOrFail(int $id): NotificationType
    {
        return $this->model->findOrFail($id);
    }

    public function create(array $data): NotificationType
    {
        return $this->model->create($data);
    }

    public function update(int $id, array $data): NotificationType
    {
        $this->model->findOrFail($id);

        DB::connection('oracle')->table('notification_types')
            ->where('OID', $id)
            ->update([
                'NAME' => $data['NAME'],
                'ICON' => $data['ICON'] ?? $data['EMOJI'] ?? 'bell',
                'EMOJI' => $data['EMOJI'] ?? null,
                'COLOR' => $data['COLOR'] ?? null,
                'PRIORITY' => $data['PRIORITY'],
                'IS_ACTIVE' => filter_var($data['IS_ACTIVE'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
                'UPDATED_AT' => now(),
            ]);

        return $this->findOrFail($id);
    }

    public function delete(int $id): bool
    {
        return (bool) $this->model->findOrFail($id)->delete();
    }

    public function hasNotifications(int $id): bool
    {
        $type = $this->model->findOrFail($id);
        return $type->hasBeenUsed();
    }

    public function getActive(): Collection
    {
        return $this->model->active()->orderBy('PRIORITY')->get();
    }
}
