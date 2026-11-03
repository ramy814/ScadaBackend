<?php

declare(strict_types=1);

namespace Tests\Unit\Fleet;

use App\Modules\Fleet\Models\Trip;
use App\Modules\Fleet\Repositories\TripRepository;
use App\Modules\Fleet\Services\DriverTripRoutePreviewService;
use Mockery;
use PHPUnit\Framework\TestCase;

class DriverTripRoutePreviewServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_returns_at_most_one_hundred_points_and_keeps_endpoints(): void
    {
        $points = collect(range(0, 99))->map(function (int $index): object {
            $sourceIndex = $index === 99 ? 249 : $index * 2;

            return (object) [
                'latitude' => 31.5 + ($sourceIndex / 10000),
                'longitude' => 34.45 + ($sourceIndex / 10000),
            ];
        });
        $repository = Mockery::mock(TripRepository::class);
        $repository->shouldReceive('routePreviewPoints')->once()->andReturn([
            'original_points' => 250,
            'points' => $points,
        ]);
        $trip = new Trip;
        $trip->id = 1591;

        $preview = (new DriverTripRoutePreviewService($repository))->forTrip($trip);

        self::assertSame(250, $preview['original_points']);
        self::assertCount(100, $preview['preview_points']);
        self::assertSame(31.5, $preview['preview_points']->first()['latitude']);
        self::assertSame(31.5249, $preview['preview_points']->last()['latitude']);
    }
}
