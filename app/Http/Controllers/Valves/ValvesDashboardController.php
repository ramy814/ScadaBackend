<?php

declare(strict_types=1);

namespace App\Http\Controllers\Valves;

use App\Http\Controllers\Controller;
use App\Services\Valves\ValvesDashboardService;
use Inertia\Inertia;
use Inertia\Response;

class ValvesDashboardController extends Controller
{
    public function __construct(
        private readonly ValvesDashboardService $dashboardService,
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
            'mapValves' => $this->dashboardService->getStats()['mapValves'] ?? [],
        ]);
    }
}
