<?php

namespace App\Listeners\Booking;

use App\Actions\Notification\QueueBookingEmailNotificationAction;
use App\Actions\Notification\SendBookingInAppNotificationOnceAction;
use App\Events\Booking\BookingCancelled;
use App\Mail\Booking\BookingCancelledMail;
use App\Models\Booking\Booking;
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

        $this->sendInAppNotifications($event);

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

    private function sendInAppNotifications(BookingCancelled $event): void
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

        $startsAt = $booking->starts_at->format('d/m/Y \a \l\a\s H:i');

        if ($client && $actor->is($client)) {
            $actorRole = 'client';
            $metadata = $this->metadata($event, $actorRole, $client, $professional);

            $this->sendNotification(
                booking: $booking,
                recipient: $professional,
                type: 'booking.cancelled_by_client',
                title: 'Reserva cancelada por el cliente',
                message: sprintf(
                    '%s canceló la reserva de %s programada para el %s.',
                    $client->name,
                    $booking->service->name,
                    $startsAt
                ),
                actionRoute: "/professional/bookings/{$booking->id}",
                metadata: $metadata
            );

            $this->sendNotification(
                booking: $booking,
                recipient: $client,
                type: 'booking.cancelled_by_client_confirmation',
                title: 'Has cancelado tu reserva',
                message: sprintf(
                    'Has cancelado tu reserva para %s programada para el %s.',
                    $booking->service->name,
                    $startsAt
                ),
                actionRoute: "/my-bookings/{$booking->id}",
                metadata: $metadata
            );

            return;
        }

        if ($professional && $actor->is($professional)) {
            $actorRole = 'professional';
            $metadata = $this->metadata($event, $actorRole, $client, $professional);

            $this->sendNotification(
                booking: $booking,
                recipient: $client,
                type: 'booking.cancelled_by_professional',
                title: 'Reserva cancelada por el profesional',
                message: sprintf(
                    '%s canceló tu reserva para %s programada para el %s.',
                    $professional->name,
                    $booking->service->name,
                    $startsAt
                ),
                actionRoute: "/my-bookings/{$booking->id}",
                metadata: $metadata
            );

            $this->sendNotification(
                booking: $booking,
                recipient: $professional,
                type: 'booking.cancelled_by_professional_confirmation',
                title: 'Has cancelado una reserva',
                message: sprintf(
                    'Has cancelado la reserva de %s para %s programada para el %s.',
                    $client?->name ?? 'el cliente',
                    $booking->service->name,
                    $startsAt
                ),
                actionRoute: "/professional/bookings/{$booking->id}",
                metadata: $metadata
            );

            return;
        }

        Log::warning('Booking cancellation actor is not a booking participant.', [
            'booking_id' => $booking->id,
            'actor_id' => $actor->id,
        ]);
    }

    private function sendNotification(
        Booking $booking,
        ?User $recipient,
        string $type,
        string $title,
        string $message,
        string $actionRoute,
        array $metadata
    ): void {
        if (! $recipient) {
            Log::warning('Booking cancellation notification has no recipient.', [
                'booking_id' => $booking->id,
                'type' => $type,
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
            metadata: $metadata
        );
    }

    private function metadata(
        BookingCancelled $event,
        string $actorRole,
        ?User $client,
        ?User $professional
    ): array {
        $booking = $event->booking;

        return [
            'booking_id' => $booking->id,
            'service_id' => $booking->service_id,
            'service_name' => $booking->service->name,
            'client_id' => $client?->id,
            'client_name' => $client?->name,
            'professional_id' => $booking->professional_id,
            'professional_name' => $professional?->name,
            'cancelled_by' => $event->actor?->id,
            'cancelled_by_role' => $actorRole,
            'cancelled_by_name' => $event->actor?->name,
            'cancellation_reason' => $booking->cancellation_reason,
            'starts_at' => $booking->starts_at?->toISOString(),
            'ends_at' => $booking->ends_at?->toISOString(),
            'cancelled_at' => $booking->cancelled_at?->toISOString(),
        ];
    }
}
