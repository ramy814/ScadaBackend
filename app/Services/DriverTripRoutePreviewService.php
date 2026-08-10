<?php

namespace App\Services;

use App\Models\Trip;
use App\Repositories\TripRepository;

class DriverTripRoutePreviewService
{
    private const MAX_POINTS = 100;

    public function __construct(private readonly TripRepository $trips) {}

    public function forTrip(Trip $trip): array
    {
        $points = $this->trips->routePreviewPoints($trip);
        $originalCount = $points->count();

        if ($originalCount > self::MAX_POINTS) {
            $lastIndex = $originalCount - 1;
            $points = collect(range(0, self::MAX_POINTS - 1))
                ->map(fn (int $index) => $points[
                    (int) round($index * $lastIndex / (self::MAX_POINTS - 1))
                ]);
        }

        return [
            'server_id' => $trip->id,
            'original_points' => $originalCount,
            'preview_points' => $points
                ->map(fn ($point): array => [
                    'latitude' => round((float) $point->latitude, 6),
                    'longitude' => round((float) $point->longitude, 6),
                ])
                ->values(),
        ];
    }
}
