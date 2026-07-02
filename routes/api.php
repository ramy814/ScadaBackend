<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BeneficiaryProfileController;
use App\Http\Controllers\Api\LocationLookupController;
use App\Http\Controllers\Api\MissionTypeLookupController;
use App\Http\Controllers\Api\TripSyncController;
use App\Http\Controllers\Api\VehicleLookupController;
use Illuminate\Support\Facades\Route;

Route::prefix('driver/auth')->group(function (): void {
    Route::post('/send-code', [AuthController::class, 'sendCode']);
    Route::post('/verify', [AuthController::class, 'verify']);
});
// dsfsd
// sdsdfsws?

// Deployment trigger v2

Route::middleware('auth:sanctum')->prefix('driver')->group(function (): void {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/me', [BeneficiaryProfileController::class, 'show']);
    Route::get('/clarifications', [BeneficiaryProfileController::class, 'clarifications']);
    Route::get('/vehicles', [VehicleLookupController::class, 'index']);
    Route::get('/my-vehicles', [VehicleLookupController::class, 'myVehicles']);
    Route::get('/locations', [LocationLookupController::class, 'index']);
    Route::get('/mission-types', [MissionTypeLookupController::class, 'index']);
    Route::get('/trips', [TripSyncController::class, 'index']);
    Route::get('/trips/reconcile', [TripSyncController::class, 'reconcile']);
    Route::post('/trips', [TripSyncController::class, 'store']);
    Route::post('/trips/{trip}/points', [TripSyncController::class, 'uploadPoints']);
    Route::post('/trips/{trip}/confirm', [TripSyncController::class, 'confirm']);
    Route::post('/trips/{trip}/clarification-response', [TripSyncController::class, 'respondClarification']);
});

// Wells App API routes
Route::prefix('wells')->group(function (): void {
    Route::post('/auth/login', [\App\Http\Controllers\Api\Wells\AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/sync/wells', [\App\Http\Controllers\Api\Wells\SyncController::class, 'wells']);
        Route::get('/sync/notification-types', [\App\Http\Controllers\Api\Wells\SyncController::class, 'notificationTypes']);
        Route::post('/readings/sync', [\App\Http\Controllers\Api\Wells\SyncController::class, 'syncReadings']);
        Route::post('/diesel-inventories/sync', [\App\Http\Controllers\Api\Wells\SyncController::class, 'syncDiesel']);
        Route::post('/notifications/sync', [\App\Http\Controllers\Api\Wells\SyncController::class, 'syncNotifications']);
        Route::get('/sync/history', [\App\Http\Controllers\Api\Wells\SyncController::class, 'history']);
    });
});
