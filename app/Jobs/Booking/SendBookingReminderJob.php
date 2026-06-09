<?php

namespace App\Jobs\Booking;

use App\Enums\Booking\BookingReminderDeliveryStatus;
use App\Models\Booking\BookingReminderDelivery;
use App\Notifications\BookingReminderNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

class SendBookingReminderJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $uniqueFor = 600;

    public function __construct(
        public readonly string $deliveryId
    ) {
        $this->onQueue('notifications');
    }

    public function uniqueId(): string
    {
        return $this->deliveryId;
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->deliveryId))
                ->dontRelease()
                ->expireAfter(300),
        ];
    }

    public function handle(): void
    {
        $delivery = BookingReminderDelivery::query()
            ->with([
                'reminderRule.professional.bookingPolicy',
                'booking.service',
                'booking.client',
                'booking.professional.user',
                'booking.clientPackage.packageProduct',
            ])
            ->find($this->deliveryId);

        if (! $delivery || ! in_array($delivery->status, [
            BookingReminderDeliveryStatus::Pending,
            BookingReminderDeliveryStatus::Processing,
        ], true)) {
            return;
        }

        $booking = $delivery->booking;
        $rule = $delivery->reminderRule;
        $policy = $rule?->professional?->bookingPolicy;

        if (
            ! $booking
            || ! $rule
            || ! $rule->is_active
            || ! $policy?->reminders_enabled
            || $booking->professional_id !== $rule->professional_id
            || ! $booking->isReminderEligible()
        ) {
            $this->markSkipped($delivery, 'La reserva o la regla ya no es elegible.');

            return;
        }

        $notifications = collect();

        if ($rule->notify_client && $booking->client) {
            $notifications->push([
                'recipient' => $booking->client,
                'notification' => new BookingReminderNotification($booking, $rule, 'client'),
            ]);
        }

        if ($rule->notify_professional && $booking->professional?->user) {
            $notifications->push([
                'recipient' => $booking->professional->user,
                'notification' => new BookingReminderNotification($booking, $rule, 'professional'),
            ]);
        }

        $notifications = $notifications->filter(
            fn (array $item): bool => $item['notification']->via($item['recipient']) !== []
        );

        if ($notifications->isEmpty()) {
            $this->markSkipped($delivery, 'La regla no tiene canales implementados disponibles.');

            return;
        }

        try {
            $notifications->each(function (array $item): void {
                Notification::sendNow($item['recipient'], $item['notification']);
            });

            $delivery->update([
                'status' => BookingReminderDeliveryStatus::Sent,
                'sent_at' => now(),
                'failure_reason' => null,
            ]);
        } catch (Throwable $exception) {
            $delivery->update([
                'status' => BookingReminderDeliveryStatus::Failed,
                'failure_reason' => Str::limit($exception->getMessage(), 2000, ''),
            ]);

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        BookingReminderDelivery::query()
            ->whereKey($this->deliveryId)
            ->whereNotIn('status', [
                BookingReminderDeliveryStatus::Sent->value,
                BookingReminderDeliveryStatus::Skipped->value,
            ])
            ->update([
                'status' => BookingReminderDeliveryStatus::Failed->value,
                'failure_reason' => Str::limit($exception->getMessage(), 2000, ''),
                'updated_at' => now(),
            ]);
    }

    private function markSkipped(
        BookingReminderDelivery $delivery,
        string $reason
    ): void {
        $delivery->update([
            'status' => BookingReminderDeliveryStatus::Skipped,
            'failure_reason' => $reason,
        ]);
    }
}
