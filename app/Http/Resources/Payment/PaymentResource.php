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
            'client_id' => $this->client_id,
            'professional_id' => $this->professional_id,
            'provider' => $this->provider?->value ?? $this->provider,
            'status' => $this->status?->value ?? $this->status,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'provider_reference' => $this->provider_reference,
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
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
