<?php

namespace App\Modules\Fleet\Services;

use App\Modules\Fleet\Models\Trip;
use App\Modules\Fleet\Repositories\TripRepository;

class DriverTripRoutePreviewService
{
    public function __construct(private readonly TripRepository $trips) {}

    public function forTrip(Trip $trip): array
    {
        $preview = $this->trips->routePreviewPoints($trip);

        return [
            'server_id' => $trip->id,
            'original_points' => $preview['original_points'],
            'preview_points' => $preview['points']
                ->map(fn ($point): array => [
                    'latitude' => round((float) $point->latitude, 6),
                    'longitude' => round((float) $point->longitude, 6),
                ])
                ->values(),
        ];
    }
}
