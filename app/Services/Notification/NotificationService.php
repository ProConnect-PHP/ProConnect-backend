<?php

namespace App\Services\Notification;

use App\Models\Notification\Notification;
use App\Models\User\User;
use App\Events\Notification\NotificationCreated;

class NotificationService
{
    public function send(
        User $user,
        string $type,
        string $title,
        string $message,
        ?string $actionRoute = null
    ): Notification {

        $notification = Notification::create([
            'recipient_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'action_route' => $actionRoute
        ]);

        event(new NotificationCreated($notification));

        return $notification;
    }
}