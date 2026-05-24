<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminAllowlistController;
use Illuminate\Support\Facades\Route;

// ─── Redirect root to admin dashboard (which will redirect to login if unauthenticated) ───

if (config('app.env') === 'local') {
    Route::get('/admin/dev-login', function () {
        $userId = request('user_id', 3); // Default to user ID 3 (سماح محمد داود الشوا)
        $guard = \Illuminate\Support\Facades\Auth::guard('admin');
        if ($guard instanceof \Illuminate\Contracts\Auth\StatefulGuard) {
            $guard->loginUsingId($userId);
        }
        return redirect()->route('admin.dashboard')->with('ok', 'تم تسجيل الدخول كمسؤول تطوير محلي.');
    })->name('admin.dev-login');
}

Route::get('/auth/sso/logout', [AdminAuthController::class, 'logout'])->name('logout');

// ─── Protected: require authenticated admin + allowlist check ─────────────────
Route::middleware(['auth:admin', 'admin.allowlisted'])->group(function () {
    Route::get('/', [AdminAuthController::class, 'dashboard']);
    Route::get('/admin/dashboard', [AdminAuthController::class, 'dashboard'])->name('admin.dashboard');
    Route::get('/admin/trips/{trip}', [AdminAuthController::class, 'showTrip'])->name('admin.trips.show');
    Route::post('/admin/trips/{trip}/approve', [AdminAuthController::class, 'approve'])->name('admin.trips.approve');
    Route::post('/admin/trips/{trip}/reject', [AdminAuthController::class, 'reject'])->name('admin.trips.reject');
    Route::post('/admin/trips/{trip}/clarify', [AdminAuthController::class, 'clarify'])->name('admin.trips.clarify');

    Route::get('/admin/locations', [AdminAuthController::class, 'locations'])->name('admin.locations');
    Route::post('/admin/locations', [AdminAuthController::class, 'storeLocation'])->name('admin.locations.store');
    Route::post('/admin/locations/{location}', [AdminAuthController::class, 'updateLocation'])->name('admin.locations.update');

    Route::get('/admin/mission-types', [AdminAuthController::class, 'missionTypes'])->name('admin.mission-types');
    Route::post('/admin/mission-types', [AdminAuthController::class, 'storeMissionType'])->name('admin.mission-types.store');
    Route::post('/admin/mission-types/{missionType}', [AdminAuthController::class, 'updateMissionType'])->name('admin.mission-types.update');

    Route::get('/admin/reports', [AdminAuthController::class, 'reports'])->name('admin.reports');
    Route::get('/admin/reports/csv', [AdminAuthController::class, 'reportsCsv'])->name('admin.reports.csv');
    Route::get('/admin/reports/pdf', [AdminAuthController::class, 'reportsPdf'])->name('admin.reports.pdf');

    // ─── Allowlist management (who can access this dashboard) ────────────────
    Route::get('/admin/allowlist', [AdminAllowlistController::class, 'index'])->name('admin.allowlist');
    Route::post('/admin/allowlist', [AdminAllowlistController::class, 'store'])->name('admin.allowlist.store');
    Route::delete('/admin/allowlist/{id}', [AdminAllowlistController::class, 'destroy'])->name('admin.allowlist.destroy');
});

