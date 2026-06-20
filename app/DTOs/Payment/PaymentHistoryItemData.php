<?php

namespace App\DTOs\Payment;

use Carbon\CarbonInterface;

final readonly class PaymentHistoryItemData
{
    /**
     * @param  array<string, mixed>|null  $booking
     * @param  array<string, mixed>|null  $packageProduct
     */
    public function __construct(
        public string $id,
        public string $source,
        public ?string $paymentId,
        public ?string $paymentIntentId,
        public ?string $bookingId,
        public ?string $packageProductId,
        public ?string $provider,
        public string $status,
        public string $displayStatus,
        public int $amount,
        public string $currency,
        public ?string $providerReference,
        public ?string $providerPaymentId,
        public ?CarbonInterface $paidAt,
        public ?CarbonInterface $failedAt,
        public ?CarbonInterface $createdAt,
        public ?string $failureReason,
        public bool $canRetry,
        public ?array $booking,
        public ?array $packageProduct,
    ) {}
}
