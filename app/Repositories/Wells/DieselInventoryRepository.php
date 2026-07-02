<?php

declare(strict_types=1);

namespace App\Repositories\Wells;

use App\Models\Wells\DieselInventory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class DieselInventoryRepository
{
    public function __construct(
        private readonly DieselInventory $model,
    ) {
    }

    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->with(['well', 'inspector'])->orderByDesc('INVENTORY_DATE');

        if (!empty($filters['well_oid'])) {
            $query->where('WELL_OID', $filters['well_oid']);
        }

        if (!empty($filters['inspector_oid'])) {
            $query->where('INSPECTOR_OID', $filters['inspector_oid']);
        }

        if (!empty($filters['generator_oid'])) {
            $query->where('GENERATOR_OID', $filters['generator_oid']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('INVENTORY_DATE', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('INVENTORY_DATE', '<=', $filters['date_to']);
        }

        return $query->paginate($perPage);
    }

    public function create(array $data): DieselInventory
    {
        return $this->model->create($data);
    }

    public function findOrFail(int $id): DieselInventory
    {
        return $this->model->with(['well', 'inspector'])->findOrFail($id);
    }

    public function findByWell(int $wellId): Collection
    {
        return $this->model->where('WELL_OID', $wellId)
            ->with(['well', 'inspector'])
            ->orderByDesc('INVENTORY_DATE')
            ->get();
    }

    public function findByInspector(int $inspectorId): Collection
    {
        return $this->model->where('INSPECTOR_OID', $inspectorId)
            ->with('well')
            ->orderByDesc('INVENTORY_DATE')
            ->get();
    }

    public function getRecentInventories(int $limit = 10): Collection
    {
        return $this->model->with(['well', 'inspector'])
            ->orderByDesc('INVENTORY_DATE')
            ->limit($limit)
            ->get();
    }

    public function getStats(): array
    {
        $total = $this->model->count();
        $today = $this->model->whereDate('INVENTORY_DATE', Carbon::today())->count();
        $thisMonth = $this->model->whereMonth('INVENTORY_DATE', Carbon::now()->month)
            ->whereYear('INVENTORY_DATE', Carbon::now()->year)
            ->count();

        $latestFuelBalance = $this->model->orderByDesc('INVENTORY_DATE')
            ->get()
            ->unique('WELL_OID')
            ->sum(fn (DieselInventory $inventory) => (float) $inventory->AVAILABLE_FUEL_LITERS);

        return [
            'total' => $total,
            'today' => $today,
            'this_month' => $thisMonth,
            'latest_available_fuel_liters' => round($latestFuelBalance, 2),
        ];
    }
}
