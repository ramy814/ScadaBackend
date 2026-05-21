<?php

namespace App\Mcp\Tools;

use App\Models\Trip;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List recent trips recorded in the fleet management local database.')]
class ListRecentTripsTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'status' => 'nullable|string|max:50',
            'review_status' => 'nullable|in:pending,approved,rejected,needs_clarification',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $status = $validated['status'] ?? null;
        $reviewStatus = $validated['review_status'] ?? null;
        $limit = $validated['limit'] ?? 10;

        $trips = Trip::query()
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($reviewStatus, fn ($query) => $query->where('review_status', $reviewStatus))
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();

        if ($trips->isEmpty()) {
            return Response::text('No recent trips found in local database.');
        }

        $formatted = $trips->map(function (Trip $trip) {
            return sprintf(
                "- ID: %d | Driver: %s | Vehicle Plate: %s | Status: %s | Review: %s | From: %s | To: %s | Started: %s",
                $trip->id,
                $trip->driver_name,
                $trip->vehicle_plate,
                $trip->status,
                $trip->review_status,
                $trip->origin_text ?? 'N/A',
                $trip->destination_text ?? 'N/A',
                $trip->started_at ? $trip->started_at->toDateTimeString() : 'N/A'
            );
        })->implode("\n");

        return Response::text("Recent trips found:\n" . $formatted);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()
                ->description('Filter trips by execution status (e.g. uploaded, confirmed).')
                ->nullable(),
            'review_status' => $schema->string()
                ->description('Filter trips by admin review status. Allowed: pending, approved, rejected, needs_clarification.')
                ->nullable(),
            'limit' => $schema->integer()
                ->description('Number of recent trips to return (default 10, max 50).')
                ->default(10),
        ];
    }
}
