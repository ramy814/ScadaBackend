<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BeneficiaryProfileController;
use App\Http\Controllers\Api\DriverAppPresenceController;
use App\Http\Controllers\Api\LocationLookupController;
use App\Http\Controllers\Api\MissionTypeLookupController;
use App\Http\Controllers\Api\TripSyncController;
use App\Http\Controllers\Api\VehicleLookupController;
use App\Http\Controllers\Api\Wells\ActivityMediaController;
use App\Http\Controllers\Api\Wells\SyncController;
use Illuminate\Support\Facades\Route;

Route::middleware('systems.operational')->prefix('driver/auth')->group(function (): void {
    Route::post('/send-code', [AuthController::class, 'sendCode']);
    Route::post('/verify', [AuthController::class, 'verify']);
});
// dsfsd
// sdsdfsws?

// Deployment trigger v2

Route::middleware(['systems.operational', 'auth:sanctum'])->prefix('driver')->group(function (): void {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/me', [BeneficiaryProfileController::class, 'show']);
    Route::post('/app-presence', [DriverAppPresenceController::class, 'store']);
    Route::get('/clarifications', [BeneficiaryProfileController::class, 'clarifications']);
    Route::get('/vehicles', [VehicleLookupController::class, 'index']);
    Route::get('/my-vehicles', [VehicleLookupController::class, 'myVehicles']);
    Route::get('/locations', [LocationLookupController::class, 'index']);
    Route::get('/mission-types', [MissionTypeLookupController::class, 'index']);
    Route::get('/trips', [TripSyncController::class, 'index']);
    Route::get('/trips/reconcile', [TripSyncController::class, 'reconcile']);
    Route::get('/trips/{trip}/route-preview', [TripSyncController::class, 'routePreview']);
    Route::post('/trips', [TripSyncController::class, 'store']);
    Route::post('/trips/{trip}/points', [TripSyncController::class, 'uploadPoints']);
    Route::post('/trips/{trip}/confirm', [TripSyncController::class, 'confirm']);
    Route::post('/trips/{trip}/clarification-response', [TripSyncController::class, 'respondClarification']);
});

// Wells App API routes
Route::middleware('systems.operational')->prefix('wells')->group(function (): void {
    Route::post('/auth/login', [App\Http\Controllers\Api\Wells\AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/sync/wells', [SyncController::class, 'wells']);
        Route::get('/sync/notification-types', [SyncController::class, 'notificationTypes']);
        Route::post('/readings/sync', [SyncController::class, 'syncReadings']);
        Route::post('/diesel-inventories/sync', [SyncController::class, 'syncDiesel']);
        Route::post('/notifications/sync', [SyncController::class, 'syncNotifications']);
        Route::post('/activity-media/sync', [ActivityMediaController::class, 'store']);
        Route::get('/sync/history', [SyncController::class, 'history']);
    });
});
