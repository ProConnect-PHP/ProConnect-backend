<?php

namespace App\Actions\Notifications;

use App\Models\Notification\Notification;

class MarkNotificationAsReadAction
{
    public function execute(Notification $notification): Notification
    {
        $notification->markAsRead();

        return $notification->refresh();
    }
}
