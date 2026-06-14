<?php

namespace App\Actions\Notifications;

use App\Models\Notification\Notification;

class DeleteNotificationAction
{
    public function execute(Notification $notification): void
    {
        $notification->delete();
    }
}
