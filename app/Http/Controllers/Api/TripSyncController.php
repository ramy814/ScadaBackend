<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmTripUploadRequest;
use App\Http\Requests\RespondClarificationRequest;
use App\Http\Requests\StoreTripRequest;
use App\Http\Requests\UploadTripPointsRequest;
use App\Models\AuditLog;
use App\Models\Trip;
use App\Services\CurrentBeneficiaryService;
use App\Services\DriverTripRoutePreviewService;
use App\Services\DriverTripSummaryService;
use App\Services\TripSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TripSyncController extends Controller
{
    public function store(
        StoreTripRequest $request,
        TripSyncService $sync,
        CurrentBeneficiaryService $drivers,
    ): JsonResponse {
        $driver = $drivers->resolve($request->user());
        $trip = $sync->storeTrip($driver, $request->validated());

        return response()->json([
            'server_id' => $trip->id,
            'local_id' => $trip->local_id,
            'status' => $trip->status,
        ], 201);
    }

    public function uploadPoints(
        UploadTripPointsRequest $request,
        Trip $trip,
        TripSyncService $sync,
        CurrentBeneficiaryService $drivers,
    ): JsonResponse {
        $driver = $drivers->resolve($request->user());
        $sync->assertOwnedBy($trip, $driver);
        $received = $sync->uploadPoints($trip, $request->validated('points'));

        return response()->json([
            'server_id' => $trip->id,
            'points_received' => $received,
        ]);
    }

    public function confirm(
        ConfirmTripUploadRequest $request,
        Trip $trip,
        TripSyncService $sync,
        DriverTripSummaryService $summaries,
        CurrentBeneficiaryService $drivers,
    ): JsonResponse {
        $driver = $drivers->resolve($request->user());
        $sync->assertOwnedBy($trip, $driver);
        $trip = $sync->confirm(
            $trip,
            (int) $request->validated('points_expected'),
            $request->validated('checksum'),
        );

        return response()->json([
            'server_id' => $trip->id,
            'status' => $trip->status,
            'confirmed_at' => $trip->confirmed_at,
            'points_received' => $trip->points_received,
            'virtual_fuel_liters' => $trip->virtual_fuel_liters,
            'can_delete_local' => $trip->status === 'confirmed',
            ...$summaries->summarize($trip),
        ]);
    }

    public function index(
        Request $request,
        CurrentBeneficiaryService $drivers,
        DriverTripSummaryService $summaries,
    ): JsonResponse {
        $driver = $drivers->resolve($request->user());
        $status = (string) $request->query('status', '');
        $reviewStatus = (string) $request->query('review_status', '');
        $from = $request->query('from');
        $to = $request->query('to');
        $q = trim((string) $request->query('q', ''));
        $perPage = (int) $request->query(
            'per_page',
            $request->query('limit', 20),
        );
        $perPage = min(max($perPage, 1), $request->has('per_page') ? 50 : 300);

        $query = Trip::query()
            ->where('oracle_driver_id', (string) $driver->getAuthIdentifier())
            ->latest('started_at')
            ->latest('id');

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($reviewStatus !== '') {
            $query->where('review_status', $reviewStatus);
        }

        if (! empty($from)) {
            $query->whereDate('started_at', '>=', $from);
        }

        if (! empty($to)) {
            $query->whereDate('started_at', '<=', $to);
        }

        if ($q !== '') {
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

        $paginator = $query->paginate(
            $perPage,
            ['*'],
            'page',
            max((int) $request->query('page', 1), 1),
        );
        $trips = $paginator
            ->getCollection()
            ->map(fn (Trip $trip): array => [
                'id' => $trip->id,
                'local_id' => $trip->local_id,
                'vehicle_plate' => $trip->vehicle_plate,
                'origin_text' => $trip->origin_text,
                'destination_text' => $trip->destination_text,
                'mission_type' => $trip->mission_type,
                'started_at' => optional($trip->started_at)?->toIso8601String(),
                'ended_at' => optional($trip->ended_at)?->toIso8601String(),
                'status' => $trip->status,
                'review_status' => $trip->review_status,
                'virtual_fuel_liters' => $trip->virtual_fuel_liters,
                'points_expected' => $trip->points_expected,
                'points_received' => $trip->points_received,
                'confirmed_at' => optional($trip->confirmed_at)?->toIso8601String(),
                ...$summaries->summarize($trip),
            ])
            ->values();

        return response()->json([
            'trips' => $trips,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function reconcile(
        Request $request,
        CurrentBeneficiaryService $drivers,
        DriverTripSummaryService $summaries,
    ): JsonResponse {
        $driver = $drivers->resolve($request->user());
        $localIdsCsv = trim((string) $request->query('local_ids', ''));
        $localIds = collect(explode(',', $localIdsCsv))
            ->map(fn ($id) => trim($id))
            ->filter()
            ->values();

        if ($localIds->isEmpty()) {
            return response()->json([
                'items' => [],
                'count' => 0,
            ]);
        }

        $rows = Trip::query()
            ->where('oracle_driver_id', (string) $driver->getAuthIdentifier())
            ->whereIn('local_id', $localIds->all())
            ->get()
            ->keyBy('local_id');

        $items = $localIds->map(function (string $localId) use ($rows, $summaries): array {
            /** @var Trip|null $trip */
            $trip = $rows->get($localId);
            if (! $trip) {
                return [
                    'local_id' => $localId,
                    'uploaded' => false,
                    'status' => 'missing',
                ];
            }

            return [
                'local_id' => $localId,
                'uploaded' => true,
                'server_id' => $trip->id,
                'status' => $trip->status,
                'review_status' => $trip->review_status,
                'confirmed_at' => optional($trip->confirmed_at)?->toIso8601String(),
                'points_expected' => $trip->points_expected,
                'points_received' => $trip->points_received,
                'virtual_fuel_liters' => $trip->virtual_fuel_liters,
                'vehicle_plate' => $trip->vehicle_plate,
                ...$summaries->summarize($trip),
            ];
        })->values();

        return response()->json([
            'items' => $items,
            'count' => $items->count(),
        ]);
    }

    public function routePreview(
        Request $request,
        Trip $trip,
        TripSyncService $sync,
        DriverTripRoutePreviewService $previews,
        CurrentBeneficiaryService $drivers,
    ): JsonResponse {
        $driver = $drivers->resolve($request->user());
        $sync->assertOwnedBy($trip, $driver);
        abort_unless($trip->status === 'confirmed', 409, 'الرحلة غير مؤكدة.');

        return response()->json($previews->forTrip($trip));
    }

    public function respondClarification(
        RespondClarificationRequest $request,
        Trip $trip,
        CurrentBeneficiaryService $drivers,
    ): JsonResponse {
        $driver = $drivers->resolve($request->user());
        abort_unless((string) $trip->oracle_driver_id === (string) $driver->getAuthIdentifier(), 403, 'غير مسموح.');
        abort_unless($trip->review_status === 'needs_clarification', 422, 'لا يوجد طلب توضيح نشط.');

        $trip->update([
            'clarification_response' => $request->validated('response'),
            'clarification_responded_at' => now(),
        ]);

        AuditLog::query()->create([
            'event_type' => 'trip.clarification.responded',
            'entity_type' => 'trip',
            'entity_id' => $trip->id,
            'actor' => 'driver:'.$driver->getAuthIdentifier(),
            'payload' => [
                'response' => $request->validated('response'),
            ],
        ]);

        return response()->json([
            'trip_id' => $trip->id,
            'status' => 'responded',
            'clarification_responded_at' => $trip->clarification_responded_at,
        ]);
    }
}
