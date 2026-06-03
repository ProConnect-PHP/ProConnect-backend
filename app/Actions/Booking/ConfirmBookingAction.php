<?php

namespace App\Actions\Booking;

use App\Enums\Booking\BookingStatus;
use App\Exceptions\ApiException;
use App\Events\Booking\BookingConfirmed;
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

            $booking = $booking->refresh()->load([
                'service',
                'professional.user',
                'client',
            ]);

            DB::afterCommit(function () use ($booking): void {
                event(new BookingConfirmed($booking));
            });

            return $booking;
        });
    }
}
