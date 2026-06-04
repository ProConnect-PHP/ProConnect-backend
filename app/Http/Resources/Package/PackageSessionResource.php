<?php

namespace App\Http\Resources\Package;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_package_id' => $this->client_package_id,
            'booking_id' => $this->booking_id,
            'client_id' => $this->client_id,
            'professional_id' => $this->professional_id,
            'status' => $this->status?->value ?? $this->status,
            'consumed_at' => $this->consumed_at?->toDateTimeString(),
            'released_at' => $this->released_at?->toDateTimeString(),
            'metadata' => $this->metadata,
            'booking' => $this->whenLoaded('booking', fn () => [
                'id' => $this->booking->id,
                'status' => $this->booking->status?->value ?? $this->booking->status,
                'starts_at' => $this->booking->starts_at?->toDateTimeString(),
                'ends_at' => $this->booking->ends_at?->toDateTimeString(),
            ]),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
