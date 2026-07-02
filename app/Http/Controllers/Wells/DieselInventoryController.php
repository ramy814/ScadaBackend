<?php

declare(strict_types=1);

namespace App\Http\Controllers\Wells;
use App\Http\Controllers\Controller;

use App\Services\Wells\DieselInventoryService;
use App\Services\Wells\InspectorService;
use App\Services\Wells\WellService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DieselInventoryController extends Controller
{
    public function __construct(
        private readonly DieselInventoryService $dieselInventoryService,
        private readonly WellService $wellService,
        private readonly InspectorService $inspectorService,
    ) {
    }

    public function index(Request $request): Response
    {
        $filters = $request->only(['well_oid', 'inspector_oid', 'generator_oid', 'date_from', 'date_to']);

        return Inertia::render('WellsSystem/DieselInventories/Index', [
            'inventories' => $this->dieselInventoryService->getPaginatedInventories(15, $filters),
            'wells' => $this->wellService->getActiveWells(),
            'inspectors' => $this->inspectorService->getAllInspectors(),
            'filters' => $filters,
            'statistics' => $this->dieselInventoryService->getStatistics(),
        ]);
    }
}
