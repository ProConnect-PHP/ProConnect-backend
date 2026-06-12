<?php

namespace App\Actions\Booking;

use App\Actions\Booking\Concerns\ValidatesBookingRules;
use App\Actions\Package\ReleasePackageSessionAction;
use App\Actions\Video\CancelVideoSessionAction;
use App\Enums\Booking\BookingStatus;
use App\Events\Booking\BookingCancelled;
use App\Exceptions\ApiException;
use App\Models\Booking\Booking;
use App\Models\User\User;
use App\Services\Booking\BookingCancellationPolicyChecker;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CancelBookingAction
{
    use ValidatesBookingRules;

    public function __construct(
        private readonly ReleasePackageSessionAction $releasePackageSession,
        private readonly CancelVideoSessionAction $cancelVideoSession,
        private readonly BookingCancellationPolicyChecker $policyChecker,
        private readonly NotificationService $notificationService
    ) {}

    public function __invoke(Booking $booking, User $actor, ?string $reason = null): Booking
    {
        return DB::transaction(function () use ($booking, $actor, $reason) {
            $booking = Booking::query()
                ->with(['service', 'professional.bookingPolicy'])
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($actor->id === $booking->client_id) {
                $this->policyChecker->assertClientCanCancel($booking);
            } elseif (! $booking->isCancellable()) {
                throw new ApiException(
                    error: 'InvalidBookingStatusTransition',
                    message: 'La reserva no puede cancelarse en su estado actual.',
                    status: Response::HTTP_CONFLICT
                );
            }

            $previousStatus = $booking->status;

            $booking->update([
                'status' => BookingStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            ($this->releasePackageSession)($booking);
            ($this->cancelVideoSession)($booking);

            $booking = $booking->refresh()->load([
                'service.professional.user',
                'professional.user',
                'client',
                'clientPackage.packageProduct',
                'packageSession',
                'videoSession.participants',
            ]);

            DB::afterCommit(function () use ($booking, $actor, $previousStatus): void {
                event(new BookingCancelled($booking, $actor, $previousStatus));

                // notificación cliente
                $this->notificationService->send(
                    user: $booking->client,
                    type: 'booking.cancelled',
                    title: 'Reserva cancelada',
                    message: "Tu reserva para el servicio '{$booking->service->name}' ha sido cancelada.",
                    actionRoute: "/bookings/{$booking->id}"
                );
            });

            return $booking;
        });
    }
}
