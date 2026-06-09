<?php

namespace App\Enums\Booking;

enum BookingReminderDeliveryStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Sent = 'sent';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
