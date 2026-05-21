<?php

namespace App\Mcp\Tools;

use App\Models\Trip;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Retrieve full details of a specific trip, including driver, vehicle, passengers, and stop locations.')]
class GetTripDetailsTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'trip_id' => 'required|integer|min:1',
        ]);

        $tripId = $validated['trip_id'];

        $trip = Trip::with(['stops', 'passengers'])->find($tripId);

        if (!$trip) {
            return Response::text("Trip with ID {$tripId} not found.");
        }

        // Prepare details
        $details = [];
        $details[] = "=== Trip #{$trip->id} Details ===";
        $details[] = "Local ID: {$trip->local_id}";
        $details[] = "Status: {$trip->status}";
        $details[] = "Review Status: {$trip->review_status}";
        if ($trip->review_note) {
            $details[] = "Review Note: {$trip->review_note}";
        }
        $details[] = "Driver: {$trip->driver_name} (Phone: {$trip->driver_phone}, Employee #: {$trip->driver_employee_number}, Oracle ID: {$trip->oracle_driver_id})";
        $details[] = "Vehicle: Name: {$trip->vehicle_name} | Plate: {$trip->vehicle_plate} | Type: {$trip->vehicle_type} (Oracle ID: {$trip->oracle_vehicle_id})";
        $details[] = "Odometer: Start: {$trip->start_odometer} | End: {$trip->end_odometer}";
        $details[] = "Duration/Wait: Started: {$trip->started_at} | Ended: {$trip->ended_at} | Wait: {$trip->wait_minutes} mins";
        $details[] = "Route: From '{$trip->origin_text}' to '{$trip->destination_text}'";
        $details[] = "Mission Type: {$trip->mission_type}";
        $details[] = "GPS Points: Expected: {$trip->points_expected} | Received: {$trip->points_received}";
        $details[] = "Virtual Fuel: {$trip->virtual_fuel_liters} Liters";

        if ($trip->passengers->isNotEmpty()) {
            $details[] = "\n--- Passengers ({$trip->passengers->count()}) ---";
            foreach ($trip->passengers as $passenger) {
                $details[] = sprintf(
                    "- %s (ID: %s, Source: %s)",
                    $passenger->name ?? 'N/A',
                    $passenger->employee_id ?? 'N/A',
                    $passenger->source ?? 'N/A'
                );
            }
        }

        if ($trip->stops->isNotEmpty()) {
            $details[] = "\n--- Stops ({$trip->stops->count()}) ---";
            foreach ($trip->stops as $stop) {
                $details[] = sprintf(
                    "- Reason: %s | Notes: %s | Duration: %s to %s",
                    $stop->reason ?? 'N/A',
                    $stop->notes ?? 'N/A',
                    $stop->started_at ? $stop->started_at->toDateTimeString() : 'N/A',
                    $stop->ended_at ? $stop->ended_at->toDateTimeString() : 'N/A'
                );
            }
        }

        return Response::text(implode("\n", $details));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'trip_id' => $schema->integer()
                ->description('The local database ID of the trip.')
                ->required(),
        ];
    }
}
