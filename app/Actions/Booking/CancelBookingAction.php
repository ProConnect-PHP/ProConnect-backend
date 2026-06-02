<?php

namespace App\Actions\Booking;

use App\Actions\Booking\Concerns\ValidatesBookingRules;
use App\Enums\Booking\BookingStatus;
use App\Exceptions\ApiException;
use App\Models\Booking\Booking;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CancelBookingAction
{
    use ValidatesBookingRules;

    public function __invoke(Booking $booking, ?string $reason = null): Booking
    {
        return DB::transaction(function () use ($booking, $reason) {
            $booking = Booking::query()
                ->with('service')
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $booking->isCancellable()) {
                throw new ApiException(
                    error: 'InvalidBookingStatusTransition',
                    message: 'La reserva no puede cancelarse en su estado actual.',
                    status: Response::HTTP_CONFLICT
                );
            }

            $this->ensureCancellationWindowIsOpen($booking, 'CancellationWindowExpired');

            $booking->update([
                'status' => BookingStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            return $booking->refresh()->load([
                'service.professional.user',
                'professional.user',
                'client',
            ]);
        });
    }
}
