<?php

namespace App\Actions\Booking;

use App\Actions\Availability\GenerateAvailabilitySlotsAction;
use App\Actions\Booking\Concerns\ValidatesBookingRules;
use App\Enums\Booking\BookingStatus;
use App\Exceptions\ApiException;
use App\Models\Booking\Booking;
use App\Models\Service\Service;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RescheduleBookingAction
{
    use ValidatesBookingRules;

    public function __construct(
        private readonly GenerateAvailabilitySlotsAction $generateAvailabilitySlots
    ) {
    }

    public function __invoke(Booking $booking, string $startsAt, ?string $reason = null): Booking
    {
        return DB::transaction(function () use ($booking, $startsAt, $reason) {
            $booking = Booking::query()
                ->with('service')
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            $service = Service::query()
                ->whereKey($booking->service_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $booking->isReschedulable()) {
                throw new ApiException(
                    error: 'InvalidBookingStatusTransition',
                    message: 'La reserva no puede reprogramarse en su estado actual.',
                    status: Response::HTTP_CONFLICT
                );
            }

            $this->ensureCancellationWindowIsOpen($booking, 'RescheduleWindowExpired');

            $startsAt = Carbon::parse($startsAt)->seconds(0);
            $endsAt = $startsAt->copy()->addMinutes((int) $service->duration_minutes);

            $this->ensureServiceCanBeBooked($service, $startsAt);
            $this->ensureSlotExists($service, $startsAt, $endsAt, $this->generateAvailabilitySlots);
            $this->ensureSlotIsNotTaken($service, $startsAt, $endsAt, $booking);

            $booking->update([
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => BookingStatus::Pending,
                'confirmed_at' => null,
                'reschedule_reason' => $reason,
            ]);

            return $booking->refresh()->load([
                'service.professional.user',
                'professional.user',
                'client',
            ]);
        });
    }
}
