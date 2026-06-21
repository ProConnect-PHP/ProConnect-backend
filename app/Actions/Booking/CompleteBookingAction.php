<?php

namespace App\Actions\Booking;

use App\Actions\Package\ConsumePackageSessionAction;
use App\Enums\Booking\BookingStatus;
use App\Events\Booking\BookingCompleted;
use App\Exceptions\ApiException;
use App\Models\Booking\Booking;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CompleteBookingAction
{
    public function __construct(
        private readonly ConsumePackageSessionAction $consumePackageSession,
    ) {}

    public function __invoke(Booking $booking, User $actor): Booking
    {
        return DB::transaction(function () use ($booking, $actor): Booking {
            $booking = Booking::query()
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $booking->isCompletable()) {
                throw new ApiException(
                    error: 'BookingCannotBeCompleted',
                    message: 'The booking cannot be completed in its current state or before its start time.',
                    status: Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            $previousStatus = $booking->status;

            $booking->update([
                'status' => BookingStatus::Completed,
                'completed_at' => now(),
            ]);

            ($this->consumePackageSession)($booking);

            $booking = $booking->refresh()->load([
                'service',
                'professional.user',
                'client',
                'clientPackage.packageProduct',
                'packageSession.clientPackage',
            ]);

            DB::afterCommit(function () use ($booking, $actor, $previousStatus): void {
                event(new BookingCompleted($booking, $actor, $previousStatus));
            });

            return $booking;
        });
    }
}
