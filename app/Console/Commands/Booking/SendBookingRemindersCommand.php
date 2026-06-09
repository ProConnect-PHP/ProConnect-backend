<?php

namespace App\Console\Commands\Booking;

use App\Actions\Notification\QueueBookingEmailNotificationAction;
use App\Enums\Booking\BookingStatus;
use App\Mail\Booking\BookingReminderMail;
use App\Models\Booking\Booking;
use App\Models\Notification\NotificationLog;
use App\Support\Booking\BookingNotificationRecipients;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendBookingRemindersCommand extends Command
{
    protected $signature = 'booking:send-reminders {--dry-run}';

    protected $description = 'Send booking reminder emails for upcoming bookings.';

    public function handle(QueueBookingEmailNotificationAction $queueNotification): int
    {
        $sent = 0;

        $sent += $this->sendUpcomingWithin24HoursReminder($queueNotification);
        $sent += $this->sendStartsInOneHourReminder($queueNotification);

        $this->info("Queued {$sent} booking reminder emails.");

        return self::SUCCESS;
    }

    private function sendUpcomingWithin24HoursReminder(
        QueueBookingEmailNotificationAction $queueNotification
    ): int {
        $from = now()->copy()->addDay();

        return $this->sendReminder(
            queueNotification: $queueNotification,
            type: 'booking_reminder_24h',
            from: $from,
            to: $from->copy()->addMinutes(5),
            markReminderSentAt: true,
        );
    }

    private function sendStartsInOneHourReminder(
        QueueBookingEmailNotificationAction $queueNotification
    ): int {
        $from = now()->copy()->addHour();
        $to = $from->copy()->addMinutes(5);

        return $this->sendReminder(
            queueNotification: $queueNotification,
            type: 'booking_reminder_1h',
            from: $from,
            to: $to,
        );
    }

    private function sendReminder(
        QueueBookingEmailNotificationAction $queueNotification,
        string $type,
        Carbon $from,
        Carbon $to,
        bool $markReminderSentAt = false
    ): int {
        $sent = 0;

        Booking::query()
            ->with(['service', 'professional.user', 'client', 'videoSession'])
            ->whereIn('status', [
                BookingStatus::Confirmed->value,
                BookingStatus::Paid->value,
            ])
            ->whereBetween('starts_at', [$from, $to])
            ->when($markReminderSentAt, fn ($query) => $query->whereNull('reminder_sent_at'))
            ->orderBy('starts_at')
            ->chunkById(100, function ($bookings) use ($queueNotification, $type, $markReminderSentAt, &$sent): void {
                foreach ($bookings as $booking) {
                    $recipients = BookingNotificationRecipients::reminderUsers($booking);

                    $recipients
                        ->each(function ($recipient) use ($queueNotification, $booking, $type, &$sent): void {
                            if ($this->alreadySent($booking->id, $recipient->id, $type)) {
                                return;
                            }

                            if ($this->option('dry-run')) {
                                $this->line("[DRY RUN] {$type} → booking {$booking->id} → {$recipient->email}");

                                return;
                            }

                            $queueNotification(
                                booking: $booking,
                                recipient: $recipient,
                                type: $type,
                                mail: new BookingReminderMail($booking, $type),
                            );

                            $sent++;
                        });

                    if ($markReminderSentAt && ! $this->option('dry-run') && $recipients->isNotEmpty()) {
                        $booking->forceFill([
                            'reminder_sent_at' => $booking->reminder_sent_at ?? now(),
                        ])->save();
                    }
                }
            });

        return $sent;
    }

    private function alreadySent(string $bookingId, string $userId, string $type): bool
    {
        return NotificationLog::query()
            ->where('booking_id', $bookingId)
            ->where('user_id', $userId)
            ->where('channel', 'email')
            ->where('type', $type)
            ->exists();
    }
}
