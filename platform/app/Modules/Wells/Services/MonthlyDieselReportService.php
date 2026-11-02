<?php

declare(strict_types=1);

namespace App\Modules\Wells\Services;

use App\Modules\Wells\Models\DieselInventory;
use App\Modules\Wells\Repositories\DieselInventoryRepository;
use App\Modules\Wells\Repositories\GeneratorRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MonthlyDieselReportService
{
    private const REPORT_COLUMNS = [
        'internal' => [
            ['key' => 'well_name', 'label' => 'اسم البئر', 'type' => 'text', 'default' => true],
            ['key' => 'generator_number', 'label' => 'رقم المولد', 'type' => 'text', 'default' => true],
            ['key' => 'first_supply_date', 'label' => 'أول توريد في الشهر', 'type' => 'date', 'default' => true],
            ['key' => 'last_supply_date', 'label' => 'آخر توريد في الشهر', 'type' => 'date', 'default' => true],
            ['key' => 'supply_count', 'label' => 'عدد التوريدات', 'type' => 'number', 'default' => true],
            ['key' => 'first_generator_reading', 'label' => 'قراءة المولد عند أول توريد', 'type' => 'number', 'default' => true],
            ['key' => 'last_generator_reading', 'label' => 'قراءة المولد عند آخر توريد', 'type' => 'number', 'default' => true],
            ['key' => 'operating_hours', 'label' => 'ساعات التشغيل الشهرية', 'type' => 'number', 'default' => true],
            ['key' => 'monthly_supplied_fuel_liters', 'label' => 'السولار المورد خلال الشهر (لتر)', 'type' => 'number', 'default' => true],
            ['key' => 'consumed_fuel_liters', 'label' => 'السولار المستهلك خلال الشهر (لتر)', 'type' => 'number', 'default' => true],
            ['key' => 'actual_consumption_lph', 'label' => 'معدل الاستهلاك (لتر/ساعة)', 'type' => 'number', 'default' => true],
            ['key' => 'consumption_status', 'label' => 'فحص الاستهلاك', 'type' => 'status', 'default' => true],
            ['key' => 'notes', 'label' => 'ملاحظات', 'type' => 'text', 'default' => false],
        ],
        'external' => [
            ['key' => 'well_name', 'label' => 'اسم البئر', 'type' => 'text', 'default' => true],
            ['key' => 'generator_number', 'label' => 'رقم المولد', 'type' => 'text', 'default' => true],
            ['key' => 'first_inventory_date', 'label' => 'أول جرد في الشهر', 'type' => 'date', 'default' => true],
            ['key' => 'last_inventory_date', 'label' => 'آخر جرد في الشهر', 'type' => 'date', 'default' => true],
            ['key' => 'inventory_count', 'label' => 'عدد مرات الجرد', 'type' => 'number', 'default' => true],
            ['key' => 'first_generator_reading', 'label' => 'قراءة المولد في أول جرد', 'type' => 'number', 'default' => true],
            ['key' => 'last_generator_reading', 'label' => 'قراءة المولد في آخر جرد', 'type' => 'number', 'default' => true],
            ['key' => 'operating_hours', 'label' => 'ساعات التشغيل الشهرية', 'type' => 'number', 'default' => true],
            ['key' => 'first_fuel_balance_liters', 'label' => 'رصيد أول جرد (لتر)', 'type' => 'number', 'default' => true],
            ['key' => 'monthly_supplied_fuel_liters', 'label' => 'كل التوريد خلال الشهر (لتر)', 'type' => 'number', 'default' => true],
            ['key' => 'interval_supplied_fuel_liters', 'label' => 'التوريد بين أول وآخر جرد (لتر)', 'type' => 'number', 'default' => true],
            ['key' => 'last_fuel_balance_liters', 'label' => 'رصيد آخر جرد (لتر)', 'type' => 'number', 'default' => true],
            ['key' => 'consumed_fuel_liters', 'label' => 'السولار المستهلك خلال الشهر (لتر)', 'type' => 'number', 'default' => true],
            ['key' => 'actual_consumption_lph', 'label' => 'معدل الاستهلاك (لتر/ساعة)', 'type' => 'number', 'default' => true],
            ['key' => 'consumption_status', 'label' => 'فحص الاستهلاك', 'type' => 'status', 'default' => true],
            ['key' => 'notes', 'label' => 'ملاحظات', 'type' => 'text', 'default' => false],
        ],
    ];

    public function __construct(
        private readonly DieselInventoryRepository $dieselInventoryRepository,
        private readonly DieselFuelCalculator $fuelCalculator,
        private readonly GeneratorRepository $generatorRepository,
    ) {}

    public function getReport(string $tankGroup, string $month, array $filters = []): array
    {
        return $tankGroup === 'external'
            ? $this->getExternalReport($month, $filters)
            : $this->getInternalReport($month, $filters);
    }

    public function getInternalReport(string $month, array $filters = []): array
    {
        [$from, $to] = $this->monthPeriod($month);
        $reportFilters = $this->periodFilters($filters, $from, $to);
        $entries = collect($this->generatorRepository->getDailyTankFuelEntries($reportFilters));

        $rows = $entries
            ->groupBy(fn (array $entry): string => sprintf(
                '%d:%d',
                (int) ($entry['well_oid'] ?? 0),
                (int) ($entry['generator_oid'] ?? 0),
            ))
            ->values()
            ->map(function (Collection $group, int $index) use ($month): array {
                $ordered = $group->sortBy(fn (array $entry): string => sprintf(
                    '%s:%020d',
                    Carbon::parse($entry['fill_up_date'])->format('Y-m-d H:i:s'),
                    (int) ($entry['fuel_detail_oid'] ?? 0),
                ))->values();
                $first = $ordered->first();
                $last = $ordered->last();
                $firstDate = Carbon::parse($first['fill_up_date']);
                $lastDate = Carbon::parse($last['fill_up_date']);
                $firstReading = $this->numeric($first['current_reading'] ?? null);
                $lastReading = $this->numeric($last['current_reading'] ?? null);
                $interval = $this->monthlyMeterInterval(
                    $firstReading,
                    $lastReading,
                    $firstDate,
                    $lastDate,
                    $ordered->count(),
                    'توريدين',
                );
                $supplied = round($ordered->sum(
                    fn (array $entry): float => $this->numeric($entry['approved_gallons'] ?? null) ?? 0.0,
                ), 2);
                $nominal = $this->numeric(
                    $last['fuel_consumption_lph']
                    ?? $last['fuel_consumption']
                    ?? null,
                );
                $tolerance = $this->numeric($last['consumption_tolerance_lph'] ?? null) ?? 0.0;
                $actual = $interval['hours'] !== null && $interval['hours'] > 0
                    ? round($supplied / $interval['hours'], 2)
                    : null;
                $assessment = $this->consumptionAssessment(
                    $actual,
                    $nominal,
                    $tolerance,
                    $interval['error'],
                );

                return [
                    'index' => $index + 1,
                    'row_id' => 'internal-'.(int) ($last['well_oid'] ?? 0).'-'.(int) ($last['generator_oid'] ?? 0),
                    'month' => $month,
                    'well_name' => $last['well_name'] ?? '-',
                    'official_well_name' => $last['well_num'] ?? '-',
                    'generator_name' => $last['generator_name'] ?? '-',
                    'generator_number' => $last['generator_num'] ?? '-',
                    'first_supply_date' => $firstDate,
                    'last_supply_date' => $lastDate,
                    'supply_count' => $ordered->count(),
                    'first_generator_reading' => $firstReading,
                    'last_generator_reading' => $lastReading,
                    'operating_hours' => $interval['hours'],
                    'monthly_supplied_fuel_liters' => $supplied,
                    'supplied_fuel_liters' => $supplied,
                    'consumed_fuel_liters' => $supplied,
                    'actual_consumption_lph' => $actual,
                    'nominal_consumption_lph' => $nominal,
                    'consumption_tolerance_lph' => $tolerance,
                    'consumption_limit_lph' => $assessment['limit'],
                    'consumption_status' => $assessment,
                    'notes' => implode(' — ', array_filter([
                        $interval['error'],
                        $nominal === null ? 'معدل استهلاك المولد غير مدخل' : null,
                    ])),
                ];
            });

        return $this->reportPayload('internal', $month, $reportFilters, $rows);
    }

    public function getExternalReport(string $month, array $filters = []): array
    {
        [$from, $to] = $this->monthPeriod($month);
        $reportFilters = $this->periodFilters($filters, $from, $to);
        $inventories = $this->dieselInventoryRepository->getReportInventories(
            $reportFilters,
            5000,
            'external',
        );
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
        $prepared = $inventories->map(function (DieselInventory $inventory) use ($generators): array {
            $generator = $this->resolveGenerator($inventory, $generators);

            return [
                'inventory' => $inventory,
                'generator' => $generator,
                'group_key' => sprintf(
                    '%d:%s',
                    (int) $this->attribute($inventory, 'WELL_OID'),
                    $generator ? (string) $generator['oid'] : 'unresolved',
                ),
            ];
        });
        $generatorIds = $prepared
            ->pluck('generator.oid')
            ->filter()
            ->unique()
            ->values()
            ->all();
        $deliveries = collect($this->generatorRepository->getApprovedFuelDeliveries(
            $generatorIds,
            $from,
            $to,
        ))->groupBy('generator_oid');

        $rows = $prepared
            ->groupBy('group_key')
            ->values()
            ->map(function (Collection $group, int $index) use ($deliveries, $month): array {
                $ordered = $group->sortBy(function (array $item): string {
                    /** @var DieselInventory $inventory */
                    $inventory = $item['inventory'];

                    return sprintf(
                        '%s:%020d',
                        Carbon::parse($this->attribute($inventory, 'INVENTORY_DATE'))->format('Y-m-d H:i:s'),
                        (int) $inventory->getKey(),
                    );
                })->values();
                /** @var DieselInventory $firstInventory */
                $firstInventory = $ordered->first()['inventory'];
                /** @var DieselInventory $lastInventory */
                $lastInventory = $ordered->last()['inventory'];
                $generator = $ordered->last()['generator'];
                $well = $lastInventory->well;
                $firstDate = Carbon::parse($this->attribute($firstInventory, 'INVENTORY_DATE'));
                $lastDate = Carbon::parse($this->attribute($lastInventory, 'INVENTORY_DATE'));
                $firstReading = $this->numeric($this->attribute($firstInventory, 'GENERATOR_HOURS_READING'));
                $lastReading = $this->numeric($this->attribute($lastInventory, 'GENERATOR_HOURS_READING'));
                $interval = $this->monthlyMeterInterval(
                    $firstReading,
                    $lastReading,
                    $firstDate,
                    $lastDate,
                    $ordered->count(),
                    'جردين',
                );
                $firstBalance = $well
                    ? $this->fuelCalculator->litersForWell(
                        $well,
                        $this->numeric($this->attribute($firstInventory, 'DIESEL_HEIGHT_CM')),
                    )
                    : null;
                $lastBalance = $well
                    ? $this->fuelCalculator->litersForWell(
                        $well,
                        $this->numeric($this->attribute($lastInventory, 'DIESEL_HEIGHT_CM')),
                    )
                    : null;
                $generatorDeliveries = $generator
                    ? $deliveries->get((int) $generator['oid'], collect())
                    : collect();
                $monthlySupply = $generator
                    ? round($generatorDeliveries->sum('liters'), 2)
                    : null;
                $intervalSupply = $generator && $ordered->count() >= 2
                    ? $this->suppliedFuelBetween(
                        $generatorDeliveries,
                        $firstDate,
                        $lastDate,
                        $firstReading,
                        $lastReading,
                    )
                    : null;
                $consumed = $firstBalance !== null
                    && $lastBalance !== null
                    && $intervalSupply !== null
                    ? round($firstBalance + $intervalSupply - $lastBalance, 2)
                    : null;
                $calculationError = $interval['error'];

                if ($consumed !== null && $consumed < 0) {
                    $calculationError ??= 'رصيد آخر جرد أعلى من رصيد أول جرد والتوريد المسجل';
                }

                $nominal = $this->numeric(
                    $generator['configured_fuel_consumption_lph']
                    ?? $generator['fuel_consumption_lph']
                    ?? null,
                );
                $tolerance = $this->numeric($generator['consumption_tolerance_lph'] ?? null) ?? 0.0;
                $actual = $consumed !== null && $interval['hours'] !== null && $interval['hours'] > 0
                    ? round($consumed / $interval['hours'], 2)
                    : null;
                $assessment = $this->consumptionAssessment(
                    $actual,
                    $nominal,
                    $tolerance,
                    $calculationError,
                );

                return [
                    'index' => $index + 1,
                    'row_id' => 'external-'.(int) $this->attribute($lastInventory, 'WELL_OID').'-'.($generator['oid'] ?? 'unresolved'),
                    'month' => $month,
                    'well_name' => $this->attribute($well, 'NAME') ?? '-',
                    'official_well_name' => $this->attribute($well, 'SOURCE_WELL_CODE')
                        ?? $this->attribute($well, 'WELL_NUM')
                        ?? '-',
                    'generator_name' => $generator['name'] ?? '-',
                    'generator_number' => $generator['number'] ?? '-',
                    'first_inventory_date' => $firstDate,
                    'last_inventory_date' => $lastDate,
                    'inventory_count' => $ordered->count(),
                    'first_generator_reading' => $firstReading,
                    'last_generator_reading' => $lastReading,
                    'operating_hours' => $interval['hours'],
                    'first_fuel_balance_liters' => $firstBalance,
                    'monthly_supplied_fuel_liters' => $monthlySupply,
                    'interval_supplied_fuel_liters' => $intervalSupply,
                    'supplied_fuel_liters' => $intervalSupply,
                    'last_fuel_balance_liters' => $lastBalance,
                    'consumed_fuel_liters' => $consumed,
                    'actual_consumption_lph' => $actual,
                    'nominal_consumption_lph' => $nominal,
                    'consumption_tolerance_lph' => $tolerance,
                    'consumption_limit_lph' => $assessment['limit'],
                    'consumption_status' => $assessment,
                    'notes' => implode(' — ', array_filter([
                        $interval['error'],
                        ! $generator ? 'لم يتم تحديد مولد واحد لسجلات الجرد' : null,
                        $firstBalance === null ? 'تعذر احتساب رصيد أول جرد لعدم اكتمال بيانات الخزان' : null,
                        $lastBalance === null ? 'تعذر احتساب رصيد آخر جرد لعدم اكتمال بيانات الخزان' : null,
                        $consumed !== null && $consumed < 0 ? $calculationError : null,
                        $nominal === null ? 'معدل استهلاك المولد غير مدخل' : null,
                    ])),
                ];
            });

        return $this->reportPayload('external', $month, $reportFilters, $rows);
    }

    public function getExportReport(
        string $tankGroup,
        string $month,
        array $filters = [],
        array $requestedColumns = [],
    ): array {
        $tankGroup = $tankGroup === 'external' ? 'external' : 'internal';
        $report = $this->getReport($tankGroup, $month, $filters);

        return array_merge($report, [
            'report_type' => 'monthly_'.$tankGroup,
            'title' => $tankGroup === 'external'
                ? 'التقرير الشهري لمولدات الخزان الخارجي'
                : 'التقرير الشهري لمولدات الديلي تانك',
            'columns' => $this->selectedColumns($tankGroup, $requestedColumns),
        ]);
    }

    public function getReportColumns(): array
    {
        return self::REPORT_COLUMNS;
    }

    private function reportPayload(
        string $tankGroup,
        string $month,
        array $filters,
        Collection $rows,
    ): array {
        return [
            'tank_group' => $tankGroup,
            'month' => $month,
            'filters' => array_merge($filters, ['month' => $month]),
            'rows' => $rows->values(),
            'total_operating_hours' => round((float) $rows->sum('operating_hours'), 2),
            'total_supplied_fuel_liters' => round((float) $rows->sum('monthly_supplied_fuel_liters'), 2),
            'total_consumed_fuel_liters' => round((float) $rows->sum('consumed_fuel_liters'), 2),
            'high_consumption_count' => $rows->where('consumption_status.key', 'high')->count(),
            'totals' => [
                'operating_hours' => round((float) $rows->sum('operating_hours'), 2),
                'monthly_supplied_fuel_liters' => round((float) $rows->sum('monthly_supplied_fuel_liters'), 2),
                'interval_supplied_fuel_liters' => round((float) $rows->sum('interval_supplied_fuel_liters'), 2),
                'supplied_fuel_liters' => round((float) $rows->sum('supplied_fuel_liters'), 2),
                'consumed_fuel_liters' => round((float) $rows->sum('consumed_fuel_liters'), 2),
            ],
            'generated_at' => now(),
        ];
    }

    private function suppliedFuelBetween(
        Collection $deliveries,
        Carbon $from,
        Carbon $to,
        ?float $firstMeterReading,
        ?float $lastMeterReading,
    ): float {
        $hasValidMeterRange = $firstMeterReading !== null
            && $lastMeterReading !== null
            && $lastMeterReading >= $firstMeterReading;

        return round($deliveries->sum(function (array $delivery) use (
            $from,
            $to,
            $firstMeterReading,
            $lastMeterReading,
            $hasValidMeterRange,
        ): float {
            $deliveryMeter = $this->numeric($delivery['meter_reading'] ?? null);

            if ($hasValidMeterRange && $deliveryMeter !== null) {
                return $deliveryMeter > $firstMeterReading && $deliveryMeter <= $lastMeterReading
                    ? (float) $delivery['liters']
                    : 0.0;
            }

            $suppliedAt = Carbon::parse($delivery['supplied_at']);
            $enteredAt = empty($delivery['entered_at']) ? null : Carbon::parse($delivery['entered_at']);
            $effectiveDate = $enteredAt?->isSameDay($suppliedAt) ? $enteredAt : $suppliedAt;

            return $effectiveDate->greaterThan($from) && $effectiveDate->lessThanOrEqualTo($to)
                ? (float) $delivery['liters']
                : 0.0;
        }), 2);
    }

    /** @return array{hours: ?float, error: ?string} */
    private function monthlyMeterInterval(
        ?float $firstReading,
        ?float $lastReading,
        Carbon $firstDate,
        Carbon $lastDate,
        int $recordCount,
        string $requiredRecordsLabel,
    ): array {
        if ($recordCount < 2) {
            return ['hours' => null, 'error' => "يلزم توفر {$requiredRecordsLabel} على الأقل داخل الشهر"];
        }

        if ($firstReading === null || $lastReading === null) {
            return ['hours' => null, 'error' => 'قراءة عداد المولد في أول أو آخر سجل مفقودة أو غير رقمية'];
        }

        if ($lastDate->lessThanOrEqualTo($firstDate)) {
            return ['hours' => null, 'error' => 'وقت آخر سجل غير مرتب بعد وقت أول سجل في الشهر'];
        }

        if ($lastReading < $firstReading) {
            return ['hours' => null, 'error' => 'قراءة عداد المولد في آخر سجل أقل من أول سجل في الشهر'];
        }

        $hours = round($lastReading - $firstReading, 2);
        if ($hours <= 0) {
            return ['hours' => null, 'error' => 'لا يوجد فرق موجب بين قراءتي عداد المولد خلال الشهر'];
        }

        $elapsedHours = $firstDate->diffInSeconds($lastDate) / 3600;
        if ($hours > $elapsedHours + 1.0) {
            return ['hours' => null, 'error' => 'فرق عداد المولد أكبر من الزمن الفعلي بين أول وآخر سجل'];
        }

        return ['hours' => $hours, 'error' => null];
    }

    /** @return array{key: string, label: string, reason: string, limit: ?float} */
    private function consumptionAssessment(
        ?float $actual,
        ?float $nominal,
        float $tolerance,
        ?string $invalidReason = null,
    ): array {
        $limit = $nominal === null ? null : round($nominal + max(0.0, $tolerance), 2);

        if ($invalidReason) {
            return ['key' => 'invalid', 'label' => 'بيانات غير صالحة', 'reason' => $invalidReason, 'limit' => $limit];
        }

        if ($actual === null || $nominal === null) {
            return [
                'key' => 'unavailable',
                'label' => 'بيانات غير مكتملة',
                'reason' => $actual === null ? 'تعذر احتساب معدل الاستهلاك الشهري' : 'معدل استهلاك المولد غير مدخل',
                'limit' => $limit,
            ];
        }

        $high = $actual > $limit;

        return [
            'key' => $high ? 'high' : 'normal',
            'label' => $high ? 'استهلاك مرتفع' : 'ضمن المعدل',
            'reason' => $high
                ? "المعدل الشهري {$actual} أعلى من الحد {$limit} لتر/ساعة"
                : "المعدل الشهري لا يتجاوز الحد {$limit} لتر/ساعة",
            'limit' => $limit,
        ];
    }

    private function selectedColumns(string $tankGroup, array $requested): array
    {
        $available = collect(self::REPORT_COLUMNS[$tankGroup]);
        $allowed = $available->pluck('key');
        $requested = collect($requested)
            ->filter(fn (mixed $key): bool => is_string($key) && $allowed->contains($key))
            ->unique()
            ->values();

        if ($requested->isEmpty()) {
            return $available->where('default', true)->values()->all();
        }

        return $requested
            ->map(fn (string $key): ?array => $available->firstWhere('key', $key))
            ->filter()
            ->values()
            ->all();
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function monthPeriod(string $month): array
    {
        $from = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();

        return [$from, $from->copy()->endOfMonth()];
    }

    private function periodFilters(array $filters, Carbon $from, Carbon $to): array
    {
        return array_filter([
            'well_oid' => $filters['well_oid'] ?? null,
            'generator_oid' => $filters['generator_oid'] ?? null,
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
        ], fn (mixed $value): bool => $value !== null && $value !== '');
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
