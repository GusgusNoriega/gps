<?php

use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\TelemetryController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/enrollments', [EnrollmentController::class, 'store'])->middleware('throttle:enroll');
    Route::middleware('throttle:enroll-status')->group(function () {
        Route::get('/enrollments/{enrollment}', [EnrollmentController::class, 'show']);
        Route::post('/enrollments/{enrollment}/exchange', [EnrollmentController::class, 'exchange']);
    });
    Route::middleware(['auth:sanctum', 'device', 'throttle:telemetry'])->group(function () {
        Route::post('/positions', [TelemetryController::class, 'store']);
        Route::post('/heartbeat', [TelemetryController::class, 'heartbeat']);
        Route::get('/configuration', [TelemetryController::class, 'configuration']);
    });
});
