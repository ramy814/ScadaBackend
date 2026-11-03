<?php

declare(strict_types=1);

namespace Tests\Unit\Wells;

use App\Modules\Wells\Models\Inspector;
use App\Modules\Wells\Models\Well;
use App\Modules\Wells\Models\WellReading;
use App\Modules\Wells\Repositories\ReadingRepository;
use App\Modules\Wells\Repositories\WellRepository;
use App\Modules\Wells\Services\ReadingService;
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
            'generator_hours_reading' => '30',
            'pump_hours_reading' => '300',
            'reading_date' => '2026-08-02 08:00:00',
        ]);
        $reading->setRelation('inspector', $inspector);

        $previous = new WellReading;
        $previous->setRawAttributes([
            'oid' => 9,
            'well_oid' => 1,
            'generator_hours_reading' => '20',
            'pump_hours_reading' => '100',
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
        self::assertSame(20.0, $report['rows'][0]['previous_reading']);
        self::assertSame(30.0, $report['rows'][0]['current_reading']);
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

    public function test_export_uses_only_requested_allowed_unique_columns(): void
    {
        $readingRepository = Mockery::mock(ReadingRepository::class);
        $readingRepository->shouldReceive('getReportReadings')
            ->once()
            ->with([])
            ->andReturn(new Collection);

        $wellRepository = Mockery::mock(WellRepository::class);
        $wellRepository->shouldReceive('getActiveWells')
            ->once()
            ->andReturn(new Collection);

        $service = new ReadingService($readingRepository, $wellRepository);
        $report = $service->getExportReport([], [
            'production',
            'not_a_report_column',
            'well_name',
            'production',
        ]);

        self::assertSame(
            ['production', 'well_name'],
            array_column($report['columns'], 'key'),
        );

        $html = view('wells.readings-report', [
            'report' => $report,
            'format' => 'excel',
        ])->render();

        self::assertStringContainsString('الإنتاج (م³)', $html);
        self::assertStringContainsString('اسم البئر', $html);
        self::assertStringNotContainsString('الإحداثيات', $html);
    }

    public function test_negative_readings_and_differences_over_twenty_hours_are_abnormal(): void
    {
        $inspector = new Inspector;
        $inspector->setRawAttributes(['oid' => 7, 'name' => 'المفتش الأول']);
        $cases = [
            1 => ['current' => -1, 'previous' => -5],
            2 => ['current' => 121, 'previous' => 100],
            3 => ['current' => 120, 'previous' => 100],
        ];
        $readings = new Collection;
        $previousReadings = [];

        foreach ($cases as $wellId => $case) {
            $reading = new WellReading;
            $reading->setRawAttributes([
                'oid' => $wellId + 10,
                'well_oid' => $wellId,
                'generator_hours_reading' => $case['current'],
                'reading_date' => '2026-08-11 08:00:00',
            ]);
            $reading->setRelation('inspector', $inspector);
            $readings->push($reading);

            $previous = new WellReading;
            $previous->setRawAttributes([
                'oid' => $wellId,
                'well_oid' => $wellId,
                'generator_hours_reading' => $case['previous'],
                'reading_date' => '2026-08-10 08:00:00',
            ]);
            $previousReadings[] = [$reading, $previous];
        }

        $readingRepository = Mockery::mock(ReadingRepository::class);
        $readingRepository->shouldReceive('getReportReadings')
            ->once()
            ->with([])
            ->andReturn($readings);

        foreach ($previousReadings as [$reading, $previous]) {
            $readingRepository->shouldReceive('getPreviousReading')
                ->once()
                ->with($reading)
                ->andReturn($previous);
        }

        $wellRepository = Mockery::mock(WellRepository::class);
        $wellRepository->shouldReceive('getActiveWells')
            ->once()
            ->andReturn(new Collection([
                $this->well(1, 'قراءة سالبة', 50),
                $this->well(2, 'فرق 21 ساعة', 50),
                $this->well(3, 'فرق 20 ساعة', 50),
            ]));

        $report = (new ReadingService($readingRepository, $wellRepository))
            ->getProductionReport();

        self::assertSame('abnormal', $report['rows'][0]['status']['key']);
        self::assertSame('abnormal', $report['rows'][1]['status']['key']);
        self::assertSame('normal', $report['rows'][2]['status']['key']);
        self::assertSame(2, $report['summary']['review_wells']);
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
