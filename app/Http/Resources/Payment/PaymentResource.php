<?php

namespace App\Http\Resources\Payment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_intent_id' => $this->payment_intent_id,
            'booking_id' => $this->booking_id,
            'package_product_id' => $this->package_product_id,
            'client_package_id' => $this->client_package_id,
            'client_id' => $this->client_id,
            'professional_id' => $this->professional_id,
            'provider' => $this->provider?->value ?? $this->provider,
            'status' => $this->status?->value ?? $this->status,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'provider_reference' => $this->provider_reference,
            'provider_payment_id' => $this->provider_payment_id,
            'raw_provider_status' => $this->raw_provider_status,
            'metadata' => $this->metadata,
            'paid_at' => $this->paid_at?->toDateTimeString(),
            'failed_at' => $this->failed_at?->toDateTimeString(),
            'refunded_at' => $this->refunded_at?->toDateTimeString(),
            'failure_reason' => $this->failure_reason,
            'booking' => $this->whenLoaded('booking', function () {
                return [
                    'id' => $this->booking->id,
                    'status' => $this->booking->status?->value ?? $this->booking->status,
                    'starts_at' => $this->booking->starts_at?->toDateTimeString(),
                    'ends_at' => $this->booking->ends_at?->toDateTimeString(),
                    'service_id' => $this->booking->service_id,
                ];
            }),
            'client_package' => $this->whenLoaded('clientPackage', function () {
                return [
                    'id' => $this->clientPackage->id,
                    'package_product_id' => $this->clientPackage->package_product_id,
                    'status' => $this->clientPackage->status?->value
                        ?? $this->clientPackage->status,
                    'total_sessions' => $this->clientPackage->total_sessions,
                    'used_sessions' => $this->clientPackage->used_sessions,
                ];
            }),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
