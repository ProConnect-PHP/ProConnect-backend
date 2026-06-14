<?php

namespace App\Actions\Notifications;

use App\Models\Notification\Notification;

class UnarchiveNotificationAction
{
    public function execute(Notification $notification): Notification
    {
        $notification->unarchive();

        return $notification->refresh();
    }
}
