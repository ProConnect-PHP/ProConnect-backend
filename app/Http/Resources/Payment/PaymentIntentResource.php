<?php

namespace App\Http\Resources\Payment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentIntentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'package_product_id' => $this->package_product_id,
            'payable_type' => $this->payable_type?->value ?? $this->payable_type,
            'payable_id' => $this->payable_id,
            'client_id' => $this->client_id,
            'professional_id' => $this->professional_id,
            'provider' => $this->provider?->value ?? $this->provider,
            'status' => $this->status?->value ?? $this->status,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'provider_reference' => $this->provider_reference,
            'checkout_url' => $this->checkout_url,
            'metadata' => $this->metadata,
            'expires_at' => $this->expires_at?->toDateTimeString(),
            'processing_at' => $this->processing_at?->toDateTimeString(),
            'succeeded_at' => $this->succeeded_at?->toDateTimeString(),
            'failed_at' => $this->failed_at?->toDateTimeString(),
            'cancelled_at' => $this->cancelled_at?->toDateTimeString(),
            'failure_reason' => $this->failure_reason,
            'payment' => new PaymentResource($this->whenLoaded('payment')),
            'booking' => $this->whenLoaded('booking', function () {
                return [
                    'id' => $this->booking->id,
                    'status' => $this->booking->status?->value ?? $this->booking->status,
                    'starts_at' => $this->booking->starts_at?->toDateTimeString(),
                    'ends_at' => $this->booking->ends_at?->toDateTimeString(),
                    'service_id' => $this->booking->service_id,
                ];
            }),
            'package_product' => $this->whenLoaded('packageProduct', function () {
                return [
                    'id' => $this->packageProduct->id,
                    'name' => $this->packageProduct->name,
                    'sessions_count' => $this->packageProduct->sessions_count,
                    'service_id' => $this->packageProduct->service_id,
                ];
            }),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
