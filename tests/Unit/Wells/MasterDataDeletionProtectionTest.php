<?php

declare(strict_types=1);

namespace Tests\Unit\Wells;

use App\Modules\Wells\Models\Inspector;
use App\Modules\Wells\Models\Team;
use App\Modules\Wells\Models\Well;
use App\Modules\Wells\Repositories\DieselInventoryRepository;
use App\Modules\Wells\Repositories\GeneratorRepository;
use App\Modules\Wells\Repositories\InspectorRepository;
use App\Modules\Wells\Repositories\TeamRepository;
use App\Modules\Wells\Repositories\WellRepository;
use App\Modules\Wells\Services\InspectorService;
use App\Modules\Wells\Services\TeamService;
use App\Modules\Wells\Services\WellMediaService;
use App\Modules\Wells\Services\WellService;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class MasterDataDeletionProtectionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_inspector_with_related_records_cannot_be_deleted(): void
    {
        $inspector = new Inspector;
        $inspector->setAttribute('teams_count', 1);
        $inspector->setAttribute('readings_count', 33);
        $inspector->setAttribute('diesel_inventories_count', 8);
        $inspector->setAttribute('notifications_count', 7);
        $inspector->setAttribute('tokens_count', 2);

        $repository = Mockery::mock(InspectorRepository::class);
        $repository->shouldReceive('findForDeletion')->once()->with(1)->andReturn($inspector);
        $repository->shouldNotReceive('delete');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('لا يمكن حذف المفتش لوجود سجلات مرتبطة به');

        (new InspectorService($repository))->deleteInspector(1);
    }

    public function test_team_with_assignments_cannot_be_deleted(): void
    {
        $team = new Team;
        $team->setAttribute('wells_count', 12);
        $team->setAttribute('inspectors_count', 1);

        $repository = Mockery::mock(TeamRepository::class);
        $repository->shouldReceive('findForDeletion')->once()->with(4)->andReturn($team);
        $repository->shouldNotReceive('delete');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('لا يمكن حذف الفريق لوجود سجلات مرتبطة به');

        (new TeamService($repository))->deleteTeam(4);
    }

    public function test_well_with_any_related_record_cannot_be_deleted(): void
    {
        $well = new Well;
        $well->setAttribute('readings_count', 0);
        $well->setAttribute('diesel_inventories_count', 0);
        $well->setAttribute('notifications_count', 1);
        $well->setAttribute('teams_count', 0);
        $well->setAttribute('generators_count', 0);
        $well->setAttribute('media_count', 0);

        $repository = Mockery::mock(WellRepository::class);
        $repository->shouldReceive('findForDeletion')->once()->with(19)->andReturn($well);
        $repository->shouldNotReceive('delete');

        $service = $this->wellService($repository);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('لا يمكن حذف البئر لوجود سجلات مرتبطة به');

        $service->deleteWell(19);
    }

    public function test_unlinked_master_records_can_still_be_deleted(): void
    {
        $inspector = new Inspector;
        foreach (['teams', 'readings', 'diesel_inventories', 'notifications', 'tokens'] as $relation) {
            $inspector->setAttribute("{$relation}_count", 0);
        }

        $inspectorRepository = Mockery::mock(InspectorRepository::class);
        $inspectorRepository->shouldReceive('findForDeletion')->once()->with(9)->andReturn($inspector);
        $inspectorRepository->shouldReceive('delete')->once()->with(9)->andReturnTrue();

        self::assertTrue((new InspectorService($inspectorRepository))->deleteInspector(9));

        $team = new Team;
        $team->setAttribute('wells_count', 0);
        $team->setAttribute('inspectors_count', 0);

        $teamRepository = Mockery::mock(TeamRepository::class);
        $teamRepository->shouldReceive('findForDeletion')->once()->with(8)->andReturn($team);
        $teamRepository->shouldReceive('delete')->once()->with(8)->andReturnTrue();

        self::assertTrue((new TeamService($teamRepository))->deleteTeam(8));

        $well = new Well;
        foreach (['readings', 'diesel_inventories', 'notifications', 'teams', 'generators', 'media'] as $relation) {
            $well->setAttribute("{$relation}_count", 0);
        }

        $wellRepository = Mockery::mock(WellRepository::class);
        $wellRepository->shouldReceive('findForDeletion')->once()->with(7)->andReturn($well);
        $wellRepository->shouldReceive('delete')->once()->with(7)->andReturnTrue();

        self::assertTrue($this->wellService($wellRepository)->deleteWell(7));
    }

    private function wellService(WellRepository $repository): WellService
    {
        return new WellService(
            $repository,
            Mockery::mock(GeneratorRepository::class),
            Mockery::mock(WellMediaService::class),
            Mockery::mock(DieselInventoryRepository::class),
        );
    }
}
