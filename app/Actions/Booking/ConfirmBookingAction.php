<?php

namespace App\Actions\Booking;

use App\Enums\Booking\BookingStatus;
use App\Exceptions\ApiException;
use App\Models\Booking\Booking;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ConfirmBookingAction
{
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

            return $booking->refresh()->load([
                'service',
                'professional.user',
                'client',
            ]);
        });
    }
}
