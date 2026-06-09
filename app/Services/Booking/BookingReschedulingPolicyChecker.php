<?php

namespace App\Services\Booking;

use App\Exceptions\ApiException;
use App\Models\Booking\Booking;
use App\Models\Booking\ProfessionalBookingPolicy;
use Symfony\Component\HttpFoundation\Response;

class BookingReschedulingPolicyChecker
{
    public function assertClientCanReschedule(Booking $booking): void
    {
        if (! $booking->isReschedulable()) {
            throw new ApiException(
                error: 'InvalidBookingStatusTransition',
                message: 'La reserva no puede reprogramarse en su estado actual.',
                status: Response::HTTP_CONFLICT
            );
        }

        if ($booking->starts_at->lte(now())) {
            throw new ApiException(
                error: 'BookingAlreadyStarted',
                message: 'La reserva ya comenzó y no puede reprogramarse.',
                status: Response::HTTP_CONFLICT
            );
        }

        $policy = $this->policyFor($booking);

        if (! $policy->allow_client_rescheduling) {
            throw new ApiException(
                error: 'ClientReschedulingDisabled',
                message: 'El profesional no permite reprogramaciones online.',
                status: Response::HTTP_CONFLICT
            );
        }

        $cutoff = (int) $policy->rescheduling_cutoff_minutes;

        if ($booking->starts_at->gte(now()->addMinutes($cutoff))) {
            return;
        }

        throw new ApiException(
            error: 'ReschedulingWindowExpired',
            message: 'La reprogramación solo está permitida hasta '
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
