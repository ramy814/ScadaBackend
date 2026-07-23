<?php

declare(strict_types=1);

namespace Tests\Unit\Wells;

use App\Models\Wells\Well;
use App\Models\Wells\WellGenerator;
use App\Repositories\Wells\DieselInventoryRepository;
use App\Repositories\Wells\GeneratorRepository;
use App\Repositories\Wells\WellRepository;
use App\Services\Wells\WellMediaService;
use App\Services\Wells\WellService;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use Tests\TestCase;

class WellServiceMapTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_map_includes_repository_wells_and_resolves_assigned_generator_numbers(): void
    {
        $assignment = new WellGenerator;
        $assignment->setRawAttributes([
            'well_oid' => 3,
            'generator_oid' => 83,
        ]);

        $well = new Well;
        $well->setRawAttributes([
            'oid' => 3,
            'name' => 'أحمد شوقي',
            'is_active' => 0,
        ]);
        $well->setRelation('generators', new Collection([$assignment]));

        $wellRepository = Mockery::mock(WellRepository::class);
        $wellRepository->shouldReceive('getWellsForMap')
            ->once()
            ->andReturn(new Collection([$well]));

        $generatorRepository = Mockery::mock(GeneratorRepository::class);
        $generatorRepository->shouldReceive('getGeneratorsByIds')
            ->once()
            ->with([83])
            ->andReturn([
                (object) [
                    'OID' => 83,
                    'GENERATOR_NUM' => 'GZM-AJP-110-012',
                    'NAME' => 'مولد أحمد شوقي',
                ],
            ]);

        $service = new WellService(
            $wellRepository,
            $generatorRepository,
            Mockery::mock(WellMediaService::class),
            Mockery::mock(DieselInventoryRepository::class),
        );

        $mapWell = $service->getWellsForMap()->first();

        self::assertSame(0, $mapWell->getAttribute('is_active'));
        self::assertSame([
            [
                'oid' => 83,
                'number' => 'GZM-AJP-110-012',
                'name' => 'مولد أحمد شوقي',
            ],
        ], $mapWell->getAttribute('MAP_GENERATORS'));
    }
}
