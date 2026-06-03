<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Availability\AvailabilityController;
use App\Http\Controllers\Availability\AvailabilityExceptionController;
use App\Http\Controllers\Availability\AvailabilityRuleController;
use App\Http\Controllers\Booking\BookingController;
use App\Http\Controllers\Booking\ProfessionalBookingController;
use App\Http\Controllers\ProfessionalProfile\ProfessionalProfileController;
use App\Http\Controllers\Public\PublicProfessionalController;
use App\Http\Controllers\Public\PublicServiceController;
use App\Http\Controllers\Review\BookingReviewController;
use App\Http\Controllers\Review\PublicServiceReviewController;
use App\Http\Controllers\Review\ReviewController;
use App\Http\Controllers\Review\ReviewReplyController;
use App\Http\Controllers\Service\ServiceController;
use App\Http\Controllers\User\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/services/{service}/availability', [AvailabilityController::class, 'show']);
    Route::get('/services/{service}/reviews', [PublicServiceReviewController::class, 'index']);

    Route::prefix('public')->group(function () {
        Route::get('/services', [PublicServiceController::class, 'index']);
        Route::get('/services/{service}', [PublicServiceController::class, 'show']);
        Route::get('/professionals/{professionalProfile}', [PublicProfessionalController::class, 'show']);
    });

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
            Route::post('/{service}/bookings', [BookingController::class, 'store']);

        });

        Route::get('/bookings/my', [BookingController::class, 'my']);
        Route::get('/bookings/{booking}', [BookingController::class, 'show']);
        Route::post('/bookings/{booking}/review', [BookingReviewController::class, 'store']);
        Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);
        Route::post('/bookings/{booking}/reschedule', [BookingController::class, 'reschedule']);
        Route::put('/reviews/{review}', [ReviewController::class, 'update']);
        Route::delete('/reviews/{review}', [ReviewController::class, 'destroy']);
        Route::post('/reviews/{review}/replies', [ReviewReplyController::class, 'store']);
        Route::put('/review-replies/{reply}', [ReviewReplyController::class, 'update']);

        Route::get('/professional/bookings', [ProfessionalBookingController::class, 'index']);
        Route::post('/bookings/{booking}/confirm', [ProfessionalBookingController::class, 'confirm']);

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
