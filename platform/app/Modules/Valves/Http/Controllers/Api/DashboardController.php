<?php

declare(strict_types=1);

namespace App\Modules\Valves\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Valves\Services\ValveMobileService;
use Illuminate\Http\JsonResponse;

/** /api/dashboard/* — ported from the legacy Api\DashboardController. */
class DashboardController extends Controller
{
    public function __construct(private readonly ValveMobileService $valves) {}

    public function stats(): JsonResponse
    {
        return response()->json($this->valves->stats());
    }

    public function recentActivity(): JsonResponse
    {
        return response()->json($this->valves->recentActivity());
    }
}
