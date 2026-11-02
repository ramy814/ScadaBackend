<?php

declare(strict_types=1);

namespace Tests\Unit\Wells;

use App\Modules\Wells\Models\WellGenerator;
use App\Modules\Wells\Repositories\GeneratorRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class GeneratorRepositoryTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_diesel_reports_only_query_filled_approved_deliveries(): void
    {
        $queries = [];
        $connection = Mockery::mock();
        $connection->shouldReceive('select')
            ->twice()
            ->andReturnUsing(function (string $sql, array $bindings) use (&$queries): array {
                $queries[] = compact('sql', 'bindings');

                return [];
            });
        DB::shouldReceive('connection')->twice()->with('oracle')->andReturn($connection);

        $repository = new GeneratorRepository(Mockery::mock(WellGenerator::class));
        $repository->getDailyTankFuelEntries();
        $repository->getApprovedFuelDeliveries(
            [1840],
            Carbon::parse('2026-07-26 13:18:58'),
            Carbon::parse('2026-08-01 00:00:00'),
        );

        self::assertCount(2, $queries);
        foreach ($queries as $query) {
            self::assertStringContainsString('fl.STATUS_OID = ?', $query['sql']);
            self::assertContains(416, $query['bindings']);
        }

        self::assertStringContainsString('fd.CURRENT_READING', $queries[1]['sql']);
        self::assertStringContainsString('fl.ENTRY_DATE', $queries[1]['sql']);
        self::assertContains('2026-07-26 00:00:00', $queries[1]['bindings']);
    }
}
