<?php

namespace App\Policies;

use App\Models\Notification\Notification;
use App\Models\User\User;

class NotificationPolicy
{
    public function view(User $user, Notification $notification): bool
    {
        return $this->ownsNotification($user, $notification);
    }

    public function update(User $user, Notification $notification): bool
    {
        return $this->ownsNotification($user, $notification);
    }

    public function archive(User $user, Notification $notification): bool
    {
        return $this->ownsNotification($user, $notification);
    }

    public function unarchive(User $user, Notification $notification): bool
    {
        return $this->ownsNotification($user, $notification);
    }

    public function delete(User $user, Notification $notification): bool
    {
        return $this->ownsNotification($user, $notification);
    }

    private function ownsNotification(
        User $user,
        Notification $notification
    ): bool {
        return $notification->recipient_id === $user->id;
    }
}
