<?php

namespace App\Services\Booking;

use App\Exceptions\ApiException;
use App\Models\Booking\Booking;

class BookingAvailableActionsService
{
    public function __construct(
        private readonly BookingCancellationPolicyChecker $cancellationChecker,
        private readonly BookingReschedulingPolicyChecker $reschedulingChecker
    ) {}

    public function getForClient(Booking $booking): array
    {
        [$canCancel, $cancelReason] = $this->evaluate(
            fn () => $this->cancellationChecker->assertClientCanCancel($booking)
        );
        [$canReschedule, $rescheduleReason] = $this->evaluate(
            fn () => $this->reschedulingChecker->assertClientCanReschedule($booking)
        );

        return [
            'can_cancel' => $canCancel,
            'can_reschedule' => $canReschedule,
            'cancel_disabled_reason' => $cancelReason,
            'reschedule_disabled_reason' => $rescheduleReason,
        ];
    }

    private function evaluate(callable $check): array
    {
        try {
            $check();

            return [true, null];
        } catch (ApiException $exception) {
            return [false, $exception->getMessage()];
        }
    }
}
