<?php

namespace App\Providers;

use App\Events\Booking\BookingCancelled;
use App\Events\Booking\BookingConfirmed;
use App\Events\Booking\BookingCreated;
use App\Events\Booking\BookingRescheduled;
use App\Listeners\Booking\SendBookingCancelledNotification;
use App\Listeners\Booking\SendBookingConfirmedNotification;
use App\Listeners\Booking\SendBookingCreatedNotification;
use App\Listeners\Booking\SendBookingRescheduledNotification;
use Illuminate\Support\Facades\Event;
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
    }
}
