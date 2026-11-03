<?php

declare(strict_types=1);

namespace Tests\Unit\Wells;

use App\Modules\Wells\Models\Well;
use App\Modules\Wells\Repositories\DieselInventoryRepository;
use App\Modules\Wells\Repositories\GeneratorRepository;
use App\Modules\Wells\Repositories\WellRepository;
use App\Modules\Wells\Services\WellMediaService;
use App\Modules\Wells\Services\WellService;
use Mockery;
use Tests\TestCase;

class WellServiceUpdateTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_well_number_name_and_quarter_remain_editable(): void
    {
        $updatedWell = Mockery::mock(Well::class)->makePartial();
        $updatedWell->shouldReceive('fresh')
            ->once()
            ->with(['teams', 'generators', 'media'])
            ->andReturnSelf();

        $wellRepository = Mockery::mock(WellRepository::class);
        $wellRepository->shouldReceive('update')
            ->once()
            ->with(19, [
                'WELL_NUM' => 'Rimal 12',
                'NAME' => 'بئر الرمال المحدث',
                'QUARTER_OID' => 7,
            ])
            ->andReturn($updatedWell);

        $wellMediaService = Mockery::mock(WellMediaService::class);
        $wellMediaService->shouldReceive('storeMany')
            ->once()
            ->with(19, []);

        $service = new WellService(
            $wellRepository,
            Mockery::mock(GeneratorRepository::class),
            $wellMediaService,
            Mockery::mock(DieselInventoryRepository::class),
        );

        $result = $service->updateWell(19, [
            'WELL_NUM' => 'Rimal 12',
            'NAME' => 'بئر الرمال المحدث',
            'QUARTER_OID' => 7,
        ]);

        self::assertSame($updatedWell, $result);
    }

    public function test_generator_consumption_rate_and_tolerance_are_passed_to_repository(): void
    {
        $updatedWell = Mockery::mock(Well::class)->makePartial();
        $updatedWell->shouldReceive('fresh')
            ->once()
            ->with(['teams', 'generators', 'media'])
            ->andReturnSelf();

        $wellRepository = Mockery::mock(WellRepository::class);
        $wellRepository->shouldReceive('update')
            ->once()
            ->with(19, ['NAME' => 'بئر الرمال'])
            ->andReturn($updatedWell);

        $generatorRepository = Mockery::mock(GeneratorRepository::class);
        $generatorRepository->shouldReceive('syncWellGenerators')
            ->once()
            ->with(19, [
                [
                    'GENERATOR_OID' => 76,
                    'FUEL_CONSUMPTION_LPH' => 8.5,
                    'CONSUMPTION_TOLERANCE_LPH' => 1.25,
                ],
                [
                    'GENERATOR_OID' => 77,
                    'FUEL_CONSUMPTION_LPH' => 10.0,
                    'CONSUMPTION_TOLERANCE_LPH' => 0.0,
                ],
            ]);

        $wellMediaService = Mockery::mock(WellMediaService::class);
        $wellMediaService->shouldReceive('storeMany')
            ->once()
            ->with(19, []);

        $service = new WellService(
            $wellRepository,
            $generatorRepository,
            $wellMediaService,
            Mockery::mock(DieselInventoryRepository::class),
        );

        $result = $service->updateWell(19, [
            'NAME' => 'بئر الرمال',
            'generators' => [
                [
                    'generator_oid' => '76',
                    'fuel_consumption_lph' => '8.5',
                    'consumption_tolerance_lph' => '1.25',
                ],
                [
                    'GENERATOR_OID' => 77,
                    'FUEL_CONSUMPTION_LPH' => 10,
                    'CONSUMPTION_TOLERANCE_LPH' => 0,
                ],
            ],
        ]);

        self::assertSame($updatedWell, $result);
    }
}
