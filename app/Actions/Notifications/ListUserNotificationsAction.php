<?php

namespace App\Actions\Notifications;

use App\Models\Notification\Notification;
use App\Models\User\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListUserNotificationsAction
{
    public function execute(
        User $user,
        int $perPage = 20,
        bool $includeArchived = false
    ): LengthAwarePaginator {
        return Notification::query()
            ->where('recipient_id', $user->id)
            ->when(
                ! $includeArchived,
                fn ($query) => $query->whereNull('archived_at')
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
