<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Services;

use App\Modules\Fleet\Models\Trip;
use App\Modules\Fleet\Repositories\TripRepository;
use Carbon\CarbonImmutable;

/**
 * Live supervision of trips without ended_at. Health rules are the legacy
 * Dashboard.vue rules moved server-side: last point within 90 s is "live";
 * a critical latest device event is technical evidence, never proof of intent.
 */
class FleetLiveService
{
    public const LIVE_SECONDS = 90;

    public const CRITICAL_EVENTS = ['gps_disabled', 'airplane_mode_on', 'device_shutdown', 'location_permission_missing', 'tracking_gap', 'clock_anomaly'];

    public function __construct(
        private readonly TripRepository $trips,
        private readonly FleetAccessService $access,
    ) {}

    /** @return array{trips: array<int, array<string, mixed>>, summary: array<string, int>} */
    public function overview(int $userId): array
    {
        $rows = $this->trips->liveTrips($this->access->allowedVehicleIds($userId))
            ->map(fn (Trip $trip): array => $this->row($trip))->values();

        return [
            'trips' => $rows->all(),
            'summary' => [
                'total' => $rows->count(),
                'live' => $rows->where('health', 'live')->count(),
                'stale' => $rows->whereIn('health', ['stale', 'waiting'])->count(),
                'incident' => $rows->where('health', 'incident')->count(),
            ],
            'refreshSeconds' => 15,
        ];
    }

    public function health(?string $latestEvent, ?string $lastPointAt, int $incidentCount): string
    {
        return match (true) {
            $incidentCount > 0, in_array($latestEvent, self::CRITICAL_EVENTS, true) => 'incident',
            $latestEvent === 'tracking_error' => 'stale',
            $lastPointAt === null => 'waiting',
            CarbonImmutable::parse($lastPointAt)->diffInSeconds(now()) <= self::LIVE_SECONDS => 'live',
            default => 'stale',
        };
    }

    /** @return array<string, mixed> */
    private function row(Trip $trip): array
    {
        $incidents = (int) $trip->gps_disabled_events_count + (int) $trip->tracking_gap_events_count;
        $lastPoint = $trip->last_point_at ? CarbonImmutable::parse($trip->last_point_at)->toIso8601String() : null;

        return [
            'id' => (int) $trip->getKey(),
            'driver_name' => $trip->driver_name,
            'vehicle_plate' => $trip->vehicle_plate,
            'origin' => $trip->origin_text,
            'destination' => $trip->destination_text,
            'started_at' => $trip->started_at?->toIso8601String(),
            'last_point_at' => $lastPoint,
            'points_received' => (int) $trip->points_received,
            'latest_event' => $trip->latestTrackingEvent?->event_type,
            'health' => $this->health($trip->latestTrackingEvent?->event_type, $lastPoint, $incidents),
        ];
    }
}
