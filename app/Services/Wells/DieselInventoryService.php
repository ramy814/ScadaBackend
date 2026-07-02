<?php

declare(strict_types=1);

namespace App\Services\Wells;

use App\Models\Wells\DieselInventory;
use App\Repositories\Wells\DieselInventoryRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DieselInventoryService
{
    public function __construct(
        private readonly DieselInventoryRepository $dieselInventoryRepository,
    ) {
    }

    public function getPaginatedInventories(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->dieselInventoryRepository->paginate($perPage, $filters);
    }

    public function getInventory(int $id): DieselInventory
    {
        return $this->dieselInventoryRepository->findOrFail($id);
    }

    public function processInventory(array $data): DieselInventory
    {
        return DB::transaction(function () use ($data) {
            $data['SYNCED_AT'] = now();

            return $this->dieselInventoryRepository
                ->create($data)
                ->fresh(['well', 'inspector']);
        });
    }

    public function batchSync(array $inventories, int $inspectorId): array
    {
        $results = [];

        foreach ($inventories as $inventoryData) {
            $inventoryData['INSPECTOR_OID'] = $inspectorId;
            $results[] = $this->processInventory($inventoryData);
        }

        return $results;
    }

    public function getStatistics(): array
    {
        return $this->dieselInventoryRepository->getStats();
    }

    public function getRecentInventories(int $limit = 10): Collection
    {
        return $this->dieselInventoryRepository->getRecentInventories($limit);
    }
}
