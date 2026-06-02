<?php

namespace App\Actions\Booking\Concerns;

use App\Actions\Availability\GenerateAvailabilitySlotsAction;
use App\Enums\Booking\BookingStatus;
use App\Exceptions\ApiException;
use App\Models\Booking\Booking;
use App\Models\Service\Service;
use App\Models\User\User;
use Carbon\Carbon;
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
            ->whereIn('status', [
                BookingStatus::Pending->value,
                BookingStatus::Confirmed->value,
                BookingStatus::Paid->value,
                BookingStatus::InProgress->value,
            ])
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

    private function ensureSlotIsNotTaken(
        Service $service,
        Carbon $startsAt,
        Carbon $endsAt,
        ?Booking $exceptBooking = null
    ): void {
        $query = Booking::query()
            ->where('service_id', $service->id)
            ->whereNotIn('status', [
                BookingStatus::Cancelled->value,
                BookingStatus::NoShow->value,
            ])
            ->where(function ($query) use ($startsAt, $endsAt) {
                $query
                    ->where('starts_at', '<', $endsAt)
                    ->where('ends_at', '>', $startsAt);
            });

        if ($exceptBooking) {
            $query->whereKeyNot($exceptBooking->id);
        }

        if (! $query->exists()) {
            return;
        }

        throw new ApiException(
            error: 'BookingSlotAlreadyTaken',
            message: 'El horario seleccionado ya fue reservado.',
            status: Response::HTTP_CONFLICT
        );
    }

    private function ensureCancellationWindowIsOpen(Booking $booking, string $error): void
    {
        $deadline = now()->addMinutes((int) $booking->service->min_reschedule_minutes);

        if ($booking->starts_at->gte($deadline)) {
            return;
        }

        $message = $error === 'RescheduleWindowExpired'
            ? 'Ya no es posible reprogramar esta reserva.'
            : 'Ya no es posible cancelar esta reserva.';

        throw new ApiException(
            error: $error,
            message: $message,
            status: Response::HTTP_CONFLICT
        );
    }
}
