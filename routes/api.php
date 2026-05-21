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
