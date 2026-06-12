<?php

namespace App\Actions\Booking;

use App\Actions\Video\EnsureVideoSessionForBookingAction;
use App\Enums\Booking\BookingStatus;
use App\Events\Booking\BookingConfirmed;
use App\Exceptions\ApiException;
use App\Models\Booking\Booking;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ConfirmBookingAction
{
    public function __construct(
        private readonly EnsureVideoSessionForBookingAction $ensureVideoSessionForBooking,
        private NotificationService $notificationService
    ) {}

    public function __invoke(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {

            $booking = Booking::query()
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($booking->status !== BookingStatus::Pending) {
                throw new ApiException(
                    error: 'InvalidBookingStatusTransition',
                    message: 'La reserva no puede confirmarse en su estado actual.',
                    status: Response::HTTP_CONFLICT
                );
            }

            $booking->update([
                'status' => BookingStatus::Confirmed,
                'confirmed_at' => now(),
            ]);

            if (in_array($booking->modality, ['remota', 'hibrida'], true)) {
                ($this->ensureVideoSessionForBooking)($booking);
            }

            $booking = $booking->refresh()->load([
                'service',
                'professional.user',
                'client',
                'videoSession.participants',
            ]);

            DB::afterCommit(function () use ($booking): void {
                event(new BookingConfirmed($booking));

                // notificación cliente
                $this->notificationService->send(
                    user: $booking->client,
                    type: 'booking.confirmed',
                    title: 'Reserva confirmada',
                    message: "Tu reserva para el servicio '{$booking->service->name}' ha sido confirmada.",
                    actionRoute: "/bookings/{$booking->id}"
                );
            });

            return $booking;
        });
    }
}
