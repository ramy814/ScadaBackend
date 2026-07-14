<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Wells;

use App\Http\Controllers\Controller;
use App\Models\Wells\Well;
use App\Models\Wells\WellReading;
use App\Models\Wells\DieselInventory;
use App\Services\Wells\DieselFuelCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SyncController extends Controller
{
    public function __construct(private readonly DieselFuelCalculator $fuelCalculator)
    {
    }

    /**
     * Get all active wells formatted for the mobile app
     */
    public function wells(Request $request): JsonResponse
    {
        $inspector = $request->user();

        $wells = Well::active()
            ->whereHas('teams', function ($query) use ($inspector) {
                $query->whereIn('teams.oid', $inspector->teams()->pluck('teams.oid'));
            })
            ->get()
            ->map(function ($well) {
                $previousDiesel = DieselInventory::where('well_oid', $well->oid)
                    ->orderByDesc('inventory_date')
                    ->first();

                $tankDiameter = $well->getAttribute('TANK_DIAMETER') ?? $well->getAttribute('tank_diameter');
                $tankHeight = $well->getAttribute('TANK_HEIGHT') ?? $well->getAttribute('tank_height');
                $previousGeneratorHours = $previousDiesel?->getAttribute('GENERATOR_HOURS_READING')
                    ?? $previousDiesel?->getAttribute('generator_hours_reading');
                $previousDieselHeight = $previousDiesel?->getAttribute('DIESEL_HEIGHT_CM')
                    ?? $previousDiesel?->getAttribute('diesel_height_cm');
                $previousAvailableFuel = $previousDiesel?->getAttribute('AVAILABLE_FUEL_LITERS')
                    ?? $previousDiesel?->getAttribute('available_fuel_liters');

                return [
                    'oid' => $well->oid,
                    'name' => $well->name,
                    'quarter_oid' => $well->quarter_oid,
                    'quarter_name' => null, // Provide if quarter relation exists
                    'pump_type' => $well->pump_type ?? 'Unknown',
                    'capacity' => (float) $well->capacity,
                    'power_source' => $well->power_source ?? 'Unknown',
                    'tank_shape' => $well->getAttribute('TANK_SHAPE') ?? $well->getAttribute('tank_shape'),
                    'tank_material' => $well->getAttribute('TANK_MATERIAL') ?? $well->getAttribute('tank_material'),
                    'tank_diameter' => $tankDiameter === null ? null : (float) $tankDiameter,
                    'tank_height' => $tankHeight === null ? null : (float) $tankHeight,
                    'previous_generator_hours_reading' => $previousGeneratorHours === null ? null : (float) $previousGeneratorHours,
                    'previous_diesel_height_cm' => $previousDieselHeight === null ? null : (float) $previousDieselHeight,
                    'previous_available_fuel_liters' => $previousAvailableFuel === null ? null : (float) $previousAvailableFuel,
                    'previous_inventory_date' => $previousDiesel?->inventory_date?->toIso8601String(),
                    'latitude' => (float) $well->latitude,
                    'longitude' => (float) $well->longitude,
                ];
            });

        return response()->json([
            'wells' => $wells,
        ]);
    }

    /**
     * Sync readings from mobile app to server
     */
    public function syncReadings(Request $request): JsonResponse
    {
        $request->validate([
            'readings' => ['required', 'array'],
        ]);

        $syncedIds = [];
        $inspector = $request->user();

        DB::transaction(function () use ($request, $inspector, &$syncedIds) {
            foreach ($request->readings as $readingData) {
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
                if ($pumpHours === null) {
                    throw ValidationException::withMessages([
                        'readings' => 'PUMP_HOURS_READING is required.',
                    ]);
                }

                $reading = new WellReading();
                $reading->well_oid = $this->payloadValue($readingData, ['WELL_OID', 'well_oid']);
                $reading->inspector_oid = $inspector->oid;
                $reading->flow_rate = $generatorHours ?? 0;
                $reading->generator_hours_reading = $generatorHours;
                $reading->pump_hours_reading = $pumpHours;
                $reading->reading_date = \Carbon\Carbon::parse($this->payloadValue($readingData, ['READING_DATE', 'reading_date']));
                $reading->latitude = $this->payloadValue($readingData, ['LATITUDE', 'latitude']);
                $reading->longitude = $this->payloadValue($readingData, ['LONGITUDE', 'longitude']);

                // Set defaults for now-nullable fields just in case
                $reading->chlorine = null;
                $reading->salinity = null;
                $reading->quality = null;
                $reading->synced_at = now();

                $reading->save();

                $localId = $this->payloadValue($readingData, ['LOCAL_ID', 'local_id']);
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
     * Sync diesel inventories from mobile app to server
     */
    public function syncDiesel(Request $request): JsonResponse
    {
        $request->validate([
            'inventories' => ['required', 'array'],
        ]);

        $syncedIds = [];
        $inspector = $request->user();

        DB::transaction(function () use ($request, $inspector, &$syncedIds) {
            foreach ($request->inventories as $invData) {
                $well = Well::find($invData['WELL_OID']);
                $availableFuel = $well
                    ? $this->fuelCalculator->litersForWell($well, $invData['DIESEL_HEIGHT_CM'] ?? null)
                    : null;

                $inventory = new DieselInventory();
                $inventory->well_oid = $invData['WELL_OID'];
                $inventory->inspector_oid = $inspector->oid;
                $inventory->inventory_date = \Carbon\Carbon::parse($invData['INVENTORY_DATE']);
                $inventory->available_fuel_liters = $availableFuel ?? ($invData['AVAILABLE_FUEL_LITERS'] ?? null);

                $inventory->generator_oid = $invData['GENERATOR_OID'] ?? null;
                $inventory->generator_hours_reading = $invData['GENERATOR_HOURS_READING'] ?? null;
                $inventory->diesel_height_cm = $invData['DIESEL_HEIGHT_CM'] ?? null;
                $inventory->latitude = $invData['LATITUDE'] ?? null;
                $inventory->longitude = $invData['LONGITUDE'] ?? null;
                $inventory->synced_at = now();

                $inventory->save();

                if (isset($invData['LOCAL_ID'])) {
                    $syncedIds[] = $invData['LOCAL_ID'];
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
        $types = \App\Models\Wells\NotificationType::active()->get()->map(function ($type) {
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
        ]);

        $syncedIds = [];
        $inspector = $request->user();

        DB::transaction(function () use ($request, $inspector, &$syncedIds) {
            foreach ($request->notifications as $notifData) {
                $notification = new \App\Models\Wells\WellNotification();
                $notification->well_oid = $notifData['WELL_OID'];
                $notification->notification_type_oid = $notifData['NOTIFICATION_TYPE_OID'];
                $notification->inspector_oid = $inspector->oid;
                $notification->message = $notifData['MESSAGE'];
                $notification->is_read = false; // Initially unread for admins
                
                $notification->save();

                if (isset($notifData['LOCAL_ID'])) {
                    $syncedIds[] = $notifData['LOCAL_ID'];
                }
            }
        });

        return response()->json([
            'synced_ids' => $syncedIds,
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
            $wellReadings = \App\Models\Wells\WellReading::where('well_oid', $wellOid)
                ->orderBy('reading_date', 'desc')
                ->take(3)
                ->get();
            $readings = array_merge($readings, $wellReadings->toArray());

            // Get last 3 diesels
            $wellDiesels = \App\Models\Wells\DieselInventory::where('well_oid', $wellOid)
                ->orderBy('inventory_date', 'desc')
                ->take(3)
                ->get();
            $dieselInventories = array_merge($dieselInventories, $wellDiesels->toArray());

            // Get last 3 notifications
            $wellNotifications = \App\Models\Wells\WellNotification::where('well_oid', $wellOid)
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
