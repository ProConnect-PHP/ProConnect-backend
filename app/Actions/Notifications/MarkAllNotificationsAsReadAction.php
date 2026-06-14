<?php

namespace App\Actions\Notifications;

use App\Models\Notification\Notification;
use App\Models\User\User;

class MarkAllNotificationsAsReadAction
{
    public function execute(User $user): int
    {
        return Notification::query()
            ->where('recipient_id', $user->id)
            ->whereNull('read_at')
            ->whereNull('archived_at')
            ->update([
                'read_at' => now(),
            ]);
    }
}
