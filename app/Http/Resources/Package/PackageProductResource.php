<?php

namespace App\Http\Resources\Package;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'professional_id' => $this->professional_id,
            'service_id' => $this->service_id,
            'name' => $this->name,
            'description' => $this->description,
            'sessions_count' => $this->sessions_count,
            'price' => $this->price,
            'currency' => $this->currency,
            'validity_days' => $this->validity_days,
            'is_active' => $this->is_active,
            'service' => $this->whenLoaded('service', fn () => [
                'id' => $this->service?->id,
                'name' => $this->service?->name,
                'modality' => $this->service?->modality,
                'duration_minutes' => $this->service?->duration_minutes,
            ]),
            'professional' => $this->whenLoaded('professional', function () {
                return [
                    'id' => $this->professional->id,
                    'user' => [
                        'id' => $this->professional->user?->id,
                        'name' => $this->professional->user?->name,
                        'avatar_url' => $this->professional->user?->avatar_url,
                    ],
                ];
            }),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
