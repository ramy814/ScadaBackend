<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Trip;
use App\Repositories\TripRepository;
use App\Services\DriverTripRoutePreviewService;
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
        $points = collect(range(0, 249))->map(fn (int $index): object => (object) [
            'latitude' => 31.5 + ($index / 10000),
            'longitude' => 34.45 + ($index / 10000),
        ]);
        $repository = Mockery::mock(TripRepository::class);
        $repository->shouldReceive('routePreviewPoints')->once()->andReturn($points);
        $trip = new Trip;
        $trip->id = 1591;

        $preview = (new DriverTripRoutePreviewService($repository))->forTrip($trip);

        self::assertSame(250, $preview['original_points']);
        self::assertCount(100, $preview['preview_points']);
        self::assertSame(31.5, $preview['preview_points']->first()['latitude']);
        self::assertSame(31.5249, $preview['preview_points']->last()['latitude']);
    }
}
