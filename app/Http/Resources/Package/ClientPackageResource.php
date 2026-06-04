<?php

namespace App\Http\Resources\Package;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientPackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'package_product_id' => $this->package_product_id,
            'client_id' => $this->client_id,
            'professional_id' => $this->professional_id,
            'service_id' => $this->service_id,
            'status' => $this->status?->value ?? $this->status,
            'total_sessions' => $this->total_sessions,
            'used_sessions' => $this->used_sessions,
            'remaining_sessions' => $this->remainingSessions(),
            'price_snapshot' => $this->price_snapshot,
            'currency' => $this->currency,
            'purchased_at' => $this->purchased_at?->toDateTimeString(),
            'expires_at' => $this->expires_at?->toDateTimeString(),
            'cancelled_at' => $this->cancelled_at?->toDateTimeString(),
            'depleted_at' => $this->depleted_at?->toDateTimeString(),
            'metadata' => $this->metadata,
            'package_product' => new PackageProductResource($this->whenLoaded('packageProduct')),
            'service' => $this->whenLoaded('service', fn () => [
                'id' => $this->service?->id,
                'name' => $this->service?->name,
                'modality' => $this->service?->modality,
            ]),
            'sessions' => PackageSessionResource::collection($this->whenLoaded('sessions')),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
