<?php

use App\Modules\Valves\Http\Controllers\MasterDataController;
use App\Modules\Valves\Http\Controllers\ValvesController;
use Illuminate\Support\Facades\Route;

Route::name('valves.')->group(function (): void {
    Route::controller(ValvesController::class)->group(function (): void {
        Route::get('/', 'today')->name('today');
        Route::get('/maintenance', 'maintenance')->name('maintenance');
        Route::get('/actions', 'actions')->name('actions');
    });

    Route::controller(MasterDataController::class)->group(function (): void {
        Route::get('/field', 'field')->name('field');
        Route::post('/teams', 'storeTeam')->name('teams.store');
        Route::put('/teams/{team}', 'updateTeam')->whereNumber('team')->name('teams.update');
        Route::delete('/teams/{team}', 'destroyTeam')->whereNumber('team')->name('teams.destroy');
        Route::post('/inspectors', 'storeInspector')->name('inspectors.store');
        Route::put('/inspectors/{inspector}', 'updateInspector')->whereNumber('inspector')->name('inspectors.update');
        Route::delete('/inspectors/{inspector}', 'destroyInspector')->whereNumber('inspector')->name('inspectors.destroy');
        Route::get('/map', 'map')->name('map');
        Route::get('/map/features', 'features')->name('map.features');
        Route::get('/map/tiles/{service}', 'tiles')->whereIn('service', ['wms', 'wmts'])->name('map.tiles');
    });
});
