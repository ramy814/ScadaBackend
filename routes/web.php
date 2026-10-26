<?php

use App\Http\Controllers\AdminAllowlistController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\DriverAppUsageController;
use App\Http\Controllers\ManualTripController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\SystemPermissionController;
use App\Repositories\SystemPermissionRepository;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// ─── Redirect root to admin dashboard (which will redirect to login if unauthenticated) ───

if (config('app.env') === 'local') {
    Route::get('/admin/dev-login', function () {
        $userId = config('mog-helpers.local_admin_user_id');
        $guard = Auth::guard('admin');
        if ($guard instanceof StatefulGuard) {
            $guard->loginUsingId($userId);
        }

        $system = request('system', 'portal');
        $redirectTo = match ($system) {
            'fleet' => route('admin.dashboard'),
            'wells' => route('wells.dashboard'),
            'valves' => route('valves.dashboard'),
            default => route('portal'),
        };

        return redirect($redirectTo)->with('ok', 'تم تسجيل الدخول كمسؤول تطوير محلي.');
    })->name('admin.dev-login');
}

// ─── Recovery control plane: remains available during a global shutdown ───────
Route::middleware('auth:admin')->group(function () {
    Route::get('/permissions', [SystemPermissionController::class, 'index'])->name('portal.permissions');
    Route::get('/permissions/search-users', [SystemPermissionController::class, 'searchUsers'])->name('portal.permissions.search-users');
    Route::post('/permissions', [SystemPermissionController::class, 'store'])->name('portal.permissions.store');
    Route::delete('/permissions/{userId}/{systemKey}', [SystemPermissionController::class, 'destroy'])->name('portal.permissions.destroy');
    Route::put('/permissions/operational-state', [SystemPermissionController::class, 'updateOperationalState'])
        ->name('portal.permissions.operational-state');
});

// ─── Protected: require authenticated admin + allowlist + operational state ───
Route::middleware(['auth:admin', 'admin.allowlisted', 'systems.operational'])->group(function () {

    // ─── Portal: landing page with system cards ───────────────────────────────
    Route::get('/', [PortalController::class, 'index'])->name('portal');

    Route::middleware('action.permission:'.SystemPermissionRepository::CREATE_MANUAL_TRIP)->group(function () {
        Route::get('/admin/manual-trips', [ManualTripController::class, 'index'])
            ->name('admin.manual-trips.index');
        Route::middleware('action.permission:'.SystemPermissionRepository::APPROVE_MANUAL_TRIP)->group(function () {
            Route::get('/admin/manual-trips/requests', [ManualTripController::class, 'requests'])
                ->name('admin.manual-trips.requests');
            Route::post('/admin/manual-trips/{trip}/approve', [ManualTripController::class, 'approve'])
                ->name('admin.manual-trips.approve');
        });
        Route::middleware('action.permission:'.SystemPermissionRepository::ENTER_MANUAL_TRIP)->group(function () {
            Route::get('/admin/manual-trips/create', [ManualTripController::class, 'create'])
                ->name('admin.manual-trips.create');
            Route::post('/admin/manual-trips', [ManualTripController::class, 'store'])
                ->name('admin.manual-trips.store');
            Route::get('/admin/manual-trips/{trip}/edit', [ManualTripController::class, 'edit'])
                ->name('admin.manual-trips.edit');
            Route::put('/admin/manual-trips/{trip}', [ManualTripController::class, 'update'])
                ->name('admin.manual-trips.update');
        });
    });

    // ─── Fleet: vehicle tracking dashboard ───────────────────────────────────
    Route::middleware('system.access:fleet')->group(function () {
        Route::get('/admin/dashboard', [AdminAuthController::class, 'dashboard'])->name('admin.dashboard');
        Route::get('/admin/driver-apps', [DriverAppUsageController::class, 'index'])->name('admin.driver-apps');
        Route::middleware('fleet.trip.access')->group(function () {
            Route::get('/admin/trips/{trip}', [AdminAuthController::class, 'showTrip'])->name('admin.trips.show');
            Route::post('/admin/trips/{trip}/approve', [AdminAuthController::class, 'approve'])->name('admin.trips.approve');
            Route::post('/admin/trips/{trip}/reject', [AdminAuthController::class, 'reject'])->name('admin.trips.reject');
            Route::post('/admin/trips/{trip}/clarify', [AdminAuthController::class, 'clarify'])->name('admin.trips.clarify');
        });

        Route::get('/admin/locations', [AdminAuthController::class, 'locations'])->name('admin.locations');
        Route::post('/admin/locations', [AdminAuthController::class, 'storeLocation'])->name('admin.locations.store');
        Route::post('/admin/locations/{location}', [AdminAuthController::class, 'updateLocation'])->name('admin.locations.update');

        Route::get('/admin/mission-types', [AdminAuthController::class, 'missionTypes'])->name('admin.mission-types');
        Route::post('/admin/mission-types', [AdminAuthController::class, 'storeMissionType'])->name('admin.mission-types.store');
        Route::post('/admin/mission-types/{missionType}', [AdminAuthController::class, 'updateMissionType'])->name('admin.mission-types.update');

        Route::get('/admin/reports', [AdminAuthController::class, 'reports'])->name('admin.reports');
        Route::get('/admin/reports/csv', [AdminAuthController::class, 'reportsCsv'])->name('admin.reports.csv');
        Route::get('/admin/reports/pdf', [AdminAuthController::class, 'reportsPdf'])->name('admin.reports.pdf');
    });

    // ─── Legacy allowlist management (global admin gate, not per-system access) ─
    if (config('app.env') === 'local') {
        Route::get('/admin/allowlist', fn () => redirect()->route('portal.permissions'))->name('admin.allowlist');
        Route::post('/admin/allowlist', [AdminAllowlistController::class, 'store'])->name('admin.allowlist.store');
        Route::delete('/admin/allowlist/{id}', [AdminAllowlistController::class, 'destroy'])->name('admin.allowlist.destroy');
    }
});
