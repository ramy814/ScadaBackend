<?php

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
