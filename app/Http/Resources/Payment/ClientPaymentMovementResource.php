<?php

namespace App\Http\Resources\Payment;

use App\DTOs\Payment\ClientPaymentMovementData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ClientPaymentMovementData */
class ClientPaymentMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'status' => $this->status,
            'display_status' => $this->displayStatus,
            'is_final' => $this->isFinal,
            'is_successful' => $this->isSuccessful,
            'is_pending' => $this->isPending,
            'can_retry' => $this->canRetry,
            'can_continue_checkout' => $this->canContinueCheckout,
            'can_view_booking' => $this->canViewBooking,
            'can_refresh_status' => $this->canRefreshStatus,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'provider' => $this->provider,
            'provider_reference' => $this->providerReference,
            'provider_payment_id' => $this->providerPaymentId,
            'provider_status' => $this->providerStatus,
            'payment_intent_id' => $this->paymentIntentId,
            'booking_id' => $this->bookingId,
            'package_product_id' => $this->packageProductId,
            'client_package_id' => $this->clientPackageId,
            'client_id' => $this->clientId,
            'professional_id' => $this->professionalId,
            'checkout_url' => $this->checkoutUrl,
            'booking' => $this->booking,
            'package_product' => $this->packageProduct,
            'client_package' => $this->clientPackage,
            'metadata' => $this->metadata,
            'failure_reason' => $this->failureReason,
            'created_at' => $this->createdAt?->toDateTimeString(),
            'updated_at' => $this->updatedAt?->toDateTimeString(),
            'relevant_at' => $this->relevantAt?->toDateTimeString(),
            'paid_at' => $this->paidAt?->toDateTimeString(),
            'failed_at' => $this->failedAt?->toDateTimeString(),
            'cancelled_at' => $this->cancelledAt?->toDateTimeString(),
            'expires_at' => $this->expiresAt?->toDateTimeString(),
            'refunded_at' => $this->refundedAt?->toDateTimeString(),
        ];
    }
}
