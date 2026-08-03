<?php

declare(strict_types=1);

namespace Tests\Unit\Wells;

use App\Models\Wells\Inspector;
use App\Models\Wells\Well;
use App\Models\Wells\WellReading;
use App\Repositories\Wells\ReadingRepository;
use App\Repositories\Wells\WellRepository;
use App\Services\Wells\ReadingService;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use Tests\TestCase;

class ReadingProductionReportTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_report_includes_active_wells_without_matching_readings(): void
    {
        $reportedWell = $this->well(1, 'البئر المدخل', 50.0);
        $missingWell = $this->well(2, 'البئر غير المدخل', 60.0);
        $inspector = new Inspector;
        $inspector->setRawAttributes(['oid' => 7, 'name' => 'المفتش الأول']);

        $reading = new WellReading;
        $reading->setRawAttributes([
            'oid' => 10,
            'well_oid' => 1,
            'pump_hours_reading' => '30',
            'reading_date' => '2026-08-02 08:00:00',
        ]);
        $reading->setRelation('inspector', $inspector);

        $previous = new WellReading;
        $previous->setRawAttributes([
            'oid' => 9,
            'well_oid' => 1,
            'pump_hours_reading' => '20',
            'reading_date' => '2026-08-01 08:00:00',
        ]);

        $readingRepository = Mockery::mock(ReadingRepository::class);
        $readingRepository->shouldReceive('getReportReadings')
            ->once()
            ->with(['date_to' => '2026-08-02'])
            ->andReturn(new Collection([$reading]));
        $readingRepository->shouldReceive('getPreviousReading')
            ->once()
            ->with($reading)
            ->andReturn($previous);

        $wellRepository = Mockery::mock(WellRepository::class);
        $wellRepository->shouldReceive('getActiveWells')
            ->once()
            ->andReturn(new Collection([$reportedWell, $missingWell]));

        $report = (new ReadingService($readingRepository, $wellRepository))
            ->getProductionReport(['date_to' => '2026-08-02']);

        self::assertCount(2, $report['rows']);
        self::assertSame('normal', $report['rows'][0]['status']['key']);
        self::assertSame(10.0, $report['rows'][0]['operating_hours']);
        self::assertSame(500.0, $report['rows'][0]['production']);
        self::assertSame('missing', $report['rows'][1]['status']['key']);
        self::assertNull($report['rows'][1]['current_reading']);
        self::assertSame([
            'total_active_wells' => 2,
            'reported_wells' => 1,
            'missing_wells' => 1,
            'normal_wells' => 1,
            'first_reading_wells' => 0,
            'review_wells' => 0,
            'completion_percentage' => 50.0,
        ], $report['summary']);
    }

    private function well(int $id, string $name, float $capacity): Well
    {
        $well = new Well;
        $well->setRawAttributes([
            'oid' => $id,
            'name' => $name,
            'well_num' => 'W-'.$id,
            'source_well_code' => 'OFF-'.$id,
            'capacity' => $capacity,
            'latitude' => '31.5',
            'longitude' => '34.45',
            'is_active' => 1,
        ]);

        return $well;
    }
}
