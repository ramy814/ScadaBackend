<?php

declare(strict_types=1);

namespace App\Http\Controllers\Valves;

use App\Http\Controllers\Controller;
use App\Services\Valves\ValveService;
use App\Services\Valves\ValvesDashboardService;
use Inertia\Inertia;
use Inertia\Response;

class ValvesDashboardController extends Controller
{
    public function __construct(
        private readonly ValvesDashboardService $dashboardService,
        private readonly ValveService $valveService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Valves/Dashboard', [
            'stats' => $this->dashboardService->getStats(),
        ]);
    }

    public function map(): Response
    {
        return Inertia::render('Valves/Map', [
            'mapValves' => $this->valveService->getMapValves(),
            'mapFeaturesUrl' => route('valves.map.features'),
        ]);
    }
}
