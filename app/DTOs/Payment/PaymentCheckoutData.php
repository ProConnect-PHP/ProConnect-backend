<?php

namespace App\DTOs\Payment;

use App\Enums\Payment\PaymentProvider;

final readonly class PaymentCheckoutData
{
    public function __construct(
        public PaymentProvider $provider,
        public string $providerReference,
        public string $checkoutUrl,
        public ?string $externalStatus = null,
        public array $metadata = [],
    ) {}
}
