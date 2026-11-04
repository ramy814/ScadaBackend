<?php

declare(strict_types=1);

namespace App\Http\Controllers\Valves;

use App\Http\Controllers\Controller;
use App\Models\Valves\ValveInspector;
use App\Models\Valves\ValveTeam;
use App\Services\Valves\ValveReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ValvesReportController extends Controller
{
    public function __construct(
        private readonly ValveReportService $reportService,
    ) {
    }

    public function index(Request $request): Response
    {
        $filters = $request->only([
            'inspector_oid',
            'team_oid',
            'action',
            'valve_code',
            'date_from',
            'date_to',
        ]);

        return Inertia::render('Valves/Reports/Index', [
            'logs' => $this->reportService->getLogs($filters),
            'filters' => $filters,
            'teams' => ValveTeam::active()->orderBy('NAME')->get(['oid', 'NAME']),
            'inspectors' => ValveInspector::active()->orderBy('NAME')->get(['oid', 'NAME']),
        ]);
    }
}
