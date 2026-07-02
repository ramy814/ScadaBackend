<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Valves\MapController;
use App\Http\Controllers\Valves\ValveController;
use App\Http\Controllers\Valves\ValveInspectorController;
use App\Http\Controllers\Valves\ValveMaintenanceController;
use App\Http\Controllers\Valves\ValveTeamController;

/*
|--------------------------------------------------------------------------
| Valves Routes
|--------------------------------------------------------------------------
*/

Route::prefix('api/valves')->middleware('api')->group(function () {
    Route::post('/auth/login', [\App\Http\Controllers\Api\Valves\AuthController::class, 'login'])
        ->name('api.valves.auth.login');
});

Route::prefix('api/dashboard')->middleware(['api', 'auth:sanctum'])->group(function () {
    Route::get('/stats', [\App\Http\Controllers\Api\DashboardController::class, 'stats'])->name('api.dashboard.stats');
    Route::get('/map-data', [\App\Http\Controllers\Api\DashboardController::class, 'mapData'])->name('api.dashboard.map-data');
    Route::get('/recent-activity', [\App\Http\Controllers\Api\DashboardController::class, 'recentActivity'])->name('api.dashboard.recent-activity');
    Route::get('/map-settings', [MapController::class, 'settings'])->name('api.dashboard.map-settings');
    Route::get('/map-features', [MapController::class, 'features'])->name('api.dashboard.map-features');
});

Route::prefix('api/valves')->middleware(['api', 'auth:sanctum'])->group(function () {
    Route::get('/', [ValveController::class, 'index'])->name('api.valves.index');
    Route::get('/{valve}', [ValveController::class, 'show'])->name('api.valves.show');

    // Actions & Logs
    Route::post('/{valve}/action', [ValveController::class, 'action'])->name('api.valves.action');
    Route::get('/{valve}/logs', [ValveController::class, 'logs'])->name('api.valves.logs');
});

Route::prefix('api/valve-maintenance')->middleware(['api', 'auth:sanctum'])->group(function () {
    Route::get('/', [ValveMaintenanceController::class, 'index'])->name('api.valve_maintenance.index');
    Route::put('/{maintenanceReport}', [ValveMaintenanceController::class, 'update'])->name('api.valve_maintenance.update');
});

Route::middleware(['web', 'auth:admin', 'system.access:valves'])->prefix('valves')->group(function () {
    Route::get('/', [\App\Http\Controllers\Valves\ValvesDashboardController::class, 'index'])->name('valves.dashboard');
    Route::get('/map', [\App\Http\Controllers\Valves\ValvesDashboardController::class, 'map'])->name('valves.map');
    Route::get('/reports', [\App\Http\Controllers\Valves\ValvesReportController::class, 'index'])->name('valves.reports.index');
    Route::redirect('/valves', '/valves/map');
    Route::redirect('/maintenance', '/valves');
    Route::resource('teams', ValveTeamController::class)->only(['index', 'store', 'update', 'destroy'])->names('valves.teams');
    Route::resource('inspectors', ValveInspectorController::class)->only(['index', 'store', 'update', 'destroy'])->names('valves.inspectors');
});
