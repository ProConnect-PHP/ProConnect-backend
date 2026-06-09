<?php

namespace App\Notifications\Channels;

use App\Models\Notification\NotificationLog;
use App\Models\User\User;
use App\Notifications\BookingReminderNotification;

class BookingReminderDatabaseChannel
{
    public function send(
        User $notifiable,
        BookingReminderNotification $notification
    ): NotificationLog {
        return NotificationLog::query()->firstOrCreate(
            [
                'booking_id' => $notification->booking->id,
                'user_id' => $notifiable->id,
                'channel' => 'database',
                'type' => $notification->notificationType(),
            ],
            [
                'recipient' => $notifiable->email,
                'status' => 'sent',
                'payload' => $notification->toArray($notifiable),
                'sent_at' => now(),
            ]
        );
    }
}
