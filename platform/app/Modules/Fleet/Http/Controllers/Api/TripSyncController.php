<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Fleet\Http\Requests\ConfirmTripUploadRequest;
use App\Modules\Fleet\Http\Requests\RespondClarificationRequest;
use App\Modules\Fleet\Http\Requests\StoreTripRequest;
use App\Modules\Fleet\Http\Requests\UploadTripPointsRequest;
use App\Modules\Fleet\Models\Trip;
use App\Modules\Fleet\Services\CurrentBeneficiaryService;
use App\Modules\Fleet\Services\DriverApiService;
use App\Modules\Fleet\Services\DriverTripRoutePreviewService;
use App\Modules\Fleet\Services\DriverTripSummaryService;
use App\Modules\Fleet\Services\TripSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Offline-first trip sync — ported from the legacy Api\TripSyncController. The app deletes a
 * local trip only after `can_delete_local` (saved + points + count + checksum confirmed).
 */
class TripSyncController extends Controller
{
    public function __construct(
        private readonly TripSyncService $sync,
        private readonly CurrentBeneficiaryService $drivers,
        private readonly DriverTripSummaryService $summaries,
        private readonly DriverApiService $driverApi,
    ) {}

    public function store(StoreTripRequest $request): JsonResponse
    {
        $trip = $this->sync->storeTrip($this->drivers->resolve($request->user()), $request->validated());

        return response()->json(['server_id' => $trip->id, 'local_id' => $trip->local_id, 'status' => $trip->status], 201);
    }

    public function uploadPoints(UploadTripPointsRequest $request, Trip $trip): JsonResponse
    {
        $this->sync->assertOwnedBy($trip, $this->drivers->resolve($request->user()));
        $received = $this->sync->uploadPoints($trip, $request->validated('points'));

        return response()->json(['server_id' => $trip->id, 'points_received' => $received]);
    }

    public function confirm(ConfirmTripUploadRequest $request, Trip $trip): JsonResponse
    {
        $this->sync->assertOwnedBy($trip, $this->drivers->resolve($request->user()));
        $trip = $this->sync->confirm($trip, (int) $request->validated('points_expected'), $request->validated('checksum'));

        return response()->json([
            'server_id' => $trip->id,
            'status' => $trip->status,
            'confirmed_at' => $trip->confirmed_at,
            'points_received' => $trip->points_received,
            'virtual_fuel_liters' => $trip->virtual_fuel_liters,
            'can_delete_local' => $trip->status === 'confirmed',
            ...$this->summaries->summarize($trip),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->driverApi->trips($this->drivers->resolve($request->user()), $request));
    }

    public function reconcile(Request $request): JsonResponse
    {
        return response()->json($this->driverApi->reconcile($this->drivers->resolve($request->user()), (string) $request->query('local_ids', '')));
    }

    public function routePreview(Request $request, Trip $trip, DriverTripRoutePreviewService $previews): JsonResponse
    {
        $this->sync->assertOwnedBy($trip, $this->drivers->resolve($request->user()));
        abort_unless($trip->status === 'confirmed', 409, 'الرحلة غير مؤكدة.');

        return response()->json($previews->forTrip($trip));
    }

    public function respondClarification(RespondClarificationRequest $request, Trip $trip): JsonResponse
    {
        return response()->json($this->driverApi->respondClarification($this->drivers->resolve($request->user()), $trip, $request->validated('response')));
    }
}
