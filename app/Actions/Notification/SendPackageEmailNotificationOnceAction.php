<?php

namespace App\Actions\Notification;

use App\Models\Booking\Booking;
use App\Models\Notification\NotificationLog;
use App\Models\Package\ClientPackage;
use App\Models\Package\PackageSession;
use App\Models\User\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendPackageEmailNotificationOnceAction
{
    public function __invoke(
        User $recipient,
        Mailable $mailable,
        string $type,
        ?ClientPackage $clientPackage = null,
        ?PackageSession $packageSession = null,
        ?Booking $booking = null,
        array $payload = []
    ): ?NotificationLog {
        if (blank($recipient->email)) {
            return null;
        }

        $query = NotificationLog::query()
            ->where('user_id', $recipient->id)
            ->where('channel', 'email')
            ->where('type', $type);

        if ($clientPackage) {
            $query->where('client_package_id', $clientPackage->id);
        }

        if ($packageSession) {
            $query->where('package_session_id', $packageSession->id);
        }

        if ($booking) {
            $query->where('booking_id', $booking->id);
        }

        $existingLog = $query->first();

        if ($existingLog) {
            return $existingLog;
        }

        $log = NotificationLog::query()->create([
            'user_id' => $recipient->id,
            'booking_id' => $booking?->id,
            'client_package_id' => $clientPackage?->id,
            'package_session_id' => $packageSession?->id,
            'channel' => 'email',
            'type' => $type,
            'recipient' => $recipient->email,
            'status' => 'queued',
            'payload' => $payload,
        ]);

        try {
            Mail::to($recipient->email)->send($mailable);

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
