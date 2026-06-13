<?php

namespace App\DTOs\Payment;

use App\Enums\Payment\PaymentStatus;

final readonly class ProviderPaymentStatus
{
    public function __construct(
        public string $providerReference,
        public PaymentStatus $status,
        public string $rawStatus,
        public ?string $providerPaymentId = null,
        public ?string $paymentIntentId = null,
        public ?string $paidAt = null,
        public ?string $amount = null,
        public ?string $currency = null,
        public array $metadata = [],
    ) {}
}
