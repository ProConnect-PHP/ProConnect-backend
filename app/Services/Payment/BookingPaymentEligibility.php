<?php

namespace App\Services\Payment;

use App\Enums\Booking\BookingStatus;
use App\Exceptions\ApiException;
use App\Models\Booking\Booking;
use Symfony\Component\HttpFoundation\Response;

final class BookingPaymentEligibility
{
    public function canStartOrContinuePayment(Booking $booking): bool
    {
        return ! in_array($booking->status, [
            BookingStatus::Cancelled,
            BookingStatus::Completed,
            BookingStatus::NoShow,
        ], true);
    }

    public function assertCanStartOrContinuePayment(Booking $booking): void
    {
        if ($this->canStartOrContinuePayment($booking)) {
            return;
        }

        throw new ApiException(
            error: 'BookingNotPayable',
            message: 'Esta reserva no puede pagarse en su estado actual.',
            status: Response::HTTP_CONFLICT,
        );
    }
}
