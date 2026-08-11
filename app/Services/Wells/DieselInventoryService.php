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
    private const REPORT_COLUMNS = [
        'internal' => [
            ['key' => 'previous_fill_date', 'label' => 'تاريخ التعبئة السابقة', 'type' => 'date', 'default' => true],
            ['key' => 'current_fill_date', 'label' => 'تاريخ التعبئة الحالية', 'type' => 'date', 'default' => true],
            ['key' => 'well_name', 'label' => 'اسم البئر', 'type' => 'text', 'default' => true],
            ['key' => 'generator_number', 'label' => 'رقم المولد', 'type' => 'text', 'default' => true],
            ['key' => 'previous_generator_reading', 'label' => 'قراءة المولد السابقة', 'type' => 'number', 'default' => true],
            ['key' => 'current_generator_reading', 'label' => 'قراءة المولد الحالية', 'type' => 'number', 'default' => true],
            ['key' => 'operating_hours', 'label' => 'فرق ساعات التشغيل', 'type' => 'number', 'default' => true],
            ['key' => 'supplied_fuel_liters', 'label' => 'كمية التعبئة (لتر)', 'type' => 'number', 'default' => true],
            ['key' => 'actual_consumption_lph', 'label' => 'معدل الاستهلاك (لتر/ساعة)', 'type' => 'number', 'default' => true],
            ['key' => 'nominal_consumption_lph', 'label' => 'المعدل المعتمد', 'type' => 'number', 'default' => false],
            ['key' => 'consumption_tolerance_lph', 'label' => 'هامش الاستهلاك', 'type' => 'number', 'default' => false],
            ['key' => 'consumption_limit_lph', 'label' => 'الحد الأعلى', 'type' => 'number', 'default' => false],
            ['key' => 'consumption_status', 'label' => 'فحص الاستهلاك', 'type' => 'status', 'default' => true],
            ['key' => 'notes', 'label' => 'ملاحظات', 'type' => 'text', 'default' => false],
        ],
        'external_inventory' => [
            ['key' => 'created_at', 'label' => 'تاريخ الإدخال', 'type' => 'date', 'default' => true],
            ['key' => 'previous_inventory_date', 'label' => 'تاريخ القراءة السابقة', 'type' => 'date', 'default' => true],
            ['key' => 'inventory_date', 'label' => 'تاريخ القراءة الحالية', 'type' => 'date', 'default' => true],
            ['key' => 'well_name', 'label' => 'اسم البئر', 'type' => 'text', 'default' => true],
            ['key' => 'inspector_name', 'label' => 'المفتش', 'type' => 'text', 'default' => true],
            ['key' => 'generator_number', 'label' => 'رقم المولد', 'type' => 'text', 'default' => false],
            ['key' => 'previous_generator_reading', 'label' => 'قراءة المولد السابقة', 'type' => 'number', 'default' => true],
            ['key' => 'current_generator_reading', 'label' => 'قراءة المولد الحالية', 'type' => 'number', 'default' => true],
            ['key' => 'previous_diesel_height_cm', 'label' => 'ارتفاع السولار السابق (سم)', 'type' => 'number', 'default' => true],
            ['key' => 'current_diesel_height_cm', 'label' => 'ارتفاع السولار الحالي (سم)', 'type' => 'number', 'default' => true],
            ['key' => 'supplied_fuel_liters', 'label' => 'كمية توريد السولار (لتر)', 'type' => 'number', 'default' => true],
            ['key' => 'tank_capacity_liters', 'label' => 'حجم الخزان (لتر)', 'type' => 'number', 'default' => true],
            ['key' => 'notes', 'label' => 'ملاحظات', 'type' => 'text', 'default' => false],
        ],
        'external_results' => [
            ['key' => 'created_at', 'label' => 'تاريخ الإدخال', 'type' => 'date', 'default' => true],
            ['key' => 'well_name', 'label' => 'اسم البئر', 'type' => 'text', 'default' => true],
            ['key' => 'generator_number', 'label' => 'رقم المولد', 'type' => 'text', 'default' => true],
            ['key' => 'operating_hours', 'label' => 'فرق ساعات تشغيل المولد', 'type' => 'number', 'default' => true],
            ['key' => 'diesel_height_difference_cm', 'label' => 'فرق ارتفاع السولار (سم)', 'type' => 'number', 'default' => true],
            ['key' => 'current_fuel_balance_liters', 'label' => 'رصيد السولار المحتسب (لتر)', 'type' => 'number', 'default' => true],
            ['key' => 'consumed_fuel_liters', 'label' => 'السولار المستهلك (لتر)', 'type' => 'number', 'default' => false],
            ['key' => 'actual_consumption_lph', 'label' => 'معدل الاستهلاك (لتر/ساعة)', 'type' => 'number', 'default' => true],
            ['key' => 'nominal_consumption_lph', 'label' => 'المعدل المعتمد', 'type' => 'number', 'default' => false],
            ['key' => 'consumption_tolerance_lph', 'label' => 'هامش الاستهلاك', 'type' => 'number', 'default' => false],
            ['key' => 'consumption_limit_lph', 'label' => 'الحد الأعلى', 'type' => 'number', 'default' => false],
            ['key' => 'consumption_status', 'label' => 'فحص معدل الاستهلاك', 'type' => 'status', 'default' => true],
            ['key' => 'notes', 'label' => 'ملاحظات', 'type' => 'text', 'default' => false],
        ],
    ];

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
        $generators = $this->generatorRepository->getReportGenerators(
            $inventories
                ->map(fn (DieselInventory $inventory): mixed => $this->attribute($inventory, 'WELL_OID'))
                ->filter()
                ->all(),
            $inventories
                ->map(fn (DieselInventory $inventory): mixed => $this->attribute($inventory, 'GENERATOR_OID'))
                ->filter()
                ->all(),
        );

        $inventories->each(function (DieselInventory $inventory) use ($previousReadings, $generators): void {
            $previous = $previousReadings->get((int) $inventory->getKey());
            $well = $inventory->well;
            $wellId = (int) $this->attribute($inventory, 'WELL_OID');
            $explicitGeneratorId = $this->attribute($inventory, 'GENERATOR_OID');
            $generator = $explicitGeneratorId
                ? ($generators['by_id'][(int) $explicitGeneratorId] ?? null)
                : ($generators['by_well'][$wellId] ?? null);

            $inventory->setAttribute(
                'CALCULATED_AVAILABLE_FUEL_LITERS',
                $well
                    ? $this->fuelCalculator->litersForWell($well, $inventory->getAttribute('diesel_height_cm'))
                    : null,
            );
            $inventory->setAttribute('RESOLVED_GENERATOR', $generator);
            $inventory->setAttribute(
                'PREVIOUS_GENERATOR_HOURS_READING',
                $previous['generator_hours_reading'] ?? null,
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

                return $calculated ?? 0.0;
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
        $inventories = $this->dieselInventoryRepository->getReportInventories(
            $filters,
            5000,
            'external',
        );
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

        $resolvedGeneratorIds = $inventories
            ->map(fn (DieselInventory $inventory): mixed => $this->resolveGenerator(
                $inventory,
                $generators,
            )['oid'] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();

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
            $generator = $this->resolveGenerator($inventory, $generators);
            $generatorId = $generator['oid'] ?? null;
            $previous = $previousReadings->get((int) $inventory->getKey(), []);
            $currentDate = Carbon::parse($this->attribute($inventory, 'INVENTORY_DATE'));
            $previousDate = empty($previous['inventory_date']) ? null : Carbon::parse($previous['inventory_date']);
            $currentHours = $this->numeric($this->attribute($inventory, 'GENERATOR_HOURS_READING'));
            $previousHours = $this->numeric($previous['generator_hours_reading'] ?? null);
            $interval = $this->meterInterval(
                $previousHours,
                $currentHours,
                $previousDate,
                $currentDate,
            );
            $operatingHours = $interval['hours'];
            $currentHeight = $this->numeric($this->attribute($inventory, 'DIESEL_HEIGHT_CM'));
            $previousHeight = $this->numeric($previous['diesel_height_cm'] ?? null);
            $currentBalance = $well
                ? $this->fuelCalculator->litersForWell($well, $currentHeight)
                : null;
            $previousBalance = $well
                ? $this->fuelCalculator->litersForWell($well, $previousHeight)
                : null;
            $suppliedFuel = $previousDate && $generatorId
                ? $this->suppliedFuelBetween(
                    $deliveries->get((int) $generatorId, collect()),
                    $previousDate,
                    $currentDate,
                    $previousHours,
                    $currentHours,
                )
                : null;
            $consumedFuel = $previousBalance !== null
                && $currentBalance !== null
                && $suppliedFuel !== null
                ? round($previousBalance + $suppliedFuel - $currentBalance, 2)
                : null;
            $notes = array_filter([(string) ($this->attribute($inventory, 'NOTES') ?? '')]);
            $calculationError = $interval['error'];
            $previousInventoryUnavailableReason = $previousDate
                ? null
                : 'لا توجد قراءة جرد أقدم زمنياً لهذا البئر';
            $suppliedFuelUnavailableReason = $suppliedFuel !== null
                ? null
                : ($previousInventoryUnavailableReason
                    ? 'تعذر احتساب التوريد لعدم وجود قراءة جرد أقدم زمنياً لهذا البئر'
                    : 'تعذر احتساب التوريد لعدم تحديد مولد واحد للسجل');

            if ($interval['error']) {
                $notes[] = $interval['error'];
            }

            if ($previousInventoryUnavailableReason) {
                $notes[] = $suppliedFuelUnavailableReason;
            }

            if ($currentBalance === null) {
                $notes[] = 'تعذر احتساب الرصيد الحالي لعدم اكتمال بيانات الخزان أو ارتفاع السولار';
            }

            if ($previousDate && $previousBalance === null) {
                $notes[] = 'تعذر احتساب الرصيد السابق لعدم اكتمال بيانات الخزان أو الارتفاع السابق';
            }

            if ($consumedFuel !== null && $consumedFuel < 0) {
                $balanceError = 'الرصيد الحالي أعلى من الرصيد السابق والتوريد المسجل';
                $calculationError ??= $balanceError;
                $notes[] = $balanceError;
                $consumedFuel = null;
            }

            if (! $generator) {
                $notes[] = 'لم يتم تحديد مولد واحد لسجل الجرد';
            }

            $fuelConsumptionRate = $this->numeric(
                $generator['configured_fuel_consumption_lph']
                ?? $generator['fuel_consumption_lph']
                ?? null,
            );
            $consumptionTolerance = $this->numeric(
                $generator['consumption_tolerance_lph'] ?? null,
            ) ?? 0.0;
            $wellCapacity = $this->numeric($this->attribute($well, 'CAPACITY'));
            $actualConsumption = $consumedFuel !== null && $operatingHours !== null && $operatingHours > 0
                ? round($consumedFuel / $operatingHours, 2)
                : null;
            $assessment = $this->consumptionAssessment(
                $actualConsumption,
                $fuelConsumptionRate,
                $consumptionTolerance,
                $calculationError,
            );

            return [
                'index' => $index + 1,
                'created_at' => $inventory->getAttributes()['created_at']
                    ?? $inventory->getAttributes()['CREATED_AT']
                    ?? null,
                'inventory_date' => $currentDate,
                'previous_inventory_date' => $previousDate,
                'previous_inventory_unavailable_reason' => $previousInventoryUnavailableReason,
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
                'diesel_height_difference_cm' => $previousHeight !== null && $currentHeight !== null
                    ? round($previousHeight - $currentHeight, 2)
                    : null,
                'supplied_fuel_liters' => $suppliedFuel,
                'supplied_fuel_unavailable_reason' => $suppliedFuelUnavailableReason,
                'previous_fuel_balance_liters' => $previousBalance,
                'current_fuel_balance_liters' => $currentBalance,
                'consumed_fuel_liters' => $consumedFuel,
                'actual_consumption_lph' => $actualConsumption,
                'nominal_consumption_lph' => $fuelConsumptionRate,
                'consumption_tolerance_lph' => $consumptionTolerance,
                'consumption_limit_lph' => $assessment['limit'],
                'consumption_status' => $assessment,
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
            'high_consumption_count' => $rows->where('consumption_status.key', 'high')->count(),
            'totals' => [
                'operating_hours' => $rows->sum('operating_hours'),
                'supplied_fuel_liters' => $rows->sum('supplied_fuel_liters'),
                'consumed_fuel_liters' => $rows->sum('consumed_fuel_liters'),
                'water_production_m3' => $rows->sum('water_production_m3'),
                'calculated_diesel_consumption_liters' => $rows->sum(
                    'calculated_diesel_consumption_liters',
                ),
            ],
            'generated_at' => now(),
        ];
    }

    public function getInternalTankReport(array $filters = []): array
    {
        $rows = collect($this->generatorRepository->getDailyTankFuelEntries($filters))
            ->map(function (array $entry, int $index): array {
                $currentDate = Carbon::parse($entry['fill_up_date']);
                $previousDate = empty($entry['previous_fill_up_date'])
                    ? null
                    : Carbon::parse($entry['previous_fill_up_date']);
                $currentReading = $this->numeric($entry['current_reading'] ?? null);
                $previousReading = $this->numeric($entry['previous_current_reading'] ?? null);
                $interval = $this->meterInterval(
                    $previousReading,
                    $currentReading,
                    $previousDate,
                    $currentDate,
                );
                $suppliedFuel = $this->numeric($entry['approved_gallons'] ?? null);
                $actualConsumption = $suppliedFuel !== null
                    && $interval['hours'] !== null
                    && $interval['hours'] > 0
                        ? round($suppliedFuel / $interval['hours'], 2)
                        : null;
                $nominalConsumption = $this->numeric(
                    $entry['fuel_consumption_lph']
                    ?? $entry['fuel_consumption']
                    ?? null,
                );
                $tolerance = $this->numeric(
                    $entry['consumption_tolerance_lph'] ?? null,
                ) ?? 0.0;
                $assessment = $this->consumptionAssessment(
                    $actualConsumption,
                    $nominalConsumption,
                    $tolerance,
                    $interval['error'],
                );
                $notes = array_filter([
                    $interval['error'],
                    $previousDate === null ? 'لا توجد تعبئة سابقة معتمدة' : null,
                    $previousDate !== null && $previousReading === null
                        ? 'قراءة عداد المولد في التعبئة السابقة مفقودة أو غير رقمية'
                        : null,
                    $currentReading === null
                        ? 'قراءة عداد المولد في التعبئة الحالية مفقودة أو غير رقمية'
                        : null,
                    $nominalConsumption === null ? 'معدل استهلاك المولد غير مدخل' : null,
                ]);

                return [
                    'index' => $index + 1,
                    'row_id' => (int) ($entry['fuel_detail_oid'] ?? 0),
                    'previous_fill_date' => $previousDate,
                    'current_fill_date' => $currentDate,
                    'well_name' => $entry['well_name'] ?? '-',
                    'official_well_name' => $entry['well_num'] ?? '-',
                    'generator_name' => $entry['generator_name'] ?? '-',
                    'generator_number' => $entry['generator_num'] ?? '-',
                    'previous_generator_reading' => $previousReading,
                    'current_generator_reading' => $currentReading,
                    'operating_hours' => $interval['hours'],
                    'supplied_fuel_liters' => $suppliedFuel,
                    'actual_consumption_lph' => $actualConsumption,
                    'nominal_consumption_lph' => $nominalConsumption,
                    'consumption_tolerance_lph' => $tolerance,
                    'consumption_limit_lph' => $assessment['limit'],
                    'consumption_status' => $assessment,
                    'notes' => implode(' — ', $notes),
                ];
            })
            ->values();

        return [
            'filters' => $filters,
            'rows' => $rows,
            'total_operating_hours' => $rows->sum('operating_hours'),
            'total_supplied_fuel_liters' => $rows->sum('supplied_fuel_liters'),
            'high_consumption_count' => $rows->where('consumption_status.key', 'high')->count(),
            'totals' => [
                'operating_hours' => $rows->sum('operating_hours'),
                'supplied_fuel_liters' => $rows->sum('supplied_fuel_liters'),
            ],
            'generated_at' => now(),
        ];
    }

    public function getReportColumns(): array
    {
        return self::REPORT_COLUMNS;
    }

    public function getExportReport(
        string $reportType,
        array $filters = [],
        array $requestedColumns = [],
    ): array {
        $reportType = array_key_exists($reportType, self::REPORT_COLUMNS)
            ? $reportType
            : 'external_results';
        $report = $reportType === 'internal'
            ? $this->getInternalTankReport($filters)
            : $this->getInventoryReport($filters);

        return array_merge($report, [
            'report_type' => $reportType,
            'title' => match ($reportType) {
                'internal' => 'تقرير مولدات الخزان الداخلي',
                'external_inventory' => 'تقرير جرد مولدات الخزان الخارجي',
                default => 'تقرير نتائج جرد مولدات الخزان الخارجي',
            },
            'columns' => $this->selectedColumns($reportType, $requestedColumns),
        ]);
    }

    public function getUnclassifiedWellCount(): int
    {
        return $this->dieselInventoryRepository->countUnclassifiedWells();
    }

    private function suppliedFuelBetween(
        iterable $deliveries,
        Carbon $from,
        Carbon $to,
        ?float $previousMeterReading,
        ?float $currentMeterReading,
    ): float {
        $hasValidMeterRange = $previousMeterReading !== null
            && $currentMeterReading !== null
            && $currentMeterReading >= $previousMeterReading;

        return round(collect($deliveries)->sum(function (array $delivery) use (
            $from,
            $to,
            $previousMeterReading,
            $currentMeterReading,
            $hasValidMeterRange,
        ): float {
            $deliveryMeterReading = $this->numeric($delivery['meter_reading'] ?? null);

            if ($hasValidMeterRange && $deliveryMeterReading !== null) {
                return $deliveryMeterReading > $previousMeterReading
                    && $deliveryMeterReading <= $currentMeterReading
                    ? (float) $delivery['liters']
                    : 0.0;
            }

            $suppliedAt = Carbon::parse($delivery['supplied_at']);
            $enteredAt = empty($delivery['entered_at'])
                ? null
                : Carbon::parse($delivery['entered_at']);
            $effectiveSuppliedAt = $enteredAt?->isSameDay($suppliedAt)
                ? $enteredAt
                : $suppliedAt;

            return $effectiveSuppliedAt->greaterThan($from) && $effectiveSuppliedAt->lessThanOrEqualTo($to)
                ? (float) $delivery['liters']
                : 0.0;
        }), 2);
    }

    /**
     * @return array{hours: ?float, error: ?string}
     */
    private function meterInterval(
        ?float $previousReading,
        ?float $currentReading,
        ?Carbon $previousDate,
        Carbon $currentDate,
    ): array {
        if ($previousReading === null || $currentReading === null || $previousDate === null) {
            return ['hours' => null, 'error' => null];
        }

        if ($currentDate->lessThanOrEqualTo($previousDate)) {
            return [
                'hours' => null,
                'error' => 'وقت التعبئة الحالية غير مرتب بعد وقت التعبئة السابقة',
            ];
        }

        if ($currentReading < $previousReading) {
            return [
                'hours' => null,
                'error' => 'قراءة عداد المولد الحالية أقل من القراءة السابقة',
            ];
        }

        $operatingHours = round($currentReading - $previousReading, 2);

        if ($operatingHours <= 0) {
            return ['hours' => null, 'error' => 'لا يوجد فرق موجب بين قراءتي عداد المولد'];
        }

        $elapsedHours = $previousDate->diffInSeconds($currentDate) / 3600;

        if ($operatingHours > $elapsedHours + 1.0) {
            return [
                'hours' => null,
                'error' => 'فرق عداد المولد أكبر من الزمن الفعلي بين القراءتين',
            ];
        }

        return ['hours' => $operatingHours, 'error' => null];
    }

    /**
     * @return array{key: string, label: string, reason: string, limit: ?float}
     */
    private function consumptionAssessment(
        ?float $actual,
        ?float $nominal,
        float $tolerance,
        ?string $invalidReason = null,
    ): array {
        if ($invalidReason) {
            return [
                'key' => 'invalid',
                'label' => 'بيانات غير صالحة',
                'reason' => $invalidReason,
                'limit' => $nominal === null ? null : round($nominal + max(0.0, $tolerance), 2),
            ];
        }

        if ($actual === null || $nominal === null) {
            return [
                'key' => 'unavailable',
                'label' => 'بيانات غير مكتملة',
                'reason' => $actual === null
                    ? 'تعذر احتساب معدل الاستهلاك'
                    : 'معدل استهلاك المولد غير مدخل',
                'limit' => $nominal === null ? null : round($nominal + max(0.0, $tolerance), 2),
            ];
        }

        $limit = round($nominal + max(0.0, $tolerance), 2);
        $high = $actual > $limit;

        return [
            'key' => $high ? 'high' : 'normal',
            'label' => $high ? 'استهلاك مرتفع' : 'ضمن المعدل',
            'reason' => $high
                ? "المعدل الفعلي {$actual} أعلى من الحد {$limit} لتر/ساعة"
                : "المعدل الفعلي لا يتجاوز الحد {$limit} لتر/ساعة",
            'limit' => $limit,
        ];
    }

    private function selectedColumns(string $reportType, array $requested): array
    {
        $available = collect(self::REPORT_COLUMNS[$reportType]);
        $allowedKeys = $available->pluck('key');
        $requested = collect($requested)
            ->filter(fn (mixed $key): bool => is_string($key) && $allowedKeys->contains($key))
            ->unique()
            ->values();

        if ($requested->isEmpty()) {
            return $available->where('default', true)->values()->all();
        }

        return $requested
            ->map(fn (string $key): array => $available->firstWhere('key', $key))
            ->filter()
            ->values()
            ->all();
    }

    private function resolveGenerator(DieselInventory $inventory, array $generators): ?array
    {
        $wellId = (int) $this->attribute($inventory, 'WELL_OID');
        $explicitGeneratorId = $this->attribute($inventory, 'GENERATOR_OID');

        if ($explicitGeneratorId) {
            return $generators['by_well_generator'][$wellId][(int) $explicitGeneratorId]
                ?? $generators['by_id'][(int) $explicitGeneratorId]
                ?? null;
        }

        return $generators['by_well'][$wellId] ?? null;
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
