<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\OAuthController;
use App\Http\Controllers\Availability\AvailabilityController;
use App\Http\Controllers\Availability\AvailabilityExceptionController;
use App\Http\Controllers\Availability\AvailabilityRuleController;
use App\Http\Controllers\Booking\BookingAvailableActionsController;
use App\Http\Controllers\Booking\BookingController;
use App\Http\Controllers\Booking\ProfessionalBookingController;
use App\Http\Controllers\Booking\ProfessionalBookingPolicyController;
use App\Http\Controllers\Booking\ProfessionalReminderRuleController;
use App\Http\Controllers\Notification\NotificationController;
use App\Http\Controllers\Package\MyClientPackageController;
use App\Http\Controllers\Package\PackagePurchaseController;
use App\Http\Controllers\Package\ProfessionalPackageProductController;
use App\Http\Controllers\Package\ProfessionalSoldPackageController;
use App\Http\Controllers\Package\PublicPackageProductController;
use App\Http\Controllers\Payment\BookingPaymentIntentController;
use App\Http\Controllers\Payment\ClientPaymentController;
use App\Http\Controllers\Payment\PaymentCheckoutController;
use App\Http\Controllers\Payment\PaymentIntentController;
use App\Http\Controllers\Payment\PaymentSimulationController;
use App\Http\Controllers\Payment\PaymentWebhookController;
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
use App\Modules\VideoSession\Infrastructure\Http\Controllers\JoinVideoSessionController as LiveKitJoinVideoSessionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/payments/webhooks/mercadopago', [PaymentWebhookController::class, 'mercadoPago'])
        ->middleware('throttle:payment-webhooks')
        ->name('payments.webhooks.mercadopago');
    Route::post('/payments/webhooks/paypal', [PaymentWebhookController::class, 'payPal'])
        ->middleware('throttle:payment-webhooks')
        ->name('payments.webhooks.paypal');

    /*
    |--------------------------------------------------------------------------
    | Public API
    |--------------------------------------------------------------------------
    |
    | Endpoints públicos consumidos por la landing, búsqueda, detalle de servicios,
    | profesionales, disponibilidad pública, reseñas públicas y paquetes públicos.
    |
    */
    Route::get('/admin/activity-logs', [ActivityLogController::class, 'index']);
    Route::middleware('throttle:api-public')->group(function (): void {
        Route::get('/services/{service}/availability', [AvailabilityController::class, 'show']);
        Route::get('/services/{service}/reviews', [PublicServiceReviewController::class, 'index']);
        Route::get('/services/{service}/package-products', [PublicPackageProductController::class, 'byService']);

        Route::prefix('public')->group(function (): void {
            Route::get('/services', [PublicServiceController::class, 'index']);
            Route::get('/services/{service}', [PublicServiceController::class, 'show']);

            Route::get('/professionals/{professionalProfile}', [PublicProfessionalController::class, 'show']);

            Route::get('/package-products', [PublicPackageProductController::class, 'index']);
            Route::get('/package-products/{packageProduct}', [PublicPackageProductController::class, 'show']);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | Registro, login tradicional, refresh token, logout autenticado y OAuth.
    |
    */

    Route::prefix('auth')->group(function (): void {
        Route::post('/register', [AuthController::class, 'register'])
            ->middleware('throttle:auth-register');

        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:auth-login');

        Route::post('/refresh', [AuthController::class, 'refresh'])
            ->middleware('throttle:auth-refresh');

        Route::prefix('oauth')
            ->middleware('throttle:api-public')
            ->group(function (): void {
                Route::get('/{provider}/redirect', [OAuthController::class, 'redirect']);
                Route::get('/{provider}/callback', [OAuthController::class, 'callback']);
                Route::post('/exchange', [OAuthController::class, 'exchange']);
            });

        Route::middleware(['auth:user_jwt', 'throttle:api-authenticated'])->group(function (): void {
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Authenticated API
    |--------------------------------------------------------------------------
    |
    | Todas las rutas debajo de este bloque requieren usuario autenticado.
    |
    */

    Route::middleware('auth:user_jwt')->group(function (): void {
        /*
        |--------------------------------------------------------------------------
        | Current User
        |--------------------------------------------------------------------------
        */

        Route::middleware('throttle:api-authenticated')->group(function (): void {
            Route::get('/me', [UserController::class, 'show']);
            Route::put('/me', [UserController::class, 'update']);
        });

        // Route::middleware(['role:admin', 'throttle:api-authenticated'])
        // ->prefix('admin')
        // ->group(function (): void {
        // });

        Route::middleware(['client-capable', 'throttle:api-authenticated'])->group(function (): void {
            Route::get('/bookings/my', [BookingController::class, 'my']);

            Route::prefix('professional-profile')->group(function (): void {
                Route::post('/', [ProfessionalProfileController::class, 'store']);
                Route::get('/', [ProfessionalProfileController::class, 'show']);
            });
        });

        /*
        |--------------------------------------------------------------------------
        | Shared Booking Read Operations
        |--------------------------------------------------------------------------
        |
        | Lectura de reservas y sesión de video asociada.
        |
        */

        Route::middleware('throttle:api-authenticated')->group(function (): void {
            Route::get('/bookings/{booking}', [BookingController::class, 'show']);
            Route::get('/bookings/{booking}/video-session', [BookingVideoSessionController::class, 'show']);
        });

        /*
        |--------------------------------------------------------------------------
        | Client Area
        |--------------------------------------------------------------------------
        |
        | Reservas propias, pagos propios, paquetes comprados y sesiones de video
        | del cliente.
        |
        */

        Route::middleware(['client-capable', 'throttle:api-authenticated'])->group(function (): void {
            Route::get('/bookings/{booking}/available-actions', [BookingAvailableActionsController::class, 'show']);

            Route::get('/video-sessions/my', [MyVideoSessionController::class, 'index']);

            Route::get('/payments/my', [ClientPaymentController::class, 'index']);

            Route::get('/client-packages/my', [MyClientPackageController::class, 'index']);
            Route::get('/client-packages/{clientPackage}', [MyClientPackageController::class, 'show']);
        });

        /*
        |--------------------------------------------------------------------------
        | Client Booking Writes
        |--------------------------------------------------------------------------
        */

        Route::middleware(['client-capable', 'throttle:booking-write'])->group(function (): void {
            Route::post('/services/{service}/bookings', [BookingController::class, 'store']);
        });

        /*
        |--------------------------------------------------------------------------
        | Client Payments and Package Purchases
        |--------------------------------------------------------------------------
        */

        Route::middleware(['client-capable', 'throttle:payment-actions'])->group(function (): void {
            Route::post('/payment-intents', [PaymentIntentController::class, 'store']);
            Route::post('/bookings/{booking}/payment-intents', [BookingPaymentIntentController::class, 'store']);

            Route::get('/payment-intents/{paymentIntent}', [PaymentIntentController::class, 'show']);
            Route::get('/payment-intents/{paymentIntent}/status', [PaymentIntentController::class, 'status']);
            Route::post('/payment-intents/{paymentIntent}/checkout', [PaymentCheckoutController::class, 'store']);
            Route::post('/payment-intents/{paymentIntent}/simulate-success', [PaymentSimulationController::class, 'success']);
            Route::post('/payment-intents/{paymentIntent}/simulate-failure', [PaymentSimulationController::class, 'failure']);

            Route::post('/package-products/{packageProduct}/purchase', [PackagePurchaseController::class, 'store']);
        });

        /*
        |--------------------------------------------------------------------------
        | Client Reviews
        |--------------------------------------------------------------------------
        */

        Route::middleware(['client-capable', 'throttle:reviews-write'])->group(function (): void {
            Route::post('/bookings/{booking}/review', [BookingReviewController::class, 'store']);
            Route::put('/reviews/{review}', [ReviewController::class, 'update']);
            Route::delete('/reviews/{review}', [ReviewController::class, 'destroy']);
        });

        /*
        |--------------------------------------------------------------------------
        | Shared Booking State Transitions
        |--------------------------------------------------------------------------
        |
        | Acciones permitidas tanto para cliente como para profesional.
        |
        */

        Route::middleware(['role:client,professional', 'throttle:booking-write'])->group(function (): void {
            Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);
            Route::post('/bookings/{booking}/reschedule', [BookingController::class, 'reschedule']);
        });

        /*
        |--------------------------------------------------------------------------
        | Video Sessions
        |--------------------------------------------------------------------------
        |
        | Creación/obtención de sesiones de video y join con integración LiveKit.
        |
        */

        Route::middleware(['role:client,professional', 'throttle:video-join'])->group(function (): void {
            Route::post('/bookings/{booking}/video-session', [BookingVideoSessionController::class, 'store']);

            Route::post('/video-sessions/bookings/{booking}/join', LiveKitJoinVideoSessionController::class);
            Route::post('/video-sessions/{videoSession}/join', [VideoSessionJoinController::class, 'store']);
        });

        /*
        |--------------------------------------------------------------------------
        | Professional Area
        |--------------------------------------------------------------------------
        |
        | Perfil profesional, servicios, agenda, disponibilidad, políticas de reserva,
        | paquetes vendidos, pagos y sesiones de video del profesional.
        |
        */

        Route::middleware(['role:professional', 'throttle:api-authenticated'])->group(function (): void {
            /*
            |--------------------------------------------------------------------------
            | Professional Profile
            |--------------------------------------------------------------------------
            */

            Route::prefix('professional-profile')->group(function (): void {
                Route::put('/', [ProfessionalProfileController::class, 'update']);
            });

            /*
            |--------------------------------------------------------------------------
            | Professional Services
            |--------------------------------------------------------------------------
            */

            Route::prefix('services')->group(function (): void {
                Route::get('/my', [ServiceController::class, 'my']);
                Route::post('/', [ServiceController::class, 'store']);
                Route::get('/{service}', [ServiceController::class, 'show']);
                Route::put('/{service}', [ServiceController::class, 'update']);
                Route::delete('/{service}', [ServiceController::class, 'destroy']);
            });

            /*
            |--------------------------------------------------------------------------
            | Professional Bookings
            |--------------------------------------------------------------------------
            */

            Route::get('/professional/bookings', [ProfessionalBookingController::class, 'index']);

            Route::get('/professional/me/booking-policy', [ProfessionalBookingPolicyController::class, 'show']);
            Route::put('/professional/me/booking-policy', [ProfessionalBookingPolicyController::class, 'update']);

            Route::get('/professional/me/reminder-rules', [ProfessionalReminderRuleController::class, 'index']);
            Route::post('/professional/me/reminder-rules', [ProfessionalReminderRuleController::class, 'store']);
            Route::put('/professional/me/reminder-rules/{reminderRule}', [ProfessionalReminderRuleController::class, 'update']);
            Route::delete('/professional/me/reminder-rules/{reminderRule}', [ProfessionalReminderRuleController::class, 'destroy']);

            /*
            |--------------------------------------------------------------------------
            | Professional Video Sessions and Payments
            |--------------------------------------------------------------------------
            */

            Route::get('/professional/video-sessions', [ProfessionalVideoSessionController::class, 'index']);
            Route::get('/professional/payments', [ProfessionalPaymentController::class, 'index']);

            /*
            |--------------------------------------------------------------------------
            | Professional Packages
            |--------------------------------------------------------------------------
            */

            Route::get('/professional/package-products', [ProfessionalPackageProductController::class, 'index']);
            Route::post('/professional/package-products', [ProfessionalPackageProductController::class, 'store']);
            Route::get('/professional/package-products/{packageProduct}', [ProfessionalPackageProductController::class, 'show']);
            Route::put('/professional/package-products/{packageProduct}', [ProfessionalPackageProductController::class, 'update']);
            Route::delete('/professional/package-products/{packageProduct}', [ProfessionalPackageProductController::class, 'destroy']);

            Route::get('/professional/client-packages', [ProfessionalSoldPackageController::class, 'index']);
            Route::get('/professional/client-packages/{clientPackage}', [ProfessionalSoldPackageController::class, 'show']);

            /*
            |--------------------------------------------------------------------------
            | Service Availability
            |--------------------------------------------------------------------------
            */

            Route::prefix('services/{service}')->group(function (): void {
                Route::get('/availability-rules', [AvailabilityRuleController::class, 'index']);
                Route::post('/availability-rules', [AvailabilityRuleController::class, 'store']);

                Route::get('/availability-exceptions', [AvailabilityExceptionController::class, 'index']);
                Route::post('/availability-exceptions', [AvailabilityExceptionController::class, 'store']);
            });

            Route::put('/availability-rules/{availabilityRule}', [AvailabilityRuleController::class, 'update']);
            Route::delete('/availability-rules/{availabilityRule}', [AvailabilityRuleController::class, 'destroy']);

            Route::put('/availability-exceptions/{availabilityException}', [AvailabilityExceptionController::class, 'update']);
            Route::delete('/availability-exceptions/{availabilityException}', [AvailabilityExceptionController::class, 'destroy']);
        });

        /*
        |--------------------------------------------------------------------------
        | Professional Booking Writes
        |--------------------------------------------------------------------------
        */

        Route::middleware(['role:professional', 'throttle:booking-write'])->group(function (): void {
            Route::post('/bookings/{booking}/confirm', [ProfessionalBookingController::class, 'confirm']);
        });

        /*
        |--------------------------------------------------------------------------
        | Professional Review Replies
        |--------------------------------------------------------------------------
        */

        Route::middleware(['role:professional', 'throttle:reviews-write'])->group(function (): void {
            Route::post('/reviews/{review}/replies', [ReviewReplyController::class, 'store']);
            Route::put('/review-replies/{reply}', [ReviewReplyController::class, 'update']);
        });

        /*
        |--------------------------------------------------------------------------
        | Notifications
        |--------------------------------------------------------------------------
        */

        Route::prefix('notifications')
            ->middleware('throttle:api-authenticated')
            ->group(function (): void {

                Route::get('/', [NotificationController::class, 'index']); // view list

                // Route::get('/{notification}', [NotificationController::class, 'show']); // view single

                Route::get('/unread-count', [NotificationController::class, 'unreadCount']);

                Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);

                Route::delete('/delete-all', [NotificationController::class, 'deleteAll']);

                Route::delete('/{notification}', [NotificationController::class, 'destroy']);
            });

    });
});
