<?php

namespace App\Listeners\Booking;

use App\Actions\Notification\QueueBookingEmailNotificationAction;
use App\Events\Booking\BookingCancelled;
use App\Mail\Booking\BookingCancelledMail;
use App\Support\Booking\BookingNotificationRecipients;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendBookingCancelledNotification implements ShouldQueue
{
    public bool $afterCommit = true;

    public function handle(BookingCancelled $event): void
    {
        $booking = $event->booking->loadMissing([
            'service',
            'professional.user',
            'client',
        ]);

        BookingNotificationRecipients::counterpartUsers($booking, $event->actor)
            ->each(function ($recipient) use ($booking, $event): void {
                app(QueueBookingEmailNotificationAction::class)(
                    booking: $booking,
                    recipient: $recipient,
                    type: 'booking_cancelled',
                    mail: new BookingCancelledMail($booking, $event->actor),
                    payload: [
                        'actor_id' => $event->actor?->id,
                    ],
                );
            });
    }
}
