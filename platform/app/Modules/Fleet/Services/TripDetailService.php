<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Services;

use App\Modules\Fleet\Models\Trip;
use App\Modules\Fleet\Repositories\TripRepository;

/**
 * Trip detail composition, moved out of the legacy AdminAuthController::showTrip().
 * Distance/fuel policy is unchanged: quality-v2 or sufficient metadata uses the
 * filtered metrics; older trips keep their recorded polyline and stored fuel.
 * Gap estimates stay review-only and are never persisted. Read-only: unlike the
 * legacy page, viewing a trip never writes driver snapshots back to Oracle.
 */
class TripDetailService
{
    public function __construct(
        private readonly VirtualFuelService $fuel,
        private readonly TripRepository $trips,
        private readonly FleetAccessService $access,
    ) {}

    /** @return array<string, mixed> */
    public function present(Trip $trip, int $userId): array
    {
        $trip->load([
            'stops:id,trip_id,started_at,ended_at,latitude,longitude,source,reason,notes',
            'trackingEvents:id,trip_id,event_type,detected_at,latitude,longitude,details_json',
            'passengers:id,trip_id,employee_id,name,source',
        ]);

        $metrics = $trip->entry_source === 'manual' ? null : $this->metrics($trip);

        return [
            'trip' => [
                'id' => (int) $trip->getKey(),
                'source' => $trip->entry_source ?? 'mobile',
                'status' => $trip->status,
                'review_status' => $trip->review_status,
                'review_note' => $trip->review_note,
                'reviewed_at' => $trip->reviewed_at?->toIso8601String(),
                'clarification_response' => $trip->clarification_response,
                'clarification_responded_at' => $trip->clarification_responded_at?->toIso8601String(),
                'started_at' => $trip->started_at?->toIso8601String(),
                'ended_at' => $trip->ended_at?->toIso8601String(),
                'driver' => ['name' => $trip->driver_name, 'phone' => $trip->driver_phone, 'employee_number' => $trip->driver_employee_number],
                'vehicle' => ['plate' => $trip->vehicle_plate, 'name' => $trip->vehicle_name, 'type' => $trip->vehicle_type],
                'origin' => $trip->origin_text,
                'destination' => $trip->destination_text,
                'mission_type' => $trip->mission_type,
                'notes' => $trip->notes,
                'odometer' => ['start' => $trip->start_odometer, 'end' => $trip->end_odometer],
                'wait_minutes' => $trip->wait_minutes,
                'points' => ['received' => (int) $trip->points_received, 'expected' => (int) $trip->points_expected],
                'stored_distance_km' => $trip->distance_km === null ? null : (float) $trip->distance_km,
                'stored_fuel_liters' => $trip->virtual_fuel_liters === null ? null : (float) $trip->virtual_fuel_liters,
                'entered_by_name' => $trip->entered_by_name,
                'passengers' => $trip->passengers->map(fn ($p): array => ['name' => $p->name, 'employee_id' => $p->employee_id])->values(),
                'stops' => $trip->stops->map(fn ($s): array => [
                    'started_at' => $s->started_at?->toIso8601String(),
                    'ended_at' => $s->ended_at?->toIso8601String(),
                    'minutes' => $s->started_at && $s->ended_at ? max(0, $s->started_at->diffInMinutes($s->ended_at)) : null,
                    'latitude' => $s->latitude === null ? null : (float) $s->latitude,
                    'longitude' => $s->longitude === null ? null : (float) $s->longitude,
                    'source' => $s->source,
                    'reason' => $s->reason,
                ])->values(),
                'events' => $trip->trackingEvents->sortBy('detected_at')->map(fn ($e): array => [
                    'type' => $e->event_type,
                    'detected_at' => $e->detected_at?->toIso8601String(),
                ])->values(),
            ],
            'metrics' => $metrics,
            'audit' => $this->trips->auditTrail((int) $trip->getKey())->map(fn ($log): array => [
                'id' => $log->id,
                'event' => $log->event_type,
                'actor' => $log->payload['actor_name'] ?? $log->actor,
                'review_status' => $log->payload['review_status'] ?? null,
                'note' => $log->payload['note'] ?? null,
                'at' => $log->created_at?->toIso8601String(),
            ])->values(),
            'can' => [
                'review' => ! in_array($trip->review_status, ['approved', 'rejected', 'draft'], true)
                    && $trip->status === 'confirmed' && $trip->ended_at !== null
                    && $this->access->canReview($userId, $trip),
                'clarify' => $trip->entry_source !== 'manual',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function metrics(Trip $trip): array
    {
        $points = $trip->points()->select([
            'id', 'trip_id', 'sequence_number', 'latitude', 'longitude', 'accuracy', 'speed',
            'captured_at', 'recorded_at', 'client_is_valid', 'quality_reason',
        ])->orderBy('sequence_number');

        $qualityVersion = (int) ($trip->quality_version ?? 0);
        $filtered = $this->fuel->metricsFromPoints($points->cursor(), $trip->started_at, $trip->ended_at, true);
        $hasFiltered = $qualityVersion >= 2 || $this->fuel->hasSufficientQualityData($filtered);

        if ($hasFiltered) {
            $metrics = $filtered;
            $complete = $trip->status === 'confirmed' && (int) $trip->points_received === (int) $trip->points_expected;
            if ($qualityVersion >= 2 && ! $complete) {
                $metrics['quality_flags'] = array_values(array_unique([...$metrics['quality_flags'], 'incomplete_upload']));
            }
            $fuel = $qualityVersion >= 2 ? ($complete ? $this->fuel->estimateFromMetrics($trip, $metrics) : null) : $trip->virtual_fuel_liters;
        } else {
            $metrics = $this->recordedMetrics($points->cursor(), $trip);
            $fuel = $trip->virtual_fuel_liters;
        }

        $provisional = false;
        if ($fuel === null && $hasFiltered) {
            $fuel = $this->fuel->estimateProvisionalFromMetrics($metrics);
            $provisional = $fuel !== null;
        }

        return [
            'filtered' => $hasFiltered,
            'segments' => $metrics['accepted_segments'],
            'gaps' => $metrics['gap_connectors'] ?? [],
            'distance_km' => round((float) $metrics['distance_km'], 2),
            'recorded_distance_km' => round((float) $metrics['raw_distance_km'], 2),
            'duration_minutes' => $metrics['duration_minutes'],
            'quality_flags' => $metrics['quality_flags'],
            'rejected_points' => $metrics['rejected_points'],
            'fuel_liters' => $fuel === null ? null : round((float) $fuel, 2),
            'fuel_provisional' => $provisional,
            'gap_estimate' => [
                'distance_km' => round((float) ($metrics['estimated_gap_distance_km'] ?? 0), 2),
                'distance_with_gaps_km' => round((float) ($metrics['estimated_distance_with_gaps_km'] ?? 0), 2),
                'fuel_with_gaps_liters' => round((float) ($metrics['estimated_fuel_with_gaps_liters'] ?? 0), 2),
                'estimated' => (int) ($metrics['estimated_gap_count'] ?? 0),
                'unestimated' => (int) ($metrics['unestimated_gap_count'] ?? 0),
            ],
        ];
    }

    /** Legacy policy for old trips without accuracy metadata: recorded polyline + Haversine. */
    private function recordedMetrics(iterable $points, Trip $trip): array
    {
        $distance = 0.0;
        $previous = null;
        $coordinates = [];
        foreach ($points as $point) {
            $current = ['lat' => (float) $point->latitude, 'lng' => (float) $point->longitude];
            if ($previous !== null) {
                $distance += $this->haversineKm($previous, $current);
            }
            $coordinates[] = $current;
            $previous = $current;
        }
        $duration = $trip->started_at && $trip->ended_at ? $trip->started_at->diffInMinutes($trip->ended_at) : null;

        return [
            'accepted_segments' => $coordinates === [] ? [] : [$coordinates],
            'gap_connectors' => [],
            'distance_km' => round($distance, 3),
            'raw_distance_km' => round($distance, 3),
            'duration_minutes' => $duration,
            'rejected_points' => 0,
            'quality_flags' => [],
        ];
    }

    /** @param array{lat: float, lng: float} $a @param array{lat: float, lng: float} $b */
    private function haversineKm(array $a, array $b): float
    {
        $h = sin(deg2rad($b['lat'] - $a['lat']) / 2) ** 2
            + cos(deg2rad($a['lat'])) * cos(deg2rad($b['lat'])) * sin(deg2rad($b['lng'] - $a['lng']) / 2) ** 2;
        $h = min(1.0, max(0.0, $h));

        return 6371.0 * 2 * atan2(sqrt($h), sqrt(max(0.0, 1 - $h)));
    }
}
