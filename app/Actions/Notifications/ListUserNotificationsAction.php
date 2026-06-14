<?php

namespace App\Actions\Notifications;

use App\Models\Notification\Notification;
use App\Models\User\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

class ListUserNotificationsAction
{
    public const STATUSES = ['active', 'archived', 'all'];

    public function execute(
        User $user,
        int $perPage = 20,
        string $status = 'active'
    ): LengthAwarePaginator {
        $query = Notification::query()
            ->where('recipient_id', $user->id);

        match ($status) {
            'active' => $query->whereNull('archived_at'),
            'archived' => $query->whereNotNull('archived_at'),
            'all' => null,
            default => throw new InvalidArgumentException(
                "Unsupported notification status [{$status}]."
            ),
        };

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
