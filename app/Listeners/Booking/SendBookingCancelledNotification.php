<?php

namespace App\Listeners\Booking;

use App\Actions\Notification\QueueBookingEmailNotificationAction;
use App\Actions\Notification\SendBookingInAppNotificationOnceAction;
use App\Events\Booking\BookingCancelled;
use App\Mail\Booking\BookingCancelledMail;
use App\Models\User\User;
use App\Support\Booking\BookingNotificationRecipients;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

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

        $this->sendInAppNotification($event);

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

    private function sendInAppNotification(BookingCancelled $event): void
    {
        $booking = $event->booking;
        $actor = $event->actor;
        $client = BookingNotificationRecipients::clientUser($booking);
        $professional = BookingNotificationRecipients::professionalUser($booking);

        if (! $actor) {
            Log::warning('Booking cancellation notification has no actor.', [
                'booking_id' => $booking->id,
            ]);

            return;
        }

        if ($client && $actor->is($client)) {
            $recipient = $professional;
            $actorRole = 'client';
            $type = 'booking.cancelled_by_client';
            $title = 'Reserva cancelada por el cliente';
            $message = sprintf(
                '%s canceló la reserva de %s programada para el %s.',
                $client->name,
                $booking->service->name,
                $booking->starts_at->format('d/m/Y \a \l\a\s H:i')
            );
            $actionRoute = "/professional/bookings/{$booking->id}";
        } elseif ($professional && $actor->is($professional)) {
            $recipient = $client;
            $actorRole = 'professional';
            $type = 'booking.cancelled_by_professional';
            $title = 'Reserva cancelada por el profesional';
            $message = sprintf(
                '%s canceló tu reserva de %s programada para el %s.',
                $professional->name,
                $booking->service->name,
                $booking->starts_at->format('d/m/Y \a \l\a\s H:i')
            );
            $actionRoute = "/bookings/{$booking->id}";
        } else {
            Log::warning('Booking cancellation actor is not a booking participant.', [
                'booking_id' => $booking->id,
                'actor_id' => $actor->id,
            ]);

            return;
        }

        if (! $recipient instanceof User || $recipient->is($actor)) {
            Log::warning('Booking cancellation notification has no valid counterpart.', [
                'booking_id' => $booking->id,
                'actor_id' => $actor->id,
            ]);

            return;
        }

        app(SendBookingInAppNotificationOnceAction::class)(
            booking: $booking,
            recipient: $recipient,
            type: $type,
            title: $title,
            message: $message,
            actionRoute: $actionRoute,
            metadata: [
                'booking_id' => $booking->id,
                'service_id' => $booking->service_id,
                'service_name' => $booking->service->name,
                'client_id' => $client?->id,
                'client_name' => $client?->name,
                'professional_id' => $booking->professional_id,
                'professional_name' => $professional?->name,
                'cancelled_by' => $actor->id,
                'cancelled_by_role' => $actorRole,
                'cancelled_by_name' => $actor->name,
                'cancellation_reason' => $booking->cancellation_reason,
                'starts_at' => $booking->starts_at?->toISOString(),
                'ends_at' => $booking->ends_at?->toISOString(),
                'cancelled_at' => $booking->cancelled_at?->toISOString(),
            ]
        );
    }
}
