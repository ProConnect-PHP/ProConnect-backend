<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Availability\AvailabilityController;
use App\Http\Controllers\Availability\AvailabilityExceptionController;
use App\Http\Controllers\Availability\AvailabilityRuleController;
use App\Http\Controllers\ProfessionalProfile\ProfessionalProfileController;
use App\Http\Controllers\Service\ServiceController;
use App\Http\Controllers\User\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/services/{service}/availability', [AvailabilityController::class, 'show']);

    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/refresh', [AuthController::class, 'refresh']);

        Route::middleware('auth:user_jwt')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });

    Route::middleware('auth:user_jwt')->group(function () {
        Route::get('/me', [UserController::class, 'show']);
        Route::put('/me', [UserController::class, 'update']);

        Route::prefix('professional-profile')->group(function () {
            Route::post('/', [ProfessionalProfileController::class, 'store']);
            Route::get('/', [ProfessionalProfileController::class, 'show']);
            Route::put('/', [ProfessionalProfileController::class, 'update']);
        });

        Route::prefix('services')->group(function () {
            Route::get('/my', [ServiceController::class, 'my']);
            Route::post('/', [ServiceController::class, 'store']);
            Route::get('/{service}', [ServiceController::class, 'show']);
            Route::put('/{service}', [ServiceController::class, 'update']);
            Route::delete('/{service}', [ServiceController::class, 'destroy']);

        });

        Route::prefix('services/{service}')->group(function () {
            Route::get('/availability-rules', [AvailabilityRuleController::class, 'index']);
            Route::post('/availability-rules', [AvailabilityRuleController::class, 'store']);

            Route::get('/availability-exceptions', [AvailabilityExceptionController::class, 'index']);
            Route::post('/availability-exceptions', [AvailabilityExceptionController::class, 'store']);
        });

        Route::put('/availability-rules/{availabilityRule}', [AvailabilityRuleController::class, 'update']);
        Route::delete('/availability-rules/{availabilityRule}', [AvailabilityRuleController::class, 'destroy']);

        Route::put('/availability-exceptions/{availabilityException}', [AvailabilityExceptionController::class, 'update']);
        Route::delete('/availability-exceptions/{availabilityException}', [AvailabilityExceptionController::class, 'destroy']);
        Route::apiResource('users', UserController::class)
            ->only(['index', 'show', 'update', 'destroy']);
    });
});
