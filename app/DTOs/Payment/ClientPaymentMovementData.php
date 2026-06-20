<?php

namespace App\DTOs\Payment;

use Carbon\CarbonInterface;

final readonly class ClientPaymentMovementData
{
    /**
     * @param  array<string, mixed>|null  $booking
     * @param  array<string, mixed>|null  $packageProduct
     * @param  array<string, mixed>|null  $clientPackage
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $id,
        public string $kind,
        public string $status,
        public string $displayStatus,
        public bool $isFinal,
        public bool $isSuccessful,
        public bool $isPending,
        public bool $canRetry,
        public bool $canContinueCheckout,
        public bool $canViewBooking,
        public bool $canRefreshStatus,
        public int $amount,
        public string $currency,
        public string $provider,
        public ?string $providerReference,
        public ?string $providerPaymentId,
        public ?string $providerStatus,
        public ?string $paymentIntentId,
        public ?string $bookingId,
        public ?string $packageProductId,
        public ?string $clientPackageId,
        public ?string $clientId,
        public ?string $professionalId,
        public ?string $checkoutUrl,
        public ?array $booking,
        public ?array $packageProduct,
        public ?array $clientPackage,
        public ?array $metadata,
        public ?string $failureReason,
        public ?CarbonInterface $createdAt,
        public ?CarbonInterface $updatedAt,
        public ?CarbonInterface $relevantAt,
        public ?CarbonInterface $paidAt,
        public ?CarbonInterface $failedAt,
        public ?CarbonInterface $cancelledAt,
        public ?CarbonInterface $expiresAt,
    ) {}
}
