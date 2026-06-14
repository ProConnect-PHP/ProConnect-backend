<?php

namespace App\Http\Controllers\Notification;

use App\Actions\Notifications\ArchiveNotificationAction;
use App\Actions\Notifications\DeleteNotificationAction;
use App\Actions\Notifications\GetUnreadNotificationCountAction;
use App\Actions\Notifications\ListUserNotificationsAction;
use App\Actions\Notifications\MarkAllNotificationsAsReadAction;
use App\Actions\Notifications\MarkNotificationAsReadAction;
use App\Actions\Notifications\UnarchiveNotificationAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Notification\NotificationResource;
use App\Models\Notification\Notification;
use App\Models\User\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    public function index(
        Request $request,
        ListUserNotificationsAction $action
    ): AnonymousResourceCollection {
        $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => [
                'sometimes',
                'string',
                'in:'.implode(',', ListUserNotificationsAction::STATUSES),
            ],
        ]);

        $status = $request->filled('status')
            ? (string) $request->input('status')
            : ($request->boolean('include_archived') ? 'all' : 'active');

        $notifications = $action->execute(
            $this->authenticatedUser($request),
            $request->integer('per_page', 20),
            $status
        );

        return NotificationResource::collection($notifications);
    }

    public function unreadCount(
        Request $request,
        GetUnreadNotificationCountAction $action
    ): JsonResponse {
        return response()->json([
            'count' => $action->execute($this->authenticatedUser($request)),
        ]);
    }

    public function markAsRead(
        Notification $notification,
        MarkNotificationAsReadAction $action
    ): NotificationResource {
        $this->authorize('update', $notification);

        return new NotificationResource($action->execute($notification));
    }

    public function markAllAsRead(
        Request $request,
        MarkAllNotificationsAsReadAction $action
    ): JsonResponse {
        $updated = $action->execute($this->authenticatedUser($request));

        return response()->json([
            'message' => 'All notifications marked as read',
            'updated' => $updated,
        ]);
    }

    public function archive(
        Notification $notification,
        ArchiveNotificationAction $action
    ): NotificationResource {
        $this->authorize('archive', $notification);

        return new NotificationResource($action->execute($notification));
    }

    public function unarchive(
        Notification $notification,
        UnarchiveNotificationAction $action
    ): NotificationResource {
        $this->authorize('unarchive', $notification);

        return new NotificationResource($action->execute($notification));
    }

    public function destroy(
        Notification $notification,
        DeleteNotificationAction $action
    ): JsonResponse {
        $this->authorize('delete', $notification);

        $action->execute($notification);

        return response()->json(['message' => 'Notification deleted']);
    }

    private function authenticatedUser(Request $request): User
    {
        /** @var User $user */
        $user = $request->user('user_jwt');

        return $user;
    }
}
