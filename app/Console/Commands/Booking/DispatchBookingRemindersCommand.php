<?php

namespace App\Console\Commands\Booking;

use App\Actions\Booking\EnsureDefaultProfessionalBookingPolicyAction;
use App\Enums\Booking\BookingReminderDeliveryStatus;
use App\Enums\Booking\BookingStatus;
use App\Jobs\Booking\SendBookingReminderJob;
use App\Models\Booking\Booking;
use App\Models\Booking\BookingReminderDelivery;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

class DispatchBookingRemindersCommand extends Command
{
    private const LOOKAHEAD_DAYS = 7;

    private const PROCESSING_WINDOW_MINUTES = 2;

    protected $signature = 'bookings:dispatch-reminders {--dry-run}';

    protected $description = 'Dispatch booking reminders from current professional rules.';

    public function handle(
        EnsureDefaultProfessionalBookingPolicyAction $ensureDefaults
    ): int {
        $now = now();
        $dispatched = 0;

        Booking::query()
            ->with([
                'professional.bookingPolicy',
                'professional.reminderRules' => fn ($query) => $query->where('is_active', true),
            ])
            ->whereIn('status', [
                BookingStatus::Confirmed->value,
                BookingStatus::Paid->value,
            ])
            ->whereBetween('starts_at', [
                $now,
                $now->copy()->addDays(self::LOOKAHEAD_DAYS),
            ])
            ->orderBy('id')
            ->chunkById(200, function ($bookings) use ($ensureDefaults, $now, &$dispatched): void {
                foreach ($bookings as $booking) {
                    $professional = $booking->professional;

                    if (! $professional) {
                        continue;
                    }

                    if (! $professional->bookingPolicy) {
                        $ensureDefaults($professional);
                        $professional->load(['bookingPolicy', 'reminderRules']);
                    }

                    if (! $professional->bookingPolicy?->reminders_enabled) {
                        continue;
                    }

                    foreach ($professional->reminderRules->where('is_active', true) as $rule) {
                        $scheduledFor = $booking->starts_at
                            ->copy()
                            ->subMinutes($rule->minutes_before_start);

                        if (
                            $scheduledFor->gt($now)
                            || $scheduledFor->lt(
                                $now->copy()->subMinutes(self::PROCESSING_WINDOW_MINUTES)
                            )
                        ) {
                            continue;
                        }

                        if ($this->option('dry-run')) {
                            $this->line(
                                "[DRY RUN] booking {$booking->id}, rule {$rule->id}, "
                                ."scheduled {$scheduledFor->toDateTimeString()}"
                            );
                            $dispatched++;

                            continue;
                        }

                        if ($this->claimAndDispatch($booking->id, $rule->id, $scheduledFor)) {
                            $dispatched++;
                        }
                    }
                }
            });

        $this->info("Dispatched {$dispatched} booking reminder jobs.");

        return self::SUCCESS;
    }

    private function claimAndDispatch(
        string $bookingId,
        string $ruleId,
        \DateTimeInterface $scheduledFor
    ): bool {
        BookingReminderDelivery::query()->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'booking_id' => $bookingId,
            'reminder_rule_id' => $ruleId,
            'scheduled_for' => $scheduledFor,
            'status' => BookingReminderDeliveryStatus::Pending->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $delivery = BookingReminderDelivery::query()
            ->where('booking_id', $bookingId)
            ->where('reminder_rule_id', $ruleId)
            ->firstOrFail();

        $claimed = BookingReminderDelivery::query()
            ->whereKey($delivery->id)
            ->where('status', BookingReminderDeliveryStatus::Pending->value)
            ->update([
                'scheduled_for' => $scheduledFor,
                'status' => BookingReminderDeliveryStatus::Processing->value,
                'failure_reason' => null,
                'updated_at' => now(),
            ]);

        if ($claimed !== 1) {
            return false;
        }

        try {
            SendBookingReminderJob::dispatch($delivery->id);
        } catch (Throwable $exception) {
            $delivery->update([
                'status' => BookingReminderDeliveryStatus::Failed,
                'failure_reason' => Str::limit($exception->getMessage(), 2000, ''),
            ]);

            throw $exception;
        }

        return true;
    }
}
