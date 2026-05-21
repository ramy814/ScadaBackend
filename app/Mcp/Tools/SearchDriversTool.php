<?php

namespace App\Mcp\Tools;

use App\Repositories\MunicipalityDriverRepository;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Search for drivers (beneficiaries) in the municipality fleet by name, phone, or employee number.')]
class SearchDriversTool extends Tool
{
    public function __construct(
        protected MunicipalityDriverRepository $driverRepository
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

        $drivers = $this->driverRepository->search($term, $limit);

        if ($drivers->isEmpty()) {
            return Response::text('No drivers found matching the search term.');
        }

        $formatted = $drivers->map(function ($driver) {
            return sprintf(
                "- Name: %s | Phone: %s | Employee Num: %s (Oracle ID: %s)",
                $driver['name'] ?? 'N/A',
                $driver['phone'] ?? 'N/A',
                $driver['employee_number'] ?? 'N/A',
                $driver['oracle_id']
            );
        })->implode("\n");

        return Response::text("Found the following drivers:\n" . $formatted);
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
                ->description('The driver name, phone, or employee number to search for (partial matches supported).')
                ->nullable(),
            'limit' => $schema->integer()
                ->description('The maximum number of results to return (default 25).')
                ->default(25),
        ];
    }
}
