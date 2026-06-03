<?php

namespace App\Actions\Notification;

use App\Models\Booking\Booking;
use App\Models\Notification\NotificationLog;
use App\Models\User\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class QueueBookingEmailNotificationAction
{
    public function __invoke(
        Booking $booking,
        User $recipient,
        string $type,
        Mailable $mail,
        array $payload = []
    ): NotificationLog {
        $log = NotificationLog::query()->firstOrCreate(
            [
                'booking_id' => $booking->id,
                'user_id' => $recipient->id,
                'channel' => 'email',
                'type' => $type,
            ],
            [
                'recipient' => $recipient->email,
                'status' => 'queued',
                'payload' => $payload,
            ]
        );

        if (! $log->wasRecentlyCreated) {
            return $log;
        }

        try {
            Mail::to($recipient->email)->send($mail);

            $log->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $log->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        return $log;
    }
}
