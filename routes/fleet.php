<?php

use App\Modules\Fleet\Http\Controllers\ManualTripController;
use App\Modules\Fleet\Http\Controllers\OperationsController;
use App\Modules\Fleet\Http\Controllers\ReviewController;
use App\Modules\Fleet\Http\Controllers\VehicleClaimController;
use App\Platform\Access\SystemPermissionRepository as Permission;
use Illuminate\Support\Facades\Route;

// Assistant manual entry: runtime Oracle station scope, no Fleet system grant required (legacy rule).
Route::name('fleet.manual')->prefix('manual')->controller(ManualTripController::class)
    ->middleware('fleet.action:'.Permission::CREATE_MANUAL_TRIP)->group(function (): void {
        Route::get('/', 'index')->name('');
        Route::middleware('fleet.action:'.Permission::APPROVE_MANUAL_TRIP)->group(function (): void {
            Route::get('/requests', 'requests')->name('.requests');
            Route::post('/{trip}/approve', 'approve')->whereNumber('trip')->name('.approve');
        });
        Route::middleware('fleet.action:'.Permission::ENTER_MANUAL_TRIP)->group(function (): void {
            Route::get('/create', 'create')->name('.create');
            Route::post('/', 'store')->name('.store');
            Route::get('/{trip}/edit', 'edit')->whereNumber('trip')->name('.edit');
            Route::put('/{trip}', 'update')->whereNumber('trip')->name('.update');
            Route::post('/{trip}/submit', 'submit')->whereNumber('trip')->name('.submit');
        });
    });

Route::name('fleet.')->middleware('app.access:fleet')->group(function (): void {
    Route::get('/', fn () => redirect()->route('fleet.reviews'));
    Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews');
    Route::get('/trips/{trip}', [ReviewController::class, 'show'])->whereNumber('trip')->name('trips.show');
    Route::post('/trips/{trip}/{decision}', [ReviewController::class, 'decide'])
        ->whereNumber('trip')->whereIn('decision', ['approve', 'reject', 'clarify'])->name('trips.decide');

    Route::get('/live', [OperationsController::class, 'live'])->name('live');
    Route::get('/app', [OperationsController::class, 'app'])->name('app');
    Route::get('/reports', [OperationsController::class, 'reports'])->name('reports');
    Route::get('/reports/csv', [OperationsController::class, 'reportsCsv'])->name('reports.csv');
    Route::get('/reports/pdf', [OperationsController::class, 'reportsPdf'])->name('reports.pdf');
    // Financial claims need an explicit grant from the permissions dashboard (legacy rule).
    Route::middleware('fleet.action:'.Permission::VIEW_VEHICLE_CLAIMS)->group(function (): void {
        Route::get('/claims', [VehicleClaimController::class, 'index'])->name('claims');
        Route::get('/claims/csv', [VehicleClaimController::class, 'csv'])->name('claims.csv');
        Route::get('/claims/pdf', [VehicleClaimController::class, 'pdf'])->name('claims.pdf');
    });
    Route::get('/catalogs', [OperationsController::class, 'catalogs'])->name('catalogs');
    Route::post('/catalogs/{type}/{id?}', [OperationsController::class, 'saveCatalog'])
        ->whereIn('type', ['locations', 'mission-types'])->whereNumber('id')->name('catalogs.save');
});
