<?php

declare(strict_types=1);

namespace Tests\Unit\Wells;

use App\Models\Wells\Well;
use App\Repositories\Wells\DieselInventoryRepository;
use App\Repositories\Wells\GeneratorRepository;
use App\Repositories\Wells\WellRepository;
use App\Services\Wells\WellMediaService;
use App\Services\Wells\WellService;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use PHPUnit\Framework\TestCase;

class WellSyncServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_formats_oracle_inventory_dates_for_the_inspector_app(): void
    {
        $well = new Well;
        $well->setRawAttributes([
            'oid' => 19,
            'name' => 'أبو علبة',
            'pump_type' => 'submersible',
            'capacity' => '50',
            'power_source' => 'solar',
            'latitude' => '31.52718568',
            'longitude' => '34.46038306',
        ]);

        $wellRepository = Mockery::mock(WellRepository::class);
        $wellRepository->shouldReceive('getActiveForInspector')
            ->once()
            ->with(1)
            ->andReturn(new Collection([$well]));

        $dieselRepository = Mockery::mock(DieselInventoryRepository::class);
        $dieselRepository->shouldReceive('getLatestForWells')
            ->once()
            ->with([19])
            ->andReturn(collect([
                19 => [
                    'inventory_date' => '2026-07-02 11:31:30',
                    'generator_hours_reading' => '2222',
                    'diesel_height_cm' => '12',
                    'available_fuel_liters' => '3333',
                ],
            ]));

        $service = new WellService(
            $wellRepository,
            Mockery::mock(GeneratorRepository::class),
            Mockery::mock(WellMediaService::class),
            $dieselRepository,
        );

        $result = $service->getSyncWellsForInspector(1);

        self::assertCount(1, $result);
        self::assertSame(19, $result[0]['oid']);
        self::assertSame('2026-07-02T11:31:30+00:00', $result[0]['previous_inventory_date']);
        self::assertSame(2222.0, $result[0]['previous_generator_hours_reading']);
    }
}
