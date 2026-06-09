<?php

namespace App\Providers;

use App\Events\Booking\BookingCancelled;
use App\Events\Booking\BookingConfirmed;
use App\Events\Booking\BookingCreated;
use App\Events\Booking\BookingRescheduled;
use App\Events\Package\PackagePurchased;
use App\Events\Package\PackageSessionReserved;
use App\Events\Payment\PaymentSucceeded;
use App\Listeners\Booking\SendBookingCancelledNotification;
use App\Listeners\Booking\SendBookingConfirmedNotification;
use App\Listeners\Booking\SendBookingCreatedNotification;
use App\Listeners\Booking\SendBookingRescheduledNotification;
use App\Listeners\Package\SendPackagePurchasedNotifications;
use App\Listeners\Package\SendPackageSessionReservedNotifications;
use App\Listeners\Payment\SendPaymentSucceededNotifications;
use App\Models\Booking\ProfessionalBookingReminderRule;
use App\Models\Package\ClientPackage;
use App\Models\Package\PackageProduct;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentIntent;
use App\Models\User\ProfessionalProfile;
use App\Models\Video\VideoSession;
use App\Observers\ProfessionalProfileObserver;
use App\Policies\ClientPackagePolicy;
use App\Policies\PackageProductPolicy;
use App\Policies\PaymentIntentPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ProfessionalBookingReminderRulePolicy;
use App\Policies\VideoSessionPolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(PaymentIntent::class, PaymentIntentPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
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
    }
}
