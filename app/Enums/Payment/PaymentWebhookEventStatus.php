<?php

namespace App\Enums\Payment;

enum PaymentWebhookEventStatus: string
{
    case Received = 'received';
    case Processing = 'processing';
    case Processed = 'processed';
    case Ignored = 'ignored';
    case InvalidSignature = 'invalid_signature';
    case Failed = 'failed';
}
