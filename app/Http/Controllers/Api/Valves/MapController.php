<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Valves;

use App\Http\Controllers\Controller;
use App\Services\Valves\ValvesMapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MapController extends Controller
{
    public function __construct(
        private readonly ValvesMapService $mapService,
    ) {
    }

    public function settings(Request $request): JsonResponse
    {
        return response()->json(
            $this->mapService->settings($request->getSchemeAndHttpHost()),
        );
    }

    public function features(Request $request): JsonResponse
    {
        return response()->json(
            $this->mapService->features(
                (string) $request->query('layers', ''),
                (float) $request->query('lat', 0),
                (float) $request->query('lng', 0),
            ),
        );
    }
}
