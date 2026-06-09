<?php

namespace App\Http\Resources\Video;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VideoSessionJoinResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'video_session_id' => $this->resource['video_session']->id,
            'provider' => $this->resource['video_session']->provider?->value ?? $this->resource['video_session']->provider,
            'room_name' => $this->resource['video_session']->room_name,
            'join_url' => $this->resource['join_url'],
            'access_token' => $this->resource['access_token'],
            'participant' => new VideoSessionParticipantResource($this->resource['participant']),
            'expires_at' => $this->resource['expires_at']?->toDateTimeString(),
        ];
    }
}
