<?php

declare(strict_types=1);

namespace App\Modules\Wells\Repositories;

use App\Modules\Wells\Models\WellActivityMedia;

class ActivityMediaRepository
{
    public function __construct(private readonly WellActivityMedia $model) {}

    public function findByClientLocalId(string $clientLocalId): ?WellActivityMedia
    {
        return $this->model
            ->where('CLIENT_LOCAL_ID', $clientLocalId)
            ->first();
    }

    public function findOrFail(int $id): WellActivityMedia
    {
        return $this->model->findOrFail($id);
    }

    public function create(array $data): WellActivityMedia
    {
        return $this->model->create($data);
    }
}
