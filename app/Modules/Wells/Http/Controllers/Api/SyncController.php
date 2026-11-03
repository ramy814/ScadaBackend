<?php

declare(strict_types=1);

namespace App\Modules\Wells\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Wells\Http\Requests\SyncDieselInventoriesRequest;
use App\Modules\Wells\Http\Requests\SyncNotificationsRequest;
use App\Modules\Wells\Http\Requests\SyncReadingsRequest;
use App\Modules\Wells\Services\MobileSyncService;
use App\Modules\Wells\Services\WellsMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** /api/wells/sync/* and record sync endpoints — ported from the legacy Api\Wells\SyncController. */
class SyncController extends Controller
{
    public function __construct(
        private readonly WellsMobileService $wells,
        private readonly MobileSyncService $mobileSync,
    ) {}

    public function wells(Request $request): JsonResponse
    {
        return response()->json($this->wells->wells($request->user()));
    }

    public function syncReadings(SyncReadingsRequest $request): JsonResponse
    {
        return response()->json($this->mobileSync->syncReadings($request->validated('readings'), (int) $request->user()->oid));
    }

    public function syncDiesel(SyncDieselInventoriesRequest $request): JsonResponse
    {
        return response()->json($this->mobileSync->syncDieselInventories($request->validated('inventories'), (int) $request->user()->oid));
    }

    public function notificationTypes(): JsonResponse
    {
        return response()->json($this->wells->notificationTypes());
    }

    public function syncNotifications(SyncNotificationsRequest $request): JsonResponse
    {
        return response()->json($this->mobileSync->syncNotifications($request->validated('notifications'), (int) $request->user()->oid));
    }

    public function history(Request $request): JsonResponse
    {
        return response()->json($this->wells->history($request->user()));
    }
}
