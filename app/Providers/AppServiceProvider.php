<?php

namespace App\Providers;

use App\Contracts\Auth\IOAuthExchangeCodeStore;
use App\Contracts\Auth\IOAuthIdentityProvider;
use App\Events\Booking\BookingCancelled;
use App\Events\Booking\BookingConfirmed;
use App\Events\Booking\BookingCreated;
use App\Events\Booking\BookingRescheduled;
use App\Events\Notification\NotificationCreated;
use App\Events\Package\PackagePurchased;
use App\Events\Package\PackageSessionReserved;
use App\Events\Payment\PaymentFailed;
use App\Events\Payment\PaymentSucceeded;
use App\Events\Video\VideoSessionCreated;
use App\Events\Video\VideoSessionEnded;
use App\Events\Video\VideoSessionJoined;
use App\Listeners\ActivityLog\LogDomainActivity;
use App\Listeners\Booking\SendBookingCancelledNotification;
use App\Listeners\Booking\SendBookingConfirmedNotification;
use App\Listeners\Booking\SendBookingCreatedNotification;
use App\Listeners\Booking\SendBookingRescheduledNotification;
use App\Listeners\Package\SendPackagePurchasedNotifications;
use App\Listeners\Package\SendPackageSessionReservedNotifications;
use App\Listeners\Payment\SendPaymentSucceededNotifications;
use App\Models\Booking\Booking;
use App\Models\Booking\ProfessionalBookingReminderRule;
use App\Models\Package\ClientPackage;
use App\Models\Package\PackageProduct;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentIntent;
use App\Models\Review\Review;
use App\Models\Review\ReviewReply;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\Video\VideoSession;
use App\Observers\ProfessionalProfileObserver;
use App\Policies\BookingPolicy;
use App\Policies\ClientPackagePolicy;
use App\Policies\PackageProductPolicy;
use App\Policies\PaymentIntentPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ProfessionalBookingReminderRulePolicy;
use App\Policies\ReviewPolicy;
use App\Policies\ReviewReplyPolicy;
use App\Policies\ServicePolicy;
use App\Policies\VideoSessionPolicy;
use App\Services\Auth\RedisOAuthExchangeCodeStore;
use App\Services\Auth\SocialiteOAuthIdentityProvider;
use App\Support\ActivityLog\ActivityLogger;
use App\Support\Security\ApiRateLimit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ActivityLogger::class);

        $this->app->bind(
            IOAuthIdentityProvider::class,
            SocialiteOAuthIdentityProvider::class
        );

        $this->app->bind(
            IOAuthExchangeCodeStore::class,
            RedisOAuthExchangeCodeStore::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for(
            'api-public',
            fn (Request $request) => ApiRateLimit::byRole($request, 'api_public')
        );
        RateLimiter::for(
            'api-authenticated',
            fn (Request $request) => ApiRateLimit::byRole($request, 'api_authenticated')
        );
        RateLimiter::for(
            'auth-login',
            fn (Request $request) => ApiRateLimit::login($request)
        );
        RateLimiter::for(
            'auth-register',
            fn (Request $request) => ApiRateLimit::byIp($request, 'auth_register')
        );
        RateLimiter::for(
            'auth-refresh',
            fn (Request $request) => ApiRateLimit::byIp($request, 'auth_refresh')
        );
        RateLimiter::for(
            'booking-write',
            fn (Request $request) => ApiRateLimit::byRole($request, 'booking_write')
        );
        RateLimiter::for(
            'payment-actions',
            fn (Request $request) => ApiRateLimit::byRole($request, 'payment_actions')
        );
        RateLimiter::for(
            'video-join',
            fn (Request $request) => ApiRateLimit::byRole($request, 'video_join')
        );
        RateLimiter::for(
            'reviews-write',
            fn (Request $request) => ApiRateLimit::byRole($request, 'reviews_write')
        );

        Gate::policy(PaymentIntent::class, PaymentIntentPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(Booking::class, BookingPolicy::class);
        Gate::policy(Service::class, ServicePolicy::class);
        Gate::policy(Review::class, ReviewPolicy::class);
        Gate::policy(ReviewReply::class, ReviewReplyPolicy::class);
        Gate::policy(PackageProduct::class, PackageProductPolicy::class);
        Gate::policy(ClientPackage::class, ClientPackagePolicy::class);
        Gate::policy(VideoSession::class, VideoSessionPolicy::class);
        Gate::policy(
            ProfessionalBookingReminderRule::class,
            ProfessionalBookingReminderRulePolicy::class
        );

        ProfessionalProfile::observe(ProfessionalProfileObserver::class);

        // Event::listen(
        //     BookingCreated::class,
        //     SendBookingCreatedNotification::class
        // );
        // Event::listen(
        //     BookingConfirmed::class,
        //     SendBookingConfirmedNotification::class
        // );
        // Event::listen(
        //     BookingCancelled::class,
        //     SendBookingCancelledNotification::class
        // );
        // Event::listen(
        //     BookingRescheduled::class,
        //     SendBookingRescheduledNotification::class
        // );
        Event::listen(
            PaymentSucceeded::class,
            SendPaymentSucceededNotifications::class
        );
        Event::listen(
            PackagePurchased::class,
            SendPackagePurchasedNotifications::class
        );
        Event::listen(
            PackageSessionReserved::class,
            SendPackageSessionReservedNotifications::class
        );

        foreach ([
            BookingCreated::class,
            BookingConfirmed::class,
            BookingCancelled::class,
            BookingRescheduled::class,
            PaymentSucceeded::class,
            PaymentFailed::class,
            PackagePurchased::class,
            PackageSessionReserved::class,
            VideoSessionCreated::class,
            VideoSessionJoined::class,
            VideoSessionEnded::class,
            NotificationCreated::class,
        ] as $activityEvent) {
            Event::listen($activityEvent, LogDomainActivity::class);
        }
    }
}
