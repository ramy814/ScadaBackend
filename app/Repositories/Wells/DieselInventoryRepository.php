<?php

declare(strict_types=1);

namespace App\Repositories\Wells;

use App\Models\Wells\DieselInventory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

class DieselInventoryRepository
{
    public function __construct(
        private readonly DieselInventory $model,
    ) {}

    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->with($this->reportRelations())->orderByDesc('INVENTORY_DATE');

        $this->applyFilters($query, $filters);

        return $query->paginate($perPage)->withQueryString();
    }

    public function getReportInventories(array $filters = [], int $limit = 5000): Collection
    {
        $query = $this->model
            ->with($this->reportRelations())
            ->orderBy('INVENTORY_DATE')
            ->orderBy('WELL_OID')
            ->orderBy('OID');

        $this->applyFilters($query, $filters);

        return $query->limit($limit)->get();
    }

    public function create(array $data): DieselInventory
    {
        return $this->model->create($data);
    }

    /**
     * Return the preceding inventory reading for each supplied inventory.
     *
     * @param  Collection<int, DieselInventory>  $inventories
     * @return SupportCollection<int, array{diesel_height_cm: mixed, available_fuel_liters: mixed, generator_hours_reading: mixed, inventory_date: mixed}>
     */
    public function previousReadingsFor(Collection $inventories): SupportCollection
    {
        $ids = $inventories->modelKeys();

        if ($ids === []) {
            return collect();
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $rows = $this->model->getConnection()->select(<<<SQL
            SELECT OID,
                   PREV_DIESEL_HEIGHT,
                   PREV_FUEL_LITERS,
                   PREV_GEN_HOURS,
                   PREV_INVENTORY_DATE
            FROM (
                SELECT OID,
                       LAG(DIESEL_HEIGHT_CM) OVER (
                           PARTITION BY WELL_OID ORDER BY INVENTORY_DATE, OID
                       ) AS PREV_DIESEL_HEIGHT,
                       LAG(AVAILABLE_FUEL_LITERS) OVER (
                           PARTITION BY WELL_OID ORDER BY INVENTORY_DATE, OID
                       ) AS PREV_FUEL_LITERS,
                       LAG(GENERATOR_HOURS_READING) OVER (
                           PARTITION BY WELL_OID ORDER BY INVENTORY_DATE, OID
                       ) AS PREV_GEN_HOURS,
                       LAG(INVENTORY_DATE) OVER (
                           PARTITION BY WELL_OID ORDER BY INVENTORY_DATE, OID
                       ) AS PREV_INVENTORY_DATE
                FROM DIESEL_INVENTORIES
            )
            WHERE OID IN ($placeholders)
        SQL, $ids);

        return collect($rows)->mapWithKeys(function (object $row): array {
            $values = array_change_key_case((array) $row, CASE_LOWER);

            return [(int) $values['oid'] => [
                'diesel_height_cm' => $values['prev_diesel_height'] ?? null,
                'available_fuel_liters' => $values['prev_fuel_liters'] ?? null,
                'generator_hours_reading' => $values['prev_gen_hours'] ?? null,
                'inventory_date' => $values['prev_inventory_date'] ?? null,
            ]];
        });
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

    public function getLatestInventoryForEachWell(): Collection
    {
        return $this->model->with('well')
            ->orderByDesc('INVENTORY_DATE')
            ->orderByDesc('OID')
            ->get()
            ->unique('well_oid')
            ->values();
    }

    /**
     * @param  array<int, int>  $wellIds
     */
    public function getLatestForWells(array $wellIds): SupportCollection
    {
        if ($wellIds === []) {
            return collect();
        }

        $placeholders = implode(', ', array_fill(0, count($wellIds), '?'));
        $rows = $this->model->getConnection()->select(<<<SQL
            SELECT WELL_OID,
                   INVENTORY_DATE,
                   GENERATOR_HOURS_READING,
                   DIESEL_HEIGHT_CM,
                   AVAILABLE_FUEL_LITERS
            FROM (
                SELECT WELL_OID,
                       INVENTORY_DATE,
                       GENERATOR_HOURS_READING,
                       DIESEL_HEIGHT_CM,
                       AVAILABLE_FUEL_LITERS,
                       ROW_NUMBER() OVER (
                           PARTITION BY WELL_OID ORDER BY INVENTORY_DATE DESC, OID DESC
                       ) AS ROW_NUM
                FROM DIESEL_INVENTORIES
                WHERE WELL_OID IN ($placeholders)
            )
            WHERE ROW_NUM = 1
        SQL, $wellIds);

        return collect($rows)->mapWithKeys(function (object $row): array {
            $values = array_change_key_case((array) $row, CASE_LOWER);

            return [(int) $values['well_oid'] => $values];
        });
    }

    public function getStats(): array
    {
        $total = $this->model->count();
        $today = $this->model->whereDate('INVENTORY_DATE', Carbon::today())->count();
        $thisMonth = $this->model->whereMonth('INVENTORY_DATE', Carbon::now()->month)
            ->whereYear('INVENTORY_DATE', Carbon::now()->year)
            ->count();

        return [
            'total' => $total,
            'today' => $today,
            'this_month' => $thisMonth,
        ];
    }

    private function applyFilters($query, array $filters): void
    {
        if (! empty($filters['well_oid'])) {
            $query->where('WELL_OID', $filters['well_oid']);
        }

        if (! empty($filters['inspector_oid'])) {
            $query->where('INSPECTOR_OID', $filters['inspector_oid']);
        }

        if (! empty($filters['generator_oid'])) {
            $query->where('GENERATOR_OID', $filters['generator_oid']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('INVENTORY_DATE', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }

        if (! empty($filters['date_to'])) {
            $query->where('INVENTORY_DATE', '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }
    }

    private function reportRelations(): array
    {
        return [
            'well' => function ($query): void {
                $query
                    ->select('wells.*', 'source_wells.SOURCE_WELL_CODE')
                    ->leftJoin(
                        DB::raw('DSS_CORE.WTR_WELLS source_wells'),
                        'source_wells.WELL_ID',
                        '=',
                        'wells.WELL_NUM',
                    );
            },
            'inspector',
        ];
    }
}
