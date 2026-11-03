<?php

declare(strict_types=1);

namespace Tests\Unit\Wells;

use App\Modules\Wells\Models\DieselInventory;
use App\Modules\Wells\Models\Inspector;
use App\Modules\Wells\Models\Well;
use App\Modules\Wells\Repositories\DieselInventoryRepository;
use App\Modules\Wells\Repositories\GeneratorRepository;
use App\Modules\Wells\Services\DieselFuelCalculator;
use App\Modules\Wells\Services\MonthlyDieselReportService;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use PHPUnit\Framework\TestCase;

class MonthlyDieselReportTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_daily_tank_monthly_report_aggregates_supply_and_meter_hours(): void
    {
        $generatorRepository = Mockery::mock(GeneratorRepository::class);
        $generatorRepository->shouldReceive('getDailyTankFuelEntries')
            ->once()
            ->with([
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-30',
            ])
            ->andReturn([
                $this->dailyEntry(103, '2026-06-25 10:00:00', '130', '60'),
                $this->dailyEntry(101, '2026-06-03 08:00:00', '100', '40'),
                $this->dailyEntry(102, '2026-06-15 09:00:00', '110', '50'),
            ]);

        $report = $this->service($generatorRepository)->getInternalReport('2026-06');
        $row = $report['rows']->first();

        self::assertCount(1, $report['rows']);
        self::assertSame(3, $row['supply_count']);
        self::assertSame(100.0, $row['first_generator_reading']);
        self::assertSame(130.0, $row['last_generator_reading']);
        self::assertSame(30.0, $row['operating_hours']);
        self::assertSame(150.0, $row['monthly_supplied_fuel_liters']);
        self::assertSame(150.0, $row['consumed_fuel_liters']);
        self::assertSame(5.0, $row['actual_consumption_lph']);
        self::assertSame('normal', $row['consumption_status']['key']);
        self::assertSame(150.0, $report['total_supplied_fuel_liters']);
        self::assertSame(150.0, $report['total_consumed_fuel_liters']);
    }

    public function test_external_monthly_report_uses_first_and_last_inventory_formula(): void
    {
        $well = new Well;
        $well->forceFill([
            'oid' => 14,
            'NAME' => 'بئر الاختبار',
            'TANK_SHAPE' => 'اسطواني عمودي',
            'TANK_DIAMETER' => '2',
            'TANK_HEIGHT' => '3',
        ]);
        $first = $this->inventory($well, 10, '2026-06-03 08:00:00', '100', '100');
        $last = $this->inventory($well, 11, '2026-06-25 10:00:00', '120', '50');

        $inventoryRepository = Mockery::mock(DieselInventoryRepository::class);
        $inventoryRepository->shouldReceive('getReportInventories')
            ->once()
            ->with([
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-30',
            ], 5000, 'external')
            ->andReturn(new Collection([$last, $first]));

        $generatorRepository = Mockery::mock(GeneratorRepository::class);
        $generatorRepository->shouldReceive('getReportGenerators')
            ->once()
            ->with([14, 14], [])
            ->andReturn([
                'by_id' => [],
                'by_well' => [
                    14 => [
                        'oid' => 76,
                        'name' => 'مولد الاختبار',
                        'number' => 'GEN-76',
                        'fuel_consumption_lph' => 90.0,
                        'configured_fuel_consumption_lph' => 90.0,
                        'consumption_tolerance_lph' => 10.0,
                    ],
                ],
                'by_well_generator' => [],
            ]);
        $generatorRepository->shouldReceive('getApprovedFuelDeliveries')
            ->once()
            ->withArgs(fn (array $ids, $from, $to): bool => $ids === [76]
                && $from->toDateString() === '2026-06-01'
                && $to->toDateString() === '2026-06-30')
            ->andReturn([
                [
                    'generator_oid' => 76,
                    'supplied_at' => '2026-06-02 09:00:00',
                    'entered_at' => '2026-06-02 09:00:00',
                    'meter_reading' => '95',
                    'liters' => 100.0,
                ],
                [
                    'generator_oid' => 76,
                    'supplied_at' => '2026-06-12 09:00:00',
                    'entered_at' => '2026-06-12 09:00:00',
                    'meter_reading' => '110',
                    'liters' => 400.0,
                ],
                [
                    'generator_oid' => 76,
                    'supplied_at' => '2026-06-28 09:00:00',
                    'entered_at' => '2026-06-28 09:00:00',
                    'meter_reading' => '125',
                    'liters' => 200.0,
                ],
            ]);

        $report = (new MonthlyDieselReportService(
            $inventoryRepository,
            new DieselFuelCalculator,
            $generatorRepository,
        ))->getExternalReport('2026-06');
        $row = $report['rows']->first();

        self::assertSame(2, $row['inventory_count']);
        self::assertSame(20.0, $row['operating_hours']);
        self::assertEqualsWithDelta(3141.59, $row['first_fuel_balance_liters'], 0.01);
        self::assertSame(700.0, $row['monthly_supplied_fuel_liters']);
        self::assertSame(400.0, $row['interval_supplied_fuel_liters']);
        self::assertEqualsWithDelta(1570.8, $row['last_fuel_balance_liters'], 0.01);
        self::assertEqualsWithDelta(1970.79, $row['consumed_fuel_liters'], 0.01);
        self::assertEqualsWithDelta(98.54, $row['actual_consumption_lph'], 0.01);
        self::assertSame('normal', $row['consumption_status']['key']);
        self::assertSame(700.0, $report['total_supplied_fuel_liters']);
        self::assertEqualsWithDelta(1970.79, $report['total_consumed_fuel_liters'], 0.01);
    }

    public function test_monthly_report_requires_two_records_for_an_operating_rate(): void
    {
        $generatorRepository = Mockery::mock(GeneratorRepository::class);
        $generatorRepository->shouldReceive('getDailyTankFuelEntries')->once()->andReturn([
            $this->dailyEntry(101, '2026-06-03 08:00:00', '100', '40'),
        ]);

        $row = $this->service($generatorRepository)
            ->getInternalReport('2026-06')['rows']->first();

        self::assertNull($row['operating_hours']);
        self::assertNull($row['actual_consumption_lph']);
        self::assertSame('invalid', $row['consumption_status']['key']);
        self::assertStringContainsString('توريدين', $row['notes']);
    }

    private function service(GeneratorRepository $generatorRepository): MonthlyDieselReportService
    {
        return new MonthlyDieselReportService(
            Mockery::mock(DieselInventoryRepository::class),
            new DieselFuelCalculator,
            $generatorRepository,
        );
    }

    private function dailyEntry(
        int $id,
        string $date,
        string $reading,
        string $liters,
    ): array {
        return [
            'fuel_detail_oid' => $id,
            'well_oid' => 14,
            'well_name' => 'بئر الاختبار',
            'well_num' => 'W-14',
            'generator_oid' => 76,
            'generator_name' => 'مولد الاختبار',
            'generator_num' => 'GEN-76',
            'fill_up_date' => $date,
            'current_reading' => $reading,
            'approved_gallons' => $liters,
            'fuel_consumption_lph' => '6',
            'consumption_tolerance_lph' => '1',
        ];
    }

    private function inventory(
        Well $well,
        int $id,
        string $date,
        string $hours,
        string $height,
    ): DieselInventory {
        $inventory = new DieselInventory;
        $inventory->setRawAttributes([
            'oid' => $id,
            'well_oid' => 14,
            'generator_oid' => null,
            'inventory_date' => $date,
            'generator_hours_reading' => $hours,
            'diesel_height_cm' => $height,
        ]);
        $inventory->setRelation('well', $well);
        $inventory->setRelation('inspector', new Inspector);

        return $inventory;
    }
}
