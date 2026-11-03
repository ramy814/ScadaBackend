<?php

declare(strict_types=1);

namespace App\Modules\Valves\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Valves\Http\Requests\UpdateMaintenanceReportRequest;
use App\Modules\Valves\Models\ValveMaintenanceReport;
use App\Modules\Valves\Services\ValveMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** /api/valve-maintenance/* — ported from the legacy Valves\ValveMaintenanceController. */
class MaintenanceController extends Controller
{
    public function __construct(private readonly ValveMobileService $valves) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->valves->maintenance($request->only(['status', 'priority'])));
    }

    public function update(UpdateMaintenanceReportRequest $request, ValveMaintenanceReport $maintenanceReport): JsonResponse
    {
        return response()->json($this->valves->updateMaintenance($request->user(), $maintenanceReport, $request->validated()));
    }
}
