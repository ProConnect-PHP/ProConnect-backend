<?php

namespace App\Support\Booking;

use App\Models\Booking\Booking;

final class BookingLocationPresenter
{
    public static function hasPhysicalLocation(Booking $booking): bool
    {
        return in_array($booking->modality, ['presencial', 'hibrida'], true)
            && filled($booking->service?->address);
    }

    public static function address(Booking $booking): ?string
    {
        return $booking->service?->address;
    }

    public static function mapUrl(Booking $booking): ?string
    {
        $service = $booking->service;

        if (! $service) {
            return null;
        }

        if ($service->latitude !== null && $service->longitude !== null) {
            return sprintf(
                'https://www.google.com/maps/search/?api=1&query=%s,%s',
                $service->latitude,
                $service->longitude
            );
        }

        if (filled($service->address)) {
            return 'https://www.google.com/maps/search/?api=1&query='.urlencode($service->address);
        }

        return null;
    }

    public static function staticMapImageUrl(Booking $booking): ?string
    {
        $service = $booking->service;

        if (! $service || $service->latitude === null || $service->longitude === null) {
            return null;
        }

        $token = config('services.mapbox.public_token');

        if (! $token) {
            return null;
        }

        $longitude = $service->longitude;
        $latitude = $service->latitude;

        return sprintf(
            'https://api.mapbox.com/styles/v1/mapbox/streets-v12/static/pin-s+4f46e5(%s,%s)/%s,%s,14,0/640x320?access_token=%s',
            $longitude,
            $latitude,
            $longitude,
            $latitude,
            $token
        );
    }
}
