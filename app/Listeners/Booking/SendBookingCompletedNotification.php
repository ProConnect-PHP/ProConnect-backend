<?php

namespace App\Listeners\Booking;

use App\Actions\Notification\SendBookingInAppNotificationOnceAction;
use App\Events\Booking\BookingCompleted;
use App\Support\Booking\BookingNotificationContext;
use App\Support\Booking\BookingNotificationRecipients;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendBookingCompletedNotification implements ShouldQueue
{
    public bool $afterCommit = true;

    public function handle(BookingCompleted $event): void
    {
        $booking = $event->booking->loadMissing([
            'service',
            'professional.user',
            'client',
        ]);
        $client = BookingNotificationRecipients::clientUser($booking);

        if (! $client) {
            return;
        }

        app(SendBookingInAppNotificationOnceAction::class)(
            booking: $booking,
            recipient: $client,
            type: 'booking.completed',
            title: 'Sesión finalizada',
            message: 'El profesional marcó la sesión como finalizada.',
            actionRoute: BookingNotificationContext::actionRoute($booking, $client),
            metadata: [
                ...BookingNotificationContext::metadata($booking),
                'completed_at' => $booking->completed_at?->toISOString(),
                'completed_by' => $event->actor->id,
                'completed_by_role' => $event->actor->role?->value ?? $event->actor->role,
            ],
        );
    }
}
