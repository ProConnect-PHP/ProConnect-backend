<?php

namespace App\Events\Notification;

use App\Models\Notification\Notification;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationCreated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Notification $notification
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('notifications.' . $this->notification->recipient_id)
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->notification->id,
            'recipient_id' => $this->notification->recipient_id,
            'type' => $this->notification->type,
            'title' => $this->notification->title,
            'message' => $this->notification->message,
            'action_route' => $this->notification->action_route,
            'metadata' => $this->notification->metadata ?? [],
            'is_read' => $this->notification->isRead(),
            'is_archived' => $this->notification->isArchived(),
            'read_at' => $this->notification->read_at?->toISOString(),
            'archived_at' => $this->notification->archived_at?->toISOString(),
            'created_at' => $this->notification->created_at?->toISOString(),
            'created_date' => $this->notification->created_at?->format('Y-m-d'),
            'created_time' => $this->notification->created_at?->format('H:i'),
        ];
    }
}
