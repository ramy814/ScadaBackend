<?php

declare(strict_types=1);

namespace Tests\Unit\Wells;

use App\Http\Controllers\Wells\WellController;
use App\Models\Wells\Well;
use App\Models\Wells\WellGenerator;
use App\Services\Wells\GeneratorService;
use App\Services\Wells\TeamService;
use App\Services\Wells\WellService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Response;
use Mockery;
use Tests\TestCase;

class WellControllerIndexTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_index_resolves_generator_ids_from_oracle_lowercase_attributes(): void
    {
        $assignment = new WellGenerator;
        $assignment->setRawAttributes([
            'oid' => 105,
            'well_oid' => 3,
            'generator_oid' => 83,
        ]);

        $well = new Well;
        $well->setRawAttributes(['oid' => 3, 'name' => 'أحمد شوقي']);
        $well->setRelation('generators', new Collection([$assignment]));

        $paginator = new LengthAwarePaginator([$well], 1, 15, 1, ['path' => '/wells/wells']);

        $wellService = Mockery::mock(WellService::class);
        $wellService->shouldReceive('getPaginatedWells')
            ->once()
            ->with(15, [])
            ->andReturn($paginator);

        $generatorService = Mockery::mock(GeneratorService::class);
        $generatorService->shouldReceive('getGeneratorsByIds')
            ->once()
            ->with([83])
            ->andReturn([
                (object) [
                    'OID' => 83,
                    'NAME' => 'مولد الاختبار',
                    'GENERATOR_NUM' => 'GZM-AJP-110-012',
                ],
            ]);

        $controller = new WellController(
            $wellService,
            Mockery::mock(TeamService::class),
            $generatorService,
        );

        self::assertInstanceOf(Response::class, $controller->index(Request::create('/wells/wells')));
    }
}
