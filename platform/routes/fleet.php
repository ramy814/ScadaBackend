<?php

use App\Modules\Fleet\Http\Controllers\OperationsController;
use App\Modules\Fleet\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

Route::name('fleet.')->group(function (): void {
    Route::get('/', fn () => redirect()->route('fleet.reviews'));
    Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews');
    Route::get('/trips/{trip}', [ReviewController::class, 'show'])->whereNumber('trip')->name('trips.show');
    Route::post('/trips/{trip}/{decision}', [ReviewController::class, 'decide'])
        ->whereNumber('trip')->whereIn('decision', ['approve', 'reject', 'clarify'])->name('trips.decide');

    Route::get('/live', [OperationsController::class, 'live'])->name('live');
    Route::get('/app', [OperationsController::class, 'app'])->name('app');
    Route::get('/reports', [OperationsController::class, 'reports'])->name('reports');
    Route::get('/reports/csv', [OperationsController::class, 'reportsCsv'])->name('reports.csv');
    Route::get('/catalogs', [OperationsController::class, 'catalogs'])->name('catalogs');
    Route::post('/catalogs/{type}/{id?}', [OperationsController::class, 'saveCatalog'])
        ->whereIn('type', ['locations', 'mission-types'])->whereNumber('id')->name('catalogs.save');
});
