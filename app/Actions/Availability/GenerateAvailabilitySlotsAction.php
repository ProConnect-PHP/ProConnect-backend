<?php

namespace App\Actions\Availability;

use App\Enums\Booking\BookingStatus;
use App\Models\Booking\Booking;
use App\Models\Service\Service;
use Carbon\Carbon;

class GenerateAvailabilitySlotsAction
{
    public function __invoke(Service $service, string $requestedDate): array
    {
        $slots = [];

        $date = Carbon::parse($requestedDate);

        $rule = $service
            ->availabilityRules()
            ->where('day_of_week', $date->dayOfWeekIso)
            ->where('is_active', true)
            ->first();

        if (! $rule) {
            return [];
        }

        $exception = $service
            ->availabilityExceptions()
            ->whereDate('exception_date', $date->toDateString())
            ->first();

        if ($exception?->is_unavailable) {
            return [];
        }

        $startTime = $exception?->alt_start ?? $rule->start_time;
        $endTime = $exception?->alt_end ?? $rule->end_time;

        if (! $startTime || ! $endTime) {
            return [];
        }

        $duration = (int) $service->duration_minutes;
        $buffer = (int) $service->buffer_minutes;

        $current = Carbon::parse($date->toDateString().' '.$startTime);
        $endBoundary = Carbon::parse($date->toDateString().' '.$endTime);

        $busyBookings = $this->busyBookingsForProfessional(
            service: $service,
            rangeStart: $current->copy(),
            rangeEnd: $endBoundary->copy(),
        );

        while (true) {
            $slotEnd = $current->copy()->addMinutes($duration);

            if ($slotEnd->gt($endBoundary)) {
                break;
            }

            if (! $this->slotOverlapsBusyBooking($current, $slotEnd, $busyBookings)) {
                $slots[] = [
                    'starts_at' => $current->toDateTimeString(),
                    'ends_at' => $slotEnd->toDateTimeString(),
                ];
            }

            $current = $slotEnd->copy()->addMinutes($buffer);
        }

        return $slots;
    }

    private function busyBookingsForProfessional(Service $service, Carbon $rangeStart, Carbon $rangeEnd)
    {
        return Booking::query()
            ->where('professional_id', $service->professional_id)
            ->whereIn('status', $this->bookingStatusesThatOccupyProfessionalTimeline())
            ->where('starts_at', '<', $rangeEnd)
            ->where('ends_at', '>', $rangeStart)
            ->get([
                'id',
                'service_id',
                'professional_id',
                'starts_at',
                'ends_at',
                'status',
            ]);
    }

    private function slotOverlapsBusyBooking(Carbon $slotStart, Carbon $slotEnd, iterable $busyBookings): bool
    {
        foreach ($busyBookings as $booking) {
            if ($slotStart->lt($booking->ends_at) && $slotEnd->gt($booking->starts_at)) {
                return true;
            }
        }

        return false;
    }

    private function bookingStatusesThatOccupyProfessionalTimeline(): array
    {
        return [
            BookingStatus::Pending->value,
            BookingStatus::Confirmed->value,
            BookingStatus::Paid->value,
            BookingStatus::InProgress->value,
        ];
    }
}
