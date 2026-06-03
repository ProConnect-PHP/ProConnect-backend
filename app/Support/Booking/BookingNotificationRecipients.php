<?php

namespace App\Support\Booking;

use App\Models\Booking\Booking;
use App\Models\User\User;
use Illuminate\Support\Collection;

final class BookingNotificationRecipients
{
    public static function professionalUser(Booking $booking): ?User
    {
        return $booking->professional?->user;
    }

    public static function clientUser(Booking $booking): ?User
    {
        return $booking->client;
    }

    public static function counterpartUsers(Booking $booking, ?User $actor): Collection
    {
        $client = self::clientUser($booking);
        $professional = self::professionalUser($booking);

        if ($actor && $client && $actor->getKey() === $client->getKey()) {
            return self::uniqueUsers([$professional]);
        }

        if ($actor && $professional && $actor->getKey() === $professional->getKey()) {
            return self::uniqueUsers([$client]);
        }

        return self::uniqueUsers([$client, $professional]);
    }

    public static function reminderUsers(Booking $booking): Collection
    {
        return self::uniqueUsers([
            self::clientUser($booking),
            self::professionalUser($booking),
        ]);
    }

    private static function uniqueUsers(array $users): Collection
    {
        return collect($users)
            ->filter(fn ($user): bool => $user instanceof User && filled($user->email))
            ->unique(fn (User $user): string => (string) $user->getKey())
            ->values();
    }
}
