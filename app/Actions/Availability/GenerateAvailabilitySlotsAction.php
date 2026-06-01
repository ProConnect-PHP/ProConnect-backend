<?php

namespace App\Actions\Availability;

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

        while (true) {
            $slotEnd = $current->copy()->addMinutes($duration);

            if ($slotEnd->gt($endBoundary)) {
                break;
            }

            $slots[] = [
                'starts_at' => $current->toDateTimeString(),
                'ends_at' => $slotEnd->toDateTimeString(),
            ];

            $current = $slotEnd->copy()->addMinutes($buffer);
        }

        return $slots;
    }
}
