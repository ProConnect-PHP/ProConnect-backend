<?php

namespace App\Support\Geo;

final class Haversine
{
    private const EARTH_RADIUS_KM = 6371;

    public static function distanceExpression(): string
    {
        return '(6371 * acos(LEAST(1, GREATEST(-1, cos(radians(?)) * cos(radians(services.latitude)) * cos(radians(services.longitude) - radians(?)) + sin(radians(?)) * sin(radians(services.latitude))))))';
    }

    public static function bindings(float $latitude, float $longitude): array
    {
        return [$latitude, $longitude, $latitude];
    }

    public static function distanceBetween(
        float $fromLatitude,
        float $fromLongitude,
        float $toLatitude,
        float $toLongitude
    ): float {
        $fromLatitude = deg2rad($fromLatitude);
        $fromLongitude = deg2rad($fromLongitude);
        $toLatitude = deg2rad($toLatitude);
        $toLongitude = deg2rad($toLongitude);

        $latDelta = $toLatitude - $fromLatitude;
        $lngDelta = $toLongitude - $fromLongitude;

        $a = sin($latDelta / 2) ** 2
            + cos($fromLatitude) * cos($toLatitude) * sin($lngDelta / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
