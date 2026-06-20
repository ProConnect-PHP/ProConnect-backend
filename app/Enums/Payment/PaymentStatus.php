<?php

namespace App\Enums\Payment;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
