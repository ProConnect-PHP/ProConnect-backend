<?php

namespace App\Services\Payment\Providers\MercadoPago;

use App\Enums\Payment\PaymentStatus;

final class MercadoPagoStatusMapper
{
    public function map(?string $status): PaymentStatus
    {
        return match (strtolower((string) $status)) {
            'approved' => PaymentStatus::Succeeded,
            'rejected' => PaymentStatus::Rejected,
            'cancelled' => PaymentStatus::Cancelled,
            'refunded', 'charged_back' => PaymentStatus::Refunded,
            default => PaymentStatus::Pending,
        };
    }
}
