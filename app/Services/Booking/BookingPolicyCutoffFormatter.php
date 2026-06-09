<?php

namespace App\Services\Booking;

final class BookingPolicyCutoffFormatter
{
    public static function format(int $minutes): string
    {
        if ($minutes === 0) {
            return 'el inicio';
        }

        if ($minutes % 1440 === 0) {
            $days = intdiv($minutes, 1440);

            return $days === 1 ? '1 día' : "{$days} días";
        }

        if ($minutes % 60 === 0) {
            $hours = intdiv($minutes, 60);

            return $hours === 1 ? '1 hora' : "{$hours} horas";
        }

        return $minutes === 1 ? '1 minuto' : "{$minutes} minutos";
    }
}
