<?php

namespace App\Events\Booking;

use App\Models\Booking\Booking;
use App\Models\User\User;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Booking $booking
    ) {
    }
}