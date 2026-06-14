<?php

namespace App\Services\Notification;

use App\Events\Notification\NotificationCreated;
use App\Models\Notification\Notification;
use App\Models\User\User;

class NotificationService
{
    public function send(
        User $user,
        string $type,
        string $title,
        string $message,
        ?string $actionRoute = null,
        array $metadata = []
    ): Notification {
        $notification = Notification::create([
            'recipient_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'action_route' => $actionRoute,
            'metadata' => $metadata,
        ]);

        event(new NotificationCreated($notification));

        return $notification;
    }
}
