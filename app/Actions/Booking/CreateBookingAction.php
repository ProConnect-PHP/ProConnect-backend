<?php

namespace App\Actions\Booking;

use App\Actions\Availability\GenerateAvailabilitySlotsAction;
use App\Actions\Booking\Concerns\ValidatesBookingRules;
use App\Actions\Package\ReservePackageSessionAction;
use App\Enums\Booking\BookingStatus;
use App\Events\Booking\BookingCreated;
use App\Models\Booking\Booking;
use App\Models\Package\ClientPackage;
use App\Models\Service\Service;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\Notification\NotificationService;


class CreateBookingAction
{
    use ValidatesBookingRules;

    public function __construct(
        private readonly GenerateAvailabilitySlotsAction $generateAvailabilitySlots,
        private readonly ReservePackageSessionAction $reservePackageSession,
        private NotificationService $notificationService
    ) {
    }

    public function __invoke(Service $service, User $client, string $startsAt, ?string $clientPackageId = null): Booking
    {
        return DB::transaction(function () use ($service, $client, $startsAt, $clientPackageId) {
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

            $booking = Booking::create([
                'service_id' => $service->id,
                'professional_id' => $service->professional_id,
                'client_id' => $client->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => BookingStatus::Pending,
                'modality' => $service->modality,
                'price_snapshot' => $service->price,
                'duration_minutes_snapshot' => $service->duration_minutes,
            ]);

            if ($clientPackageId) {
                $clientPackage = ClientPackage::query()->findOrFail($clientPackageId);

                ($this->reservePackageSession)(
                    clientPackage: $clientPackage,
                    booking: $booking
                );

                $booking->refresh();
            }

            $booking->load([
                'service.professional.user',
                'professional.user',
                'client',
                'clientPackage.packageProduct',
                'packageSession',
            ]);

            DB::afterCommit(function () use ($booking): void {
                event(new BookingCreated($booking));
            });

            DB::afterCommit(function () use ($booking, $service): void {

                $professionalUser = $service->professional->user ?? null;

                if ($professionalUser) {
                    $this->notificationService->send(
                        user: $professionalUser,
                        type: 'booking.created',
                        title: 'Nueva reserva',
                        message: 'Tienes una nueva reserva pendiente.',
                        actionRoute: "/professional/bookings/{$booking->id}"
                    );
                }

                event(new BookingCreated($booking));
            });

            return $booking;
        });
    }
}
