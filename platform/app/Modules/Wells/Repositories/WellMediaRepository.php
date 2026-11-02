<?php

declare(strict_types=1);

namespace App\Modules\Wells\Repositories;

use App\Modules\Wells\Models\WellMedia;

class WellMediaRepository
{
    public function __construct(private readonly WellMedia $model) {}

    public function create(array $data): WellMedia
    {
        return $this->model->create($data);
    }

    public function findOrFail(int $id): WellMedia
    {
        return $this->model->findOrFail($id);
    }

    public function findForWellOrFail(int $wellId, int $id): WellMedia
    {
        return $this->model
            ->where('WELL_OID', $wellId)
            ->findOrFail($id);
    }

    public function delete(WellMedia $media): bool
    {
        return (bool) $media->delete();
    }
}
