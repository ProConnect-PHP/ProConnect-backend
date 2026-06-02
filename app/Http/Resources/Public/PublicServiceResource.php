<?php

namespace App\Http\Resources\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'duration_minutes' => $this->duration_minutes,
            'modality' => $this->modality,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'buffer_minutes' => $this->buffer_minutes,
            'min_reschedule_minutes' => $this->min_reschedule_minutes,
            'is_active' => $this->is_active,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'distance_km' => $this->when(
                array_key_exists('distance_km', $this->resource->getAttributes()),
                fn () => round((float) $this->distance_km, 2)
            ),
            'professional' => $this->whenLoaded(
                'professional',
                fn () => new PublicProfessionalResource($this->professional)
            ),
            'company' => $this->whenLoaded(
                'company',
                fn () => $this->company && ! $this->company->is_private
                    ? new PublicCompanyResource($this->company)
                    : null
            ),
            'created_at' => $this->created_at,
        ];
    }
}
