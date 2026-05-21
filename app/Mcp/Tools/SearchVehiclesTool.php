<?php

namespace App\Mcp\Tools;

use App\Repositories\MunicipalityVehicleRepository;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Search for vehicles in the municipality fleet by plate number.')]
class SearchVehiclesTool extends Tool
{
    public function __construct(
        protected MunicipalityVehicleRepository $vehicleRepository
    ) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'term' => 'nullable|string|max:100',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $term = $validated['term'] ?? null;
        $limit = $validated['limit'] ?? 25;

        $vehicles = $this->vehicleRepository->search($term, $limit);

        if ($vehicles->isEmpty()) {
            return Response::text('No vehicles found matching the search term.');
        }

        $formatted = $vehicles->map(function ($vehicle) {
            return sprintf(
                "- Plate: %s | Name: %s | Type: %s (Oracle ID: %s)",
                $vehicle['plate_number'],
                $vehicle['name'] ?? 'N/A',
                $vehicle['vehicle_type'] ?? 'N/A',
                $vehicle['oracle_id']
            );
        })->implode("\n");

        return Response::text("Found the following vehicles:\n" . $formatted);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'term' => $schema->string()
                ->description('The vehicle plate number to search for (partial or full matches supported).')
                ->nullable(),
            'limit' => $schema->integer()
                ->description('The maximum number of results to return (default 25).')
                ->default(25),
        ];
    }
}
