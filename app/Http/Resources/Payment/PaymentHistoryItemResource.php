<?php

namespace App\Http\Resources\Payment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentHistoryItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'payment_id' => $this->paymentId,
            'payment_intent_id' => $this->paymentIntentId,
            'booking_id' => $this->bookingId,
            'package_product_id' => $this->packageProductId,
            'provider' => $this->provider,
            'status' => $this->status,
            'display_status' => $this->displayStatus,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'provider_reference' => $this->providerReference,
            'provider_payment_id' => $this->providerPaymentId,
            'paid_at' => $this->paidAt?->toDateTimeString(),
            'failed_at' => $this->failedAt?->toDateTimeString(),
            'created_at' => $this->createdAt?->toDateTimeString(),
            'failure_reason' => $this->failureReason,
            'can_retry' => $this->canRetry,
            'booking' => $this->booking,
            'package_product' => $this->packageProduct,
        ];
    }
}
