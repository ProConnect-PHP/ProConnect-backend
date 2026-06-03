<?php

namespace App\Listeners\Booking;

use App\Actions\Notification\QueueBookingEmailNotificationAction;
use App\Events\Booking\BookingCreated;
use App\Mail\Booking\BookingCreatedForClientMail;
use App\Mail\Booking\BookingCreatedForProfessionalMail;
use App\Support\Booking\BookingNotificationRecipients;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendBookingCreatedNotification implements ShouldQueue
{
    public bool $afterCommit = true;

    public function handle(BookingCreated $event): void
    {
        $booking = $event->booking->loadMissing([
            'service',
            'professional.user',
            'client',
        ]);

        $queueEmail = app(QueueBookingEmailNotificationAction::class);

        $client = BookingNotificationRecipients::clientUser($booking);

        if ($client) {
            $queueEmail(
                booking: $booking,
                recipient: $client,
                type: 'booking_created_client',
                mail: new BookingCreatedForClientMail($booking),
            );
        }

        $professional = BookingNotificationRecipients::professionalUser($booking);

        if ($professional) {
            $queueEmail(
                booking: $booking,
                recipient: $professional,
                type: 'booking_created_professional',
                mail: new BookingCreatedForProfessionalMail($booking),
            );
        }
    }
}
