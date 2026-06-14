<?php

namespace App\Listeners\Booking;

use App\Actions\Notification\QueueBookingEmailNotificationAction;
use App\Events\Booking\BookingRescheduled;
use App\Mail\Booking\BookingRescheduledMail;
use App\Support\Booking\BookingNotificationRecipients;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendBookingRescheduledNotification implements ShouldQueue
{


    public function handle(BookingRescheduled $event): void
    {
        $booking = $event->booking->loadMissing([
            'service',
            'professional.user',
            'client',
            'videoSession',
        ]);

        BookingNotificationRecipients::counterpartUsers($booking, $event->actor)
            ->each(function ($recipient) use ($booking, $event): void {
                app(QueueBookingEmailNotificationAction::class)(
                    booking: $booking,
                    recipient: $recipient,
                    type: 'booking_rescheduled',
                    mail: new BookingRescheduledMail($booking, $event->actor),
                    payload: [
                        'actor_id' => $event->actor?->id,
                    ],
                );
            });
    }
}
