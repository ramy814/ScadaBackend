<?php

declare(strict_types=1);

namespace Tests\Unit\Wells;

use App\Models\Wells\DieselInventory;
use App\Models\Wells\Inspector;
use App\Models\Wells\Well;
use App\Repositories\Wells\DieselInventoryRepository;
use App\Repositories\Wells\GeneratorRepository;
use App\Services\Wells\DieselFuelCalculator;
use App\Services\Wells\DieselInventoryService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use PHPUnit\Framework\TestCase;

class DieselInventoryReportTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_builds_the_diesel_report_from_inventory_generator_and_supply_data(): void
    {
        $well = new Well;
        $well->forceFill([
            'oid' => 14,
            'NAME' => 'الجندي',
            'SOURCE_WELL_CODE' => 'Rimal 01',
            'CAPACITY' => '60',
            'TANK_SHAPE' => 'اسطواني عمودي',
            'TANK_DIAMETER' => '2',
            'TANK_HEIGHT' => '3',
        ]);
        $inspector = new Inspector;
        $inspector->forceFill(['NAME' => 'مفتش الاختبار']);
        $inventory = new DieselInventory;
        $inventory->setRawAttributes([
            'oid' => 10,
            'well_oid' => 14,
            'inventory_date' => '2026-06-28 12:00:00',
            'generator_hours_reading' => '120',
            'diesel_height_cm' => '50',
            'available_fuel_liters' => '999999',
        ]);
        $inventory->setRelation('well', $well);
        $inventory->setRelation('inspector', $inspector);
        $inventories = new Collection([$inventory]);

        $inventoryRepository = Mockery::mock(DieselInventoryRepository::class);
        $inventoryRepository->shouldReceive('getReportInventories')
            ->once()
            ->with([], 5000, 'external')
            ->andReturn($inventories);
        $inventoryRepository->shouldReceive('previousReadingsFor')->once()->andReturn(collect([
            10 => [
                'diesel_height_cm' => '100',
                'available_fuel_liters' => '888888',
                'generator_hours_reading' => '100',
                'inventory_date' => '2026-06-27 12:00:00',
            ],
        ]));

        $generatorRepository = Mockery::mock(GeneratorRepository::class);
        $generatorRepository->shouldReceive('getReportGenerators')->once()->andReturn([
            'by_id' => [],
            'by_well' => [
                14 => [
                    'oid' => 76,
                    'name' => 'مولد الجندي',
                    'number' => 'GZM-AJP-088-005',
                    'capacity_kva' => 88.0,
                    'fuel_consumption_lph' => 10.0,
                    'configured_fuel_consumption_lph' => 10.0,
                    'consumption_tolerance_lph' => 1.0,
                ],
            ],
            'by_well_generator' => [],
        ]);
        $generatorRepository->shouldReceive('getApprovedFuelDeliveries')->once()->andReturn([
            [
                'generator_oid' => 76,
                'supplied_at' => '2026-06-28 08:00:00',
                'liters' => 400.0,
            ],
        ]);

        $report = (new DieselInventoryService(
            $inventoryRepository,
            new DieselFuelCalculator,
            $generatorRepository,
        ))->getInventoryReport();
        $row = $report['rows']->first();

        self::assertSame(20.0, $row['operating_hours']);
        self::assertEqualsWithDelta(1970.79, $row['consumed_fuel_liters'], 0.01);
        self::assertEqualsWithDelta(98.54, $row['actual_consumption_lph'], 0.01);
        self::assertEqualsWithDelta(9424.78, $row['tank_capacity_liters'], 0.01);
        self::assertSame(1200.0, $row['water_production_m3']);
        self::assertSame(200.0, $row['calculated_diesel_consumption_liters']);
        self::assertSame('GZM-AJP-088-005', $row['generator_number']);
        self::assertSame('high', $row['consumption_status']['key']);
        self::assertSame(11.0, $row['consumption_limit_lph']);
        self::assertSame(1, $report['high_consumption_count']);
    }

    public function test_daily_tank_report_calculates_normal_and_high_consumption(): void
    {
        $generatorRepository = Mockery::mock(GeneratorRepository::class);
        $generatorRepository->shouldReceive('getDailyTankFuelEntries')
            ->once()
            ->with([])
            ->andReturn([
                $this->dailyTankEntry([
                    'fuel_detail_oid' => 101,
                    'approved_gallons' => '40',
                ]),
                $this->dailyTankEntry([
                    'fuel_detail_oid' => 102,
                    'approved_gallons' => '50',
                ]),
            ]);

        $report = $this->serviceWithGeneratorRepository($generatorRepository)
            ->getInternalTankReport();

        self::assertCount(2, $report['rows']);

        $normal = $report['rows'][0];
        self::assertSame(5.0, $normal['operating_hours']);
        self::assertSame(40.0, $normal['supplied_fuel_liters']);
        self::assertSame(8.0, $normal['actual_consumption_lph']);
        self::assertSame(9.0, $normal['consumption_limit_lph']);
        self::assertSame('normal', $normal['consumption_status']['key']);

        $high = $report['rows'][1];
        self::assertSame(10.0, $high['actual_consumption_lph']);
        self::assertSame('high', $high['consumption_status']['key']);
        self::assertSame(1, $report['high_consumption_count']);
    }

    public function test_daily_tank_report_marks_a_missing_previous_fill_as_unavailable(): void
    {
        $generatorRepository = Mockery::mock(GeneratorRepository::class);
        $generatorRepository->shouldReceive('getDailyTankFuelEntries')
            ->once()
            ->andReturn([
                $this->dailyTankEntry([
                    'previous_fill_up_date' => null,
                    'previous_current_reading' => null,
                ]),
            ]);

        $row = $this->serviceWithGeneratorRepository($generatorRepository)
            ->getInternalTankReport()['rows'][0];

        self::assertNull($row['operating_hours']);
        self::assertNull($row['actual_consumption_lph']);
        self::assertSame('unavailable', $row['consumption_status']['key']);
        self::assertStringContainsString('لا توجد تعبئة سابقة معتمدة', $row['notes']);
    }

    public function test_daily_tank_report_rejects_an_impossible_meter_jump(): void
    {
        $generatorRepository = Mockery::mock(GeneratorRepository::class);
        $generatorRepository->shouldReceive('getDailyTankFuelEntries')
            ->once()
            ->andReturn([
                $this->dailyTankEntry([
                    'previous_fill_up_date' => '2026-07-22 08:00:00',
                    'fill_up_date' => '2026-07-22 10:00:00',
                    'previous_current_reading' => '100',
                    'current_reading' => '110',
                ]),
            ]);

        $row = $this->serviceWithGeneratorRepository($generatorRepository)
            ->getInternalTankReport()['rows'][0];

        self::assertNull($row['operating_hours']);
        self::assertNull($row['actual_consumption_lph']);
        self::assertSame('invalid', $row['consumption_status']['key']);
        self::assertStringContainsString('أكبر من الزمن الفعلي', $row['consumption_status']['reason']);
    }

    public function test_daily_tank_report_rejects_a_repeated_fill_time(): void
    {
        $generatorRepository = Mockery::mock(GeneratorRepository::class);
        $generatorRepository->shouldReceive('getDailyTankFuelEntries')
            ->once()
            ->andReturn([
                $this->dailyTankEntry([
                    'previous_fill_up_date' => '2026-07-22 10:00:00',
                    'fill_up_date' => '2026-07-22 10:00:00',
                ]),
            ]);

        $row = $this->serviceWithGeneratorRepository($generatorRepository)
            ->getInternalTankReport()['rows'][0];

        self::assertNull($row['operating_hours']);
        self::assertSame('invalid', $row['consumption_status']['key']);
        self::assertStringContainsString('غير مرتب', $row['consumption_status']['reason']);
    }

    public function test_export_keeps_only_allowed_unique_columns_in_requested_order(): void
    {
        $generatorRepository = Mockery::mock(GeneratorRepository::class);
        $generatorRepository->shouldReceive('getDailyTankFuelEntries')
            ->once()
            ->with(['well_oid' => 14])
            ->andReturn([]);

        $report = $this->serviceWithGeneratorRepository($generatorRepository)->getExportReport(
            'internal',
            ['well_oid' => 14],
            [
                'generator_number',
                'not_a_report_column',
                'actual_consumption_lph',
                'generator_number',
            ],
        );

        self::assertSame(
            ['generator_number', 'actual_consumption_lph'],
            array_column($report['columns'], 'key'),
        );
        self::assertSame('internal', $report['report_type']);
    }

    public function test_paginated_inventory_exposes_the_previous_generator_hours_reading(): void
    {
        $well = new Well;
        $well->forceFill([
            'oid' => 14,
            'TANK_SHAPE' => 'اسطواني عمودي',
            'TANK_DIAMETER' => '2',
            'TANK_HEIGHT' => '3',
        ]);
        $inventory = new DieselInventory;
        $inventory->setRawAttributes([
            'oid' => 10,
            'well_oid' => 14,
            'diesel_height_cm' => '50',
            'available_fuel_liters' => '999999',
        ]);
        $inventory->setRelation('well', $well);
        $inventories = new Collection([$inventory]);
        $paginator = new LengthAwarePaginator($inventories, 1, 15, 1, ['path' => '/']);

        $inventoryRepository = Mockery::mock(DieselInventoryRepository::class);
        $inventoryRepository->shouldReceive('paginate')->once()->andReturn($paginator);
        $inventoryRepository->shouldReceive('previousReadingsFor')->once()->andReturn(collect([
            10 => [
                'diesel_height_cm' => '100',
                'available_fuel_liters' => '3141.59',
                'generator_hours_reading' => '875.5',
                'inventory_date' => '2026-06-27 12:00:00',
            ],
        ]));

        $generatorRepository = Mockery::mock(GeneratorRepository::class);
        $generatorRepository->shouldReceive('getReportGenerators')->once()->with([14], [])->andReturn([
            'by_id' => [],
            'by_well' => [
                14 => [
                    'oid' => 76,
                    'name' => 'مولد الجندي',
                    'number' => 'GZM-AJP-088-005',
                ],
            ],
        ]);

        $result = (new DieselInventoryService(
            $inventoryRepository,
            new DieselFuelCalculator,
            $generatorRepository,
        ))->getPaginatedInventories();
        $row = $result->getCollection()->first();

        self::assertSame('875.5', $row->getAttribute('PREVIOUS_GENERATOR_HOURS_READING'));
        self::assertSame(1570.8, $row->getAttribute('CALCULATED_AVAILABLE_FUEL_LITERS'));
        self::assertSame('GZM-AJP-088-005', $row->getAttribute('RESOLVED_GENERATOR')['number']);
        self::assertNull($row->getAttribute('PREVIOUS_AVAILABLE_FUEL_LITERS'));
    }

    private function serviceWithGeneratorRepository(
        GeneratorRepository $generatorRepository,
    ): DieselInventoryService {
        return new DieselInventoryService(
            Mockery::mock(DieselInventoryRepository::class),
            new DieselFuelCalculator,
            $generatorRepository,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function dailyTankEntry(array $overrides = []): array
    {
        return array_merge([
            'fuel_detail_oid' => 100,
            'well_oid' => 14,
            'well_name' => 'بئر الجندي',
            'well_num' => 'Rimal 01',
            'generator_oid' => 76,
            'generator_name' => 'مولد الجندي',
            'generator_num' => 'GZM-AJP-088-005',
            'previous_fill_up_date' => '2026-07-22 08:00:00',
            'fill_up_date' => '2026-07-22 18:00:00',
            'previous_current_reading' => '100',
            'current_reading' => '105',
            'approved_gallons' => '40',
            'fuel_consumption_lph' => '8',
            'fuel_consumption' => '12',
            'consumption_tolerance_lph' => '1',
        ], $overrides);
    }
}
