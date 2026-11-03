<?php

declare(strict_types=1);

namespace App\Modules\Valves\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Valves\Http\Requests\StoreValveActionRequest;
use App\Modules\Valves\Models\Valve;
use App\Modules\Valves\Services\ValveMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** /api/valves/* — ported from the legacy Valves\ValveController (JSON endpoints). */
class ValveController extends Controller
{
    public function __construct(private readonly ValveMobileService $valves) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->valves->assigned($request->user(), $request->only(['area', 'status'])));
    }

    public function show(Request $request, Valve $valve): JsonResponse
    {
        return response()->json($this->valves->show($request->user(), $valve));
    }

    public function action(StoreValveActionRequest $request, Valve $valve): JsonResponse
    {
        return response()->json($this->valves->act($request->user(), $valve, $request->validated()));
    }

    public function logs(Request $request, Valve $valve): JsonResponse
    {
        return response()->json($this->valves->logs($request->user(), $valve));
    }
}
