<?php

namespace App\Http\Resources\Video;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VideoSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'client_id' => $this->client_id,
            'professional_id' => $this->professional_id,
            'provider' => $this->provider?->value ?? $this->provider,
            'status' => $this->status?->value ?? $this->status,
            'room_name' => $this->room_name,
            'join_url' => $this->join_url,
            'scheduled_start_at' => $this->scheduled_start_at?->toDateTimeString(),
            'scheduled_end_at' => $this->scheduled_end_at?->toDateTimeString(),
            'opened_at' => $this->opened_at?->toDateTimeString(),
            'started_at' => $this->started_at?->toDateTimeString(),
            'ended_at' => $this->ended_at?->toDateTimeString(),
            'cancelled_at' => $this->cancelled_at?->toDateTimeString(),
            'expired_at' => $this->expired_at?->toDateTimeString(),
            'can_join_now' => $this->isJoinWindowOpen() && ! $this->hasEnded() && ! $this->isCancelled(),
            'booking' => $this->whenLoaded('booking', fn () => [
                'id' => $this->booking->id,
                'status' => $this->booking->status instanceof BackedEnum
                    ? $this->booking->status->value
                    : $this->booking->status,
                'starts_at' => $this->booking->starts_at?->toDateTimeString(),
                'ends_at' => $this->booking->ends_at?->toDateTimeString(),
                'service_id' => $this->booking->service_id,
            ]),
            'participants' => VideoSessionParticipantResource::collection($this->whenLoaded('participants')),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
