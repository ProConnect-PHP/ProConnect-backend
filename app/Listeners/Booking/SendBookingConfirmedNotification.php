<?php

namespace App\Listeners\Booking;

use App\Actions\Notification\QueueBookingEmailNotificationAction;
use App\Events\Booking\BookingConfirmed;
use App\Mail\Booking\BookingConfirmedForClientMail;
use App\Support\Booking\BookingNotificationRecipients;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendBookingConfirmedNotification implements ShouldQueue
{


    public function handle(BookingConfirmed $event): void
    {
        $booking = $event->booking->loadMissing([
            'service',
            'professional.user',
            'client',
            'videoSession',
        ]);

        $recipient = BookingNotificationRecipients::clientUser($booking);

        if (! $recipient) {
            return;
        }

        app(QueueBookingEmailNotificationAction::class)(
            booking: $booking,
            recipient: $recipient,
            type: 'booking_confirmed',
            mail: new BookingConfirmedForClientMail($booking),
        );
    }
}
