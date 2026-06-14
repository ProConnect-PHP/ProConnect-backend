<?php

namespace App\Http\Resources\Notification;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'action_route' => $this->action_route,
            'metadata' => $this->metadata ?? [],
            'is_read' => $this->read_at !== null,
            'is_archived' => $this->archived_at !== null,
            'read_at' => $this->read_at?->toISOString(),
            'archived_at' => $this->archived_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'created_date' => $this->created_at?->format('Y-m-d'),
            'created_time' => $this->created_at?->format('H:i'),
        ];
    }
}
