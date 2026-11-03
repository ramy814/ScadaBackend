<?php

declare(strict_types=1);

namespace App\Modules\Wells\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Wells\Http\Requests\StoreActivityMediaRequest;
use App\Modules\Wells\Services\WellsMobileService;
use Illuminate\Http\JsonResponse;

/** POST /api/wells/activity-media/sync — ported from the legacy Api\Wells\ActivityMediaController. */
class ActivityMediaController extends Controller
{
    public function store(StoreActivityMediaRequest $request, WellsMobileService $wells): JsonResponse
    {
        return response()->json($wells->storeActivityMedia($request->user(), $request->validated()));
    }
}
