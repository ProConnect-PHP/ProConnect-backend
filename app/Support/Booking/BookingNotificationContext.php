<?php

namespace App\Support\Booking;

use App\Models\Booking\Booking;
use App\Models\User\User;
use InvalidArgumentException;

final class BookingNotificationContext
{
    public static function actionRoute(Booking $booking, User $recipient): string
    {
        $booking->loadMissing([
            'professional.user',
            'client',
        ]);

        if ($booking->client?->is($recipient)) {
            return "/my-bookings/{$booking->id}";
        }

        if ($booking->professional?->user?->is($recipient)) {
            return "/professional/bookings/{$booking->id}";
        }

        throw new InvalidArgumentException('The notification recipient is not a booking participant.');
    }

    /**
     * @return array{
     *     booking_id: string,
     *     service_id: string,
     *     service_name: string,
     *     client_id: string,
     *     client_name: string,
     *     professional_id: string,
     *     professional_name: string,
     *     starts_at: string|null,
     *     ends_at: string|null
     * }
     */
    public static function metadata(Booking $booking): array
    {
        $booking->loadMissing([
            'service',
            'professional.user',
            'client',
        ]);

        return [
            'booking_id' => $booking->id,
            'service_id' => $booking->service_id,
            'service_name' => $booking->service->name,
            'client_id' => $booking->client_id,
            'client_name' => $booking->client->name,
            'professional_id' => $booking->professional_id,
            'professional_name' => $booking->professional->user->name,
            'starts_at' => $booking->starts_at?->toISOString(),
            'ends_at' => $booking->ends_at?->toISOString(),
        ];
    }
}
