<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Wells;

use App\Http\Controllers\Controller;
use App\Models\Wells\DieselInventory;
use App\Models\Wells\NotificationType;
use App\Models\Wells\Well;
use App\Models\Wells\WellNotification;
use App\Models\Wells\WellReading;
use App\Repositories\Wells\DieselInventoryRepository;
use App\Repositories\Wells\ReadingRepository;
use App\Services\Wells\DieselFuelCalculator;
use App\Services\Wells\WellService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SyncController extends Controller
{
    public function __construct(
        private readonly DieselFuelCalculator $fuelCalculator,
        private readonly WellService $wellService,
        private readonly ReadingRepository $readingRepository,
        private readonly DieselInventoryRepository $dieselInventoryRepository,
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
    public function syncReadings(Request $request): JsonResponse
    {
        $request->validate([
            'readings' => ['required', 'array'],
            'readings.*.LOCAL_ID' => ['nullable', 'string', 'max:64'],
        ]);

        $syncedIds = [];
        $syncedRecords = [];
        $inspector = $request->user();

        DB::transaction(function () use (
            $request,
            $inspector,
            &$syncedIds,
            &$syncedRecords,
        ) {
            foreach ($request->readings as $readingData) {
                $localId = $this->payloadValue($readingData, ['LOCAL_ID', 'local_id']);
                $reading = $localId === null
                    ? null
                    : $this->readingRepository->findSyncedByLocalIdIncludingDeleted(
                        $localId,
                        (int) $inspector->oid,
                    );

                if ($reading !== null) {
                    $syncedIds[] = $localId;
                    $syncedRecords[] = [
                        'local_id' => $localId,
                        'oid' => $reading->getKey(),
                    ];

                    continue;
                }

                $generatorHours = $this->payloadValue($readingData, [
                    'GENERATOR_HOURS_READING',
                    'generator_hours_reading',
                    'FLOW_RATE',
                    'flow_rate',
                ]);
                $pumpHours = $this->payloadValue($readingData, [
                    'PUMP_HOURS_READING',
                    'pump_hours_reading',
                ]);

                $reading = new WellReading;
                $reading->well_oid = $this->payloadValue($readingData, ['WELL_OID', 'well_oid']);
                $reading->inspector_oid = $inspector->oid;
                $reading->flow_rate = $generatorHours ?? 0;
                $reading->generator_hours_reading = $generatorHours;
                $reading->pump_hours_reading = $pumpHours;
                $reading->reading_date = Carbon::parse($this->payloadValue($readingData, ['READING_DATE', 'reading_date']));
                $reading->latitude = $this->payloadValue($readingData, ['LATITUDE', 'latitude']);
                $reading->longitude = $this->payloadValue($readingData, ['LONGITUDE', 'longitude']);

                // Set defaults for now-nullable fields just in case
                $reading->chlorine = null;
                $reading->salinity = null;
                $reading->quality = null;
                $reading->synced_at = now();
                $reading->client_local_id = $localId;

                $reading->save();

                if ($localId !== null) {
                    $syncedIds[] = $localId;
                    $syncedRecords[] = [
                        'local_id' => $localId,
                        'oid' => $reading->getKey(),
                    ];
                }
            }
        });

        return response()->json([
            'synced_ids' => $syncedIds,
            'synced_records' => $syncedRecords,
        ]);
    }

    /**
     * Sync diesel inventories from mobile app to server
     */
    public function syncDiesel(Request $request): JsonResponse
    {
        $request->validate([
            'inventories' => ['required', 'array', 'min:1'],
            'inventories.*.WELL_OID' => ['required', 'integer'],
            'inventories.*.INVENTORY_DATE' => ['required', 'date'],
            'inventories.*.GENERATOR_HOURS_READING' => ['required', 'numeric', 'min:0'],
            'inventories.*.DIESEL_HEIGHT_CM' => ['required', 'numeric', 'min:0'],
            'inventories.*.LOCAL_ID' => ['nullable', 'string', 'max:64'],
        ]);

        $syncedIds = [];
        $inspector = $request->user();

        DB::transaction(function () use ($request, $inspector, &$syncedIds) {
            foreach ($request->inventories as $invData) {
                $localId = $invData['LOCAL_ID'] ?? null;
                $inventory = $localId === null
                    ? null
                    : $this->dieselInventoryRepository->findSyncedByLocalIdIncludingDeleted(
                        $localId,
                        (int) $inspector->oid,
                    );

                if ($inventory !== null) {
                    $syncedIds[] = $localId;

                    continue;
                }

                $well = Well::find($invData['WELL_OID']);
                $availableFuel = $well
                    ? $this->fuelCalculator->litersForWell($well, $invData['DIESEL_HEIGHT_CM'])
                    : null;

                $inventory = new DieselInventory;
                $inventory->well_oid = $invData['WELL_OID'];
                $inventory->inspector_oid = $inspector->oid;
                $inventory->inventory_date = Carbon::parse($invData['INVENTORY_DATE']);
                $inventory->available_fuel_liters = $availableFuel ?? ($invData['AVAILABLE_FUEL_LITERS'] ?? null);

                $inventory->generator_oid = $invData['GENERATOR_OID'] ?? null;
                $inventory->generator_hours_reading = $invData['GENERATOR_HOURS_READING'];
                $inventory->diesel_height_cm = $invData['DIESEL_HEIGHT_CM'];
                $inventory->latitude = $invData['LATITUDE'] ?? null;
                $inventory->longitude = $invData['LONGITUDE'] ?? null;
                $inventory->synced_at = now();
                $inventory->client_local_id = $localId;

                $inventory->save();

                if ($localId !== null) {
                    $syncedIds[] = $localId;
                }
            }
        });

        return response()->json([
            'synced_ids' => $syncedIds,
        ]);
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
    public function syncNotifications(Request $request): JsonResponse
    {
        $request->validate([
            'notifications' => ['required', 'array'],
            'notifications.*.LOCAL_ID' => ['nullable', 'string', 'max:64'],
        ]);

        $syncedIds = [];
        $syncedRecords = [];
        $inspector = $request->user();

        DB::transaction(function () use (
            $request,
            $inspector,
            &$syncedIds,
            &$syncedRecords,
        ) {
            foreach ($request->notifications as $notifData) {
                $localId = $notifData['LOCAL_ID'] ?? null;
                $notification = $localId === null
                    ? null
                    : WellNotification::query()
                        ->where('CLIENT_LOCAL_ID', $localId)
                        ->where('INSPECTOR_OID', $inspector->oid)
                        ->first();

                if ($notification !== null) {
                    $syncedIds[] = $localId;
                    $syncedRecords[] = [
                        'local_id' => $localId,
                        'oid' => $notification->getKey(),
                    ];

                    continue;
                }

                $notification = new WellNotification;
                $notification->well_oid = $notifData['WELL_OID'];
                $notification->notification_type_oid = $notifData['NOTIFICATION_TYPE_OID'];
                $notification->inspector_oid = $inspector->oid;
                $notification->message = $notifData['MESSAGE'];
                $notification->is_read = false; // Initially unread for admins
                $notification->client_local_id = $localId;

                $notification->save();

                if ($localId !== null) {
                    $syncedIds[] = $localId;
                    $syncedRecords[] = [
                        'local_id' => $localId,
                        'oid' => $notification->getKey(),
                    ];
                }
            }
        });

        return response()->json([
            'synced_ids' => $syncedIds,
            'synced_records' => $syncedRecords,
        ]);
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

    private function payloadValue(array $payload, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload)) {
                return $payload[$key];
            }
        }

        return null;
    }
}
