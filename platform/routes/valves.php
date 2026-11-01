<?php

use App\Modules\Valves\Http\Controllers\ValvesController;
use Illuminate\Support\Facades\Route;

Route::name('valves.')->controller(ValvesController::class)->group(function (): void {
    Route::get('/', 'today')->name('today');
    Route::get('/maintenance', 'maintenance')->name('maintenance');
    Route::get('/actions', 'actions')->name('actions');
    Route::get('/field', 'field')->name('field');
});
