<?php

namespace App\Services\Booking;

use App\Exceptions\ApiException;
use App\Models\Booking\Booking;
use App\Models\Booking\ProfessionalBookingPolicy;
use Symfony\Component\HttpFoundation\Response;

class BookingCancellationPolicyChecker
{
    public function assertClientCanCancel(Booking $booking): void
    {
        if (! $booking->isCancellable()) {
            throw new ApiException(
                error: 'InvalidBookingStatusTransition',
                message: 'La reserva no puede cancelarse en su estado actual.',
                status: Response::HTTP_CONFLICT
            );
        }

        if ($booking->starts_at->lte(now())) {
            throw new ApiException(
                error: 'BookingAlreadyStarted',
                message: 'La reserva ya comenzó y no puede cancelarse.',
                status: Response::HTTP_CONFLICT
            );
        }

        $policy = $this->policyFor($booking);

        if (! $policy->allow_client_cancellation) {
            throw new ApiException(
                error: 'ClientCancellationDisabled',
                message: 'El profesional no permite cancelaciones online.',
                status: Response::HTTP_CONFLICT
            );
        }

        $cutoff = (int) $policy->cancellation_cutoff_minutes;

        if ($booking->starts_at->gte(now()->addMinutes($cutoff))) {
            return;
        }

        throw new ApiException(
            error: 'CancellationWindowExpired',
            message: 'La cancelación solo está permitida hasta '
                .BookingPolicyCutoffFormatter::format($cutoff)
                .' antes del inicio.',
            status: Response::HTTP_CONFLICT
        );
    }

    private function policyFor(Booking $booking): ProfessionalBookingPolicy
    {
        $booking->loadMissing('professional.bookingPolicy');

        return $booking->professional?->bookingPolicy
            ?? new ProfessionalBookingPolicy(ProfessionalBookingPolicy::DEFAULTS);
    }
}
