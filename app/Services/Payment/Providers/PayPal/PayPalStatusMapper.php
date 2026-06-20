<?php

namespace App\Services\Payment\Providers\PayPal;

use App\Enums\Payment\PaymentStatus;

final class PayPalStatusMapper
{
    public function map(?string $status): PaymentStatus
    {
        return match (strtoupper((string) $status)) {
            'COMPLETED' => PaymentStatus::Succeeded,
            'PENDING' => PaymentStatus::Pending,
            'VOIDED' => PaymentStatus::Cancelled,
            'DENIED', 'DECLINED', 'FAILED' => PaymentStatus::Rejected,
            default => PaymentStatus::Pending,
        };
    }
}
