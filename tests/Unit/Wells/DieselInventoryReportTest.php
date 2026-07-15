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
            'available_fuel_liters' => '1570.80',
        ]);
        $inventory->setRelation('well', $well);
        $inventory->setRelation('inspector', $inspector);
        $inventories = new Collection([$inventory]);

        $inventoryRepository = Mockery::mock(DieselInventoryRepository::class);
        $inventoryRepository->shouldReceive('getReportInventories')->once()->andReturn($inventories);
        $inventoryRepository->shouldReceive('previousReadingsFor')->once()->andReturn(collect([
            10 => [
                'diesel_height_cm' => '100',
                'available_fuel_liters' => '3141.59',
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
                ],
            ],
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
    }
}
