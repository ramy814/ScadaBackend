<?php

use App\Http\Controllers\Wells\DashboardController;
use App\Http\Controllers\Wells\WellController;
use App\Http\Controllers\Wells\TeamController;
use App\Http\Controllers\Wells\InspectorController;
use App\Http\Controllers\Wells\ReadingController;
use App\Http\Controllers\Wells\DieselInventoryController;
use App\Http\Controllers\Wells\NotificationTypeController;
use App\Http\Controllers\Wells\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth:admin', 'system.access:wells'])->prefix('wells')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('wells.dashboard');

    Route::resource('wells', WellController::class)->names('wells.wells');
    Route::get('/wells-map', [WellController::class, 'map'])->name('wells.wells.map');

    Route::resource('teams', TeamController::class)->except(['show'])->names('wells.teams');
    Route::resource('inspectors', InspectorController::class)->except(['show'])->names('wells.inspectors');

    Route::get('/readings', [ReadingController::class, 'index'])->name('wells.readings.index');
    Route::get('/readings/report/pdf', [ReadingController::class, 'reportPdf'])->name('wells.readings.report.pdf');
    Route::get('/readings/report/excel', [ReadingController::class, 'reportExcel'])->name('wells.readings.report.excel');
    Route::get('/readings/{reading}', [ReadingController::class, 'show'])->name('wells.readings.show');
    Route::get('/diesel-inventories', [DieselInventoryController::class, 'index'])->name('wells.diesel-inventories.index');

    Route::resource('notification-types', NotificationTypeController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->names('wells.notification-types');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('wells.notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('wells.notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('wells.notifications.read-all');
});
