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
        $service = app(NotificationService::class);

        // User
        $user = User::find($this->booking->user_id);

        if ($user) {
            $service->send(
                user: $professional,
                type: 'booking.confirmed',
                title: 'Reserva confirmada',
                message: 'Tu reserva fue confirmada.',
                actionRoute: "/user/bookings/{$this->booking->id}"
            );
        }
    }
}
