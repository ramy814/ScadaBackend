<?php

use App\Modules\Wells\Http\Controllers\MasterDataController;
use App\Modules\Wells\Http\Controllers\ReportsController;
use App\Modules\Wells\Http\Controllers\WellsController;
use Illuminate\Support\Facades\Route;

Route::name('wells.')->group(function (): void {
    Route::controller(WellsController::class)->group(function (): void {
        Route::get('/', 'today')->name('today');
        Route::get('/signals', 'signals')->name('signals');
        Route::post('/signals/read', 'markRead')->name('signals.read-all');
        Route::post('/signals/{signal}/read', 'markRead')->whereNumber('signal')->name('signals.read');
        // Same paths and names as the legacy routes, so stored media URLs stay valid.
        Route::get('/activity-media/{media}', 'media')->whereNumber('media')->name('activity-media.show');
    });

    Route::controller(ReportsController::class)->group(function (): void {
        Route::get('/readings', 'readings')->name('readings');
        Route::get('/readings/export/{format}', 'readingsExport')->whereIn('format', ['pdf', 'excel'])->name('readings.export');
        Route::delete('/readings/{reading}', 'deleteReading')->whereNumber('reading')->name('readings.destroy');
        Route::get('/diesel', 'diesel')->name('diesel');
        Route::get('/diesel/export/{format}', 'dieselExport')->whereIn('format', ['pdf', 'excel'])->name('diesel.export');
        Route::delete('/diesel/{inventory}', 'deleteInventory')->whereNumber('inventory')->name('diesel.destroy');
        Route::get('/diesel/monthly', 'monthly')->name('diesel.monthly');
        Route::get('/diesel/monthly/export/{format}', 'monthlyExport')->whereIn('format', ['pdf', 'excel'])->name('diesel.monthly.export');
    });

    Route::controller(MasterDataController::class)->group(function (): void {
        Route::get('/assets', 'assets')->name('assets');
        Route::get('/assets/create', 'createWell')->name('assets.create');
        Route::post('/assets', 'storeWell')->name('assets.store');
        Route::get('/assets/{well}/edit', 'editWell')->whereNumber('well')->name('assets.edit');
        Route::post('/assets/{well}', 'updateWell')->whereNumber('well')->name('assets.update'); // POST: multipart photo uploads
        Route::delete('/assets/{well}', 'destroyWell')->whereNumber('well')->name('assets.destroy');
        Route::delete('/assets/{well}/media/{media}', 'destroyMedia')->whereNumber(['well', 'media'])->name('assets.media.destroy');
        Route::get('/media/{media}', 'showMedia')->whereNumber('media')->name('media.show');
        Route::get('/map', 'map')->name('map');

        Route::get('/field', 'field')->name('field');
        Route::post('/teams', 'storeTeam')->name('teams.store');
        Route::put('/teams/{team}', 'updateTeam')->whereNumber('team')->name('teams.update');
        Route::delete('/teams/{team}', 'destroyTeam')->whereNumber('team')->name('teams.destroy');
        Route::post('/inspectors', 'storeInspector')->name('inspectors.store');
        Route::put('/inspectors/{inspector}', 'updateInspector')->whereNumber('inspector')->name('inspectors.update');
        Route::delete('/inspectors/{inspector}', 'destroyInspector')->whereNumber('inspector')->name('inspectors.destroy');

        Route::get('/settings/signal-types', 'signalTypes')->name('signal-types');
        Route::post('/settings/signal-types', 'storeSignalType')->name('signal-types.store');
        Route::put('/settings/signal-types/{type}', 'updateSignalType')->whereNumber('type')->name('signal-types.update');
        Route::delete('/settings/signal-types/{type}', 'destroySignalType')->whereNumber('type')->name('signal-types.destroy');
    });
});
