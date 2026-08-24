<?php

use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\Valves\AuthController;
use App\Http\Controllers\Api\Valves\MapController;
use App\Http\Controllers\Valves\ValveController;
use App\Http\Controllers\Valves\ValveInspectorController;
use App\Http\Controllers\Valves\ValveMaintenanceController;
use App\Http\Controllers\Valves\ValvesDashboardController;
use App\Http\Controllers\Valves\ValvesReportController;
use App\Http\Controllers\Valves\ValveTeamController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Valves Routes
|--------------------------------------------------------------------------
*/

Route::prefix('api/valves')->middleware(['api', 'systems.operational'])->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->name('api.valves.auth.login');
});

Route::prefix('api/dashboard')->middleware(['api', 'systems.operational', 'auth:sanctum'])->group(function () {
    Route::get('/stats', [DashboardController::class, 'stats'])->name('api.dashboard.stats');
    Route::get('/recent-activity', [DashboardController::class, 'recentActivity'])->name('api.dashboard.recent-activity');
});

Route::prefix('api/valves')->middleware(['api', 'systems.operational', 'auth:sanctum'])->group(function () {
    Route::get('/', [ValveController::class, 'index'])->name('api.valves.index');
    Route::get('/{valve}', [ValveController::class, 'show'])->name('api.valves.show');

    // Actions & Logs
    Route::post('/{valve}/action', [ValveController::class, 'action'])->name('api.valves.action');
    Route::get('/{valve}/logs', [ValveController::class, 'logs'])->name('api.valves.logs');
});

Route::prefix('api/valve-maintenance')->middleware(['api', 'systems.operational', 'auth:sanctum'])->group(function () {
    Route::get('/', [ValveMaintenanceController::class, 'index'])->name('api.valve_maintenance.index');
    Route::put('/{maintenanceReport}', [ValveMaintenanceController::class, 'update'])->name('api.valve_maintenance.update');
});

Route::middleware(['web', 'auth:admin', 'systems.operational', 'system.access:valves'])->prefix('valves')->group(function () {
    Route::get('/', [ValvesDashboardController::class, 'index'])->name('valves.dashboard');
    Route::get('/map-features', [MapController::class, 'features'])->name('valves.map.features');
    Route::get('/map', [ValvesDashboardController::class, 'map'])->name('valves.map');
    Route::get('/reports', [ValvesReportController::class, 'index'])->name('valves.reports.index');
    Route::redirect('/valves', '/valves/map');
    Route::redirect('/maintenance', '/valves');
    Route::resource('teams', ValveTeamController::class)->only(['index', 'store', 'update', 'destroy'])->names('valves.teams');
    Route::resource('inspectors', ValveInspectorController::class)->only(['index', 'store', 'update', 'destroy'])->names('valves.inspectors');
});
