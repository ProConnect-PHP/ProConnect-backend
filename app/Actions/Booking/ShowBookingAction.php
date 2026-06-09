<?php

namespace App\Actions\Booking;

use App\Models\Booking\Booking;

class ShowBookingAction
{
    public function __invoke(Booking $booking): Booking
    {
        return $booking->load([
            'service.professional.user',
            'professional.user',
            'client',
            'payment',
            'clientPackage.packageProduct',
            'packageSession',
            'videoSession.participants',
        ]);
    }
}
