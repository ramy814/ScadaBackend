<?php

declare(strict_types=1);

namespace App\Http\Controllers\Wells;
use App\Http\Controllers\Controller;

use App\Services\Wells\DashboardService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('WellsSystem/Dashboard', [
            'stats' => $this->dashboardService->getStats(),
        ]);
    }
}
