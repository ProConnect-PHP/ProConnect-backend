<?php

namespace App\Events\Booking;

use App\Models\Booking\Booking;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingConfirmed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Booking $booking
    ) {
    }
}
