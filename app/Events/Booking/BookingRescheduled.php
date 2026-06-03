<?php

namespace App\Events\Booking;

use App\Models\Booking\Booking;
use App\Models\User\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingRescheduled
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Booking $booking,
        public readonly ?User $actor = null
    ) {
    }
}
