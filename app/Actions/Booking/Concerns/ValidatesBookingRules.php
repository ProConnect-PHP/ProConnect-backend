<?php

namespace App\Actions\Booking\Concerns;

use App\Actions\Availability\GenerateAvailabilitySlotsAction;
use App\Enums\Booking\BookingStatus;
use App\Exceptions\ApiException;
use App\Models\Booking\Booking;
use App\Models\Service\Service;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

trait ValidatesBookingRules
{
    private function ensureServiceCanBeBooked(Service $service, Carbon $startsAt): void
    {
        if (! $service->is_active || ! $service->professional()->whereHas('user')->exists()) {
            throw new ApiException(
                error: 'ServiceNotAvailable',
                message: 'El servicio no está disponible para reservas.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if (
            ($service->starts_at && $startsAt->toDateString() < $service->starts_at->toDateString())
            || ($service->ends_at && $startsAt->toDateString() > $service->ends_at->toDateString())
        ) {
            throw new ApiException(
                error: 'ServiceNotAvailableOnDate',
                message: 'El servicio no está disponible en la fecha seleccionada.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    private function ensureClientDoesNotOwnService(User $client, Service $service): void
    {
        if ($client->professionalProfile?->id !== $service->professional_id) {
            return;
        }

        throw new ApiException(
            error: 'CannotBookOwnService',
            message: 'No puedes reservar tu propio servicio.',
            status: Response::HTTP_FORBIDDEN
        );
    }

    /**
     * Lock lógico por profesional.
     *
     * Esto serializa reservas concurrentes del mismo profesional dentro de la transacción.
     * Es importante porque lockForUpdate sobre Service no alcanza para evitar doble reserva
     * entre servicios distintos del mismo profesional.
     */
    private function lockProfessionalBookingTimeline(int|string $professionalId): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::select(
            'select pg_advisory_xact_lock(hashtext(?))',
            ['professional-booking-timeline:' . $professionalId]
        );
    }

    private function ensureSlotExists(
        Service $service,
        Carbon $startsAt,
        Carbon $endsAt,
        GenerateAvailabilitySlotsAction $generateAvailabilitySlots
    ): void {
        $slots = $generateAvailabilitySlots($service, $startsAt->toDateString());

        $exists = collect($slots)->contains(function (array $slot) use ($startsAt, $endsAt) {
            return Carbon::parse($slot['starts_at'])->equalTo($startsAt)
                && Carbon::parse($slot['ends_at'])->equalTo($endsAt);
        });

        if ($exists) {
            return;
        }

        throw new ApiException(
            error: 'InvalidBookingSlot',
            message: 'El horario seleccionado no está disponible.',
            status: Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }

    private function ensureMaxBookingsPerClient(Service $service, User $client): void
    {
        if ($service->max_bookings_per_client === null) {
            return;
        }

        $activeCount = Booking::query()
            ->where('service_id', $service->id)
            ->where('client_id', $client->id)
            ->whereIn('status', $this->bookingStatusesThatOccupyProfessionalTimeline())
            ->count();

        if ($activeCount < $service->max_bookings_per_client) {
            return;
        }

        throw new ApiException(
            error: 'MaxBookingsPerClientReached',
            message: 'Alcanzaste el máximo de reservas permitidas para este servicio.',
            status: Response::HTTP_CONFLICT
        );
    }

    /**
     * Valida la agenda completa del profesional, no solamente el servicio.
     */
    private function ensureSlotIsNotTaken(
        Service $service,
        Carbon $startsAt,
        Carbon $endsAt,
        ?Booking $exceptBooking = null
    ): void {
        $query = Booking::query()
            ->where('professional_id', $service->professional_id)
            ->whereIn('status', $this->bookingStatusesThatOccupyProfessionalTimeline())
            ->where(function ($query) use ($startsAt, $endsAt) {
                $query
                    ->where('starts_at', '<', $endsAt)
                    ->where('ends_at', '>', $startsAt);
            });

        if ($exceptBooking) {
            $query->whereKeyNot($exceptBooking->id);
        }

        $conflictingBooking = $query->first([
            'id',
            'service_id',
            'professional_id',
            'client_id',
            'starts_at',
            'ends_at',
            'status',
        ]);

        if (! $conflictingBooking) {
            return;
        }

        throw new ApiException(
            error: 'ProfessionalTimeSlotUnavailable',
            message: 'El profesional ya tiene una reserva en ese horario.',
            status: Response::HTTP_CONFLICT,
            details: [
                'conflicting_booking_id' => $conflictingBooking->id,
                'conflicting_service_id' => $conflictingBooking->service_id,
                'conflicting_starts_at' => $conflictingBooking->starts_at?->toDateTimeString(),
                'conflicting_ends_at' => $conflictingBooking->ends_at?->toDateTimeString(),
                'conflicting_status' => $conflictingBooking->status?->value ?? $conflictingBooking->status,
            ],
        );
    }

    /**
     * Estados que bloquean la agenda del profesional.
     *
     * Cancelled no bloquea.
     * NoShow normalmente ocurre después del horario, por eso no lo usamos para bloquear nuevos turnos.
     */
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
