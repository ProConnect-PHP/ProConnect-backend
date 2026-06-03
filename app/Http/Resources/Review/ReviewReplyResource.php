<?php

namespace App\Http\Resources\Review;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewReplyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'edited_at' => $this->edited_at?->toDateTimeString(),
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
