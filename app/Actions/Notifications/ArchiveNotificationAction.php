<?php

namespace App\Actions\Notifications;

use App\Models\Notification\Notification;

class ArchiveNotificationAction
{
    public function execute(Notification $notification): Notification
    {
        $notification->archive();

        return $notification->refresh();
    }
}
