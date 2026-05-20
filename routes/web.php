<?php

use App\Http\Controllers\AdminAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('admin.login');
});

Route::get('/admin/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');

Route::get('/auth/sso/callback', function (\Illuminate\Http\Request $request) {
    $state = $request->input('state');
    if (config('app.env') !== 'local' && $state && str_starts_with($state, 'dev_')) {
        $query = http_build_query($request->all());
        return redirect('http://127.0.0.1:8000/auth/sso/callback?' . $query);
    }

    return \Mog\ProjectHelper\AuthHelpers::callback($request);
})->name('sso.callback')->middleware('web');

Route::get('/admin/dashboard', [AdminAuthController::class, 'dashboard'])->name('admin.dashboard');
Route::get('/admin/locations', [AdminAuthController::class, 'locations'])->name('admin.locations');
Route::post('/admin/locations', [AdminAuthController::class, 'storeLocation'])->name('admin.locations.store');
Route::post('/admin/locations/{location}', [AdminAuthController::class, 'updateLocation'])->name('admin.locations.update');
Route::get('/admin/mission-types', [AdminAuthController::class, 'missionTypes'])->name('admin.mission-types');
Route::post('/admin/mission-types', [AdminAuthController::class, 'storeMissionType'])->name('admin.mission-types.store');
Route::post('/admin/mission-types/{missionType}', [AdminAuthController::class, 'updateMissionType'])->name('admin.mission-types.update');
Route::get('/admin/reports', [AdminAuthController::class, 'reports'])->name('admin.reports');
Route::get('/admin/reports/csv', [AdminAuthController::class, 'reportsCsv'])->name('admin.reports.csv');
Route::get('/admin/reports/pdf', [AdminAuthController::class, 'reportsPdf'])->name('admin.reports.pdf');
Route::get('/admin/trips/{trip}', [AdminAuthController::class, 'showTrip'])->name('admin.trips.show');
Route::post('/admin/trips/{trip}/approve', [AdminAuthController::class, 'approve'])->name('admin.trips.approve');
Route::post('/admin/trips/{trip}/reject', [AdminAuthController::class, 'reject'])->name('admin.trips.reject');
Route::post('/admin/trips/{trip}/clarify', [AdminAuthController::class, 'clarify'])->name('admin.trips.clarify');
Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');
