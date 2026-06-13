<?php

namespace App\Enums\Payment;

enum PaymentIntentStatus: string
{
    case Pending = 'pending';
    case CheckoutCreated = 'checkout_created';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
