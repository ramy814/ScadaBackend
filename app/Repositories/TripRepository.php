<?php

namespace App\Repositories;

use App\Models\Trip;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TripRepository
{
    private const ROUTE_PREVIEW_POINTS = 100;

    /** @return array{original_points: int, points: Collection<int, mixed>} */
    public function routePreviewPoints(Trip $trip): array
    {
        $eligible = $trip->points()
            ->where('client_is_valid', true)
            ->where(function ($query): void {
                $query->whereNull('quality_reason')
                    ->orWhere('quality_reason', '!=', 'stationary_heartbeat');
            });

        $stats = (clone $eligible)
            ->selectRaw(
                'COUNT(*) AS original_points, MIN(sequence_number) AS first_sequence, MAX(sequence_number) AS last_sequence'
            )
            ->first();
        $originalPoints = (int) ($stats?->original_points ?? 0);
        if ($originalPoints === 0) {
            return ['original_points' => 0, 'points' => collect()];
        }

        if ($originalPoints <= self::ROUTE_PREVIEW_POINTS) {
            return [
                'original_points' => $originalPoints,
                'points' => $eligible
                    ->orderBy('sequence_number')
                    ->get(['latitude', 'longitude'])
                    ->values(),
            ];
        }

        $firstSequence = (int) $stats->first_sequence;
        $lastSequence = (int) $stats->last_sequence;
        $sampleSize = min(self::ROUTE_PREVIEW_POINTS, $originalPoints);
        $sequenceNumbers = collect(range(0, $sampleSize - 1))
            ->map(fn (int $index): int => (int) round(
                $firstSequence
                + ($index * ($lastSequence - $firstSequence) / max(1, $sampleSize - 1))
            ))
            ->unique()
            ->values();

        $points = $eligible
            ->whereIn('sequence_number', $sequenceNumbers->all())
            ->orderBy('sequence_number')
            ->get(['latitude', 'longitude'])
            ->values();

        return ['original_points' => $originalPoints, 'points' => $points];
    }

    public function latestUsageByDriver(): Collection
    {
        $ranked = Trip::query()
            ->select([
                'oracle_driver_id',
                'driver_name',
                'driver_phone',
                'driver_employee_number',
                'oracle_vehicle_id',
                'vehicle_plate',
                'vehicle_name',
                'vehicle_type',
                'started_at',
            ])
            ->selectRaw(
                'ROW_NUMBER() OVER (PARTITION BY oracle_driver_id ORDER BY started_at DESC, id DESC) AS usage_rank'
            )
            ->whereNotNull('oracle_driver_id');

        return Trip::query()
            ->fromSub($ranked, 'latest_driver_trip')
            ->where('usage_rank', 1)
            ->get();
    }

    public function paginateForDashboard(
        DateTimeInterface $from,
        DateTimeInterface $to,
        string $responseStatus,
        string $search,
        bool $showExperimental = false,
        int $perPage = 30,
    ): LengthAwarePaginator {
        $query = $this->dashboardTrips($showExperimental)
            ->with('latestTrackingEvent')
            ->withCount([
                'trackingEvents as gps_disabled_events_count' => fn ($query) => $query
                    ->where('event_type', 'gps_disabled'),
                'trackingEvents as airplane_mode_events_count' => fn ($query) => $query
                    ->where('event_type', 'airplane_mode_on'),
                'trackingEvents as tracking_gap_events_count' => fn ($query) => $query
                    ->where('event_type', 'tracking_gap'),
            ])
            ->withMax('points as last_point_at', 'recorded_at')
            ->where(function ($query) use ($from, $to): void {
                $query->whereBetween('started_at', [$from, $to])
                    ->orWhereNull('ended_at');
            })
            ->latest('started_at');

        if ($responseStatus === 'pending') {
            $query->where('review_status', 'needs_clarification')
                ->whereNull('clarification_response');
        } elseif ($responseStatus === 'responded') {
            $query->whereNotNull('clarification_response');
        }

        if ($search !== '') {
            $likeSearch = "%{$search}%";

            $query->where(function ($query) use ($likeSearch, $search): void {
                $query->where('vehicle_plate', 'like', $likeSearch)
                    ->orWhere('driver_name', 'like', $likeSearch);

                if (ctype_digit($search)) {
                    $query->orWhere('id', (int) $search);
                }
            });
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function dashboardCounts(bool $showExperimental = false): array
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $counts = $this->dashboardTrips($showExperimental)
            ->selectRaw(
                <<<'SQL'
                    SUM(CASE WHEN started_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS today_trips,
                    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed_trips,
                    SUM(CASE WHEN review_status = 'approved' THEN 1 ELSE 0 END) AS approved_trips,
                    SUM(CASE WHEN review_status = 'rejected' THEN 1 ELSE 0 END) AS rejected_trips,
                    SUM(CASE WHEN review_status = 'needs_clarification' THEN 1 ELSE 0 END) AS clarification_trips,
                    SUM(CASE WHEN review_status = 'needs_clarification' AND clarification_response IS NULL THEN 1 ELSE 0 END) AS clarification_pending_response,
                    SUM(CASE WHEN clarification_response IS NOT NULL THEN 1 ELSE 0 END) AS clarification_responded
                SQL,
                [$todayStart, $todayEnd],
            )
            ->first();

        return [
            'todayTrips' => (int) ($counts?->today_trips ?? 0),
            'confirmedTrips' => (int) ($counts?->confirmed_trips ?? 0),
            'approvedTrips' => (int) ($counts?->approved_trips ?? 0),
            'rejectedTrips' => (int) ($counts?->rejected_trips ?? 0),
            'clarificationTrips' => (int) ($counts?->clarification_trips ?? 0),
            'clarificationPendingResponse' => (int) ($counts?->clarification_pending_response ?? 0),
            'clarificationResponded' => (int) ($counts?->clarification_responded ?? 0),
        ];
    }

    public function dashboardFuelConsumption(
        DateTimeInterface $from,
        DateTimeInterface $to,
        bool $showExperimental = false,
    ): array {
        $fuel = $this->dashboardTrips($showExperimental)
            ->where('status', 'confirmed')
            ->whereNotNull('virtual_fuel_liters')
            ->whereBetween('started_at', [$from, $to])
            ->selectRaw('SUM(virtual_fuel_liters) AS fuel_liters, COUNT(*) AS trip_count')
            ->first();

        return [
            'fuelConsumptionLiters' => round((float) ($fuel?->fuel_liters ?? 0), 3),
            'fuelConsumptionTrips' => (int) ($fuel?->trip_count ?? 0),
        ];
    }

    private function dashboardTrips(bool $showExperimental): Builder
    {
        $query = Trip::query();

        return $showExperimental
            ? $query
            : $query->where(fn (Builder $query) => $query
                ->whereNull('ended_at')
                ->orWhere('points_received', '>=', 50));
    }
}
