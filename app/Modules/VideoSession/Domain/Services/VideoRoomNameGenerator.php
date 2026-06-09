<?php

namespace App\Modules\VideoSession\Domain\Services;

use App\Models\Booking\Booking;

final class VideoRoomNameGenerator
{
    public function forBooking(Booking $booking): string
    {
        return 'booking_'.$booking->getKey();
    }
}
