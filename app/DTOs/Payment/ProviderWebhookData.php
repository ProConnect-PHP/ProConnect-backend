<?php

namespace App\DTOs\Payment;

use App\Enums\Payment\PaymentProvider;

final readonly class ProviderWebhookData
{
    public function __construct(
        public PaymentProvider $provider,
        public ?string $providerEventId,
        public ?string $eventType,
        public ?string $resourceType,
        public ?string $resourceId,
        public bool $signatureValid,
        public array $payload,
    ) {}
}
