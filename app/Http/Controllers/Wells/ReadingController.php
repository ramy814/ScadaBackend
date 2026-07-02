<?php

declare(strict_types=1);

namespace App\Http\Controllers\Wells;
use App\Http\Controllers\Controller;

use App\Services\Wells\ReadingService;
use App\Services\Wells\WellService;
use App\Services\Wells\InspectorService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReadingController extends Controller
{
    public function __construct(
        private readonly ReadingService $readingService,
        private readonly WellService $wellService,
        private readonly InspectorService $inspectorService,
    ) {
    }

    public function index(Request $request): Response
    {
        $filters = $request->only(['well_oid', 'inspector_oid', 'quality', 'date_from', 'date_to']);

        return Inertia::render('WellsSystem/Readings/Index', [
            'readings' => $this->readingService->getPaginatedReadings(15, $filters),
            'wells' => $this->wellService->getActiveWells(),
            'inspectors' => $this->inspectorService->getAllInspectors(),
            'filters' => $filters,
            'statistics' => $this->readingService->getStatistics(),
        ]);
    }

    public function show(int $reading): Response
    {
        return Inertia::render('WellsSystem/Readings/Show', [
            'reading' => $this->readingService->getReading($reading),
        ]);
    }
}
