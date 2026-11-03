<?php

use App\Modules\Fleet\Http\Controllers\Api\AuthController as DriverAuthController;
use App\Modules\Fleet\Http\Controllers\Api\DriverController;
use App\Modules\Fleet\Http\Controllers\Api\MapTileController;
use App\Modules\Fleet\Http\Controllers\Api\TripSyncController;
use App\Modules\Valves\Http\Controllers\Api\AuthController as ValvesAuthController;
use App\Modules\Valves\Http\Controllers\Api\DashboardController as ValvesDashboardController;
use App\Modules\Valves\Http\Controllers\Api\MaintenanceController as ValvesMaintenanceController;
use App\Modules\Valves\Http\Controllers\Api\ValveController;
use App\Modules\Wells\Http\Controllers\Api\ActivityMediaController as WellsActivityMediaController;
use App\Modules\Wells\Http\Controllers\Api\AuthController as WellsAuthController;
use App\Modules\Wells\Http\Controllers\Api\SyncController as WellsSyncController;
use Illuminate\Support\Facades\Route;

/*
| Mobile APIs for the Flutter apps. Paths, middleware names, route names and JSON
| payloads are identical to the legacy backend so the apps switch without a release
| (docs/05-cutover-plan.md). Every entry point honors the global emergency switch.
*/

// ── Map tiles for authorised app clients (legacy routes/api.php) ─────────────
Route::get('/map/tiles/{zoom}/{x}/{y}.png', MapTileController::class)
    ->whereNumber(['zoom', 'x', 'y'])
    ->middleware(['systems.operational', 'throttle:600,1', 'map.client'])
    ->name('api.map.tiles.show');

// ── Driver app (legacy routes/api.php `driver`) ──────────────────────────────
Route::middleware('systems.operational')->prefix('driver/auth')->group(function (): void {
    Route::post('/send-code', [DriverAuthController::class, 'sendCode']);
    Route::post('/verify', [DriverAuthController::class, 'verify']);
});

Route::middleware(['systems.operational', 'auth:sanctum', 'abilities:driver', 'mobile.principal:driver'])->prefix('driver')->group(function (): void {
    Route::post('/auth/logout', [DriverAuthController::class, 'logout']);
    Route::get('/me', [DriverController::class, 'me']);
    Route::post('/app-presence', [DriverController::class, 'presence']);
    Route::get('/clarifications', [DriverController::class, 'clarifications']);
    Route::get('/vehicles', [DriverController::class, 'vehicles']);
    Route::get('/my-vehicles', [DriverController::class, 'myVehicles']);
    Route::get('/locations', [DriverController::class, 'locations']);
    Route::get('/mission-types', [DriverController::class, 'missionTypes']);
    Route::get('/trips', [TripSyncController::class, 'index']);
    Route::get('/trips/reconcile', [TripSyncController::class, 'reconcile']);
    Route::get('/trips/{trip}/route-preview', [TripSyncController::class, 'routePreview']);
    Route::post('/trips', [TripSyncController::class, 'store']);
    Route::post('/trips/{trip}/points', [TripSyncController::class, 'uploadPoints']);
    Route::post('/trips/{trip}/confirm', [TripSyncController::class, 'confirm']);
    Route::post('/trips/{trip}/clarification-response', [TripSyncController::class, 'respondClarification']);
});

// ── Wells inspector app (legacy routes/api.php `wells`) ───────────────────────
Route::middleware('systems.operational')->prefix('wells')->group(function (): void {
    Route::post('/auth/login', [WellsAuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'abilities:wells', 'mobile.principal:wells'])->group(function (): void {
        Route::get('/sync/wells', [WellsSyncController::class, 'wells']);
        Route::get('/sync/notification-types', [WellsSyncController::class, 'notificationTypes']);
        Route::post('/readings/sync', [WellsSyncController::class, 'syncReadings']);
        Route::post('/diesel-inventories/sync', [WellsSyncController::class, 'syncDiesel']);
        Route::post('/notifications/sync', [WellsSyncController::class, 'syncNotifications']);
        Route::post('/activity-media/sync', [WellsActivityMediaController::class, 'store']);
        Route::get('/sync/history', [WellsSyncController::class, 'history']);
    });
});

// ── SCADA valves app (legacy routes/valves.php) ──────────────────────────────
Route::middleware('systems.operational')->prefix('valves')->group(function (): void {
    Route::post('/auth/login', [ValvesAuthController::class, 'login'])->name('api.valves.auth.login');
});

Route::middleware(['systems.operational', 'auth:sanctum', 'abilities:scada', 'mobile.principal:scada'])->group(function (): void {
    Route::prefix('dashboard')->group(function (): void {
        Route::get('/stats', [ValvesDashboardController::class, 'stats'])->name('api.dashboard.stats');
        Route::get('/recent-activity', [ValvesDashboardController::class, 'recentActivity'])->name('api.dashboard.recent-activity');
    });

    Route::prefix('valves')->group(function (): void {
        Route::get('/', [ValveController::class, 'index'])->name('api.valves.index');
        Route::get('/{valve}', [ValveController::class, 'show'])->name('api.valves.show');
        Route::post('/{valve}/action', [ValveController::class, 'action'])->name('api.valves.action');
        Route::get('/{valve}/logs', [ValveController::class, 'logs'])->name('api.valves.logs');
    });

    Route::prefix('valve-maintenance')->group(function (): void {
        Route::get('/', [ValvesMaintenanceController::class, 'index'])->name('api.valve_maintenance.index');
        Route::put('/{maintenanceReport}', [ValvesMaintenanceController::class, 'update'])->name('api.valve_maintenance.update');
    });
});
