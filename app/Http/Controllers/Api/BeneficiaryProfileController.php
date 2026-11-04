<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Services\CurrentBeneficiaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BeneficiaryProfileController extends Controller
{
    public function show(Request $request, CurrentBeneficiaryService $drivers): JsonResponse
    {
        $driver = $drivers->resolve($request->user());

        return response()->json([
            'driver' => $driver->toAuthPayload(),
            'oracle_available' => extension_loaded('oci8'),
        ]);
    }

    public function clarifications(Request $request, CurrentBeneficiaryService $drivers): JsonResponse
    {
        $driver = $drivers->resolve($request->user());

        $items = Trip::query()
            ->where('oracle_driver_id', (string) $driver->getAuthIdentifier())
            ->where('review_status', 'needs_clarification')
            ->latest('reviewed_at')
            ->limit(20)
            ->get()
            ->map(fn ($trip): array => [
                'trip_id' => $trip->id,
                'local_id' => $trip->local_id,
                'vehicle_plate' => $trip->vehicle_plate,
                'origin_text' => $trip->origin_text,
                'destination_text' => $trip->destination_text,
                'review_note' => $trip->review_note,
                'reviewed_at' => optional($trip->reviewed_at)?->toIso8601String(),
                'clarification_response' => $trip->clarification_response,
                'clarification_responded_at' => optional($trip->clarification_responded_at)?->toIso8601String(),
            ])
            ->values();

        return response()->json(['clarifications' => $items]);
    }
}
