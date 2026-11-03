<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Repositories;

use App\Modules\Fleet\Models\MissionLocation;
use App\Modules\Fleet\Models\MissionType;
use App\Modules\Fleet\Models\Trip;
use App\Platform\Audit\AuditLog;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Trip queries for the platform. Visibility rules are ported verbatim from the
 * legacy TripRepository (drafts hidden, manual rows limited to the reviewer's
 * Oracle vehicle scope, completed trips under 50 points are "experimental").
 */
class TripRepository
{
    private const ROUTE_PREVIEW_POINTS = 100;

    public const QUEUES = ['awaiting', 'responded', 'clarification', 'decided', 'all'];

    /**
     * The review inbox. Decision queues ignore the period so nothing waiting for a
     * supervisor can fall outside the selected window (legacy only did this for
     * manual trips); the period applies to `decided` and `all`.
     *
     * @param  array{queue: string, source: string, search: string, experimental: bool, from: DateTimeInterface, to: DateTimeInterface}  $filters
     * @param  array<int, string>  $allowedVehicleIds
     */
    public function reviewQueue(array $filters, array $allowedVehicleIds, int $perPage = 25): LengthAwarePaginator
    {
        $query = $this->source($this->visible($filters['experimental'], $allowedVehicleIds), $filters['source'])
            ->with('latestTrackingEvent')
            ->withCount([
                'trackingEvents as gps_disabled_events_count' => fn ($q) => $q->where('event_type', 'gps_disabled'),
                'trackingEvents as airplane_mode_events_count' => fn ($q) => $q->where('event_type', 'airplane_mode_on'),
                'trackingEvents as tracking_gap_events_count' => fn ($q) => $q->where('event_type', 'tracking_gap'),
            ]);

        $this->applyQueue($query, $filters['queue']);

        if (in_array($filters['queue'], ['decided', 'all'], true)) {
            $query->whereBetween('started_at', [$filters['from'], $filters['to']]);
        }

        $search = trim($filters['search']);
        if ($search !== '') {
            $like = '%'.ltrim($search, '#').'%';
            $query->where(function (Builder $q) use ($search, $like): void {
                $q->where('vehicle_plate', 'like', $like)->orWhere('driver_name', 'like', $like);
                if (ctype_digit(ltrim($search, '#'))) {
                    $q->orWhere('id', (int) ltrim($search, '#'));
                }
            });
        }

        return $query->latest('started_at')->latest('id')->paginate($perPage)->withQueryString();
    }

    /**
     * One aggregate for every queue counter (no per-tab query amplification).
     *
     * @param  array<int, string>  $allowedVehicleIds
     * @return array<string, int>
     */
    public function queueCounts(string $source, bool $experimental, array $allowedVehicleIds): array
    {
        $row = $this->source($this->visible($experimental, $allowedVehicleIds), $source)
            ->selectRaw(<<<'SQL'
                SUM(CASE WHEN review_status = 'pending' AND status = 'confirmed' AND ended_at IS NOT NULL THEN 1 ELSE 0 END) AS awaiting,
                SUM(CASE WHEN review_status = 'needs_clarification' AND clarification_response IS NOT NULL THEN 1 ELSE 0 END) AS responded,
                SUM(CASE WHEN review_status = 'needs_clarification' AND clarification_response IS NULL THEN 1 ELSE 0 END) AS clarification
            SQL)
            ->toBase()
            ->first();

        return [
            'awaiting' => (int) ($row->awaiting ?? 0),
            'responded' => (int) ($row->responded ?? 0),
            'clarification' => (int) ($row->clarification ?? 0),
        ];
    }

    /** @param array<int, string> $allowedVehicleIds */
    public function liveTrips(array $allowedVehicleIds): Collection
    {
        return $this->visible(true, $allowedVehicleIds)
            ->whereNull('ended_at')
            ->where(fn (Builder $q) => $q->whereNull('entry_source')->orWhere('entry_source', 'mobile'))
            ->with('latestTrackingEvent')
            ->withMax('points as last_point_at', 'recorded_at')
            ->withCount([
                'trackingEvents as gps_disabled_events_count' => fn ($q) => $q->where('event_type', 'gps_disabled'),
                'trackingEvents as tracking_gap_events_count' => fn ($q) => $q->where('event_type', 'tracking_gap'),
            ])
            ->latest('started_at')
            ->limit(200)
            ->get();
    }

    /**
     * Official period fuel: persisted VIRTUAL_FUEL_LITERS of confirmed visible trips only.
     *
     * @param  array<int, string>  $allowedVehicleIds
     * @return array{liters: float, trips: int}
     */
    public function officialFuel(DateTimeInterface $from, DateTimeInterface $to, array $allowedVehicleIds): array
    {
        $row = $this->visible(false, $allowedVehicleIds)
            ->where('status', 'confirmed')
            ->whereNotNull('virtual_fuel_liters')
            ->whereBetween('started_at', [$from, $to])
            ->selectRaw('SUM(virtual_fuel_liters) AS liters, COUNT(*) AS trips')
            ->toBase()
            ->first();

        return ['liters' => round((float) ($row->liters ?? 0), 1), 'trips' => (int) ($row->trips ?? 0)];
    }

    /** @param array<int, string> $allowedVehicleIds */
    public function report(DateTimeInterface $from, DateTimeInterface $to, string $driver, string $vehicle, array $allowedVehicleIds): Builder
    {
        return $this->visible(true, $allowedVehicleIds)
            ->whereBetween('started_at', [$from, $to])
            ->when($driver !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('driver_name', 'like', "%{$driver}%")
                ->orWhere('driver_phone', 'like', "%{$driver}%")
                ->orWhere('driver_employee_number', 'like', "%{$driver}%")
                ->orWhere('oracle_driver_id', $driver)))
            ->when($vehicle !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('vehicle_plate', 'like', "%{$vehicle}%")
                ->orWhere('vehicle_name', 'like', "%{$vehicle}%")
                ->orWhere('oracle_vehicle_id', $vehicle)))
            ->latest('started_at');
    }

    /**
     * Approved, finished trips that form a vehicle financial claim, attributed by start time
     * (legacy approvedTripsForClaims(): fleet visibility scope, no experimental filter).
     *
     * @param  array<int, string>  $allowedVehicleIds
     * @return Collection<int, Trip>
     */
    public function approvedTripsForClaims(DateTimeInterface $from, DateTimeInterface $to, string $vehicleSearch, array $allowedVehicleIds): Collection
    {
        return $this->visible(true, $allowedVehicleIds)
            ->where('review_status', 'approved')
            ->whereNotNull('oracle_vehicle_id')
            ->whereNotNull('ended_at')
            ->whereBetween('started_at', [$from, $to])
            ->when($vehicleSearch !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('vehicle_plate', 'like', "%{$vehicleSearch}%")
                ->orWhere('vehicle_name', 'like', "%{$vehicleSearch}%")
                ->orWhere('oracle_vehicle_id', $vehicleSearch)))
            ->orderBy('started_at')
            ->get(['id', 'oracle_vehicle_id', 'vehicle_plate', 'vehicle_name', 'driver_name', 'entry_source', 'started_at', 'ended_at', 'hourly_price']);
    }

    public function find(int $tripId): ?Trip
    {
        return Trip::query()->find($tripId);
    }

    public function auditTrail(int $tripId): Collection
    {
        return AuditLog::query()
            ->where('entity_type', 'trip')
            ->where('entity_id', $tripId)
            ->orderBy('id')
            ->get(['id', 'event_type', 'actor', 'payload', 'created_at']);
    }

    /**
     * Atomic review decision. Enforces the documented immutability rule that the
     * legacy controller only enforced in the UI.
     */
    public function decide(int $tripId, string $status, ?string $note, string $actor, string $actorName, ?float $hourlyPrice = null): Trip
    {
        return DB::transaction(function () use ($tripId, $status, $note, $actor, $actorName, $hourlyPrice): Trip {
            $trip = Trip::query()->whereKey($tripId)->lockForUpdate()->firstOrFail();

            if (in_array($trip->review_status, ['approved', 'rejected'], true)) {
                throw ValidationException::withMessages(['decision' => __('fleet.errors.final')]);
            }
            if ($trip->review_status === 'draft' || $trip->status !== 'confirmed' || $trip->ended_at === null) {
                throw ValidationException::withMessages(['decision' => __('fleet.errors.not_ready')]);
            }
            if ($status === 'needs_clarification' && $trip->entry_source === 'manual') {
                throw ValidationException::withMessages(['decision' => __('fleet.errors.manual_clarify')]);
            }

            // Same note composition as the legacy review(): keep the question when a final
            // decision follows a driver's clarification answer.
            if ($note && $trip->review_note && $trip->clarification_response && $status !== 'needs_clarification') {
                $note = 'الإجراء النهائي: '.$note."\n---\nسؤال التوضيح: ".$trip->review_note;
            }

            $trip->update([
                'review_status' => $status,
                'review_note' => $note ?: null,
                'reviewed_at' => now(),
                'reviewed_by' => $actor,
                // The hourly price is frozen at approval only; any other status clears it (legacy rule).
                'hourly_price' => $status === 'approved' ? $hourlyPrice : null,
            ]);

            AuditLog::query()->create([
                'event_type' => 'trip.reviewed',
                'entity_type' => 'trip',
                'entity_id' => $trip->getKey(),
                'actor' => $actor,
                'payload' => [
                    'review_status' => $status, 'note' => $note, 'actor_name' => $actorName, 'source' => 'platform',
                    'hourly_price' => $status === 'approved' ? $hourlyPrice : null,
                ],
            ]);

            return $trip->refresh();
        });
    }

    public function latestUsageByDriver(): Collection
    {
        $ranked = Trip::query()
            ->select(['oracle_driver_id', 'driver_name', 'driver_phone', 'driver_employee_number', 'oracle_vehicle_id', 'vehicle_plate', 'vehicle_name', 'vehicle_type', 'started_at'])
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY oracle_driver_id ORDER BY started_at DESC, id DESC) AS usage_rank')
            ->whereNotNull('oracle_driver_id');

        return Trip::query()->fromSub($ranked, 'latest_driver_trip')->where('usage_rank', 1)->get();
    }

    private function applyQueue(Builder $query, string $queue): void
    {
        match ($queue) {
            'awaiting' => $query->where('review_status', 'pending')->where('status', 'confirmed')->whereNotNull('ended_at'),
            'responded' => $query->where('review_status', 'needs_clarification')->whereNotNull('clarification_response'),
            'clarification' => $query->where('review_status', 'needs_clarification')->whereNull('clarification_response'),
            'decided' => $query->whereIn('review_status', ['approved', 'rejected']),
            default => null,
        };
    }

    private function source(Builder $query, string $source): Builder
    {
        return $query->where(fn (Builder $q) => $source === 'manual'
            ? $q->where('entry_source', 'manual')
            : $q->whereNull('entry_source')->orWhere('entry_source', 'mobile'));
    }

    /**
     * Legacy dashboardTrips() + scopeToFleetVisibility(): drafts never visible, manual
     * rows only inside the reviewer's vehicle scope, experimental trips optional.
     *
     * @param  array<int, string>  $allowedManualVehicleIds
     */
    private function visible(bool $experimental, array $allowedManualVehicleIds): Builder
    {
        $query = Trip::query()
            ->where(fn (Builder $q) => $q->whereNull('entry_source')->orWhere('entry_source', '!=', 'manual')
                ->orWhereNull('review_status')->orWhere('review_status', '!=', 'draft'))
            ->where(function (Builder $q) use ($allowedManualVehicleIds): void {
                $q->whereNull('entry_source')->orWhere('entry_source', 'mobile')
                    ->orWhere(function (Builder $manual) use ($allowedManualVehicleIds): void {
                        $manual->where('entry_source', 'manual');
                        $this->scopeToVehicleIds($manual, $allowedManualVehicleIds);
                    });
            });

        return $experimental ? $query : $query->where(fn (Builder $q) => $q
            ->whereNull('ended_at')->orWhere('entry_source', 'manual')->orWhere('points_received', '>=', 50));
    }

    /** @param array<int, string> $ids */
    private function scopeToVehicleIds(Builder $query, array $ids): void
    {
        $ids = collect($ids)->filter(fn ($id): bool => filled($id))->map(fn ($id): string => (string) $id)->unique()->values();
        if ($ids->isEmpty()) {
            $query->whereRaw('1 = 0');

            return;
        }

        // Oracle IN-list limit: 1000 expressions.
        $query->where(function (Builder $q) use ($ids): void {
            foreach ($ids->chunk(900)->values() as $index => $chunk) {
                $index === 0 ? $q->whereIn('oracle_vehicle_id', $chunk->all()) : $q->orWhereIn('oracle_vehicle_id', $chunk->all());
            }
        });
    }

    // ── Assistant manual entry (ported verbatim from the legacy TripRepository) ──

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
        $query = $this->manualTripsQuery($filters);
        $this->scopeToVehicleIds($query, $vehicleIds);

        return $query
            ->where('review_status', '!=', 'draft')
            ->where('status', 'confirmed')->whereNotNull('ended_at')
            ->orderByRaw("case when review_status = 'pending' then 0 else 1 end")
            ->latest('started_at')->paginate($perPage)->withQueryString();
    }

    public function findManualForApproval(int $tripId): ?Trip
    {
        return Trip::query()->whereKey($tripId)->where('entry_source', 'manual')->first();
    }

    public function approveManual(int $tripId, int $actorId, string $actorName, ?string $note, ?float $hourlyPrice = null): Trip
    {
        return DB::transaction(function () use ($tripId, $actorId, $actorName, $note, $hourlyPrice): Trip {
            $trip = Trip::query()->whereKey($tripId)->where('entry_source', 'manual')->lockForUpdate()->firstOrFail();
            if ($trip->review_status !== 'pending' || $trip->status !== 'confirmed' || $trip->ended_at === null) {
                throw ValidationException::withMessages(['approval' => 'الطلب غير جاهز للاعتماد أو تمت مراجعته مسبقاً.']);
            }

            $trip->update([
                'review_status' => 'approved',
                'review_note' => $note,
                'reviewed_by' => $actorId,
                'reviewed_at' => now(),
                'hourly_price' => $hourlyPrice,
            ]);
            AuditLog::query()->create([
                'event_type' => 'trip.reviewed',
                'entity_type' => 'trip',
                'entity_id' => $trip->getKey(),
                'actor' => (string) $actorId,
                'payload' => [
                    'review_status' => 'approved', 'note' => $note, 'actor_name' => $actorName,
                    'source' => 'manual_assistant', 'hourly_price' => $hourlyPrice,
                ],
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
            ->where('review_status', 'draft')
            ->first();
    }

    public function updateManual(Trip $trip, array $attributes, string $actor): Trip
    {
        return DB::transaction(function () use ($trip, $attributes, $actor): Trip {
            $trip = Trip::query()->whereKey($trip->getKey())->where('entry_source', 'manual')->lockForUpdate()->firstOrFail();
            if ($trip->review_status !== 'draft') {
                throw ValidationException::withMessages(['trip' => 'لا يمكن تعديل الرحلة بعد ترحيلها للاعتماد.']);
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

    public function submitManual(int $tripId, int $actorId, string $actorName): Trip
    {
        return DB::transaction(function () use ($tripId, $actorId, $actorName): Trip {
            $trip = Trip::query()->whereKey($tripId)->where('entry_source', 'manual')
                ->where('entered_by_user_id', $actorId)->lockForUpdate()->firstOrFail();
            if ($trip->review_status !== 'draft' || $trip->status !== 'confirmed' || $trip->ended_at === null) {
                throw ValidationException::withMessages(['trip' => 'لا يمكن ترحيل الرحلة إلا إذا كانت مسودة مكتملة.']);
            }

            $trip->update(['review_status' => 'pending']);
            AuditLog::query()->create([
                'event_type' => 'trip.manual.submitted',
                'entity_type' => 'trip',
                'entity_id' => $trip->getKey(),
                'actor' => (string) $actorId,
                'payload' => ['review_status' => 'pending', 'actor_name' => $actorName],
            ]);

            return $trip->refresh();
        });
    }

    // ── Driver app (ported verbatim for GET /api/driver/trips/{trip}/route-preview) ──

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

    // ── Driver app queries (legacy Api\TripSyncController / BeneficiaryProfileController, unchanged) ──

    /** @param  array{status: string, review_status: string, from: mixed, to: mixed, q: string}  $filters */
    public function paginateForDriver(string $driverId, array $filters, int $perPage, int $page): LengthAwarePaginator
    {
        $query = Trip::query()->where('oracle_driver_id', $driverId)->latest('started_at')->latest('id');

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }
        if ($filters['review_status'] !== '') {
            $query->where('review_status', $filters['review_status']);
        }
        if (! empty($filters['from'])) {
            $query->whereDate('started_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('started_at', '<=', $filters['to']);
        }
        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($nested) use ($q): void {
                $nested->where('local_id', 'like', '%'.$q.'%')
                    ->orWhere('origin_text', 'like', '%'.$q.'%')
                    ->orWhere('destination_text', 'like', '%'.$q.'%')
                    ->orWhere('mission_type', 'like', '%'.$q.'%')
                    ->orWhere('vehicle_plate', 'like', '%'.$q.'%');
                if (ctype_digit($q)) {
                    $nested->orWhere('id', (int) $q);
                }
            });
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /** @param  array<int, string>  $localIds */
    public function driverTripsByLocalIds(string $driverId, array $localIds): Collection
    {
        return Trip::query()->where('oracle_driver_id', $driverId)->whereIn('local_id', $localIds)->get()->keyBy('local_id');
    }

    public function driverClarifications(string $driverId): Collection
    {
        return Trip::query()
            ->where('oracle_driver_id', $driverId)
            ->where('review_status', 'needs_clarification')
            ->latest('reviewed_at')
            ->limit(20)
            ->get();
    }

    public function recordClarificationResponse(Trip $trip, string $response, string $actor): Trip
    {
        $trip->update(['clarification_response' => $response, 'clarification_responded_at' => now()]);

        AuditLog::query()->create([
            'event_type' => 'trip.clarification.responded',
            'entity_type' => 'trip',
            'entity_id' => $trip->id,
            'actor' => $actor,
            'payload' => ['response' => $response],
        ]);

        return $trip;
    }
}
