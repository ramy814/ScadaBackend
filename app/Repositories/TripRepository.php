<?php

namespace App\Repositories;

use App\Models\Trip;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TripRepository
{
    public function paginateForDashboard(
        DateTimeInterface $from,
        DateTimeInterface $to,
        string $responseStatus,
        string $search,
        int $perPage = 30,
    ): LengthAwarePaginator {
        $query = Trip::query()
            ->whereBetween('started_at', [$from, $to])
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
