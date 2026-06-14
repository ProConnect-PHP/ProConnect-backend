<?php

namespace App\Actions\Notification;

use App\Models\Booking\Booking;
use App\Models\Notification\Notification;
use App\Models\Notification\NotificationLog;
use App\Models\User\User;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\DB;

class SendBookingInAppNotificationOnceAction
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    public function __invoke(
        Booking $booking,
        User $recipient,
        string $type,
        string $title,
        string $message,
        string $actionRoute,
        array $metadata
    ): NotificationLog {
        return DB::transaction(function () use (
            $booking,
            $recipient,
            $type,
            $title,
            $message,
            $actionRoute,
            $metadata
        ): NotificationLog {
            $logId = NotificationLog::query()->firstOrCreate(
                [
                    'booking_id' => $booking->id,
                    'user_id' => $recipient->id,
                    'channel' => 'database',
                    'type' => $type,
                ],
                [
                    'recipient' => (string) $recipient->id,
                    'status' => 'queued',
                    'payload' => $metadata,
                ]
            )->id;

            $log = NotificationLog::query()
                ->lockForUpdate()
                ->findOrFail($logId);

            if ($log->status === 'sent') {
                return $log;
            }

            $existingNotification = Notification::query()
                ->where('recipient_id', $recipient->id)
                ->where('type', $type)
                ->where('metadata->booking_id', $booking->id)
                ->when(
                    array_key_exists('cancelled_at', $metadata),
                    fn ($query) => $query->where(
                        'metadata->cancelled_at',
                        $metadata['cancelled_at']
                    )
                )
                ->first();

            if ($existingNotification) {
                $this->markAsSent($log, $metadata, $existingNotification);

                return $log->refresh();
            }

            $notification = $this->notificationService->send(
                user: $recipient,
                type: $type,
                title: $title,
                message: $message,
                actionRoute: $actionRoute,
                metadata: $metadata
            );

            $this->markAsSent($log, $metadata, $notification);

            return $log->refresh();
        });
    }

    private function markAsSent(
        NotificationLog $log,
        array $metadata,
        Notification $notification
    ): void {
        $log->update([
            'status' => 'sent',
            'error' => null,
            'sent_at' => $log->sent_at ?? now(),
            'payload' => [
                ...$metadata,
                'notification_id' => $notification->id,
            ],
        ]);
    }
}
