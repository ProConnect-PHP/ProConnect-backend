<?php

namespace App\Enums\Payment;

enum PayableType: string
{
    case Booking = 'booking';
    case Package = 'package';
}
