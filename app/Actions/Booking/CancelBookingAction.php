<?php

namespace App\Actions\Booking;

use App\Actions\Booking\Concerns\ValidatesBookingRules;
use App\Actions\Package\ReleasePackageSessionAction;
use App\Enums\Booking\BookingStatus;
use App\Exceptions\ApiException;
use App\Events\Booking\BookingCancelled;
use App\Models\Booking\Booking;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CancelBookingAction
{
    use ValidatesBookingRules;

    public function __construct(
        private readonly ReleasePackageSessionAction $releasePackageSession
    ) {
    }

    public function __invoke(Booking $booking, User $actor, ?string $reason = null): Booking
    {
        return DB::transaction(function () use ($booking, $actor, $reason) {
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

            ($this->releasePackageSession)($booking);

            $booking = $booking->refresh()->load([
                'service.professional.user',
                'professional.user',
                'client',
                'clientPackage.packageProduct',
                'packageSession',
            ]);

            DB::afterCommit(function () use ($booking, $actor): void {
                event(new BookingCancelled($booking, $actor));
            });

            return $booking;
        });
    }
}
