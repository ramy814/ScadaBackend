<?php

namespace App\Repositories;

use App\Models\AuditLog;
use App\Models\MissionLocation;
use App\Models\MissionType;
use App\Models\Trip;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TripRepository
{
    private const ROUTE_PREVIEW_POINTS = 100;

    public function reportQuery(
        DateTimeInterface $from,
        DateTimeInterface $to,
        string $driverSearch,
        string $vehicleSearch,
        array $allowedVehicleIds,
    ): Builder {
        return $this->scopeToVehicleIds(Trip::query(), $allowedVehicleIds)
            ->whereBetween('started_at', [$from, $to])
            ->latest('started_at')
            ->when($driverSearch !== '', function (Builder $query) use ($driverSearch): void {
                $query->where(function (Builder $query) use ($driverSearch): void {
                    $query->where('driver_name', 'like', "%{$driverSearch}%")
                        ->orWhere('driver_phone', 'like', "%{$driverSearch}%")
                        ->orWhere('driver_employee_number', 'like', "%{$driverSearch}%")
                        ->orWhere('oracle_driver_id', $driverSearch);
                });
            })
            ->when($vehicleSearch !== '', function (Builder $query) use ($vehicleSearch): void {
                $query->where(function (Builder $query) use ($vehicleSearch): void {
                    $query->where('vehicle_plate', 'like', "%{$vehicleSearch}%")
                        ->orWhere('vehicle_name', 'like', "%{$vehicleSearch}%")
                        ->orWhere('oracle_vehicle_id', $vehicleSearch);
                });
            });
    }

    /** @return array{locations: Collection<int, string>, mission_types: Collection<int, string>} */
    public function manualTripOptions(): array
    {
        return [
            'locations' => MissionLocation::query()
                ->where('active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->pluck('name'),
            'mission_types' => MissionType::query()
                ->where('active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->pluck('name'),
        ];
    }

    public function createManual(array $attributes, string $actor): Trip
    {
        return DB::transaction(function () use ($attributes, $actor): Trip {
            $trip = Trip::query()->create($attributes);

            AuditLog::query()->create([
                'event_type' => 'trip.manual.created',
                'entity_type' => 'trip',
                'entity_id' => $trip->getKey(),
                'actor' => $actor,
                'payload' => [
                    'vehicle_id' => $trip->oracle_vehicle_id,
                    'vehicle_plate' => $trip->vehicle_plate,
                    'started_at' => optional($trip->started_at)->toISOString(),
                    'ended_at' => optional($trip->ended_at)->toISOString(),
                    'estimated_distance_km' => $trip->distance_km,
                ],
            ]);

            return $trip;
        });
    }

    /** @param array<string, mixed> $filters */
    public function paginateManualForAssistant(int $ssoUserId, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->manualTripsQuery($filters)
            ->where('entered_by_user_id', $ssoUserId)
            ->latest('started_at')->paginate($perPage)->withQueryString();
    }

    public function paginateManualForApproval(array $vehicleIds, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->scopeToVehicleIds($this->manualTripsQuery($filters), $vehicleIds)
            ->where('status', 'confirmed')->whereNotNull('ended_at')
            ->orderByRaw("case when review_status = 'pending' then 0 else 1 end")
            ->latest('started_at')->paginate($perPage)->withQueryString();
    }

    public function findManualForApproval(int $tripId): ?Trip
    {
        return Trip::query()->whereKey($tripId)->where('entry_source', 'manual')->first();
    }

    public function approveManual(int $tripId, int $actorId, string $actorName, ?string $note): Trip
    {
        return DB::transaction(function () use ($tripId, $actorId, $actorName, $note): Trip {
            $trip = Trip::query()->whereKey($tripId)->where('entry_source', 'manual')->lockForUpdate()->firstOrFail();
            if ($trip->review_status !== 'pending' || $trip->status !== 'confirmed' || $trip->ended_at === null) {
                throw ValidationException::withMessages(['approval' => 'الطلب غير جاهز للاعتماد أو تمت مراجعته مسبقاً.']);
            }

            $trip->update([
                'review_status' => 'approved',
                'review_note' => $note,
                'reviewed_by' => $actorId,
                'reviewed_at' => now(),
            ]);
            AuditLog::query()->create([
                'event_type' => 'trip.reviewed',
                'entity_type' => 'trip',
                'entity_id' => $trip->getKey(),
                'actor' => (string) $actorId,
                'payload' => ['review_status' => 'approved', 'note' => $note, 'actor_name' => $actorName, 'source' => 'manual_assistant'],
            ]);

            return $trip->refresh();
        });
    }

    private function manualTripsQuery(array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return Trip::query()
            ->where('entry_source', 'manual')
            ->when(filled($filters['review_status'] ?? null) && $filters['review_status'] !== 'all', fn (Builder $query) => $query
                ->where('review_status', $filters['review_status']))
            ->when(filled($filters['oracle_vehicle_id'] ?? null), fn (Builder $query) => $query
                ->where('oracle_vehicle_id', $filters['oracle_vehicle_id']))
            ->when(filled($filters['date_from'] ?? null), fn (Builder $query) => $query
                ->where('started_at', '>=', CarbonImmutable::parse($filters['date_from'])->startOfDay()))
            ->when(filled($filters['date_to'] ?? null), fn (Builder $query) => $query
                ->where('started_at', '<=', CarbonImmutable::parse($filters['date_to'])->endOfDay()))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = "%{$search}%";
                $query->where(function (Builder $query) use ($search, $like): void {
                    $query->where('vehicle_plate', 'like', $like)
                        ->orWhere('driver_name', 'like', $like)
                        ->orWhere('origin_text', 'like', $like)
                        ->orWhere('destination_text', 'like', $like)
                        ->orWhere('mission_type', 'like', $like);

                    if (ctype_digit($search)) {
                        $query->orWhere('id', (int) $search);
                    }
                });
            });
    }

    public function findEditableManualForAssistant(int $tripId, int $ssoUserId): ?Trip
    {
        return Trip::query()
            ->whereKey($tripId)
            ->where('entry_source', 'manual')
            ->where('entered_by_user_id', $ssoUserId)
            ->where('review_status', 'pending')
            ->first();
    }

    public function updateManual(Trip $trip, array $attributes, string $actor): Trip
    {
        return DB::transaction(function () use ($trip, $attributes, $actor): Trip {
            $trip = Trip::query()->whereKey($trip->getKey())->where('entry_source', 'manual')->lockForUpdate()->firstOrFail();
            if ($trip->review_status !== 'pending') {
                throw ValidationException::withMessages(['trip' => 'لا يمكن تعديل الطلب بعد مراجعته.']);
            }
            $trip->update($attributes);

            AuditLog::query()->create([
                'event_type' => 'trip.manual.updated',
                'entity_type' => 'trip',
                'entity_id' => $trip->getKey(),
                'actor' => $actor,
                'payload' => [
                    'vehicle_id' => $trip->oracle_vehicle_id,
                    'vehicle_plate' => $trip->vehicle_plate,
                    'started_at' => optional($trip->started_at)->toISOString(),
                    'ended_at' => optional($trip->ended_at)->toISOString(),
                    'estimated_distance_km' => $trip->distance_km,
                ],
            ]);

            return $trip->refresh();
        });
    }

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
        string $tripSource = 'mobile',
        int $perPage = 30,
        ?array $allowedVehicleIds = null,
    ): LengthAwarePaginator {
        $query = $this->scopeToVehicleIds(
            $this->dashboardTrips($showExperimental),
            $allowedVehicleIds,
        )
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
                    ->orWhereNull('ended_at')
                    ->orWhere(function ($query): void {
                        $query->where('entry_source', 'manual')
                            ->where('review_status', 'pending');
                    });
            })
            ->where(function (Builder $query) use ($tripSource): void {
                $tripSource === 'manual'
                    ? $query->where('entry_source', 'manual')
                    : $query->whereNull('entry_source')->orWhere('entry_source', 'mobile');
            });

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

        if ($tripSource === 'manual') {
            $query->orderByRaw("CASE WHEN review_status = 'pending' THEN 0 ELSE 1 END");
        }

        return $query->latest('started_at')->paginate($perPage)->withQueryString();
    }

    public function dashboardCounts(
        bool $showExperimental = false,
        ?array $allowedVehicleIds = null,
    ): array {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $counts = $this->scopeToVehicleIds(
            $this->dashboardTrips($showExperimental),
            $allowedVehicleIds,
        )
            ->selectRaw(
                <<<'SQL'
                    SUM(CASE WHEN started_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS today_trips,
                    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed_trips,
                    SUM(CASE WHEN review_status = 'approved' THEN 1 ELSE 0 END) AS approved_trips,
                    SUM(CASE WHEN review_status = 'rejected' THEN 1 ELSE 0 END) AS rejected_trips,
                    SUM(CASE WHEN entry_source = 'manual' AND review_status = 'pending' THEN 1 ELSE 0 END) AS pending_manual_trips,
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
            'manualPendingTrips' => (int) ($counts?->pending_manual_trips ?? 0),
            'clarificationTrips' => (int) ($counts?->clarification_trips ?? 0),
            'clarificationPendingResponse' => (int) ($counts?->clarification_pending_response ?? 0),
            'clarificationResponded' => (int) ($counts?->clarification_responded ?? 0),
        ];
    }

    public function dashboardFuelConsumption(
        DateTimeInterface $from,
        DateTimeInterface $to,
        bool $showExperimental = false,
        ?array $allowedVehicleIds = null,
    ): array {
        $fuel = $this->scopeToVehicleIds(
            $this->dashboardTrips($showExperimental),
            $allowedVehicleIds,
        )
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
                ->orWhere('entry_source', 'manual')
                ->orWhere('points_received', '>=', 50));
    }

    private function scopeToVehicleIds(Builder $query, ?array $allowedVehicleIds): Builder
    {
        if ($allowedVehicleIds === null) {
            return $query;
        }

        $ids = collect($allowedVehicleIds)
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): string => (string) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $query) use ($ids): void {
            foreach ($ids->chunk(900)->values() as $index => $chunk) {
                $index === 0
                    ? $query->whereIn('oracle_vehicle_id', $chunk->all())
                    : $query->orWhereIn('oracle_vehicle_id', $chunk->all());
            }
        });
    }
}
