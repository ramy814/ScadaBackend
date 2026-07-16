<?php

declare(strict_types=1);

namespace App\Services\Valves;

use App\Models\Valves\ValveInspector;
use App\Repositories\Valves\ValveRepository;
use Illuminate\Database\Eloquent\Collection;

class ValveService
{
    public function __construct(
        private readonly ValveRepository $valveRepository,
    ) {
    }

    public function getAllValves(): Collection
    {
        return $this->valveRepository->all();
    }

    public function getAssignedValves(?ValveInspector $inspector, array $filters = []): Collection
    {
        return $this->valveRepository->assignedToInspector($inspector, $filters);
    }
}
