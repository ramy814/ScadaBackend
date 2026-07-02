<?php

declare(strict_types=1);

namespace App\Repositories\Valves;

use App\Models\Valves\Valve;
use Illuminate\Database\Eloquent\Collection;

class ValveRepository
{
    public function __construct(
        private readonly Valve $model,
    ) {
    }

    public function all(): Collection
    {
        return $this->model->orderBy('W4_GENERAL_ID')->get();
    }

    public function findOrFail(int $id): Valve
    {
        return $this->model->findOrFail($id);
    }

    public function exists(int $id): bool
    {
        return $this->model->whereKey($id)->exists();
    }
}
