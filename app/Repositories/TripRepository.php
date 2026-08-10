<?php

namespace App\Repositories;

use App\Models\Trip;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TripRepository
{
    public function routePreviewPoints(Trip $trip): Collection
    {
        return $trip->points()
            ->where('client_is_valid', true)
            ->where(function ($query): void {
                $query->whereNull('quality_reason')
                    ->orWhere('quality_reason', '!=', 'stationary_heartbeat');
            })
            ->orderBy('sequence_number')
            ->get(['latitude', 'longitude'])
            ->values();
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
        $trips = $this->dashboardTrips($showExperimental);

        return [
            'todayTrips' => (clone $trips)->whereDate('started_at', now()->toDateString())->count(),
            'confirmedTrips' => (clone $trips)->where('status', 'confirmed')->count(),
            'approvedTrips' => (clone $trips)->where('review_status', 'approved')->count(),
            'rejectedTrips' => (clone $trips)->where('review_status', 'rejected')->count(),
            'clarificationTrips' => (clone $trips)->where('review_status', 'needs_clarification')->count(),
            'clarificationPendingResponse' => (clone $trips)
                ->where('review_status', 'needs_clarification')
                ->whereNull('clarification_response')
                ->count(),
            'clarificationResponded' => (clone $trips)->whereNotNull('clarification_response')->count(),
        ];
    }

    private function dashboardTrips(bool $showExperimental): Builder
    {
        $query = Trip::query();

        return $showExperimental
            ? $query
            : $query->where(fn (Builder $query) => $query
                ->whereNull('ended_at')
                ->orWhereHas('points', fn (Builder $query) => $query, '>=', 50));
    }
}
