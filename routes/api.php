<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Availability\AvailabilityController;
use App\Http\Controllers\Availability\AvailabilityExceptionController;
use App\Http\Controllers\Availability\AvailabilityRuleController;
use App\Http\Controllers\Booking\BookingAvailableActionsController;
use App\Http\Controllers\Booking\BookingController;
use App\Http\Controllers\Booking\ProfessionalBookingController;
use App\Http\Controllers\Booking\ProfessionalBookingPolicyController;
use App\Http\Controllers\Booking\ProfessionalReminderRuleController;
use App\Http\Controllers\Package\MyClientPackageController;
use App\Http\Controllers\Package\PackagePurchaseController;
use App\Http\Controllers\Package\ProfessionalPackageProductController;
use App\Http\Controllers\Package\ProfessionalSoldPackageController;
use App\Http\Controllers\Package\PublicPackageProductController;
use App\Http\Controllers\Payment\BookingPaymentIntentController;
use App\Http\Controllers\Payment\ClientPaymentController;
use App\Http\Controllers\Payment\PaymentSimulationController;
use App\Http\Controllers\Payment\ProfessionalPaymentController;
use App\Http\Controllers\ProfessionalProfile\ProfessionalProfileController;
use App\Http\Controllers\Public\PublicProfessionalController;
use App\Http\Controllers\Public\PublicServiceController;
use App\Http\Controllers\Review\BookingReviewController;
use App\Http\Controllers\Review\PublicServiceReviewController;
use App\Http\Controllers\Review\ReviewController;
use App\Http\Controllers\Review\ReviewReplyController;
use App\Http\Controllers\Service\ServiceController;
use App\Http\Controllers\User\UserController;
use App\Http\Controllers\Video\BookingVideoSessionController;
use App\Http\Controllers\Video\MyVideoSessionController;
use App\Http\Controllers\Video\ProfessionalVideoSessionController;
use App\Http\Controllers\Video\VideoSessionJoinController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/services/{service}/availability', [AvailabilityController::class, 'show']);
    Route::get('/services/{service}/reviews', [PublicServiceReviewController::class, 'index']);
    Route::get('/services/{service}/package-products', [PublicPackageProductController::class, 'byService']);

    Route::prefix('public')->group(function () {
        Route::get('/services', [PublicServiceController::class, 'index']);
        Route::get('/services/{service}', [PublicServiceController::class, 'show']);
        Route::get('/professionals/{professionalProfile}', [PublicProfessionalController::class, 'show']);
        Route::get('/package-products', [PublicPackageProductController::class, 'index']);
        Route::get('/package-products/{packageProduct}', [PublicPackageProductController::class, 'show']);
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
        Route::get('/bookings/{booking}/available-actions', [BookingAvailableActionsController::class, 'show']);
        Route::get('/bookings/{booking}/video-session', [BookingVideoSessionController::class, 'show']);
        Route::post('/bookings/{booking}/video-session', [BookingVideoSessionController::class, 'store']);
        Route::post('/bookings/{booking}/payment-intents', [BookingPaymentIntentController::class, 'store']);
        Route::post('/bookings/{booking}/review', [BookingReviewController::class, 'store']);
        Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);
        Route::post('/bookings/{booking}/reschedule', [BookingController::class, 'reschedule']);
        Route::get('/video-sessions/my', [MyVideoSessionController::class, 'index']);
        Route::post('/video-sessions/{videoSession}/join', [VideoSessionJoinController::class, 'store']);
        Route::post('/payment-intents/{paymentIntent}/simulate-success', [PaymentSimulationController::class, 'success']);
        Route::post('/payment-intents/{paymentIntent}/simulate-failure', [PaymentSimulationController::class, 'failure']);
        Route::get('/payments/my', [ClientPaymentController::class, 'index']);
        Route::post('/package-products/{packageProduct}/purchase', [PackagePurchaseController::class, 'store']);
        Route::get('/client-packages/my', [MyClientPackageController::class, 'index']);
        Route::get('/client-packages/{clientPackage}', [MyClientPackageController::class, 'show']);
        Route::put('/reviews/{review}', [ReviewController::class, 'update']);
        Route::delete('/reviews/{review}', [ReviewController::class, 'destroy']);
        Route::post('/reviews/{review}/replies', [ReviewReplyController::class, 'store']);
        Route::put('/review-replies/{reply}', [ReviewReplyController::class, 'update']);

        Route::get('/professional/bookings', [ProfessionalBookingController::class, 'index']);
        Route::get('/professional/me/booking-policy', [ProfessionalBookingPolicyController::class, 'show']);
        Route::put('/professional/me/booking-policy', [ProfessionalBookingPolicyController::class, 'update']);
        Route::get('/professional/me/reminder-rules', [ProfessionalReminderRuleController::class, 'index']);
        Route::post('/professional/me/reminder-rules', [ProfessionalReminderRuleController::class, 'store']);
        Route::put('/professional/me/reminder-rules/{reminderRule}', [ProfessionalReminderRuleController::class, 'update']);
        Route::delete('/professional/me/reminder-rules/{reminderRule}', [ProfessionalReminderRuleController::class, 'destroy']);
        Route::get('/professional/video-sessions', [ProfessionalVideoSessionController::class, 'index']);
        Route::get('/professional/payments', [ProfessionalPaymentController::class, 'index']);
        Route::get('/professional/package-products', [ProfessionalPackageProductController::class, 'index']);
        Route::post('/professional/package-products', [ProfessionalPackageProductController::class, 'store']);
        Route::get('/professional/package-products/{packageProduct}', [ProfessionalPackageProductController::class, 'show']);
        Route::put('/professional/package-products/{packageProduct}', [ProfessionalPackageProductController::class, 'update']);
        Route::delete('/professional/package-products/{packageProduct}', [ProfessionalPackageProductController::class, 'destroy']);
        Route::get('/professional/client-packages', [ProfessionalSoldPackageController::class, 'index']);
        Route::get('/professional/client-packages/{clientPackage}', [ProfessionalSoldPackageController::class, 'show']);
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
