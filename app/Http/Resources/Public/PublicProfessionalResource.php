<?php

namespace App\Http\Resources\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicProfessionalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bio' => $this->bio,
            'avg_rating' => $this->avg_rating,
            'reviews_count' => $this->reviews_count,
            'is_verified' => $this->is_verified,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'avatar_url' => $this->user?->avatar_url,
            ]),
            'services' => $this->whenLoaded(
                'services',
                fn () => PublicServiceResource::collection($this->services)
            ),
        ];
    }
}
