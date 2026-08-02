<?php

namespace App\Repositories;

use App\Models\Trip;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TripRepository
{
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
        int $perPage = 30,
    ): LengthAwarePaginator {
        $query = Trip::query()
            ->with('latestTrackingEvent')
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
}
