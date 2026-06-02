<?php

namespace App\Actions\Booking;

use App\Actions\Availability\GenerateAvailabilitySlotsAction;
use App\Actions\Booking\Concerns\ValidatesBookingRules;
use App\Enums\Booking\BookingStatus;
use App\Models\Booking\Booking;
use App\Models\Service\Service;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CreateBookingAction
{
    use ValidatesBookingRules;

    public function __construct(
        private readonly GenerateAvailabilitySlotsAction $generateAvailabilitySlots
    ) {
    }

    public function __invoke(Service $service, User $client, string $startsAt): Booking
    {
        return DB::transaction(function () use ($service, $client, $startsAt) {
            $service = Service::query()
                ->whereKey($service->id)
                ->lockForUpdate()
                ->firstOrFail();

            $startsAt = Carbon::parse($startsAt)->seconds(0);
            $endsAt = $startsAt->copy()->addMinutes((int) $service->duration_minutes);

            $this->ensureServiceCanBeBooked($service, $startsAt);
            $this->ensureClientDoesNotOwnService($client, $service);
            $this->ensureSlotExists($service, $startsAt, $endsAt, $this->generateAvailabilitySlots);
            $this->ensureMaxBookingsPerClient($service, $client);
            $this->ensureSlotIsNotTaken($service, $startsAt, $endsAt);

            return Booking::create([
                'service_id' => $service->id,
                'professional_id' => $service->professional_id,
                'client_id' => $client->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => BookingStatus::Pending,
                'modality' => $service->modality,
                'price_snapshot' => $service->price,
                'duration_minutes_snapshot' => $service->duration_minutes,
            ])->load(['service.professional.user', 'professional.user', 'client']);
        });
    }
}
