<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Wells;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wells\SyncDieselInventoriesRequest;
use App\Http\Requests\Wells\SyncNotificationsRequest;
use App\Http\Requests\Wells\SyncReadingsRequest;
use App\Models\Wells\DieselInventory;
use App\Models\Wells\NotificationType;
use App\Models\Wells\Well;
use App\Models\Wells\WellNotification;
use App\Models\Wells\WellReading;
use App\Services\Wells\MobileSyncService;
use App\Services\Wells\WellService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function __construct(
        private readonly WellService $wellService,
        private readonly MobileSyncService $mobileSync,
    ) {}

    /**
     * Get all active wells formatted for the mobile app
     */
    public function wells(Request $request): JsonResponse
    {
        return response()->json([
            'wells' => $this->wellService->getSyncWellsForInspector((int) $request->user()->oid),
        ]);
    }

    /**
     * Sync readings from mobile app to server
     */
    public function syncReadings(SyncReadingsRequest $request): JsonResponse
    {
        return response()->json($this->mobileSync->syncReadings(
            $request->validated('readings'),
            (int) $request->user()->oid,
        ));
    }

    /**
     * Sync diesel inventories from mobile app to server
     */
    public function syncDiesel(SyncDieselInventoriesRequest $request): JsonResponse
    {
        return response()->json($this->mobileSync->syncDieselInventories(
            $request->validated('inventories'),
            (int) $request->user()->oid,
        ));
    }

    /**
     * Get active notification types
     */
    public function notificationTypes(): JsonResponse
    {
        $types = NotificationType::active()->get()->map(function ($type) {
            return [
                'oid' => $type->oid,
                'name' => $type->name,
                'description' => $type->description,
                'is_active' => $type->is_active,
            ];
        });

        return response()->json([
            'notification_types' => $types,
        ]);
    }

    /**
     * Sync notifications from mobile app to server
     */
    public function syncNotifications(SyncNotificationsRequest $request): JsonResponse
    {
        return response()->json($this->mobileSync->syncNotifications(
            $request->validated('notifications'),
            (int) $request->user()->oid,
        ));
    }

    /**
     * Get recent history for accessible wells
     */
    public function history(Request $request): JsonResponse
    {
        $inspector = $request->user();

        $wellOids = Well::active()
            ->whereHas('teams', function ($query) use ($inspector) {
                $query->whereIn('teams.oid', $inspector->teams()->pluck('teams.oid'));
            })
            ->pluck('oid');

        $readings = [];
        $dieselInventories = [];
        $notifications = [];

        foreach ($wellOids as $wellOid) {
            // Get last 3 readings for this well
            $wellReadings = WellReading::where('well_oid', $wellOid)
                ->orderBy('reading_date', 'desc')
                ->take(3)
                ->get();
            $readings = array_merge($readings, $wellReadings->toArray());

            // Get last 3 diesels
            $wellDiesels = DieselInventory::where('well_oid', $wellOid)
                ->orderBy('inventory_date', 'desc')
                ->take(3)
                ->get();
            $dieselInventories = array_merge($dieselInventories, $wellDiesels->toArray());

            // Get last 3 notifications
            $wellNotifications = WellNotification::where('well_oid', $wellOid)
                ->orderBy('created_at', 'desc')
                ->take(3)
                ->get();
            $notifications = array_merge($notifications, $wellNotifications->toArray());
        }

        return response()->json([
            'readings' => $readings,
            'diesel_inventories' => $dieselInventories,
            'notifications' => $notifications,
        ]);
    }
}
