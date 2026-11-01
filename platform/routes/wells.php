<?php

use App\Modules\Wells\Http\Controllers\WellsController;
use Illuminate\Support\Facades\Route;

Route::name('wells.')->controller(WellsController::class)->group(function (): void {
    Route::get('/', 'today')->name('today');
    Route::get('/signals', 'signals')->name('signals');
    Route::post('/signals/read', 'markRead')->name('signals.read-all');
    Route::post('/signals/{signal}/read', 'markRead')->whereNumber('signal')->name('signals.read');
    Route::get('/readings', 'readings')->name('readings');
    Route::get('/assets', 'assets')->name('assets');
    Route::get('/field', 'field')->name('field');
    Route::get('/media/{media}', 'media')->whereNumber('media')->name('media');
});
