<?php

namespace App\Http\Resources\Video;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VideoSessionParticipantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'video_session_id' => $this->video_session_id,
            'user_id' => $this->user_id,
            'role' => $this->role,
            'provider_identity' => $this->provider_identity,
            'display_name' => $this->display_name,
            'first_joined_at' => $this->first_joined_at?->toDateTimeString(),
            'last_joined_at' => $this->last_joined_at?->toDateTimeString(),
            'left_at' => $this->left_at?->toDateTimeString(),
            'join_count' => $this->join_count,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
