<?php

namespace App\Services\Payment\Providers\PayPal;

use App\Enums\Payment\PaymentStatus;

final class PayPalStatusMapper
{
    public function map(?string $status): PaymentStatus
    {
        return match (strtoupper((string) $status)) {
            'COMPLETED' => PaymentStatus::Succeeded,
            'VOIDED' => PaymentStatus::Cancelled,
            'DECLINED', 'FAILED' => PaymentStatus::Rejected,
            'REFUNDED' => PaymentStatus::Refunded,
            'PARTIALLY_REFUNDED' => PaymentStatus::PartiallyRefunded,
            default => PaymentStatus::Pending,
        };
    }
}
