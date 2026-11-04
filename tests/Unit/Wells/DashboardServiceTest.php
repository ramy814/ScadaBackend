<?php

declare(strict_types=1);

namespace Tests\Unit\Wells;

use App\Models\Wells\Inspector;
use App\Models\Wells\Well;
use App\Models\Wells\WellReading;
use App\Repositories\Wells\DieselInventoryRepository;
use App\Repositories\Wells\InspectorRepository;
use App\Repositories\Wells\ReadingRepository;
use App\Repositories\Wells\TeamRepository;
use App\Repositories\Wells\WellRepository;
use App\Services\Wells\DashboardService;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_recent_readings_include_reading_and_record_creation_dates(): void
    {
        $well = new Well;
        $well->setRawAttributes(['oid' => 1, 'name' => 'بئر الاختبار']);

        $inspector = new Inspector;
        $inspector->setRawAttributes(['oid' => 2, 'name' => 'مفتش الاختبار']);

        $reading = new WellReading;
        $reading->setRawAttributes([
            'oid' => 3,
            'generator_hours_reading' => '125.5',
            'pump_hours_reading' => '80.25',
            'reading_date' => '2026-07-20 00:00:00',
            'created_at' => '2026-07-23 09:30:00',
        ]);
        $reading->setRelation('well', $well);
        $reading->setRelation('inspector', $inspector);
        $reading->setRelation('media', new Collection);

        $wellRepository = Mockery::mock(WellRepository::class);
        $wellRepository->shouldReceive('getTotalCount')->once()->andReturn(1);

        $teamRepository = Mockery::mock(TeamRepository::class);
        $teamRepository->shouldReceive('getActiveCount')->once()->andReturn(1);

        $inspectorRepository = Mockery::mock(InspectorRepository::class);
        $inspectorRepository->shouldReceive('getActiveCount')->once()->andReturn(1);

        $readingRepository = Mockery::mock(ReadingRepository::class);
        $readingRepository->shouldReceive('getStats')->once()->andReturn([
            'total' => 1,
            'today' => 0,
        ]);
        $readingRepository->shouldReceive('getRecentlySubmittedReadings')
            ->once()
            ->with(5)
            ->andReturn(new Collection([$reading]));

        $dieselRepository = Mockery::mock(DieselInventoryRepository::class);
        $dieselRepository->shouldReceive('getStats')->once()->andReturn([
            'total' => 0,
            'today' => 0,
            'latest_available_fuel_liters' => 0,
        ]);

        $service = new DashboardService(
            $wellRepository,
            $teamRepository,
            $inspectorRepository,
            $readingRepository,
            $dieselRepository,
        );

        $recentReading = $service->getStats()['recentReadings'][0];

        self::assertSame('2026-07-20 00:00:00', $recentReading['reading_date']);
        self::assertSame(
            '2026-07-23 09:30:00',
            $recentReading['created_at']->format('Y-m-d H:i:s'),
        );
        self::assertSame([], $recentReading['media']);
    }
}
