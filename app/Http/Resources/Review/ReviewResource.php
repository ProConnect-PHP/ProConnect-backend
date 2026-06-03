<?php

namespace App\Http\Resources\Review;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'service_id' => $this->service_id,
            'professional_id' => $this->professional_id,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'comment_deleted_at' => $this->comment_deleted_at?->toDateTimeString(),
            'edited_at' => $this->edited_at?->toDateTimeString(),
            'client' => $this->whenLoaded('client', function () {
                return [
                    'id' => $this->client->id,
                    'name' => $this->client->name,
                    'avatar_url' => $this->client->avatar_url,
                ];
            }),
            'reply' => $this->whenLoaded(
                'reply',
                fn () => $this->reply ? new ReviewReplyResource($this->reply) : null
            ),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
