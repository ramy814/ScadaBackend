<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Services;

use App\Modules\Fleet\Models\Trip;
use App\Modules\Fleet\Repositories\TripRepository;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TripReviewService
{
    public const DECISIONS = [
        'approve' => 'approved',
        'reject' => 'rejected',
        'clarify' => 'needs_clarification',
    ];

    public function __construct(
        private readonly TripRepository $trips,
        private readonly FleetAccessService $access,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated ReviewQueueRequest data
     * @return array{trips: LengthAwarePaginator, counts: array<string, int>, filters: array<string, mixed>}
     */
    public function queue(int $userId, array $input): array
    {
        [$from, $to] = $this->period($input['period'] ?? 'month', $input['date_from'] ?? null, $input['date_to'] ?? null);
        $filters = [
            'queue' => $input['queue'] ?? 'awaiting',
            'source' => $input['source'] ?? 'mobile',
            'search' => (string) ($input['search'] ?? ''),
            'experimental' => (bool) ($input['experimental'] ?? false),
            'period' => $input['period'] ?? 'month',
            'date_from' => $input['date_from'] ?? null,
            'date_to' => $input['date_to'] ?? null,
        ];
        $scope = $this->access->allowedVehicleIds($userId);

        $trips = $this->trips->reviewQueue([...$filters, 'from' => $from, 'to' => $to], $scope);
        $trips->getCollection()->transform(fn (Trip $trip): array => $this->row($trip, $userId));

        return [
            'trips' => $trips,
            'counts' => [
                'mobile' => $this->trips->queueCounts('mobile', $filters['experimental'], $scope),
                'manual' => $this->trips->queueCounts('manual', $filters['experimental'], $scope),
            ],
            'filters' => $filters,
        ];
    }

    public function decide(int $userId, string $actorName, Trip $trip, string $decision, ?string $note): Trip
    {
        if (! $this->access->canReview($userId, $trip)) {
            throw new AuthorizationException(__('fleet.errors.scope'));
        }

        return $this->trips->decide((int) $trip->getKey(), self::DECISIONS[$decision], $note, (string) $userId, $actorName);
    }

    /** @return array<string, mixed> */
    private function row(Trip $trip, int $userId): array
    {
        $event = $trip->latestTrackingEvent;

        return [
            'id' => (int) $trip->getKey(),
            'source' => $trip->entry_source ?? 'mobile',
            'status' => $trip->status,
            'review_status' => $trip->review_status,
            'started_at' => $trip->started_at?->toIso8601String(),
            'ended_at' => $trip->ended_at?->toIso8601String(),
            'driver_name' => $trip->driver_name,
            'vehicle_plate' => $trip->vehicle_plate,
            'vehicle_name' => $trip->vehicle_name,
            'origin' => $trip->origin_text,
            'destination' => $trip->destination_text,
            'mission_type' => $trip->mission_type,
            'distance_km' => $trip->distance_km === null ? null : (float) $trip->distance_km,
            'fuel_liters' => $trip->virtual_fuel_liters === null ? null : (float) $trip->virtual_fuel_liters,
            'points_received' => (int) $trip->points_received,
            'experimental' => $trip->ended_at !== null && $trip->entry_source !== 'manual' && (int) $trip->points_received < 50,
            'has_response' => filled($trip->clarification_response),
            'entered_by_name' => $trip->entered_by_name,
            'evidence' => [
                'gps_disabled' => (int) ($trip->gps_disabled_events_count ?? 0),
                'airplane_mode' => (int) ($trip->airplane_mode_events_count ?? 0),
                'tracking_gap' => (int) ($trip->tracking_gap_events_count ?? 0),
                'latest' => $event?->event_type,
            ],
            'can_review' => $this->access->inScope($userId, $trip),
        ];
    }

    /** Same windows as the legacy dashboard; custom includes whole calendar days. */
    public function period(string $period, ?string $from, ?string $to): array
    {
        $now = CarbonImmutable::now();

        return match (true) {
            $period === 'custom' && $from && $to => [CarbonImmutable::parse($from)->startOfDay(), CarbonImmutable::parse($to)->endOfDay()],
            $period === 'day' => [$now->subDay(), $now],
            $period === 'week' => [$now->subWeek(), $now],
            default => [$now->subMonth(), $now],
        };
    }
}
