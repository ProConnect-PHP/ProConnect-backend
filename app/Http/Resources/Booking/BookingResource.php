<?php

namespace App\Http\Resources\Booking;

use App\Enums\Booking\BookingStatus;
use App\Http\Resources\Payment\PaymentResource;
use App\Http\Resources\Public\PublicServiceResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_id' => $this->service_id,
            'professional_id' => $this->professional_id,
            'client_id' => $this->client_id,
            'starts_at' => $this->starts_at?->toDateTimeString(),
            'ends_at' => $this->ends_at?->toDateTimeString(),
            'status' => $this->status?->value ?? $this->status,
            'modality' => $this->modality,
            'price_snapshot' => $this->price_snapshot,
            'duration_minutes_snapshot' => $this->duration_minutes_snapshot,
            'confirmed_at' => $this->confirmed_at?->toDateTimeString(),
            'cancelled_at' => $this->cancelled_at?->toDateTimeString(),
            'paid_at' => $this->paid_at?->toDateTimeString(),
            'completed_at' => $this->completed_at?->toDateTimeString(),
            'no_show_at' => $this->no_show_at?->toDateTimeString(),
            'cancellation_reason' => $this->cancellation_reason,
            'reschedule_reason' => $this->reschedule_reason,
            'payment' => new PaymentResource($this->whenLoaded('payment')),
            'payment_status' => $this->when(
                $this->relationLoaded('payment'),
                fn () => $this->payment?->status?->value ?? $this->payment?->status
            ),
            'can_pay' => $this->status === BookingStatus::Confirmed
                && (! $this->relationLoaded('payment') || $this->payment === null),
            'service' => $this->whenLoaded(
                'service',
                fn () => new PublicServiceResource($this->service)
            ),
            'professional' => $this->whenLoaded('professional', function () {
                return [
                    'id' => $this->professional->id,
                    'bio' => $this->professional->bio,
                    'is_verified' => $this->professional->is_verified,
                    'user' => [
                        'id' => $this->professional->user?->id,
                        'name' => $this->professional->user?->name,
                        'avatar_url' => $this->professional->user?->avatar_url,
                    ],
                ];
            }),
            'client' => $this->whenLoaded('client', function () {
                return [
                    'id' => $this->client->id,
                    'name' => $this->client->name,
                    'avatar_url' => $this->client->avatar_url,
                ];
            }),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
