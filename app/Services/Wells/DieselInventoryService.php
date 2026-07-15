<?php

declare(strict_types=1);

namespace App\Services\Wells;

use App\Models\Wells\DieselInventory;
use App\Repositories\Wells\DieselInventoryRepository;
use App\Repositories\Wells\GeneratorRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DieselInventoryService
{
    public function __construct(
        private readonly DieselInventoryRepository $dieselInventoryRepository,
        private readonly DieselFuelCalculator $fuelCalculator,
        private readonly GeneratorRepository $generatorRepository,
    ) {}

    public function getPaginatedInventories(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $paginator = $this->dieselInventoryRepository->paginate($perPage, $filters);
        $inventories = $paginator->getCollection();
        $previousReadings = $this->dieselInventoryRepository->previousReadingsFor($inventories);

        $inventories->each(function (DieselInventory $inventory) use ($previousReadings): void {
            $previous = $previousReadings->get((int) $inventory->getKey());
            $well = $inventory->well;

            $inventory->setAttribute(
                'CALCULATED_AVAILABLE_FUEL_LITERS',
                $well
                    ? $this->fuelCalculator->litersForWell($well, $inventory->getAttribute('diesel_height_cm'))
                    : null,
            );
            $inventory->setAttribute(
                'PREVIOUS_AVAILABLE_FUEL_LITERS',
                $well && $previous
                    ? $this->fuelCalculator->litersForWell($well, $previous['diesel_height_cm'])
                        ?? $previous['available_fuel_liters']
                    : null,
            );
            $inventory->setAttribute('PREVIOUS_DIESEL_HEIGHT_CM', $previous['diesel_height_cm'] ?? null);
        });

        return $paginator;
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
        $statistics = $this->dieselInventoryRepository->getStats();
        $latestFuelBalance = $this->dieselInventoryRepository
            ->getLatestInventoryForEachWell()
            ->sum(function (DieselInventory $inventory): float {
                $calculated = $inventory->well
                    ? $this->fuelCalculator->litersForWell(
                        $inventory->well,
                        $inventory->getAttribute('diesel_height_cm'),
                    )
                    : null;

                return $calculated ?? (float) $inventory->getAttribute('available_fuel_liters');
            });

        $statistics['latest_available_fuel_liters'] = round($latestFuelBalance, 2);

        return $statistics;
    }

    public function getRecentInventories(int $limit = 10): Collection
    {
        return $this->dieselInventoryRepository->getRecentInventories($limit);
    }

    public function getInventoryReport(array $filters = []): array
    {
        $inventories = $this->dieselInventoryRepository->getReportInventories($filters);
        $previousReadings = $this->dieselInventoryRepository->previousReadingsFor($inventories);
        $wellIds = $inventories
            ->map(fn (DieselInventory $inventory): mixed => $this->attribute($inventory, 'WELL_OID'))
            ->filter()
            ->all();
        $explicitGeneratorIds = $inventories
            ->map(fn (DieselInventory $inventory): mixed => $this->attribute($inventory, 'GENERATOR_OID'))
            ->filter()
            ->all();
        $generators = $this->generatorRepository->getReportGenerators($wellIds, $explicitGeneratorIds);

        $resolvedGeneratorIds = $inventories->map(function (DieselInventory $inventory) use ($generators): mixed {
            $wellId = (int) $this->attribute($inventory, 'WELL_OID');
            $explicitId = $this->attribute($inventory, 'GENERATOR_OID');

            return $explicitId ?: ($generators['by_well'][$wellId]['oid'] ?? null);
        })->filter()->unique()->values()->all();

        $previousDates = $previousReadings
            ->pluck('inventory_date')
            ->filter()
            ->map(fn (mixed $date): Carbon => Carbon::parse($date));
        $currentDates = $inventories
            ->map(fn (DieselInventory $inventory): mixed => $this->attribute($inventory, 'INVENTORY_DATE'))
            ->filter()
            ->map(fn (mixed $date): Carbon => Carbon::parse($date));
        $deliveries = $previousDates->isNotEmpty() && $currentDates->isNotEmpty()
            ? collect($this->generatorRepository->getApprovedFuelDeliveries(
                $resolvedGeneratorIds,
                $previousDates->sort()->first(),
                $currentDates->sortDesc()->first(),
            ))->groupBy('generator_oid')
            : collect();

        $rows = $inventories->map(function (DieselInventory $inventory, int $index) use (
            $previousReadings,
            $generators,
            $deliveries,
        ): array {
            $well = $inventory->well;
            $wellId = (int) $this->attribute($inventory, 'WELL_OID');
            $explicitGeneratorId = $this->attribute($inventory, 'GENERATOR_OID');
            $generator = $explicitGeneratorId
                ? ($generators['by_id'][(int) $explicitGeneratorId] ?? null)
                : ($generators['by_well'][$wellId] ?? null);
            $generatorId = $generator['oid'] ?? null;
            $previous = $previousReadings->get((int) $inventory->getKey(), []);
            $currentDate = Carbon::parse($this->attribute($inventory, 'INVENTORY_DATE'));
            $previousDate = empty($previous['inventory_date']) ? null : Carbon::parse($previous['inventory_date']);
            $currentHours = $this->numeric($this->attribute($inventory, 'GENERATOR_HOURS_READING'));
            $previousHours = $this->numeric($previous['generator_hours_reading'] ?? null);
            $operatingHours = $currentHours !== null && $previousHours !== null && $currentHours >= $previousHours
                ? round($currentHours - $previousHours, 2)
                : null;
            $currentHeight = $this->numeric($this->attribute($inventory, 'DIESEL_HEIGHT_CM'));
            $previousHeight = $this->numeric($previous['diesel_height_cm'] ?? null);
            $currentBalance = $well
                ? $this->fuelCalculator->litersForWell($well, $currentHeight)
                    ?? $this->numeric($this->attribute($inventory, 'AVAILABLE_FUEL_LITERS'))
                : $this->numeric($this->attribute($inventory, 'AVAILABLE_FUEL_LITERS'));
            $previousBalance = $well
                ? $this->fuelCalculator->litersForWell($well, $previousHeight)
                    ?? $this->numeric($previous['available_fuel_liters'] ?? null)
                : $this->numeric($previous['available_fuel_liters'] ?? null);
            $suppliedFuel = $previousDate && $generatorId
                ? $this->suppliedFuelBetween(
                    $deliveries->get((int) $generatorId, collect()),
                    $previousDate,
                    $currentDate,
                )
                : null;
            $consumedFuel = $previousBalance !== null && $currentBalance !== null
                ? round($previousBalance + ($suppliedFuel ?? 0) - $currentBalance, 2)
                : null;
            $notes = array_filter([(string) ($this->attribute($inventory, 'NOTES') ?? '')]);

            if ($currentHours !== null && $previousHours !== null && $currentHours < $previousHours) {
                $notes[] = 'قراءة عداد المولد الحالية أقل من القراءة السابقة';
            }

            if ($consumedFuel !== null && $consumedFuel < 0) {
                $notes[] = 'الرصيد الحالي أعلى من الرصيد السابق والتوريد المسجل';
                $consumedFuel = null;
            }

            if (! $generator) {
                $notes[] = 'لم يتم تحديد مولد واحد لسجل الجرد';
            }

            $fuelConsumptionRate = $this->numeric($generator['fuel_consumption_lph'] ?? null);
            $wellCapacity = $this->numeric($this->attribute($well, 'CAPACITY'));

            return [
                'index' => $index + 1,
                'inventory_date' => $currentDate,
                'well_name' => $this->attribute($well, 'NAME') ?? '-',
                'official_well_name' => $this->attribute($well, 'SOURCE_WELL_CODE')
                    ?? $this->attribute($well, 'WELL_NUM')
                    ?? '-',
                'generator_name' => $generator['name'] ?? '-',
                'generator_number' => $generator['number'] ?? '-',
                'generator_capacity_kva' => $this->numeric($generator['capacity_kva'] ?? null),
                'generator_fuel_consumption_lph' => $fuelConsumptionRate,
                'well_capacity_m3h' => $wellCapacity,
                'previous_generator_reading' => $previousHours,
                'current_generator_reading' => $currentHours,
                'operating_hours' => $operatingHours,
                'previous_diesel_height_cm' => $previousHeight,
                'current_diesel_height_cm' => $currentHeight,
                'supplied_fuel_liters' => $suppliedFuel,
                'previous_fuel_balance_liters' => $previousBalance,
                'current_fuel_balance_liters' => $currentBalance,
                'consumed_fuel_liters' => $consumedFuel,
                'actual_consumption_lph' => $consumedFuel !== null && $operatingHours > 0
                    ? round($consumedFuel / $operatingHours, 2)
                    : null,
                'tank_capacity_liters' => $well ? $this->fuelCalculator->tankCapacityLiters($well) : null,
                'water_production_m3' => $operatingHours !== null && $wellCapacity !== null
                    ? round($operatingHours * $wellCapacity, 2)
                    : null,
                'calculated_diesel_consumption_liters' => $operatingHours !== null && $fuelConsumptionRate !== null
                    ? round($operatingHours * $fuelConsumptionRate, 2)
                    : null,
                'inspector_name' => $this->attribute($inventory->inspector, 'NAME') ?? '-',
                'notes' => implode(' — ', $notes),
            ];
        })->values();

        return [
            'filters' => $filters,
            'rows' => $rows,
            'total_operating_hours' => $rows->sum('operating_hours'),
            'total_supplied_fuel_liters' => $rows->sum('supplied_fuel_liters'),
            'total_consumed_fuel_liters' => $rows->sum('consumed_fuel_liters'),
            'total_water_production_m3' => $rows->sum('water_production_m3'),
            'total_calculated_diesel_consumption_liters' => $rows->sum('calculated_diesel_consumption_liters'),
            'generated_at' => now(),
        ];
    }

    private function suppliedFuelBetween(iterable $deliveries, Carbon $from, Carbon $to): float
    {
        return round(collect($deliveries)->sum(function (array $delivery) use ($from, $to): float {
            $suppliedAt = Carbon::parse($delivery['supplied_at']);

            return $suppliedAt->greaterThan($from) && $suppliedAt->lessThanOrEqualTo($to)
                ? (float) $delivery['liters']
                : 0.0;
        }), 2);
    }

    private function numeric(mixed $value): ?float
    {
        return $value === null || $value === '' || ! is_numeric($value) ? null : (float) $value;
    }

    private function attribute(?object $model, string $key): mixed
    {
        if (! $model || ! method_exists($model, 'getAttribute')) {
            return null;
        }

        return $model->getAttribute($key) ?? $model->getAttribute(strtolower($key));
    }
}
