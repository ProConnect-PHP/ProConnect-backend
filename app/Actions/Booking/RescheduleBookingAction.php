<?php

namespace App\Actions\Booking;

use App\Actions\Availability\GenerateAvailabilitySlotsAction;
use App\Actions\Booking\Concerns\ValidatesBookingRules;
use App\Enums\Booking\BookingReminderDeliveryStatus;
use App\Enums\Booking\BookingStatus;
use App\Events\Booking\BookingRescheduled;
use App\Exceptions\ApiException;
use App\Models\Booking\Booking;
use App\Models\Service\Service;
use App\Models\User\User;
use App\Services\Booking\BookingReschedulingPolicyChecker;
use App\Services\Notification\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RescheduleBookingAction
{
    use ValidatesBookingRules;

    public function __construct(
        private readonly GenerateAvailabilitySlotsAction $generateAvailabilitySlots,
        private readonly BookingReschedulingPolicyChecker $policyChecker,
        private readonly NotificationService $notificationService
    ) {}

    public function __invoke(Booking $booking, User $actor, string $startsAt, ?string $reason = null): Booking
    {
        return DB::transaction(function () use ($booking, $actor, $startsAt, $reason) {
            $booking = Booking::query()
                ->with(['service', 'professional.bookingPolicy'])
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            $service = Service::query()
                ->whereKey($booking->service_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($actor->id === $booking->client_id) {
                $this->policyChecker->assertClientCanReschedule($booking);
            } elseif (! $booking->isReschedulable()) {
                throw new ApiException(
                    error: 'InvalidBookingStatusTransition',
                    message: 'La reserva no puede reprogramarse en su estado actual.',
                    status: Response::HTTP_CONFLICT
                );
            }

            $oldStartsAt = $booking->starts_at?->toISOString();
            $oldEndsAt = $booking->ends_at?->toISOString();

            $startsAt = Carbon::parse($startsAt)->seconds(0);
            $endsAt = $startsAt->copy()->addMinutes((int) $service->duration_minutes);

            $this->ensureServiceCanBeBooked($service, $startsAt);
            $this->ensureSlotExists($service, $startsAt, $endsAt, $this->generateAvailabilitySlots);
            $this->ensureSlotIsNotTaken($service, $startsAt, $endsAt, $booking);

            $booking->update([
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => $booking->status === BookingStatus::Paid
                    ? BookingStatus::Paid
                    : BookingStatus::Pending,
                'confirmed_at' => $booking->status === BookingStatus::Paid
                    ? $booking->confirmed_at
                    : null,
                'reschedule_reason' => $reason,
            ]);

            $booking->reminderDeliveries()
                ->where('status', '!=', BookingReminderDeliveryStatus::Sent->value)
                ->delete();

            $booking = $booking->refresh()->load([
                'service.professional.user',
                'professional.user',
                'client',
            ]);

            DB::afterCommit(function () use ($booking, $actor, $oldStartsAt, $oldEndsAt): void {
                event(new BookingRescheduled($booking, $actor, $oldStartsAt, $oldEndsAt));

                if ($actor->id === $booking->client_id) {
                    $recipient = $booking->professional->user;
                } else {
                    $recipient = $booking->client;
                }

                $this->notificationService->send(
                    user: $recipient,
                    type: 'booking.rescheduled',
                    title: 'Reserva reprogramada',
                    message: "Tu reserva para el servicio '{$booking->service->name}' ha sido reprogramada para el {$booking->starts_at->format('d/m/Y \a las H:i')}.",
                    actionRoute: "/bookings/{$booking->id}"
                );

            });

            return $booking;
        });
    }
}
