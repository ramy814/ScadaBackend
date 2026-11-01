<?php

use App\Platform\Access\AccessController;
use App\Platform\Applications\ApplicationsController;
use App\Platform\Audit\AuditController;
use App\Platform\Http\LocaleController;
use App\Platform\Identity\AuthController;
use App\Platform\Operations\OperationsController;
use App\Platform\Overview\OverviewController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'login'])->name('login');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:admin')->name('logout');
Route::post('/locale', LocaleController::class)->name('locale');

if (app()->environment('local')) {
    Route::get('/dev-login', [AuthController::class, 'devLogin'])->name('dev-login');
}

Route::middleware(['auth:admin', 'platform.user'])->group(function (): void {

    // Workspaces honor the global emergency switch (same state as the legacy backend).
    Route::middleware('platform.operational')->group(function (): void {
        Route::get('/', OverviewController::class)->name('overview');

        Route::prefix('fleet')->middleware('app.access:fleet')->group(base_path('routes/fleet.php'));
        Route::prefix('wells')->middleware('app.access:wells')->group(base_path('routes/wells.php'));
        Route::prefix('valves')->middleware('app.access:valves')->group(base_path('routes/valves.php'));
    });

    // Recovery control plane: stays reachable while systems are suspended.
    Route::prefix('platform')->name('platform.')->group(function (): void {
        Route::get('/applications', ApplicationsController::class)->name('applications');
        Route::get('/audit', AuditController::class)->middleware('app.access:fleet')->name('audit');

        Route::middleware('platform.admin')->group(function (): void {
            Route::get('/access', [AccessController::class, 'index'])->name('access');
            Route::get('/access/search', [AccessController::class, 'search'])->name('access.search');
            Route::put('/access/{userId}', [AccessController::class, 'update'])->whereNumber('userId')->name('access.update');
            Route::get('/operations', [OperationsController::class, 'show'])->name('operations');
            Route::put('/operations', [OperationsController::class, 'update'])->name('operations.update');
        });
    });
});
